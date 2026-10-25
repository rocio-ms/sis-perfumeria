<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SesionCajaStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_is_closed_when_user_has_no_open_cash_session(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'dni' => '12345678',
            'username' => 'test-user',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('sesiones-caja.verificar-abierta'));

        $response
            ->assertOk()
            ->assertExactJson(['abierta' => false]);
    }
}
