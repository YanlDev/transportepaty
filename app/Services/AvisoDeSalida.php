<?php

namespace App\Services;

use App\Models\Programacion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * El preaviso que se le manda por WhatsApp al conductor antes de que la
 * unidad salga: está prohibido avanzar sin guía de remisión.
 *
 * El texto se arma acá y no en el navegador para que sea uno solo: es la
 * constancia de lo que se avisó, y dos redacciones distintas según quién dio
 * el clic no servirían de respaldo ante nadie.
 */
class AvisoDeSalida
{
    /**
     * El aviso para una salida concreta, con su unidad, su conductor y su
     * destino, tal como se le manda al conductor.
     */
    public function mensajeParaConductor(Programacion $programacion): string
    {
        $conductor = "{$programacion->conductor->nombres} {$programacion->conductor->apellidos}";

        $lineas = [
            '*PROGRAMACIÓN — TRANSPORTES PATY*',
            "Fecha: {$programacion->fecha->format('d/m/Y')}",
            "Unidad: *{$programacion->vehiculo->placa}*",
            "Conductor: {$conductor}",
            "Cliente: {$programacion->cliente->alias}",
            "Destino: {$programacion->destino}",
            '',
            '*No inicies el viaje sin documentación validada.*',
        ];

        return implode("\n", $lineas);
    }

    /**
     * Los avisos a las áreas de la casa por esta salida: abastecimiento
     * prepara la carga y facturación registra el flete acordado.
     *
     * Van con el número que corresponde a cada una, ya resuelto, y se omite
     * la que no tenga WhatsApp configurado. El monto solo viaja al de
     * facturación: al patio no le corresponde ver precios.
     *
     * @return array<int, array{area: string, numero: string, mensaje: string}>
     */
    public function avisosDeArea(Programacion $programacion): array
    {
        $areas = [
            'Abastecimiento' => [
                'numero' => $this->numeroWhatsapp(config('transpaty.areas.abastecimiento')),
                'mensaje' => $this->mensajeParaAbastecimiento($programacion),
            ],
            'Facturación' => [
                'numero' => $this->numeroWhatsapp(config('transpaty.areas.facturacion')),
                'mensaje' => $this->mensajeParaFacturacion($programacion),
            ],
        ];

        $avisos = [];

        foreach ($areas as $area => $datos) {
            if ($datos['numero'] === null) {
                continue;
            }

            $avisos[] = ['area' => $area, 'numero' => $datos['numero'], 'mensaje' => $datos['mensaje']];
        }

        return $avisos;
    }

    /** Lo que abastecimiento necesita para preparar: qué unidad sale y a dónde. */
    public function mensajeParaAbastecimiento(Programacion $programacion): string
    {
        return implode("\n", [
            '*UNIDAD PROGRAMADA — CARGA PARTICULAR*',
            "Fecha: {$programacion->fecha->format('d/m/Y')}",
            "Unidad: *{$programacion->vehiculo->placa}*",
            "Conductor: {$programacion->conductor->nombres} {$programacion->conductor->apellidos}",
            "Destino: {$programacion->destino}",
        ]);
    }

    /** Lo mismo para facturación, con el cliente y el flete acordado. */
    public function mensajeParaFacturacion(Programacion $programacion): string
    {
        return implode("\n", [
            '*UNIDAD PROGRAMADA — CARGA PARTICULAR*',
            "Fecha: {$programacion->fecha->format('d/m/Y')}",
            "Unidad: *{$programacion->vehiculo->placa}*",
            "Conductor: {$programacion->conductor->nombres} {$programacion->conductor->apellidos}",
            "Cliente: {$programacion->cliente->alias}",
            "Destino: {$programacion->destino}",
            $this->lineaDelFlete($programacion),
        ]);
    }

    /**
     * A quién se le puede mandar el aviso de esta salida: el celular del
     * conductor, su alterno si lo tiene y el número adicional que se haya
     * cargado en la programación.
     *
     * Se devuelven en orden de preferencia y sin repetidos —si el alterno es
     * el mismo número, ofrecer dos veces el mismo chat solo confunde—.
     *
     * @return array<int, array{etiqueta: string, numero: string}>
     */
    public function destinatarios(Programacion $programacion): array
    {
        $candidatos = [
            'Conductor' => $programacion->conductor->telefono,
            'Alterno' => $programacion->conductor->telefono_alterno,
            'Adicional' => $programacion->whatsapp_adicional,
        ];

        $destinatarios = [];

        foreach ($candidatos as $etiqueta => $telefono) {
            $numero = $this->numeroWhatsapp($telefono);

            if ($numero === null || in_array($numero, array_column($destinatarios, 'numero'), strict: true)) {
                continue;
            }

            $destinatarios[] = ['etiqueta' => $etiqueta, 'numero' => $numero];
        }

        return $destinatarios;
    }

