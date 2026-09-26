/**
 * Puente entre Transpaty y WhatsApp, con Baileys: el número de la empresa se
 * vincula como un «dispositivo» más (igual que WhatsApp Web) y Laravel le
 * pide acá que mande los mensajes.
 *
 * Solo escucha en 127.0.0.1 y exige un token: no es una API pública, es un
 * proceso auxiliar de la app que corre en el mismo servidor, bajo supervisor.
 *
 *   GET  /estado        → { estado, qr, numero }
 *   POST /vincular      → { telefono? } → { codigo? }   (sin teléfono: por QR)
 *   POST /enviar        → { numero, texto, imagen? }   → { id }
 *   POST /desvincular   → cierra la sesión y la borra del disco
 *
 * Cuando un aviso llega al celular o lo leen, le avisa a Laravel en
 * POST {APP_URL}/whatsapp/recibos, con el mismo token (los ✓✓ del chat).
 *
 * Variables: WHATSAPP_SERVICIO_TOKEN (obligatoria), WHATSAPP_PUERTO (3100),
 * WHATSAPP_SESION_DIR (storage/app/private/whatsapp), WHATSAPP_LOG (warn),
 * WHATSAPP_RECIBOS_URL (por defecto, APP_URL del .env de Laravel).
 */
import { timingSafeEqual } from 'node:crypto';
import { access, mkdir, readFile, rm } from 'node:fs/promises';
import http from 'node:http';
import { fileURLToPath } from 'node:url';
import makeWASocket, {
    Browsers,
    DisconnectReason,
    fetchLatestBaileysVersion,
    isJidBroadcast,
    isJidGroup,
    isJidNewsletter,
    useMultiFileAuthState,
} from '@whiskeysockets/baileys';
import pino from 'pino';
import QRCode from 'qrcode';

const PUERTO = Number(process.env.WHATSAPP_PUERTO ?? 3100);
const TOKEN = process.env.WHATSAPP_SERVICIO_TOKEN ?? '';
const SESION =
    process.env.WHATSAPP_SESION_DIR ??
    fileURLToPath(new URL('../storage/app/private/whatsapp', import.meta.url));

// Una imagen de aviso cabe de sobra; más que esto es un error del que llama.
const LIMITE_CUERPO = 10 * 1024 * 1024;

if (TOKEN.length < 32) {
    console.error(
        'WHATSAPP_SERVICIO_TOKEN falta o es demasiado corto (mínimo 32 caracteres).',
    );
    process.exit(1);
}

const logger = pino({ level: process.env.WHATSAPP_LOG ?? 'warn' });

/**
 * Los recibos de WhatsApp que importan: 3 entregado, 4 leído, 5
 * reproducido (una nota de voz; para un aviso cuenta como leído).
 */
const ESTADOS_RECIBO = { 3: 'entregado', 4: 'leido', 5: 'leido' };

/** Recibos que todavía no se le pasaron a Laravel, por id de mensaje. */
const recibosPendientes = new Map();

/** @type {ReturnType<typeof makeWASocket> | null} */
let sock = null;
/** desconectado | vinculando | conectado */
let estado = 'desconectado';
let qr = null;
let numero = null;
/** Quien espera el primer QR para pedir el código de vinculación. */
let esperandoQr = [];
/** La versión de WhatsApp Web, consultada una vez por proceso. */
let versionWhatsapp = null;

/**
 * La versión se pide a GitHub una sola vez y se reusa en cada reconexión:
 * sin esto, cada corte del socket suma una llamada externa que puede tardar
 * o fallar. Si falla, Baileys devuelve la que trae de fábrica.
 */
async function versionDeWhatsapp() {
    versionWhatsapp ??= (await fetchLatestBaileysVersion()).version;

    return versionWhatsapp;
}

/**
 * Lo que no es un chat uno a uno no le interesa a la app: el número solo
 * manda avisos. Sin este filtro, Baileys descifra cada mensaje de cada grupo
 * y cada estado, gasta CPU y llena la sesión de miles de claves.
 */
function ignorar(jid) {
    return Boolean(
        isJidGroup(jid) || isJidBroadcast(jid) || isJidNewsletter(jid),
    );
}

