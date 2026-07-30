<?php

namespace Database\Seeders;

use App\Models\Ubicacion;
use Illuminate\Database\Seeder;

/**
 * Catálogo de puntos del circuito, sembrado a partir de los reportes reales de
 * disponibilidad de un mes: 121 lugares distintos, no los veinte que uno
 * supondría leyendo la descripción de la ruta.
 *
 * Se corre con `updateOrCreate` sobre el código para poder repetirlo cuando se
 * corrija una coordenada o se agregue un punto, sin duplicar nada ni perder los
 * alias que el catálogo haya aprendido.
 */
class UbicacionSeeder extends Seeder
{
    /**
     * Zonas del corredor, de la mina hacia el norte. Varias ubicaciones
     * comparten zona a propósito: entre el altiplano y la costa el mismo tramo
     * se hace a veces por La Joya, a veces por Majes y a veces por Yura y
     * Arequipa, y las tres son legítimas.
     *
     * Numeradas de diez en diez para poder intercalar zonas sin renumerar.
     *
     * @var array<int, string>
     */
    private const ZONAS = [
        10 => 'Mina y altiplano norte',
        20 => 'Azángaro',
        30 => 'Juliaca',
        40 => 'Imata',
        50 => 'Arequipa y campiña',
        60 => 'Camaná',
        70 => 'Costa Atico – Yauca',
        80 => 'Nazca e Ica',
        90 => 'Pisco',
        100 => 'Cañete',
        110 => 'Sur de Lima',
        120 => 'Lima y Callao',
        130 => 'Norte de Lima',
    ];

    /**
     * El punto de referencia de cada zona: el pueblo grande sobre la carretera.
     * Es el que se recorre para medir distancias, porque dentro de una zona las
     * alternativas están a pocos kilómetros y no cambian una cuenta que se
     * entrega en días.
     *
     * @var list<string>
     */
    private const EJES = [
        'san_rafael', 'azangaro', 'juliaca', 'imata', 'arequipa', 'camana',
        'chala', 'nazca', 'pisco', 'canete', 'lurin', 'lima', 'huaral',
    ];

