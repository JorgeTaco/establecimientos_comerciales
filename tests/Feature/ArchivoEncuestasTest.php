<?php

namespace Tests\Feature;

use App\Models\EncuestaImportada;
use App\Models\FilaEncuesta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchivoEncuestasTest extends TestCase
{
    use RefreshDatabase;

    public function test_archiva_todas_las_filas_y_protege_la_vista_de_datos_originales(): void
    {
        $almacen = Storage::fake('local');
        $almacen->put('encuesta.csv', implode("\n", [
            'Nombre del establecimiento,Tipo de establecimiento,Parroquia,Latitud,Longitud,Observaciones',
            'Tienda válida,Tienda,Mulaló,-0.775,-78.58,<b>Datos originales</b>',
            'Revisar ubicación,Tienda,Mulaló,0.775,-78.58,Observación pendiente',
        ]));
        $opciones = [
            'archivo' => $almacen->path('encuesta.csv'),
            '--archivar' => true,
            '--json' => $almacen->path('reporte.json'),
        ];

        $this->artisan('establecimientos:importar', $opciones)->assertSuccessful();
        $encuesta = EncuestaImportada::query()->sole();
        $fila = $encuesta->filas()->where('estado', 'revisar')->sole();
        $this->assertEquals(['total' => 2, 'importadas' => 1, 'revisar' => 1], array_intersect_key($encuesta->resumen, array_flip(['total', 'importadas', 'revisar'])));
        $this->assertDatabaseCount('establecimientos', 1);
        $this->assertSame('Observación pendiente', $fila->celdas[5]);
        $this->assertStringNotContainsString('Observación pendiente', $fila->getRawOriginal('celdas'));
        $this->assertNull($fila->establecimiento_id);

        $this->artisan('establecimientos:importar', $opciones)->assertSuccessful();
        $this->assertDatabaseCount('encuestas_importadas', 1);
        $this->assertDatabaseCount('filas_encuesta', 2);
        $this->assertSame('importada', $encuesta->filas()->where('numero', 2)->sole()->estado);

        $url = route('admin.encuestas.filas.show', [$encuesta, $fila]);
        $this->get($url)->assertRedirectToRoute('login');
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['rol' => 'administrador']))->get($url)
            ->assertOk()->assertSee('Observación pendiente')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('admin.encuestas.show', ['encuesta' => $encuesta, 'estado' => 'revisar']))
            ->assertOk()->assertSee('Revisar ubicación')->assertDontSee('Fila 2');
        $this->get(route('admin.encuestas.filas.show', [$encuesta, $encuesta->filas()->where('numero', 2)->sole()]))
            ->assertOk()->assertSee('&lt;b&gt;Datos originales&lt;/b&gt;', false)->assertDontSee('<b>Datos originales</b>', false);

        $otra = EncuestaImportada::query()->create([
            'huella' => str_repeat('a', 64), 'archivo' => 'otra.csv', 'fila_encabezado' => 1, 'encabezados' => [], 'resumen' => [],
        ]);
        $this->get(route('admin.encuestas.filas.show', [$otra, $fila]))->assertNotFound();
        $this->assertSame(2, FilaEncuesta::query()->count());
    }
}
