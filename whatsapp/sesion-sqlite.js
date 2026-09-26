/**
 * La sesión de WhatsApp (credenciales y claves de cifrado) en un solo archivo
 * SQLite, en vez de un archivo por clave como `useMultiFileAuthState` de
 * Baileys —que su propia documentación desaconseja fuera de un bot: miles de
 * archivos sueltos que además hay que recorrer en cada deploy—.
 *
 * Usa `node:sqlite`, que viene con Node: no suma dependencias. Las claves se
 * guardan con el mismo nombre que tenían los archivos, así la sesión que ya
 * estaba vinculada se importa tal cual y no hay que volver a escanear el QR.
 */
import { readdir, readFile, rename } from 'node:fs/promises';
import { DatabaseSync } from 'node:sqlite';
import { BufferJSON, initAuthCreds, proto } from '@whiskeysockets/baileys';

/** El mismo nombre que Baileys le daba al archivo, sin el `.json`. */
function clave(nombre) {
    return nombre.replace(/\//g, '__').replace(/:/g, '-');
}

/**
 * @param {string} archivo  El .sqlite de la sesión.
 * @param {string} carpetaVieja  La carpeta de `useMultiFileAuthState`, si existe.
 */
export async function abrirSesion(archivo, carpetaVieja, logger) {
    const db = new DatabaseSync(archivo);

    db.exec(`
        PRAGMA journal_mode = WAL;
        PRAGMA synchronous = NORMAL;
        CREATE TABLE IF NOT EXISTS sesion (clave TEXT PRIMARY KEY, valor TEXT NOT NULL);
    `);

    const leer = db.prepare('SELECT valor FROM sesion WHERE clave = ?');
    const escribir = db.prepare(
        'INSERT INTO sesion (clave, valor) VALUES (?, ?) ON CONFLICT (clave) DO UPDATE SET valor = excluded.valor',
    );
    const quitar = db.prepare('DELETE FROM sesion WHERE clave = ?');
    const vacia = () =>
        db.prepare('SELECT COUNT(*) AS n FROM sesion').get().n === 0;

    const obtener = (nombre) => {
        const fila = leer.get(clave(nombre));

        return fila ? JSON.parse(fila.valor, BufferJSON.reviver) : null;
    };

    const guardar = (nombre, valor) =>
        escribir.run(clave(nombre), JSON.stringify(valor, BufferJSON.replacer));

    /** Todo o nada: si falla a la mitad, no queda una sesión a medias. */
    const enTransaccion = (hacer) => {
        db.exec('BEGIN');

        try {
            hacer();
            db.exec('COMMIT');
        } catch (error) {
            db.exec('ROLLBACK');

            throw error;
        }
    };

    // La primera vez: se trae la sesión de la carpeta de archivos y la
    // carpeta queda renombrada como respaldo (no se vuelve a importar).
    if (vacia() && carpetaVieja) {
        const archivos = await readdir(carpetaVieja).catch(() => []);
        const jsons = archivos.filter((nombre) => nombre.endsWith('.json'));

        if (jsons.length > 0) {
            const contenidos = await Promise.all(
                jsons.map(async (nombre) => [
                    nombre.slice(0, -'.json'.length),
                    await readFile(`${carpetaVieja}/${nombre}`, 'utf8'),
                ]),
            );

            enTransaccion(() => {
                for (const [nombre, contenido] of contenidos) {
                    // Se valida que sea JSON, pero se guarda tal cual estaba.
                    JSON.parse(contenido);
                    escribir.run(nombre, contenido);
                }
            });

            await rename(carpetaVieja, `${carpetaVieja}-archivos-migrados`);
            logger?.warn(
                { claves: contenidos.length },
                'Sesión importada de archivos a SQLite',
            );
        }
    }

    return {
        hayCredenciales: () => leer.get('creds') !== undefined,

        /** Lo que pide `makeWASocket` en `auth`, leído de nuevo en cada conexión. */
        estado() {
            const creds = obtener('creds') ?? initAuthCreds();

            return {
                state: {
                    creds,
                    keys: {
                        get: async (tipo, ids) => {
                            const datos = {};

                            for (const id of ids) {
                                let valor = obtener(`${tipo}-${id}`);

                                if (tipo === 'app-state-sync-key' && valor) {
                                    valor =
                                        proto.Message.AppStateSyncKeyData.fromObject(
                                            valor,
                                        );
                                }

                                datos[id] = valor;
                            }

                            return datos;
                        },
                        set: async (datos) => {
                            enTransaccion(() => {
                                for (const tipo in datos) {
                                    for (const id in datos[tipo]) {
                                        const valor = datos[tipo][id];
                                        const nombre = `${tipo}-${id}`;

                                        if (valor) {
                                            guardar(nombre, valor);
                                        } else {
                                            quitar.run(clave(nombre));
                                        }
                                    }
                                }
                            });
                        },
                    },
                },
                saveCreds: async () => guardar('creds', creds),
            };
        },

        /** Al desvincular: la sesión ya no sirve. */
        borrar() {
            db.exec('DELETE FROM sesion');
        },
    };
}
