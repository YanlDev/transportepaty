<?php

namespace App\Console\Commands;

use App\Enums\TipoCarga;
use App\Models\Viaje;
use App\Services\ImportadorViaje;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('transpaty:clasificar-carga-minsur
    {--dry-run : Solo muestra qué se reclasificaría, sin escribir nada}')]
#[Description('Reclasifica el tipo de carga de los viajes de Minsur que quedaron en "Particular" (el default) por haberse importado antes de que existiera TipoCarga::desdeGuiaRemitenteMinsur(). Nunca toca un viaje con un tipo de carga distinto a Particular, para no pisar una corrección manual.')]
class ClasificarCargaMinsur extends Command
{
    public function __construct(private readonly ImportadorViaje $importador)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $seco = (bool) $this->option('dry-run');

        $viajes = Viaje::query()
            ->where('tipo_carga', TipoCarga::Particular->value)
            ->whereNotNull('guias_remitente')
            ->get();

        $reclasificados = $sinPatron = 0;

        foreach ($viajes as $viaje) {
            $tipoCarga = $this->importador->clasificarCarga($viaje->guias_remitente ?? []);

            if ($tipoCarga === null) {
                $sinPatron++;

                continue;
            }

            $this->line("  {$viaje->numero_gr} · {$viaje->cliente} → {$tipoCarga->label()}");

            if (! $seco) {
                $viaje->update(['tipo_carga' => $tipoCarga->value]);
            }

            $reclasificados++;
        }

        $this->newLine();
        $this->info(
            ($seco ? '[dry-run] ' : '')
            ."Reclasificados: {$reclasificados} · sin patrón reconocido: {$sinPatron}"
        );

        return self::SUCCESS;
    }
}
