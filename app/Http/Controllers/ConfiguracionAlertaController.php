<?php

namespace App\Http\Controllers;

use App\Enums\EstadoEnvio;
use App\Enums\TipoAlerta;
use App\Http\Requests\ActualizarConfiguracionAlertaRequest;
use App\Models\AreaAviso;
use App\Models\ConfiguracionAlerta;
use App\Models\EnvioWhatsapp;
use App\Models\User;
use App\Services\Alertas;
use App\Services\WhatsappServicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Qué alertas salen, por dónde y a quién. Cada tipo se guarda por separado,
 * y el que nadie tocó sigue con sus valores de fábrica.
 */
class ConfiguracionAlertaController extends Controller
{
    /** Cuántos envíos recientes se muestran para ver si están llegando. */
    private const ENVIOS_RECIENTES = 15;

    public function index(Alertas $alertas, WhatsappServicio $whatsapp): Response
    {
        $this->authorize('configurar-alertas');

        return Inertia::render('configuracion/alertas', [
            'tipos' => collect(TipoAlerta::cases())
                ->map(function (TipoAlerta $tipo) use ($alertas): array {
                    $configuracion = ConfiguracionAlerta::para($tipo);
                    $destinatarios = $alertas->destinatarios($configuracion);

                    return [
                        'tipo' => $tipo->value,
                        'label' => $tipo->label(),
                        'descripcion' => $tipo->descripcion(),
                        'es_resumen' => $tipo->esResumenDiario(),
                        'activa' => $configuracion->activa,
                        'por_app' => $configuracion->por_app,
                        'por_whatsapp' => $configuracion->por_whatsapp,
                        'roles' => $configuracion->roles ?? [],
                        'usuarios' => $configuracion->usuarios ?? [],
                        'areas' => $configuracion->areas ?? [],
                        'hora' => $configuracion->hora,
                        // Quiénes la reciben hoy, para ver el efecto de los
                        // roles sin tener que ir a la lista de usuarios.
                        'destinatarios' => $destinatarios->map(fn (User $usuario): array => [
                            'nombre' => $usuario->name,
                            'con_celular' => $usuario->celular !== null,
                        ])->values()->all(),
                    ];
                })
                ->all(),
            'roles' => Role::query()->orderBy('name')->pluck('name')->all(),
            'usuarios' => User::query()
                ->activos()
                ->orderBy('name')
                ->get(['id', 'name', 'celular'])
                ->map(fn (User $usuario): array => [
                    'id' => $usuario->id,
                    'nombre' => $usuario->name,
                    'con_celular' => $usuario->celular !== null,
                ])
                ->all(),
            'areas' => AreaAviso::query()
                ->orderBy('orden')
                ->orderBy('id')
                ->get(['id', 'nombre', 'activa'])
                ->map(fn (AreaAviso $area): array => ['id' => $area->id, 'nombre' => $area->nombre, 'activa' => $area->activa])
                ->all(),
            'whatsappConectado' => fn (): bool => $whatsapp->conectado(),
            'envios' => EnvioWhatsapp::query()
                ->where('tipo', EnvioWhatsapp::TIPO_ALERTA)
                ->latest('id')
                ->limit(self::ENVIOS_RECIENTES)
                ->get()
                ->map(fn (EnvioWhatsapp $envio): array => [
                    'id' => $envio->id,
                    'alerta' => TipoAlerta::tryFrom((string) $envio->alerta)?->label() ?? 'Alerta',
                    'destino' => $envio->destino,
                    'estado' => $envio->estado->value,
                    'estado_label' => $envio->estado->label(),
                    'error' => $envio->estado === EstadoEnvio::Fallido ? $envio->error : null,
                    'fecha' => $envio->created_at->toIso8601String(),
                ])
                ->all(),
        ]);
    }

    public function update(ActualizarConfiguracionAlertaRequest $request, TipoAlerta $tipo): RedirectResponse
    {
        $this->authorize('configurar-alertas');

        $datos = $request->validated();

        ConfiguracionAlerta::query()->updateOrCreate(['tipo' => $tipo->value], [
            'activa' => $datos['activa'],
            'por_app' => $datos['por_app'],
            'por_whatsapp' => $datos['por_whatsapp'],
            'roles' => array_values(array_unique($datos['roles'])),
            'usuarios' => array_values(array_unique(array_map('intval', $datos['usuarios']))),
            'areas' => array_values(array_unique(array_map('intval', $datos['areas']))),
            'hora' => $tipo->esResumenDiario() ? $datos['hora'] : null,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => "Alerta «{$tipo->label()}» guardada."]);
    }

    /**
     * Manda una de prueba solo a quien la pide, para ver cómo llega.
     */
    public function probar(Request $request, TipoAlerta $tipo, Alertas $alertas): RedirectResponse
    {
        $this->authorize('configurar-alertas');

        $usuario = $request->user();
        $configuracion = ConfiguracionAlerta::para($tipo);
        $alertas->probar($tipo, $usuario);

        $sinCelular = $configuracion->por_whatsapp && $usuario->celular === null;

        return back()->with('toast', [
            'type' => $sinCelular ? 'warning' : 'success',
            'message' => $sinCelular
                ? 'Te llegó a la campanita, pero no por WhatsApp: tu usuario no tiene celular cargado.'
                : 'Prueba enviada: mírala en la campanita'.($configuracion->por_whatsapp ? ' y en tu WhatsApp.' : '.'),
        ]);
    }
}
