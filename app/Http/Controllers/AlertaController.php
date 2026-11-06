<?php

namespace App\Http\Controllers;

use App\Enums\TipoAlerta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La bandeja de alertas de cada quien: lo que le avisó el sistema, leído o
 * no. Cada uno ve solo las suyas, así que no hace falta permiso.
 */
class AlertaController extends Controller
{
    /** Cuántas alertas trae la campanita, sin abrir la bandeja. */
    public const RECIENTES = 6;

    public function index(Request $request): Response
    {
        $alertas = $request->user()->notifications()->paginate(30);

        return Inertia::render('alertas/index', [
            'alertas' => $alertas->through(fn (DatabaseNotification $alerta): array => self::fila($alerta)),
            'sinLeer' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Abre una alerta: la marca como leída y lleva a donde se resuelve.
     */
    public function abrir(Request $request, string $alerta): RedirectResponse
    {
        /** @var DatabaseNotification $notificacion */
        $notificacion = $request->user()->notifications()->findOrFail($alerta);
        $notificacion->markAsRead();

        $url = $notificacion->data['url'] ?? null;

        return is_string($url) && $url !== ''
            ? redirect()->to($url)
            : to_route('alertas.index');
    }

    public function leerTodas(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    /**
     * Lo que muestra la campanita en todas las páginas: cuántas faltan leer
     * y las últimas.
     *
     * @return array{sin_leer: int, recientes: array<int, array<string, mixed>>}
     */
    public static function campanita(Request $request): array
    {
        $usuario = $request->user();

        return [
            'sin_leer' => $usuario->unreadNotifications()->count(),
            'recientes' => $usuario->notifications()
                ->limit(self::RECIENTES)
                ->get()
                ->map(fn (DatabaseNotification $alerta): array => self::fila($alerta))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{id: string, tipo: string, tipo_label: string, titulo: string, mensaje: string, leida: bool, fecha: string}
     */
    private static function fila(DatabaseNotification $alerta): array
    {
        $tipo = TipoAlerta::tryFrom((string) ($alerta->data['tipo'] ?? ''));

        return [
            'id' => $alerta->id,
            'tipo' => $tipo->value ?? '',
            'tipo_label' => $tipo?->label() ?? 'Alerta',
            'titulo' => (string) ($alerta->data['titulo'] ?? ''),
            'mensaje' => (string) ($alerta->data['mensaje'] ?? ''),
            'leida' => $alerta->read_at !== null,
            'fecha' => $alerta->created_at?->toIso8601String() ?? '',
        ];
    }
}
