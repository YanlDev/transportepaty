<?php

use App\Enums\TipoDocumentoConductor;
use App\Models\Conductor;
use App\Models\ConductorDocumento;
use App\Services\RelojOperativo;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/**
 * La ficha del conductor muestra el expediente en bloques, a lo ancho, sin
 * desbordarse en el celular.
 */
beforeEach(function (): void {
    Role::findOrCreate('admin', 'web');

    $this->conductor = Conductor::factory()->create(['nombres' => 'Edgard Rene', 'apellidos' => 'Cusihuaman Mamani']);
    $hoy = RelojOperativo::fechaDeHoy();

    foreach (TipoDocumentoConductor::obligatorios() as $indice => $tipo) {
        if ($tipo === TipoDocumentoConductor::Anexo4Pisco) {
            continue;
        }

        ConductorDocumento::create([
            'conductor_id' => $this->conductor->id,
            'tipo' => $tipo,
            'numero' => $tipo === TipoDocumentoConductor::Dni ? '41939768' : null,
            'fecha_vencimiento' => match ($tipo) {
                TipoDocumentoConductor::Anexo4SanRafael => $hoy->subMonths(4)->toDateString(),
                TipoDocumentoConductor::ExamenMedico => $hoy->addDays(10)->toDateString(),
                default => $hoy->addYear()->addDays($indice)->toDateString(),
            },
        ]);
    }
});

it('muestra el expediente del conductor en bloques', function (string $dispositivo): void {
    actingAs(actorConRol('admin'));

    $pagina = visit("/conductores/{$this->conductor->id}");
    $pagina = $dispositivo === 'movil' ? $pagina->on()->mobile() : $pagina->on()->desktop();

    $pagina->assertSee('Documentos y licencias')
        ->assertSee('Vencidos (1)')
        ->assertSee('Por vencer (1)')
        ->assertSee('Faltan (1)')
        ->assertSee('Estado de documentos')
        ->assertSee('exigibles')
        ->assertDontSee('Próximos viajes')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors()
        // La dona anima al entrar: se espera a que termine para la captura.
        ->wait(1.5)
        ->screenshot(fullPage: true, filename: "ficha-conductor-{$dispositivo}");
})->with(['escritorio', 'movil']);

it('filtra los documentos por estado', function (): void {
    actingAs(actorConRol('admin'));

    visit("/conductores/{$this->conductor->id}")
        ->on()->desktop()
        ->click('[data-filtro="vencido"]')
        ->assertSee('Anexo 4 · San Rafael')
        ->assertDontSee('Examen de manejo')
        ->assertNoJavaScriptErrors();
});