    /**
     * El resumen del día para el número de operaciones: qué unidades salen y
     * cuáles siguen sin GR. Es el aviso que mira quien no está en el patio.
     *
     * @param  Collection<int, Programacion>  $programaciones
     * @param  array<int, string>  $guias  El N° de GR del día por `vehiculo_id`.
     */
    public function resumenDelDia(string $fecha, Collection $programaciones, array $guias): string
    {
        $dia = CarbonImmutable::parse($fecha)->format('d/m/Y');

        $lineas = [
            "🚛 *TRANSPORTES PATY — SALIDAS {$dia}*",
            '',
        ];

        foreach ($programaciones as $programacion) {
            $numeroGr = $guias[$programacion->vehiculo_id] ?? null;
            $marca = $numeroGr === null ? '❌ SIN GR' : "✅ {$numeroGr}";

            $lineas[] = "{$marca} · {$programacion->vehiculo->placa} · {$programacion->cliente->alias} · {$programacion->destino}";
        }

        $sinGr = $programaciones->filter(fn (Programacion $programacion): bool => ! isset($guias[$programacion->vehiculo_id]))->count();

        $lineas[] = '';
        $lineas[] = $sinGr === 0
            ? 'Todas las unidades programadas tienen su GR.'
            : "*{$sinGr} ".($sinGr === 1 ? 'unidad sigue' : 'unidades siguen').' sin GR: ninguna puede avanzar hasta emitirla.*';

        return implode("\n", $lineas);
    }

    /**
     * El teléfono en el formato que pide un enlace de WhatsApp: solo dígitos y
     * con el código de país. Los celulares se guardan con los nueve dígitos de
     * siempre, sin el 51 adelante.
     *
     * Devuelve null si no hay con qué armar un número: sin eso, el botón de
     * avisar abriría un chat vacío.
     */
    public function numeroWhatsapp(?string $telefono): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $telefono) ?? '';

        if (strlen($digitos) === 9 && str_starts_with($digitos, '9')) {
            return "51{$digitos}";
        }

        // Ya viene con código de país (51 + nueve dígitos) o es un fijo con
        // su prefijo: se manda tal cual mientras tenga largo de teléfono.
        return strlen($digitos) >= 9 ? $digitos : null;
    }

    /** El número de operaciones que recibe copia de los avisos del día. */
    public function whatsappOperaciones(): ?string
    {
        return $this->numeroWhatsapp(config('transpaty.operaciones.whatsapp'));
    }

    /**
     * La advertencia, igual para todos: qué documentos se necesitan, quién
     * los valida y quién responde por la multa si la unidad avanza igual.
     *
     * Va como mensaje aparte del aviso de programación —y no pegada a él—
     * para que no quede escondida detrás del «ver más» de WhatsApp.
     */
    public function advertencia(): string
    {
        $lineas = [
            '*PROHIBIDO INICIAR EL VIAJE SIN DOCUMENTACIÓN VALIDADA*',
            '',
            'Antes de salir verifica que cuentes con:',
            '• Guía de Remisión del Remitente (GR)',
            '• Guía de Remisión del Transportista (GRT)',
            '',
            'Programación valida la GR y gestiona la GRT antes del viaje. ¿No corresponde GRT por excepción normativa? *Programación debe validarlo, no lo determines por tu cuenta.*',
            '',
            'Revisa que la guía tenga bien las placas, tu nombre, tu DNI y el destino. Si algo no coincide, *no salgas*: avisa a Programación.',
            '',
            'Si no tienes la documentación validada, *llama y espera*. Esperar no es falta; salir sin documentos sí lo es.',
            '',
            'Multa SUNAT de hasta 4 UIT, más retención del vehículo y de la carga. *Si igual inicias el viaje, el conductor se hará cargo de pagar la multa.*',
            '',
            $this->lineaDeOficina(),
        ];

        return implode("\n", $lineas);
    }

    /**
     * El flete acordado, con el otro importe calculado: si se pactó sin IGV
     * se agrega el total, y si se pactó con IGV adentro se muestra el neto.
     * Facturación necesita los dos y no tiene por qué sacar la cuenta.
     *
     * Cuando no hay precio se dice: una línea faltante se lee como un olvido,
     * y facturación termina preguntando.
     */
    private function lineaDelFlete(Programacion $programacion): string
    {
        if ($programacion->precio_flete === null) {
            return 'Flete: *sin precio acordado*';
        }

        ['neto' => $neto, 'total' => $total] = $this->desglosarFlete($programacion);

        if ($programacion->precio_incluye_igv) {
            return 'Flete acordado: *S/ '.number_format($total, 2)."* (IGV incluido)\nNeto: S/ ".number_format($neto, 2);
        }

        return 'Flete acordado: *S/ '.number_format($neto, 2)."* + IGV\nTotal con IGV: S/ ".number_format($total, 2);
    }

    /**
     * El flete acordado en sus dos importes. El monto guardado es el que se
     * pactó; el otro sale de la tasa vigente, para que no queden dos cifras
     * guardadas que puedan contradecirse.
     *
     * @return array{neto: float, total: float}
     */
    public function desglosarFlete(Programacion $programacion): array
    {
        $monto = (float) $programacion->precio_flete;
        $igv = (float) config('transpaty.igv');

        if ($programacion->precio_incluye_igv) {
            return [
                'neto' => round($monto / (1 + $igv), 2),
                'total' => round($monto, 2),
            ];
        }

        return [
            'neto' => round($monto, 2),
            'total' => round($monto * (1 + $igv), 2),
        ];
    }

    /**
     * A qué número llamar. Va solo en la advertencia —el aviso de
     * programación se deja corto— y se omite si no hay ninguno configurado:
     * media frase con un teléfono en blanco es peor que no ponerla.
     */
    private function lineaDeOficina(): string
    {
        $oficina = config('transpaty.operaciones.telefono_oficina');

        return $oficina ? "Oficina: {$oficina}" : '';
    }
}
