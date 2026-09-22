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
}
