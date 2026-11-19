<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GestionComercialTest extends TestCase
{
    use RefreshDatabase;

    public function test_registro_sesion_y_permisos_de_catalogo(): void
    {
        $this->get('/api/productos')->assertUnauthorized();
        $this->getJson('/api/sesion/csrf')->assertOk()->assertJsonStructure(['csrf_token']);
        $cuenta = [
            'name' => 'Jorge de prueba', 'email' => 'demo@example.test', 'rol' => 'administrador',
            'password' => 'clave-de-prueba-123', 'password_confirmation' => 'clave-de-prueba-123',
        ];
        $this->postJson('/api/registro', $cuenta)->assertUnprocessable()->assertJsonValidationErrors('rol');
        $cuenta['rol'] = 'emprendedor';
        $this->postJson('/api/registro', $cuenta)->assertCreated()->assertJsonPath('usuario.rol', 'emprendedor');
        $this->getJson('/api/admin/usuarios')->assertForbidden();
        $categoria = Categoria::query()->create(['nombre' => 'Lácteos']);
        $producto = $this->postJson('/api/productos', [
            'categoria_id' => $categoria->id, 'nombre' => 'Yogur', 'precio_comercio' => '1.20',
            'documentacion' => ['nota privada'],
        ])->assertCreated()->assertJsonPath('producto.precio_comercio', '1.20')->json('producto.id');

        $this->getJson("/api/productos/$producto")->assertOk();
        $pdf = $this->get("/api/productos/$producto/ficha.pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());

        $url = $this->postJson('/api/catalogo/compartir')->assertOk()->json('url');
        $ruta = parse_url($url, PHP_URL_PATH);
        $this->getJson($ruta)->assertOk()->assertJsonPath('catalogo.productos.0.nombre', 'Yogur')
            ->assertJsonMissingPath('catalogo.productos.0.documentacion');
        $this->deleteJson('/api/catalogo/compartir')->assertNoContent();
        $this->getJson($ruta)->assertNotFound();
        $this->deleteJson('/api/sesion')->assertNoContent();
        Auth::forgetGuards();
        $this->postJson('/api/sesion', ['email' => $cuenta['email'], 'password' => $cuenta['password']])
            ->assertOk()->assertJsonPath('usuario.email', $cuenta['email']);
    }

    public function test_ficha_distancia_compatibilidad_propuesta_y_seguimiento(): void
    {
        $categoria = Categoria::query()->create(['nombre' => 'Lácteos']);
        $comercio = User::factory()->create(['rol' => 'comercio']);
        $emprendedor = User::factory()->create();
        $otro = User::factory()->create();
        $id = $this->actingAs($comercio)->postJson('/api/establecimientos', [
            'nombre' => 'Tienda de prueba', 'tipo' => 'tienda', 'parroquia' => 'Mulaló',
            'latitud' => -0.78, 'longitud' => -78.58, 'categorias' => [$categoria->id],
            'recepcion_proveedores' => 'recibe_propuestas',
            'perfil' => ['tiene_refrigeracion' => true, 'pedido_inicial' => 12, 'canton' => 'Latacunga',
                'contacto_telefono' => '0991234567', 'contacto_email' => 'comercio@example.test',
                'canales_contacto' => ['whatsapp'], 'autorizar_contacto' => true,
            ],
        ])->assertCreated()->assertJsonPath('establecimiento.contacto.telefono', '0991234567')
            ->assertJsonPath('establecimiento.contacto.email', null)->json('establecimiento.id');
        $this->patchJson("/api/establecimientos/$id", ['perfil' => ['autorizar_contacto' => false]])->assertOk();
        $this->getJson("/api/establecimientos/$id")->assertJsonPath('establecimiento.contacto', null);
        $this->getJson('/api/establecimientos/buscar?latitud=-0.78&longitud=-78.58&distancia_km=3&canton=Latacunga&categoria_id='.$categoria->id)
            ->assertOk()->assertJsonPath('establecimientos.0.id', $id)->assertJsonPath('establecimientos.0.distanciaKm', 0);
        $this->getJson('/api/establecimientos/buscar?distancia_km=3')->assertUnprocessable();

        $producto = $this->actingAs($emprendedor)->postJson('/api/productos', [
            'categoria_id' => $categoria->id, 'nombre' => 'Yogur', 'zonas_entrega' => ['Mulalo'],
            'requiere_refrigeracion' => true, 'pedido_minimo' => 6, 'capacidad_semanal' => 20,
        ])->assertCreated()->json('producto.id');
        $this->getJson("/api/productos/$producto/establecimientos-compatibles")
            ->assertOk()->assertJsonPath('establecimientos.0.id', $id)
            ->assertJsonPath('establecimientos.0.motivos.3', 'Cuenta con refrigeración.');
        $datos = ['productos' => [$producto], 'mensaje' => 'Puedo entregar muestras.',
            'referencia' => '2835eea6-bea8-4ec3-80af-47e148ace9cc',
        ];
        $propuesta = $this->postJson("/api/establecimientos/$id/propuestas", $datos)
            ->assertCreated()->assertJsonPath('propuesta.productos.0.nombre', 'Yogur')->json('propuesta');
        $this->postJson("/api/establecimientos/$id/propuestas", $datos)
            ->assertOk()->assertJsonPath('propuesta.id', $propuesta['id']);
        $oportunidad = $propuesta['oportunidad_id'];
        $this->patchJson("/api/oportunidades/$oportunidad", ['estado' => 'primer_pedido', 'notas' => 'Negociación privada'])
            ->assertOk()->assertJsonPath('oportunidad.notas', 'Negociación privada');
        $this->actingAs($otro)->getJson("/api/oportunidades/$oportunidad/propuestas")->assertNotFound();
        $this->getJson("/api/productos/$producto")->assertNotFound();
        $this->actingAs($comercio)->getJson('/api/propuestas-recibidas')->assertOk()
            ->assertJsonPath('propuestas.0.id', $propuesta['id'])->assertJsonMissingPath('propuestas.0.notas');
        $this->getJson('/api/oportunidades')->assertForbidden();
        $this->patchJson('/api/propuestas-recibidas/'.$propuesta['id'].'/respuesta', ['respuesta' => 'me_interesa'])
            ->assertOk()->assertJsonPath('propuesta.respuesta', 'me_interesa');
    }

    public function test_administracion_y_carga_aditiva_sin_publicar_contactos(): void
    {
        $administrador = User::factory()->create(['rol' => 'administrador']);
        $usuario = User::factory()->create();
        $fila = [
            'nombre' => 'Demo Centro', 'tipo' => 'tienda', 'parroquia' => 'Centro',
            'recepcion_proveedores' => 'recibe_propuestas',
            'interes_encuesta' => true,
            'perfil' => ['direccion' => 'Demo 123', 'contacto_telefono' => '0991234567',
                'canales_contacto' => ['telefono'], 'autorizar_contacto' => true,
            ],
        ];
        $this->actingAs($usuario)->postJson('/api/admin/establecimientos/importar', ['filas' => [$fila]])->assertForbidden();
        $this->actingAs($administrador)->postJson('/api/admin/categorias', ['nombre' => 'Bebidas'])->assertCreated();
        $respuesta = $this->postJson('/api/admin/establecimientos/importar', ['filas' => [$fila, $fila, ['nombre' => 'Invalida']]])
            ->assertOk()->assertJsonPath('resultados.0.estado', 'creado')
            ->assertJsonPath('resultados.1.estado', 'posible_duplicado')->assertJsonPath('resultados.2.estado', 'invalida');
        $id = $respuesta->json('resultados.0.establecimiento_id');
        $this->getJson("/api/establecimientos/$id")->assertOk()
            ->assertJsonPath('establecimiento.contacto', null)
            ->assertJsonMissingPath('establecimiento.interes_encuesta')
            ->assertJsonPath('establecimiento.recepcionProveedores', 'por_confirmar');
        $this->assertDatabaseHas('perfiles_comerciales', ['establecimiento_id' => $id, 'interes_encuesta' => 1]);
        $this->postJson("/api/admin/establecimientos/$id/verificar")->assertOk();
        $this->getJson('/api/admin/indicadores')->assertOk()->assertJsonPath('indicadores.puntos_venta_activos', 0);
        $this->actingAs($usuario)->postJson("/api/establecimientos/$id/reportes", ['descripcion' => 'Corrige la ubicación.'])->assertCreated();
        $this->actingAs($administrador)->patchJson('/api/admin/usuarios/'.$usuario->id, ['suspendido' => true])->assertOk();
        $this->actingAs($usuario->refresh())->getJson('/api/sesion')->assertForbidden();
    }

    public function test_acepta_fotos_validas_y_rechaza_otros_archivos(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/fotos', ['foto' => UploadedFile::fake()->create('archivo.svg', 1, 'image/svg+xml')])
            ->assertUnprocessable();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+gvhcAAAAASUVORK5CYII=');
        $archivo = UploadedFile::fake()->createWithContent('foto.png', $png);
        $this->postJson('/api/fotos', ['foto' => $archivo])->assertCreated()->assertJsonStructure(['foto_url']);
        $this->assertCount(1, Storage::disk('public')->allFiles('fotos'));
    }
}
