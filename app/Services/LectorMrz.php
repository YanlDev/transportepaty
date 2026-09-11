<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Lee la banda legible por máquina del DNI: las tres líneas de caracteres y
 * chevrones que van al pie del dorso de la tarjeta.
 *
 * Existe porque el OCR sobre los campos impresos del DNI no es confiable —se
 * comprobó que confunde dígitos, y una fecha de vencimiento mal leída es peor
 * que no tenerla—. La banda, en cambio, trae dígitos de control: cada fecha
 * viene seguida de un dígito calculado a partir de ella, así que un carácter
 * mal reconocido se detecta en vez de guardarse.
 *
 * De ahí la regla de la clase: o devuelve una fecha verificada, o no devuelve
 * nada. Nunca una fecha probable.
 *
 * El formato es el TD1 de la OACI, tres líneas de treinta caracteres, y lo que
 * interesa está en la segunda:
 *
 *     YYMMDD C S YYMMDD C NNN
 *     └nac.┘ │ │ └cad.┘ │ └── nacionalidad
 *            │ └── sexo │
 *            └── control└── control
 */
class LectorMrz
{
    /**
     * Los pesos que la OACI define para el dígito de control: se repiten 7, 3,
     * 1 sobre los caracteres del campo.
     *
     * @var list<int>
     */
    private const PESOS = [7, 3, 1];

    /**
     * Una fecha del MRZ viene como `YYMMDD`, sin siglo. Un DNI vigente o
     * recién vencido nunca caduca en el siglo pasado, así que dos dígitos
     * bastan y se les antepone «20».
     */
    private const SIGLO = '20';

    /**
     * La fecha de caducidad que declara la banda, o null si no se pudo leer o
     * si el dígito de control no cuadra.
     *
     * @param  string  $texto  La salida cruda del OCR, con la basura que traiga.
     */
    public function caducidad(string $texto): ?CarbonImmutable
    {
        foreach ($this->lineasCandidatas($texto) as $linea) {
            // La segunda línea del TD1: nacimiento, su control, el sexo, la
            // caducidad y su control. Se busca el patrón en vez de cortar por
            // posición porque el OCR suele comerse o agregar un carácter al
            // principio de la línea.
            if (preg_match('/(\d{6})(\d)([MF<])(\d{6})(\d)/', $linea, $coincidencia) !== 1) {
                continue;
            }

            [, $nacimiento, $controlNacimiento, , $caducidad, $controlCaducidad] = $coincidencia;

            // Se exige que cuadren los dos controles y no solo el de la
            // caducidad: que la fecha de nacimiento también valide es lo que
            // confirma que se está leyendo un MRZ de verdad y no una cadena de
            // dígitos que dio la casualidad de encajar en el patrón.
            if (! $this->controlValido($caducidad, $controlCaducidad)) {
                continue;
            }

            if (! $this->controlValido($nacimiento, $controlNacimiento)) {
                continue;
            }

            $fecha = $this->comoFecha($caducidad);

            if ($fecha !== null) {
                return $fecha;
            }
        }

        return null;
    }

    /**
     * Las líneas del OCR que pueden ser MRZ: suficientemente largas y con
     * chevrones, que es lo que ninguna otra parte del documento tiene.
     *
     * @return list<string>
     */
    private function lineasCandidatas(string $texto): array
    {
        $candidatas = [];

        foreach (preg_split('/\R/', $texto) ?: [] as $cruda) {
            $limpia = preg_replace('/[^A-Z0-9<]/', '', mb_strtoupper($cruda)) ?? '';

            if (mb_strlen($limpia) >= 25 && substr_count($limpia, '<') >= 3) {
                $candidatas[] = $limpia;
            }
        }

        return $candidatas;
    }

    /**
     * El dígito de control de la OACI: cada carácter vale su cifra, o su
     * posición en el alfabeto más diez, o cero si es un chevrón. Se multiplica
     * por 7, 3, 1 según la posición y se toma el módulo 10.
     */
    private function controlValido(string $campo, string $esperado): bool
    {
        $suma = 0;

        foreach (str_split($campo) as $posicion => $caracter) {
            $valor = match (true) {
                ctype_digit($caracter) => (int) $caracter,
                $caracter === '<' => 0,
                ctype_upper($caracter) => ord($caracter) - 55,
                default => null,
            };

            if ($valor === null) {
                return false;
            }

            $suma += $valor * self::PESOS[$posicion % 3];
        }

        return $suma % 10 === (int) $esperado;
    }

    /**
     * `YYMMDD` a fecha. Devuelve null si los dígitos validaron el control pero
     * no forman un día real —un 31 de febrero pasaría el control igual, porque
     * el dígito solo comprueba la transcripción, no el calendario.
     */
    private function comoFecha(string $yymmdd): ?CarbonImmutable
    {
        $anio = self::SIGLO.substr($yymmdd, 0, 2);
        $mes = substr($yymmdd, 2, 2);
        $dia = substr($yymmdd, 4, 2);

        if (! checkdate((int) $mes, (int) $dia, (int) $anio)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', "{$anio}-{$mes}-{$dia}")->startOfDay();
    }
}
