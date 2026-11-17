<?php

namespace App\Http\Controllers;

use App\Enums\Permiso;
use App\Models\CierreDeMes;
use App\Models\Factura;
use App\Models\Viaje;
use App\Services\RelojOperativo;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Cerrar un mes de la cobranza y reabrirlo. Solo se cierra un mes que ya
 * terminó; cerrar no exige que todo esté cobrado, pero la pantalla dice qué
 * queda suelto antes de hacerlo.
 */
class CierreDeMesController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->checkPermissionTo(Permiso::CobranzaCerrarMes) ?? false, 403);

        $datos = $request->validate([
            'mes' => ['required', 'date_format:Y-m', Rule::unique('cierres_de_mes', 'mes')],
        ], ['mes.unique' => 'Ese mes ya está cerrado.']);

        $inicio = substr((string) config('transpaty.facturacion.inicio_cobranza'), 0, 7);

        if ($datos['mes'] >= RelojOperativo::fechaDeHoy()->format('Y-m') || $datos['mes'] < $inicio) {
            return back()->withErrors(['mes' => 'Solo se cierra un mes que ya terminó, desde el inicio de la cobranza.']);
        }

        CierreDeMes::query()->create(['mes' => $datos['mes'], 'cerrado_por' => $request->user()->id]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Mes '.self::etiqueta($datos['mes']).' cerrado.']);
    }

    public function destroy(Request $request, string $mes): RedirectResponse
    {
        abort_unless($request->user()?->checkPermissionTo(Permiso::CobranzaCerrarMes) ?? false, 403);

        CierreDeMes::query()->where('mes', $mes)->firstOrFail()->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Mes '.self::etiqueta($mes).' reabierto.']);
    }

    /**
     * Los meses desde el inicio de la cobranza, el más reciente primero, con
     * lo que cada uno tiene suelto: es lo que se mira antes de cerrar.
     *
     * @return list<array<string, mixed>>
     */
    public static function meses(): array
    {
        $hoy = RelojOperativo::fechaDeHoy();
        $inicio = CarbonImmutable::parse((string) config('transpaty.facturacion.inicio_cobranza'))->startOfMonth();
        $cierres = CierreDeMes::query()->with('cerradoPor:id,name')->get()->keyBy('mes');
        $meses = [];

        for ($mes = CarbonImmutable::parse($hoy->format('Y-m-01')); $mes->gte($inicio); $mes = $mes->subMonth()) {
            $clave = $mes->format('Y-m');
            $rango = [$mes->toDateString(), $mes->endOfMonth()->toDateString()];
            $cierre = $cierres->get($clave);
            $facturas = Factura::query()->whereBetween('fecha_emision', $rango);

            $meses[] = [
                'mes' => $clave,
                'label' => self::etiqueta($clave),
                'cerrado' => $cierre !== null,
                'cerrado_por' => $cierre?->cerradoPor?->name,
                'cerrado_el' => $cierre?->created_at?->toDateString(),
                'en_curso' => $clave === $hoy->format('Y-m'),
                'facturas' => (clone $facturas)->count(),
                'sin_cobrar' => (clone $facturas)->whereNull('fecha_pago')->count(),
                'gr_sin_facturar' => Viaje::query()
                    ->whereBetween('fecha_traslado', $rango)
                    ->whereNull('no_facturable_at')
                    ->whereDoesntHave('facturas')
                    ->count(),
            ];
        }

        return $meses;
    }

    private static function etiqueta(string $mes): string
    {
        $nombres = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        return $nombres[(int) substr($mes, 5, 2) - 1].' '.substr($mes, 0, 4);
    }
}