    /**
     * Puntos con posición conocida. La última columna es la zona del corredor;
     * null son destinos válidos que quedan fuera del eje —Cusco, Tacna,
     * Moquegua— y por lo tanto no participan del cálculo de llegada.
     *
     * @var list<array{0: string, 1: string, 2: float, 3: float, 4: int|null}>
     */
    private const PUNTOS = [
        // Zona 10 — mina y altiplano norte
        ['san_rafael', 'San Rafael', -14.2400, -70.3300, 10],
        ['antauta', 'Antauta', -14.3000, -70.3000, 10],
        ['san_anton', 'San Antón', -14.2464, -70.3244, 10],
        ['asillo', 'Asillo', -14.7906, -70.3506, 10],
        ['progreso', 'Progreso', -14.6833, -70.3667, 10],

        // Zona 20 — Azángaro
        ['azangaro', 'Azángaro', -14.9111, -70.1953, 20],
        ['calapuja', 'Calapuja', -15.4167, -70.0667, 20],

        // Zona 30 — Juliaca y alrededores
        ['juliaca', 'Juliaca', -15.4997, -70.1333, 30],
        ['caracoto', 'Caracoto', -15.5667, -70.1000, 30],
        ['cabanillas', 'Cabanillas', -15.6333, -70.3500, 30],
        ['lagunillas', 'Lagunillas', -15.7667, -70.6500, 30],
        ['santa_lucia', 'Santa Lucía', -15.6969, -70.6069, 30],
        ['puno', 'Puno', -15.8402, -70.0219, 30],
        ['chucuito', 'Chucuito', -15.8931, -69.8894, 30],

        // Zona 40 — Imata
        ['imata', 'Imata', -15.8375, -71.0894, 40],
        ['patahuasi', 'Patahuasi', -15.9500, -71.4167, 40],

        // Zona 50 — Arequipa y campiña. Los reportes muestran unidades bajando
        // de mina por acá, así que Arequipa sí pertenece al corredor.
        ['arequipa', 'Arequipa', -16.4090, -71.5375, 50],
        ['yura', 'Yura', -16.2500, -71.6833, 50],
        ['la_joya', 'La Joya', -16.5836, -71.9147, 50],
        ['majes', 'Majes', -16.3419, -72.1889, 50],
        ['la_reparticion', 'La Repartición', -16.4833, -71.9667, 50],

        // Zona 60 — Camaná
        ['camana', 'Camaná', -16.6236, -72.7111, 60],
        ['quilca', 'Quilca', -16.7167, -72.4167, 60],
        ['ocona', 'Ocoña', -16.4267, -73.1119, 60],

        // Zona 70 — costa de Atico a Yauca
        ['chala', 'Chala', -15.8636, -74.2461, 70],
        ['atico', 'Atico', -16.2264, -73.6114, 70],
        ['caraveli', 'Caravelí', -15.7739, -73.3653, 70],
        ['atiquipa', 'Atiquipa', -15.7833, -74.3667, 70],
        ['yauca', 'Yauca', -15.6667, -74.5333, 70],
        ['lomas', 'Lomas', -15.5667, -74.8500, 70],
        ['la_planchada', 'La Planchada', -16.1833, -73.7500, 70],

        // Zona 80 — Nazca e Ica
        ['nazca', 'Nazca', -14.8286, -74.9425, 80],
        ['palpa', 'Palpa', -14.5333, -75.1833, 80],
        ['ica', 'Ica', -14.0678, -75.7286, 80],
        ['rio_grande', 'Río Grande', -14.5833, -75.2000, 80],

        // Zona 90 — Pisco
        ['pisco', 'Pisco', -13.7100, -76.2036, 90],
        ['paracas', 'Paracas', -13.8333, -76.2500, 90],
        ['chincha', 'Chincha', -13.4500, -76.1333, 90],

        // Zona 100 — Cañete
        ['canete', 'Cañete', -13.0783, -76.3856, 100],
        ['cerro_azul', 'Cerro Azul', -13.0242, -76.4783, 100],
        ['quilmana', 'Quilmaná', -12.9667, -76.4333, 100],
        ['mala', 'Mala', -12.6567, -76.6317, 100],
        ['asia', 'Asia', -12.7833, -76.5833, 100],
        ['bujama', 'Bujama', -12.6167, -76.6500, 100],

        // Zona 110 — sur de Lima
        ['lurin', 'Lurín', -12.2769, -76.8756, 110],
        ['chilca', 'Chilca', -12.5197, -76.7383, 110],
        ['pucusana', 'Pucusana', -12.4794, -76.7969, 110],
        ['punta_negra', 'Punta Negra', -12.3667, -76.7833, 110],
        ['punta_hermosa', 'Punta Hermosa', -12.3333, -76.8167, 110],
        ['san_bartolo', 'San Bartolo', -12.3833, -76.7833, 110],

        // Zona 120 — Lima y Callao
        ['lima', 'Lima', -12.0464, -77.0428, 120],
        ['callao', 'Callao', -12.0566, -77.1181, 120],
        ['los_olivos', 'Los Olivos', -11.9667, -77.0667, 120],
        ['comas', 'Comas', -11.9500, -77.0500, 120],
        ['santa_anita', 'Santa Anita', -12.0500, -76.9667, 120],
        ['puente_piedra', 'Puente Piedra', -11.8667, -77.0667, 120],
        ['chorrillos', 'Chorrillos', -12.1667, -77.0167, 120],
        ['la_molina', 'La Molina', -12.0833, -76.9333, 120],

        // Zona 130 — norte de Lima
        ['huaral', 'Huaral', -11.4950, -77.2072, 130],
        ['chancay', 'Chancay', -11.5667, -77.2667, 130],

        // Fuera del corredor: destinos de carga particular.
        ['cusco', 'Cusco', -13.5319, -71.9675, null],
        ['poroy', 'Poroy', -13.5000, -72.0333, null],
        ['saylla', 'Saylla', -13.5500, -71.8500, null],
        ['oropesa', 'Oropesa', -13.6000, -71.7667, null],
        ['san_jeronimo', 'San Jerónimo', -13.5500, -71.8833, null],
        ['pucyura', 'Pucyura', -13.4667, -72.1500, null],
        ['ancahuasi', 'Ancahuasi', -13.4667, -72.2500, null],
        ['tacna', 'Tacna', -18.0146, -70.2536, null],
        ['palca', 'Palca', -17.7833, -70.0500, null],
        ['moquegua', 'Moquegua', -17.1950, -70.9350, null],
        ['torata', 'Torata', -17.0667, -70.8500, null],
        ['mollendo', 'Mollendo', -17.0231, -72.0147, null],
        ['abancay', 'Abancay', -13.6339, -72.8814, null],
        ['puquio', 'Puquio', -14.7000, -74.1333, null],
        ['viru', 'Virú', -8.4167, -78.7500, null],
    ];

