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
 * Variables: WHATSAPP_SERVICIO_TOKEN (obligatoria), WHATSAPP_PUERTO (3100),
 * WHATSAPP_SESION_DIR (storage/app/private/whatsapp), WHATSAPP_LOG (warn).
 */
import { timingSafeEqual } from 'node:crypto';
import { access, mkdir, rm } from 'node:fs/promises';
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
