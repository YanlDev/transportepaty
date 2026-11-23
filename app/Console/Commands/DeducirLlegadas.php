<?php

namespace App\Console\Commands;

use App\Enums\EstadoVuelta;
use App\Models\Vuelta;
use App\Services\LlegadaDeducida;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pone la llegada a los tickets que siguen «en ruta» aunque su unidad ya
 * volvió a salir con otro ticket: el día antes de esa nueva salida. Los que
 * no tienen un ticket posterior no se tocan: esos los registra una persona.
 *
 * Sin `--aplicar` solo muestra lo que haría.
 */
#[Signature('transpaty:deducir-llegadas {--aplicar : Guarda los cambios; sin esto solo simula}')]
#[Description('Anota la llegada de los tickets cuya unidad ya volvió a salir con otro ticket')]
class DeducirLlegadas extends Command
{
    public function handle(LlegadaDeducida $llegada): int
    {
        $sinLlegada = Vuelta::query()
            ->with('tracto:id,placa')
            ->where('estado', EstadoVuelta::EnCurso)
            ->whereNull('llegada_el')
            ->whereNotNull('inicio')
            ->orderBy('inicio')
            ->get();

        $anotadas = 0;

        DB::beginTransaction();

        foreach ($sinLlegada as $ticket) {
            $siguiente = Vuelta::query()
                ->where('tracto_id', $ticket->tracto_id)
                ->whereKeyNot($ticket->id)
                ->whereDate('inicio', '>', $ticket->inicio->toDateString())
                ->orderBy('inicio')
                ->first();

            if ($siguiente === null) {
                continue;
            }

            $llegada->anotar($ticket, $siguiente->inicio);
            $anotadas++;

            $this->line(sprintf('<info>%s</info> · %s llegó el %s (volvió a salir el %s con %s)',
                $ticket->tracto?->placa, $ticket->codigo, $ticket->llegada_el->format('d/m'), $siguiente->inicio->format('d/m'), $siguiente->codigo));
        }

        if ($this->option('aplicar')) {
            DB::commit();
            $this->info("Listo: {$anotadas} llegadas anotadas.");
        } else {
            DB::rollBack();
            $this->warn("Simulación: {$anotadas} llegadas por anotar. Con --aplicar se guardan.");
        }

        return self::SUCCESS;
    }
}
