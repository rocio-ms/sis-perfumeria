<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class RespuestaErrorTest extends TestCase
{
    public function test_excepciones_inesperadas_devuelven_una_respuesta_segura(): void
    {
        config(['app.debug' => true]);
        Route::get('/prueba-fallo-servidor', static function () {
            throw new RuntimeException('Detalle interno de prueba');
        });

        $this->get('/prueba-fallo-servidor')->assertStatus(500)
            ->assertSee('Actualizar página')->assertDontSee('Detalle interno de prueba');

        $this->getJson('/prueba-fallo-servidor')->assertStatus(500)
            ->assertExactJson(['message' => 'Ocurrió un error al procesar la solicitud. Actualiza la página si el problema continúa.']);

        $this->get('/prueba-fallo-servidor', ['X-Inertia' => 'true'])->assertStatus(500)
            ->assertJsonStructure(['message'])->assertDontSee('Detalle interno de prueba');
    }

    public function test_sesion_vencida_y_limite_de_solicitudes_conservan_su_estado(): void
    {
        Route::post('/prueba-sesion-vencida', static fn () => abort(419));
        Route::get('/prueba-limite', static fn () => abort(429, '', ['Retry-After' => '30']));

        $this->post('/prueba-sesion-vencida', [], ['X-Inertia' => 'true'])
            ->assertStatus(419)->assertJsonPath('message', 'La sesión o el formulario venció. Actualiza la página e inténtalo nuevamente.');
        $this->getJson('/prueba-limite')->assertStatus(429)->assertHeader('Retry-After', '30');
    }
}
