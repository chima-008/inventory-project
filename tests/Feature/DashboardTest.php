<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_dashboard_summary(): void
    {
        $user = User::factory()->create();

        $electronics = Category::factory()->create([
            'business_id' => $user->business_id,
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]);

        $furniture = Category::factory()->create([
            'business_id' => $user->business_id,
            'name' => 'Furniture',
            'slug' => 'furniture',
        ]);

        Product::factory()->create([
            'business_id' => $user->business_id,
            'category_id' => $electronics->id,
            'name' => 'Keyboard',
            'price' => 10000,
            'stock_quantity' => 20,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'business_id' => $user->business_id,
            'category_id' => $electronics->id,
            'name' => 'Mouse',
            'price' => 5000,
            'stock_quantity' => 3,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'business_id' => $user->business_id,
            'category_id' => $furniture->id,
            'name' => 'Office Chair',
            'price' => 50000,
            'stock_quantity' => 0,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/dashboard/summary');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_products',
                    'total_categories',
                    'total_stock_units',
                    'low_stock_count',
                    'out_of_stock_count',
                    'inventory_value',
                ],
            ]);

        $response->assertJsonPath('data.total_products', 3);
        $response->assertJsonPath('data.total_categories', 2);
        $response->assertJsonPath('data.total_stock_units', 23);
        $response->assertJsonPath('data.low_stock_count', 1);
        $response->assertJsonPath('data.out_of_stock_count', 1);
        $response->assertJsonPath('data.inventory_value', '215000.00');
    }

    public function test_inactive_products_are_excluded_from_dashboard_totals(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create([
            'business_id' => $user->business_id,
        ]);

        Product::factory()->create([
            'business_id' => $user->business_id,
            'category_id' => $category->id,
            'name' => 'Active Product',
            'price' => 10000,
            'stock_quantity' => 10,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'business_id' => $user->business_id,
            'category_id' => $category->id,
            'name' => 'Inactive Product',
            'price' => 50000,
            'stock_quantity' => 100,
            'low_stock_threshold' => 5,
            'is_active' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/dashboard/summary');

        $response
            ->assertOk()
            ->assertJsonPath('data.total_products', 1)
            ->assertJsonPath('data.total_stock_units', 10)
            ->assertJsonPath('data.low_stock_count', 0)
            ->assertJsonPath('data.out_of_stock_count', 0)
            ->assertJsonPath('data.inventory_value', '100000.00');
    }

    public function test_dashboard_counts_low_stock_count_correctly(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create([
            'business_id' => $user->business_id,
        ]);

        Product::factory()->create([
            'business_id' => $user->business_id,
            'category_id' => $category->id,
            'stock_quantity' => 5,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'business_id' => $user->business_id,
            'category_id' => $category->id,
            'stock_quantity' => 4,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'business_id' => $user->business_id,
            'category_id' => $category->id,
            'stock_quantity' => 6,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/dashboard/summary');

        $response
            ->assertOk()
            ->assertJsonPath('data.low_stock_count', 2)
            ->assertJsonPath('data.out_of_stock_count', 0);
    }

    public function test_dashboard_counts_out_of_stock_count_correctly(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create([
            'business_id' => $user->business_id,
        ]);

        Product::factory()->create([
            'business_id' => $user->business_id,
            'category_id' => $category->id,
            'stock_quantity' => 0,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'business_id' => $user->business_id,
            'category_id' => $category->id,
            'stock_quantity' => 0,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'business_id' => $user->business_id,
            'category_id' => $category->id,
            'stock_quantity' => 5,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/dashboard/summary');

        $response
            ->assertOk()
            ->assertJsonPath('data.out_of_stock_count', 2)
            ->assertJsonPath('data.low_stock_count', 1);
    }

    public function test_unauthenticated_user_cannot_view_dashboard_summary(): void
    {
        $response = $this->getJson('/api/dashboard/summary');

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }
}