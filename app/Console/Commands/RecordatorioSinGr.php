<?php

namespace App\Console\Commands;

use App\Models\Ajuste;
use App\Models\AreaAviso;
use App\Models\EnvioWhatsapp;
use App\Services\AvisoDeSalida;
use App\Services\AvisosPorWhatsapp;
use App\Services\RelojOperativo;
use App\Services\WhatsappServicio;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * El recordatorio de las unidades programadas para hoy que siguen sin GR, a
 * las áreas marcadas en el panel de WhatsApp, a la hora configurada ahí.
 *
 * Corre cada minuto y decide solo: manda una vez por día y área, y únicamente
 * si ya pasó la hora, si queda alguna unidad sin GR y si el número está
 * conectado. Si a la hora justa el servidor o WhatsApp estaban caídos, sale
 * apenas vuelvan, no se pierde.
 */
#[Signature('transpaty:recordatorio-sin-gr')]
#[Description('Avisa a las áreas marcadas de las unidades de hoy que siguen sin GR')]
class RecordatorioSinGr extends Command
{
    public function handle(AvisosPorWhatsapp $avisos, WhatsappServicio $whatsapp, AvisoDeSalida $aviso): int
    {
        $hora = Ajuste::valor(Ajuste::HORA_RECORDATORIO);

        if ($hora === null || RelojOperativo::ahora()->format('H:i') < $hora) {
            return self::SUCCESS;
        }

        $areas = $this->areasQueNoLoRecibieronHoy();

        if ($areas->isEmpty()) {
            return self::SUCCESS;
        }

        $sinGr = $avisos->unidadesSinGr(RelojOperativo::hoy());

        if ($sinGr->isEmpty()) {
            return self::SUCCESS;
        }

        if (! $whatsapp->conectado()) {
            $this->warn('WhatsApp no está conectado: se vuelve a intentar en el próximo minuto.');

            return self::SUCCESS;
        }

        foreach ($areas as $area) {
            $numero = $aviso->numeroWhatsapp($area->numero);

            if ($numero !== null) {
                $avisos->encolarRecordatorio($area, $numero);
                $this->info("Recordatorio de {$sinGr->count()} unidades sin GR a {$area->nombre}.");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Las áreas activas que reciben el recordatorio y hoy (en Lima) todavía
     * no lo tienen anotado.
     *
     * @return Collection<int, AreaAviso>
     */
    private function areasQueNoLoRecibieronHoy(): Collection
    {
        $inicioDelDia = RelojOperativo::ahora()->startOfDay()->utc();

        $yaRecibieron = EnvioWhatsapp::query()
            ->where('tipo', EnvioWhatsapp::TIPO_RECORDATORIO)
            ->where('created_at', '>=', $inicioDelDia)
            ->pluck('area_aviso_id')
            ->all();

        return AreaAviso::query()
            ->activas()
            ->where('recibe_recordatorio', true)
            ->whereNotIn('id', $yaRecibieron)
            ->get();
    }
}