async function conectar() {
    await mkdir(SESION, { recursive: true });

    const { state, saveCreds } = await useMultiFileAuthState(SESION);
    const version = await versionDeWhatsapp();

    estado = 'vinculando';

    const socket = makeWASocket({
        version,
        auth: state,
        logger,
        browser: Browsers.ubuntu('Transpaty'),
        // El número sigue siendo de una persona: no se marca «en línea» ni
        // se baja el historial de chats, que la app no usa.
        markOnlineOnConnect: false,
        syncFullHistory: false,
        shouldIgnoreJid: ignorar,
    });

    sock = socket;
    socket.ev.on('creds.update', saveCreds);

    // Solo los mensajes que mandó el número: los ✓✓ de los avisos.
    socket.ev.on('messages.update', (cambios) => {
        for (const { key, update } of cambios) {
            const estadoRecibo = ESTADOS_RECIBO[update?.status];

            if (key?.fromMe && key.id && estadoRecibo) {
                recibosPendientes.set(key.id, estadoRecibo);
            }
        }
    });

    socket.ev.on(
        'connection.update',
        async ({ connection, lastDisconnect, qr: nuevoQr }) => {
            if (nuevoQr) {
                qr = await QRCode.toDataURL(nuevoQr, { margin: 1, width: 280 });
                esperandoQr.splice(0).forEach((resolver) => resolver());
            }

            if (connection === 'open') {
                estado = 'conectado';
                qr = null;
                numero =
                    (socket.user?.phoneNumber ?? socket.user?.id ?? '').split(
                        /[:@]/,
                    )[0] || null;
                logger.warn({ numero }, 'WhatsApp conectado');
            }

            if (connection === 'close') {
                const codigo = lastDisconnect?.error?.output?.statusCode;

                sock = null;
                qr = null;
                estado = 'desconectado';

                // Cerraron la sesión desde el celular: lo guardado ya no sirve.
                if (codigo === DisconnectReason.loggedOut) {
                    numero = null;
                    await rm(SESION, { recursive: true, force: true });
                    logger.warn(
                        'Sesión cerrada desde el celular; hay que volver a vincular.',
                    );

                    return;
                }

                // Nadie escaneó el QR a tiempo: se espera a que lo vuelvan a
                // pedir, en vez de generar códigos para siempre. Justo después
                // de vincular WhatsApp pide reiniciar (515): eso sí reconecta.
                if (
                    !state.creds.registered &&
                    codigo !== DisconnectReason.restartRequired
                ) {
                    return;
                }

                setTimeout(
                    () => conectar().catch((error) => logger.error(error)),
                    3000,
                );
            }
        },
    );
}

async function vincular(telefono) {
    if (estado === 'conectado') {
        return {};
    }

    const primerQr = new Promise((resolver) => esperandoQr.push(resolver));

    if (!sock) {
        await conectar();
    }

    if (!telefono) {
        return {};
    }

    // El código de 8 dígitos se pide una vez que el socket ya negoció con
    // WhatsApp, que es cuando emite su primer QR.
    await Promise.race([
        primerQr,
        new Promise((resolver) => setTimeout(resolver, 15000)),
    ]);

    return { codigo: await sock.requestPairingCode(telefono) };
}

async function enviar({ numero: destino, texto, imagen }) {
    if (estado !== 'conectado' || !sock) {
        throw new ErrorHttp(503, 'WhatsApp no está conectado.');
    }

    const digitos = String(destino ?? '').replace(/\D/g, '');

    if (digitos.length < 9 || (!texto && !imagen)) {
        throw new ErrorHttp(422, 'Falta el número o el mensaje.');
    }

    // Se confirma que el número tenga WhatsApp antes de mandar: sin esto el
    // mensaje «sale» igual y se pierde sin aviso.
    const [contacto] = (await sock.onWhatsApp(digitos)) ?? [];

    if (!contacto?.exists) {
        throw new ErrorHttp(422, `El número ${digitos} no tiene WhatsApp.`);
    }

    const contenido = imagen
        ? { image: Buffer.from(imagen, 'base64'), caption: texto ?? undefined }
        : { text: texto };

    const mensaje = await sock.sendMessage(contacto.jid, contenido);

    return { id: mensaje?.key?.id ?? null };
}

async function desvincular() {
    if (sock) {
        await sock.logout().catch(() => {});
    }

    sock = null;
    qr = null;
    numero = null;
    estado = 'desconectado';
    await rm(SESION, { recursive: true, force: true });
}

/**
 * A dónde mandar los recibos: WHATSAPP_RECIBOS_URL, o si no, APP_URL del
 * .env de Laravel (el servicio vive dentro del mismo proyecto).
 */
