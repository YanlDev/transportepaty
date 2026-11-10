<?php

namespace App\Services;

use App\Models\Articulo;
use App\Models\Cliente;
use App\Models\Conductor;
use App\Models\OrdenTrabajo;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Models\Vuelta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * El buscador de toda la app: una placa, un ticket, una OT, una GR, un
 * artículo, un conductor o un cliente, desde cualquier pantalla.
 *
 * Cada grupo se busca solo si la persona puede ver ese módulo: lo que no
 * aparece en su menú tampoco aparece acá.
 *
 * @phpstan-type Resultado array{id: string, titulo: string, detalle: string|null, url: string}
 * @phpstan-type Grupo array{titulo: string, items: list<Resultado>}
 */
class BuscadorGlobal
{
    /** Cuántos resultados por grupo: es para llegar, no para listar. */
    private const POR_GRUPO = 5;

    /** Con menos letras casi todo coincide. */
    public const MINIMO = 2;

    /**
     * @return list<Grupo>
     */
    public function buscar(User $user, string $texto): array
    {
        $texto = trim($texto);

        if (mb_strlen($texto) < self::MINIMO) {
            return [];
        }

        $grupos = [
            'Unidades' => $user->can('vehiculos.ver') ? $this->unidades($texto) : [],
            'Tickets' => $user->can('programacion.ver') ? $this->tickets($texto) : [],
            'Órdenes de trabajo' => $user->can('mantenimiento.ver') ? $this->ordenes($texto) : [],
            'Artículos' => $user->can('almacen.ver') ? $this->articulos($texto) : [],
            'Guías (GR)' => $user->can('viajes.ver') ? $this->guias($texto) : [],
            'Conductores' => $user->can('conductores.ver') ? $this->conductores($texto) : [],
            'Clientes' => $user->can('clientes.ver') ? $this->clientes($texto) : [],
        ];

        $conResultados = [];

        foreach ($grupos as $titulo => $items) {
            if ($items !== []) {
                $conResultados[] = ['titulo' => $titulo, 'items' => array_values($items)];
            }
        }

        return $conResultados;
    }

    /**
     * @return array<int, Resultado>
     */
    private function unidades(string $texto): array
    {
        return Vehiculo::query()
            ->where(fn (Builder $query) => $this->conPlaca($query, $texto))
            ->orderBy('placa')
            ->limit(self::POR_GRUPO)
            ->get(['id', 'placa', 'tipo', 'marca', 'modelo'])
            ->map(fn (Vehiculo $vehiculo): array => [
                'id' => "unidad-{$vehiculo->id}",
                'titulo' => $vehiculo->placa,
                'detalle' => trim(ucfirst((string) $vehiculo->tipo->value)." · {$vehiculo->marca} {$vehiculo->modelo}", ' ·'),
                'url' => route('vehiculos.show', $vehiculo, absolute: false),
            ])
            ->values()
            ->all();
    }

    /**
     * Por código del ticket o por la placa del tracto; primero los más nuevos.
     *
     * @return array<int, Resultado>
     */
    private function tickets(string $texto): array
    {
        return Vuelta::query()
            ->with(['tracto:id,placa', 'conductor:id,nombres,apellidos'])
            ->where(function (Builder $query) use ($texto): void {
                $query->whereLike('codigo', "%{$texto}%", caseSensitive: false)
                    ->orWhereHas('tracto', fn (Builder $tracto) => $this->conPlaca($tracto, $texto));
            })
            ->latest('id')
            ->limit(self::POR_GRUPO)
            ->get()
            ->map(fn (Vuelta $vuelta): array => [
                'id' => "ticket-{$vuelta->id}",
                'titulo' => "{$vuelta->codigo} · {$vuelta->tracto->placa}",
                'detalle' => $vuelta->conductor?->nombre_completo,
                'url' => route('vueltas.show', $vuelta, absolute: false),
            ])
            ->values()
            ->all();
    }

