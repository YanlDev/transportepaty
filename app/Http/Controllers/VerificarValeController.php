<?php

namespace App\Http\Controllers;

use App\Enums\EstadoValeCompra;
use App\Models\DetalleValeCompra;
use App\Models\ValeCompra;
use App\Services\FirmaDeVales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * La página pública donde un proveedor comprueba un vale de compra, sin
 * cuenta en el sistema: llega por el QR o escribiendo el código impreso.
 *
 * Lo que muestra sale de la base en ese momento, no del papel: el vale real,
 * si su firma sigue siendo válida y si todavía sirve para comprar. Sin el QR
 * o el código no se ve nada: no hay listado ni números que recorrer.
 */
class VerificarValeController extends Controller
{
    public function formulario(): \Inertia\Response
    {
        return Inertia::render('publico/verificar-vale', ['vale' => null, 'buscado' => false]);
    }

    /**
     * Busca por el código que se escribe a mano y lleva a la página del vale.
     */
    public function buscar(Request $request): RedirectResponse
    {
        $request->validate(
            ['codigo' => ['required', 'string', 'max:40']],
            ['codigo.required' => 'Escribe el código que está impreso en el vale.'],
        );

        $vale = ValeCompra::query()
            ->where('codigo_verificacion', ValeCompra::normalizarCodigo($request->string('codigo')->value()))
            ->first();

        if ($vale === null) {
            return back()->withErrors(['codigo' => 'No existe ningún vale con ese código. Revisa que esté bien escrito.']);
        }

        return to_route('vales.verificar', $vale->codigo_publico);
    }

    public function show(Request $request, string $codigo, FirmaDeVales $firma): Response
    {
        $vale = ValeCompra::query()
            ->where('codigo_publico', $codigo)
            ->with(['proveedor', 'solicitante:id,name', 'aprobador:id,name', 'detalles.articulo', 'vehiculo:id,placa'])
            ->first();

        if ($vale === null) {
            return Inertia::render('publico/verificar-vale', ['vale' => null, 'buscado' => true])
                ->toResponse($request)
                ->setStatusCode(Response::HTTP_NOT_FOUND);
        }

        $situacion = $vale->situacion();
        $aprobado = $vale->estado === EstadoValeCompra::Aprobado;
        $autentico = $aprobado && $firma->esAutentico($vale);

        $vale->consultas()->create([
            'ip' => $request->ip(),
            'navegador' => mb_substr((string) $request->userAgent(), 0, 255),
            'situacion' => $situacion,
            'created_at' => now(),
        ]);

        return Inertia::render('publico/verificar-vale', [
            'buscado' => true,
            'vale' => [
                'numero' => $vale->numero(),
                'codigo_verificacion' => $vale->codigo_verificacion,
                'tipo_label' => $vale->tipo->label(),
                'situacion' => $situacion->value,
                'situacion_label' => $situacion->label(),
                // Vale para comprar solo si está vigente y la firma es la del servidor.
                'valido' => $autentico && $situacion->sirveParaComprar(),
                'aprobado' => $aprobado,
                'firma_valida' => $aprobado ? $autentico : null,
                'proveedor' => $vale->proveedor->razon_social,
                'proveedor_ruc' => $vale->proveedor->ruc,
                'simbolo' => $vale->moneda->simbolo(),
                'total' => (float) $vale->total,
                'saldo' => $vale->saldo(),
                'recibido' => $vale->recibido(),
                'placa' => $vale->vehiculo?->placa,
                'solicitado_por' => $vale->solicitante?->name,
                'aprobado_por' => $vale->aprobador?->name,
                'aprobado_at' => $vale->aprobado_at?->toIso8601String(),
                'vence_el' => $vale->vence_el?->toDateString(),
                'detalles' => $vale->detalles->map(fn (DetalleValeCompra $detalle): array => [
                    'descripcion' => $detalle->descripcion,
                    'unidad' => $detalle->articulo === null ? 'serv' : ($detalle->articulo->unidad_compra ?? $detalle->articulo->unidad)->abreviatura(),
                    'cantidad' => (float) $detalle->cantidad,
                    'precio_unitario' => (float) $detalle->precio_unitario,
                    'total' => (float) $detalle->total,
                ])->values()->all(),
            ],
        ])->toResponse($request);
    }
}
