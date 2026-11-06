<?php

namespace App\Models\Concerns;

use App\Models\HistorialCambio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Deja en el historial cada alta, cambio y baja del registro: quién lo hizo,
 * cuándo, y el valor de cada campo antes y después. Lo que se guarda con
 * `saveQuietly` o con un update masivo no pasa por acá.
 *
 * El modelo puede afinar:
 * - `etiquetaHistorial()`: cómo se reconoce el registro («V-00003», una placa);
 * - `camposSinHistorial()`: campos que no se registran (además de las fechas
 *   de alta y cambio; los ocultos se registran enmascarados).
 *
 * @mixin Model
 */
trait RegistraHistorial
{
    public static function bootRegistraHistorial(): void
    {
        static::created(function (Model $modelo): void {
            /** @var Model&self $modelo */
            $modelo->anotarEnHistorial('creado', $modelo->cambiosDeHistorial(
                array_fill_keys(array_keys($modelo->getAttributes()), null),
                $modelo->getAttributes(),
            ));
        });

        static::updated(function (Model $modelo): void {
            /** @var Model&self $modelo */
            $cambios = $modelo->cambiosDeHistorial(
                array_intersect_key($modelo->getRawOriginal(), $modelo->getChanges()),
                $modelo->getChanges(),
            );

            if ($cambios === []) {
                return;
            }

            $modelo->anotarEnHistorial($modelo->accionDeHistorial($cambios), $cambios);
        });

        static::deleted(function (Model $modelo): void {
            /** @var Model&self $modelo */
            $modelo->anotarEnHistorial('eliminado', $modelo->cambiosDeHistorial(
                $modelo->getRawOriginal(),
                array_fill_keys(array_keys($modelo->getRawOriginal()), null),
            ));
        });
    }

    /**
     * Cómo se reconoce el registro en el historial.
     */
    public function etiquetaHistorial(): ?string
    {
        return null;
    }

    /**
     * Campos que no vale la pena registrar: contadores, cachés.
     *
     * @return list<string>
     */
    protected function camposSinHistorial(): array
    {
        return [];
    }

    /**
     * Un cambio que anula el registro se anota como anulación, no como un
     * cambio más.
     *
     * @param  array<string, array{0: mixed, 1: mixed}>  $cambios
     */
    protected function accionDeHistorial(array $cambios): string
    {
        foreach (['anulado_at', 'anulada_at'] as $campo) {
            if (isset($cambios[$campo]) && $cambios[$campo][0] === null && $cambios[$campo][1] !== null) {
                return 'anulado';
            }
        }

        return 'editado';
    }

    /**
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $despues
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function cambiosDeHistorial(array $antes, array $despues): array
    {
        $ignorados = ['id', 'created_at', 'updated_at', 'remember_token', ...$this->camposSinHistorial()];
        $cambios = [];

        foreach ($despues as $campo => $valor) {
            if (in_array($campo, $ignorados, true)) {
                continue;
            }

            $anterior = $antes[$campo] ?? null;

            if ($anterior === $valor || (is_scalar($anterior) && is_scalar($valor) && (string) $anterior === (string) $valor)) {
                continue;
            }

            $cambios[$campo] = in_array($campo, $this->getHidden(), true)
                ? [$anterior === null ? null : '••••', $valor === null ? null : '••••']
                : [$anterior, $valor];
        }

        return $cambios;
    }

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $cambios
     */
    private function anotarEnHistorial(string $accion, array $cambios): void
    {
        HistorialCambio::query()->create([
            'user_id' => Auth::id(),
            'accion' => $accion,
            'modelo' => class_basename($this),
            'modelo_id' => $this->getKey(),
            'etiqueta' => mb_substr($this->etiquetaHistorial() ?? '', 0, 160) ?: null,
            'cambios' => $cambios === [] ? null : $cambios,
        ]);
    }
}
