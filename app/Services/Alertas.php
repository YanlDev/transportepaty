<?php

namespace App\Services;

use App\Enums\TipoAlerta;
use App\Models\AreaAviso;
use App\Models\ConfiguracionAlerta;
use App\Models\User;
use App\Notifications\Alerta;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * La puerta de entrada de las alertas: quien quiere avisar algo dice qué pasó
 * y esto decide, con la configuración del tipo, si se avisa, por dónde y a
 * quién.
 *
 * Una alerta nunca rompe lo que la disparó: si algo falla al avisar, se
 * reporta y la operación sigue (taller guardó su apto aunque WhatsApp esté
 * caído).
 */
class Alertas
{
    /** Mientras sea mayor que cero no se avisa nada (ver `sinAvisar()`). */
    private static int $silencio = 0;

    public function __construct(
        private readonly AvisosPorWhatsapp $avisos,
        private readonly ContactoWhatsapp $contacto,
    ) {}

    /**
     * Corre algo sin disparar alertas: la reconstrucción del historial pasa
     * GR viejas por el mismo camino que una GR nueva, y no tiene sentido
     * avisar hoy que salió un ticket de agosto.
     *
     * @template T
     *
     * @param  callable(): T  $hacer
     * @return T
     */
    public static function sinAvisar(callable $hacer): mixed
    {
        self::$silencio++;

        try {
            return $hacer();
        } finally {
            self::$silencio--;
        }
    }

    /**
     * Avisa que pasó algo. Quien lo hizo no recibe su propia alerta.
     */
    public function avisar(TipoAlerta $tipo, string $titulo, string $mensaje, ?string $url = null, ?User $autor = null): void
    {
        if (self::$silencio > 0) {
            return;
        }

        rescue(function () use ($tipo, $titulo, $mensaje, $url, $autor): void {
            $configuracion = ConfiguracionAlerta::para($tipo);

            if (! $configuracion->activa) {
                return;
            }

            $usuarios = $this->destinatarios($configuracion)
                ->reject(fn (User $usuario): bool => $usuario->id === $autor?->id);

            $this->enviar($configuracion, $usuarios, new Alerta(
                $tipo,
                $titulo,
                $mensaje,
                $url,
                porApp: $configuracion->por_app,
                porWhatsapp: $configuracion->por_whatsapp,
            ));
        });
    }

    /**
     * Una alerta de prueba solo para quien la pide, por los canales del tipo
     * aunque esté apagado: sirve para ver cómo llega antes de prenderla.
     */
    public function probar(TipoAlerta $tipo, User $usuario): void
    {
        $configuracion = ConfiguracionAlerta::para($tipo);

        Notification::send($usuario, new Alerta(
            $tipo,
            "Prueba: {$tipo->label()}",
            'Así te va a llegar esta alerta.',
            route('alertas.index'),
            porApp: $configuracion->por_app,
            porWhatsapp: $configuracion->por_whatsapp,
        ));
    }

    /**
     * Los usuarios que reciben el tipo: los de sus roles más los elegidos
     * uno por uno, sin repetir.
     *
     * @return Collection<int, User>
     */
    public function destinatarios(ConfiguracionAlerta $configuracion): Collection
    {
        $roles = $configuracion->roles ?? [];
        $sueltos = $configuracion->usuarios ?? [];

        if ($roles === [] && $sueltos === []) {
            return new Collection;
        }

        return User::query()
            ->activos()
            ->where(function ($query) use ($roles, $sueltos): void {
                $query->whereIn('id', $sueltos)
                    ->when($roles !== [], fn ($query) => $query->orWhereHas('roles', fn ($query) => $query->whereIn('name', $roles)));
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, User>  $usuarios
     */
    private function enviar(ConfiguracionAlerta $configuracion, Collection $usuarios, Alerta $alerta): void
    {
        if ($usuarios->isNotEmpty()) {
            Notification::send($usuarios, $alerta);
        }

        if (! $configuracion->por_whatsapp || ($configuracion->areas ?? []) === []) {
            return;
        }

        // Las áreas no son usuarios: no tienen campanita, solo su número.
        AreaAviso::query()
            ->whereIn('id', $configuracion->areas)
            ->where('activa', true)
            ->get()
            ->each(function (AreaAviso $area) use ($alerta): void {
                $numero = $this->contacto->numeroWhatsapp($area->numero);

                if ($numero !== null) {
                    $this->avisos->encolarAlerta($alerta->tipo, $numero, $area->nombre, $alerta->textoWhatsapp(), area: $area);
                }
            });
    }
}