    /**
     * Puntos que salen en los reportes y a los que sí se les puede asignar zona,
     * pero de los que no conocemos la posición exacta: almacenes de operadores
     * logísticos y tiendas. Entran al cálculo de llegada por su zona y quedan
     * fuera del mapa hasta que alguien les ponga la chincheta.
     *
     * @var list<array{0: string, 1: string, 2: int}>
     */
    private const SIN_POSICION = [
        ['kio', 'Kio', 120],
        ['ransa', 'Ransa', 120],
        ['promart', 'Promart', 120],
        ['crissar', 'Crissar', 120],
    ];

    /**
     * Lugares que aparecen en los reportes y que no supimos ubicar. Se siembran
     * igual para que el importador los reconozca en vez de dejarlos como texto
     * suelto, pero sin zona ni coordenadas: inventarlas desviaría las
     * estimaciones de llegada en silencio, que es el peor error posible porque
     * no da señal.
     *
     * Que alguien de operaciones los coloque es trabajo pendiente.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const POR_UBICAR = [
        ['chavina', 'Chaviña'],
        ['aychuyo', 'Aychuyo'],
        ['tumpiri', 'Tumpiri'],
        ['mucra', 'Mucra'],
        ['timpure', 'Timpure'],
        ['jaguey', 'Jaguey'],
        ['chillo', 'Chillo'],
        ['mataro_chico', 'Mataro Chico'],
        ['poroma', 'Poroma'],
        ['vacas', 'Vacas'],
        ['camata', 'Camata'],
        ['higueritas', 'Higueritas'],
        ['puerto_grau', 'Puerto Grau'],
        ['pozo_santo', 'Pozo Santo'],
        ['herbay_bajo', 'Herbay Bajo'],
        ['tanaka', 'Tanaka'],
        ['pucamarca', 'Pucamarca'],
    ];

    /**
     * Variantes que la normalización por sí sola no resuelve, porque no son
     * cuestión de tildes ni de mayúsculas sino errores de tecleo. Salen de los
     * reportes reales; el resto de los alias los aprende el catálogo solo.
     *
     * @var array<string, list<string>>
     */
    private const ALIAS = [
        'san_rafael' => ['U.M. SAN RAFAEL', 'UM SAN RAFAEL', 'MINA SAN RAFAEL'],
        'nazca' => ['NASCA'],
        'callao' => ['PUERTO DEL CALLAO'],
        'camana' => ['CAMNA'],
        'pisco' => ['PISO'],
        'canete' => ['NUEVO CANETE', 'NUEVO CANATE'],
        'bujama' => ['BUJAMA ALTA'],
    ];

    public function run(): void
    {
        foreach (self::PUNTOS as [$codigo, $nombre, $latitud, $longitud, $zona]) {
            $this->registrar($codigo, [
                'nombre' => $nombre,
                'latitud' => $latitud,
                'longitud' => $longitud,
                'orden_corredor' => $zona,
                'es_eje_corredor' => in_array($codigo, self::EJES, true),
                // Juliaca es la base: la única con taller y desde la única que
                // se despacha a mina.
                'es_zona_base' => $codigo === 'juliaca',
                'tiene_taller' => $codigo === 'juliaca',
                'dias_permanencia_habitual' => $codigo === 'juliaca' ? 2 : null,
            ]);
        }

        foreach (self::SIN_POSICION as [$codigo, $nombre, $zona]) {
            $this->registrar($codigo, [
                'nombre' => $nombre,
                'latitud' => null,
                'longitud' => null,
                'orden_corredor' => $zona,
                'es_eje_corredor' => false,
                'observaciones' => 'Falta confirmar la posición exacta.',
            ]);
        }

        foreach (self::POR_UBICAR as [$codigo, $nombre]) {
            $this->registrar($codigo, [
                'nombre' => $nombre,
                'latitud' => null,
                'longitud' => null,
                'orden_corredor' => null,
                'es_eje_corredor' => false,
                'observaciones' => 'Sale en los reportes pero falta ubicarla en el corredor.',
            ]);
        }

        foreach (self::ALIAS as $codigo => $variantes) {
            $ubicacion = Ubicacion::query()->where('codigo', $codigo)->firstOrFail();

            foreach ($variantes as $variante) {
                $ubicacion->registrarAlias($variante);
            }
        }
    }

    /**
     * Nombre legible de cada zona, para las pantallas.
     *
     * @return array<int, string>
     */
    public static function zonas(): array
    {
        return self::ZONAS;
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function registrar(string $codigo, array $atributos): void
    {
        Ubicacion::query()->updateOrCreate(['codigo' => $codigo], $atributos);
    }
}