    /**
     * Por número (OT-00012 o 12) o por la placa de la unidad.
     *
     * @return array<int, Resultado>
     */
    private function ordenes(string $texto): array
    {
        $numero = preg_match('/^(?:ot[-\s]?)?0*(\d+)$/i', $texto, $partes) === 1 ? (int) $partes[1] : null;

        return OrdenTrabajo::query()
            ->with('vehiculo:id,placa')
            ->where(function (Builder $query) use ($texto, $numero): void {
                $query->whereHas('vehiculo', fn (Builder $vehiculo) => $this->conPlaca($vehiculo, $texto));

                if ($numero !== null) {
                    $query->orWhere('id', $numero);
                }
            })
            ->latest('id')
            ->limit(self::POR_GRUPO)
            ->get()
            ->map(fn (OrdenTrabajo $orden): array => [
                'id' => "orden-{$orden->id}",
                'titulo' => "{$orden->numero()} · {$orden->vehiculo->placa}",
                'detalle' => $orden->trabajo,
                'url' => route('mantenimiento.ordenes.edit', $orden, absolute: false),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, Resultado>
     */
    private function articulos(string $texto): array
    {
        return Articulo::query()
            ->where(function (Builder $query) use ($texto): void {
                $query->whereLike('nombre', "%{$texto}%", caseSensitive: false)
                    ->orWhereLike('codigo', "%{$texto}%", caseSensitive: false)
                    ->orWhereLike('numero_parte', "%{$texto}%", caseSensitive: false);
            })
            ->orderBy('nombre')
            ->limit(self::POR_GRUPO)
            ->get(['id', 'codigo', 'nombre', 'numero_parte'])
            ->map(fn (Articulo $articulo): array => [
                'id' => "articulo-{$articulo->id}",
                'titulo' => $articulo->nombre,
                'detalle' => trim("{$articulo->codigo} · {$articulo->numero_parte}", ' ·'),
                'url' => route('almacen.articulos.show', $articulo, absolute: false),
            ])
            ->values()
            ->all();
    }

    /**
     * Las GR no tienen ficha propia: se abre el listado ya filtrado.
     *
     * @return array<int, Resultado>
     */
    private function guias(string $texto): array
    {
        return Viaje::query()
            ->whereLike('numero_gr', "%{$texto}%", caseSensitive: false)
            ->latest('fecha_emision')
            ->limit(self::POR_GRUPO)
            ->get(['id', 'numero_gr', 'placa_tracto', 'cliente'])
            ->map(fn (Viaje $viaje): array => [
                'id' => "guia-{$viaje->id}",
                'titulo' => $viaje->numero_gr,
                'detalle' => trim("{$viaje->placa_tracto} · {$viaje->cliente}", ' ·'),
                'url' => route('viajes.index', ['buscar' => $viaje->numero_gr], absolute: false),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, Resultado>
     */
    private function conductores(string $texto): array
    {
        return Conductor::query()
            ->where(function (Builder $query) use ($texto): void {
                $query->whereLike('nombres', "%{$texto}%", caseSensitive: false)
                    ->orWhereLike('apellidos', "%{$texto}%", caseSensitive: false)
                    ->orWhereLike('documento', "%{$texto}%", caseSensitive: false);
            })
            ->orderBy('apellidos')
            ->limit(self::POR_GRUPO)
            ->get()
            ->map(fn (Conductor $conductor): array => [
                'id' => "conductor-{$conductor->id}",
                'titulo' => $conductor->nombre_completo,
                'detalle' => $conductor->documento,
                'url' => route('conductores.show', $conductor, absolute: false),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, Resultado>
     */
    private function clientes(string $texto): array
    {
        return Cliente::query()
            ->where(function (Builder $query) use ($texto): void {
                $query->whereLike('razon_social', "%{$texto}%", caseSensitive: false)
                    ->orWhereLike('alias', "%{$texto}%", caseSensitive: false)
                    ->orWhereLike('nombre_comercial', "%{$texto}%", caseSensitive: false)
                    ->orWhereLike('ruc', "%{$texto}%", caseSensitive: false);
            })
            ->orderBy('razon_social')
            ->limit(self::POR_GRUPO)
            ->get(['id', 'razon_social', 'alias', 'ruc'])
            ->map(fn (Cliente $cliente): array => [
                'id' => "cliente-{$cliente->id}",
                'titulo' => $cliente->alias ?: $cliente->razon_social,
                'detalle' => $cliente->ruc,
                'url' => route('clientes.show', $cliente, absolute: false),
            ])
            ->values()
            ->all();
    }

    /**
     * La placa se compara sin guion ni mayúsculas de los dos lados: en la
     * base está «CCB-888» y la gente escribe «ccb888», «CCB 888» o «ccb-8».
     *
     * @param  Builder<covariant Model>  $query
     */
    private function conPlaca(Builder $query, string $texto): void
    {
        $limpio = mb_strtolower(str_replace(['-', ' '], '', $texto));

        $query->whereRaw("lower(replace(placa, '-', '')) like ?", ["%{$limpio}%"]);
    }
}
