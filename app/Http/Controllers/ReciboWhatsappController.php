<?php

namespace App\Http\Controllers;

use App\Enums\EstadoEnvio;
use App\Models\EnvioWhatsapp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Los recibos que manda el servicio de WhatsApp cuando un aviso llega al
 * celular (✓✓) o lo leen (✓✓ azul). No pasa por el login: lo llama el
 * proceso Node del mismo servidor, con el token que comparten.
 */
class ReciboWhatsappController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = (string) config('transpaty.whatsapp.token');

        abort_unless($token !== '' && hash_equals($token, (string) $request->bearerToken()), 401);

        $recibos = $request->validate([
            'recibos' => ['required', 'array', 'max:200'],
            'recibos.*.id' => ['required', 'string', 'max:100'],
            'recibos.*.estado' => ['required', 'in:entregado,leido'],
        ])['recibos'];

        $envios = EnvioWhatsapp::query()
            ->whereIn('mensaje_id', array_column($recibos, 'id'))
            ->get()
            ->keyBy('mensaje_id');

        foreach ($recibos as $recibo) {
            $envios->get($recibo['id'])?->avanzarA(EstadoEnvio::from($recibo['estado']));
        }

        return response()->json(['ok' => true]);
    }
}