async function urlDeRecibos() {
    if (process.env.WHATSAPP_RECIBOS_URL) {
        return process.env.WHATSAPP_RECIBOS_URL;
    }

    const env = await readFile(
        new URL('../.env', import.meta.url),
        'utf8',
    ).catch(() => '');
    const appUrl = env.match(/^APP_URL=["']?([^"'\s]+)/m)?.[1];

    return appUrl ? `${appUrl.replace(/\/$/, '')}/whatsapp/recibos` : null;
}

/**
 * Cada pocos segundos, le pasa a Laravel los recibos juntados. Si Laravel
 * no responde, se quedan y van en la tanda siguiente; si se juntan
 * demasiados (Laravel caído mucho rato), se descartan los más viejos.
 */
async function entregarRecibos() {
    if (recibosPendientes.size === 0) {
        return;
    }

    const url = await urlDeRecibos();

    if (!url) {
        return;
    }

    const tanda = [...recibosPendientes].slice(0, 200);

    try {
        const respuesta = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                Authorization: `Bearer ${TOKEN}`,
            },
            body: JSON.stringify({
                recibos: tanda.map(([id, estado]) => ({ id, estado })),
            }),
            signal: AbortSignal.timeout(5000),
        });

        if (!respuesta.ok) {
            throw new Error(`Laravel respondió ${respuesta.status}`);
        }

        for (const [id, estado] of tanda) {
            // Si mientras tanto llegó un recibo más nuevo, se queda.
            if (recibosPendientes.get(id) === estado) {
                recibosPendientes.delete(id);
            }
        }
    } catch (error) {
        logger.warn(
            { error: String(error) },
            'No se pudieron entregar los recibos; se reintenta',
        );

        if (recibosPendientes.size > 1000) {
            [...recibosPendientes.keys()]
                .slice(0, recibosPendientes.size - 1000)
                .forEach((id) => recibosPendientes.delete(id));
        }
    }
}

setInterval(
    () => entregarRecibos().catch((error) => logger.error(error)),
    3000,
).unref();

class ErrorHttp extends Error {
    constructor(status, mensaje) {
        super(mensaje);
        this.status = status;
    }
}

function autorizado(peticion) {
    const recibido = Buffer.from(peticion.headers.authorization ?? '');
    const esperado = Buffer.from(`Bearer ${TOKEN}`);

    return (
        recibido.length === esperado.length &&
        timingSafeEqual(recibido, esperado)
    );
}

async function leerJson(peticion) {
    let cuerpo = '';

    for await (const trozo of peticion) {
        cuerpo += trozo;

        if (cuerpo.length > LIMITE_CUERPO) {
            throw new ErrorHttp(413, 'El mensaje es demasiado grande.');
        }
    }

    try {
        return cuerpo ? JSON.parse(cuerpo) : {};
    } catch {
        throw new ErrorHttp(400, 'El cuerpo no es JSON válido.');
    }
}

function responder(respuesta, status, datos) {
    respuesta.writeHead(status, { 'Content-Type': 'application/json' });
    respuesta.end(JSON.stringify(datos));
}

const rutas = {
    'GET /estado': async () => ({ estado, qr, numero }),
    'POST /vincular': async (datos) =>
        vincular(String(datos.telefono ?? '').replace(/\D/g, '')),
    'POST /enviar': async (datos) => enviar(datos),
    'POST /desvincular': async () => {
        await desvincular();

        return {};
    },
};

const servidor = http.createServer(async (peticion, respuesta) => {
    if (!autorizado(peticion)) {
        return responder(respuesta, 401, { error: 'No autorizado.' });
    }

    const ruta = rutas[`${peticion.method} ${peticion.url}`];

    if (!ruta) {
        return responder(respuesta, 404, { error: 'No existe.' });
    }

    try {
        responder(respuesta, 200, await ruta(await leerJson(peticion)));
    } catch (error) {
        const status = error instanceof ErrorHttp ? error.status : 500;

        if (status === 500) {
            logger.error(error);
        }

        responder(respuesta, status, { error: error.message });
    }
});

servidor.listen(PUERTO, '127.0.0.1', async () => {
    logger.warn(`Servicio de WhatsApp escuchando en 127.0.0.1:${PUERTO}`);

    // Si ya había un número vinculado, se retoma la sesión sin pedir QR.
    const haySesion = await access(`${SESION}/creds.json`).then(
        () => true,
        () => false,
    );

    if (haySesion) {
        conectar().catch((error) => logger.error(error));
    }
});

for (const senal of ['SIGINT', 'SIGTERM']) {
    process.on(senal, () => {
        servidor.close();
        sock?.end(undefined);
        process.exit(0);
    });
}
