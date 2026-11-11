<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_seller_is_redirected_to_sales_create_after_login(): void
    {
        $user = User::where('username', 'vendedor')->firstOrFail();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect('/ventas/create');
    }

    public function test_seller_cannot_visit_dashboard(): void
    {
        $user = User::where('username', 'vendedor')->firstOrFail();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertStatus(403);
    }

    public function test_login_sends_a_seller_to_the_sales_screen(): void
    {
        $user = User::where('username', 'vendedor')->firstOrFail();
        $user->update(['password' => 'clave-de-prueba']);

        $this->post('/login', ['username' => $user->username, 'password' => 'clave-de-prueba'])
            ->assertRedirect(route('ventas.create'));
    }
}
