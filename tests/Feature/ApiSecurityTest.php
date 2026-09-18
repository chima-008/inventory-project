<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_product_returns_json_404(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/products/999999');

        $response
            ->assertNotFound()
            ->assertJsonStructure([
                'message',
            ]);
    }

    public function test_duplicate_product_sku_is_rejected(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Product::factory()->create([
            'category_id' => $category->id,
            'sku' => 'ELEC-001',
            'slug' => 'existing-product',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'Another Product',
            'slug' => 'another-product',
            'sku' => 'ELEC-001',
            'price' => 10000,
            'stock_quantity' => 10,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku']);
    }

    public function test_duplicate_product_slug_is_rejected(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Product::factory()->create([
            'category_id' => $category->id,
            'sku' => 'ELEC-001',
            'slug' => 'existing-product',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'Another Product',
            'slug' => 'existing-product',
            'sku' => 'ELEC-002',
            'price' => 10000,
            'stock_quantity' => 10,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_negative_product_price_is_rejected(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'Invalid Product',
            'slug' => 'invalid-product',
            'sku' => 'INVALID-001',
            'price' => -100,
            'stock_quantity' => 10,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price']);
    }

    public function test_missing_category_returns_json_404(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/categories/999999');

        $response
            ->assertNotFound()
            ->assertJsonStructure([
                'message',
            ]);
    }

    public function test_soft_deleted_product_is_not_returned(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
        ]);

        $product->delete();

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertNotFound();
    }

    public function test_user_resource_does_not_expose_password(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => 'secret-password',
        ]);

        $targetUser = User::factory()->create([
            'role' => 'manager',
            'password' => 'another-secret',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/users');

        $response
            ->assertOk()
            ->assertJsonMissingPath('data.0.password')
            ->assertJsonMissingPath('data.0.remember_token');
    }

    public function test_manager_cannot_delete_product(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
        ]);

        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'deleted_at' => null,
        ]);
    }

    public function test_manager_cannot_delete_category(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
        ]);

        $category = Category::factory()->create();

        Sanctum::actingAs($manager);

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_invalid_product_request_returns_json_validation_response(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/products', []);

        $response
            ->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors',
            ])
            ->assertJsonValidationErrors([
                'category_id',
                'name',
                'slug',
                'sku',
                'price',
                'stock_quantity',
                'low_stock_threshold',
                'is_active',
            ]);
    }
}