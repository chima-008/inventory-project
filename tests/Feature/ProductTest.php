<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_products(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Wireless Keyboard',
            'sku' => 'ELEC-KEY-001',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/products');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'category_id',
                        'name',
                        'slug',
                        'sku',
                        'description',
                        'price',
                        'stock_quantity',
                        'low_stock_threshold',
                        'is_active',
                        'stock_status',
                        'category',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ])
            ->assertJsonFragment([
                'name' => 'Wireless Keyboard',
                'sku' => 'ELEC-KEY-001',
            ])

            ->assertJsonPath('data.0.category.id', $category->id)
            ->assertJsonPath('data.0.category.name', $category->name)
            ->assertJsonPath('data.0.category.slug', $category->slug);
    }

    public function test_authenticated_user_can_create_a_product(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'USB-C Fast Charger',
            'slug' => 'usb-c-fast-charger',
            'sku' => 'ELEC-CHG-001',
            'description' => 'Fast USB-C wall charger.',
            'price' => 15000,
            'stock_quantity' => 20,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'USB-C Fast Charger')
            ->assertJsonPath('data.sku', 'ELEC-CHG-001')
            ->assertJsonPath('data.stock_quantity', 20);

        $this->assertDatabaseHas('products', [
            'sku' => 'ELEC-CHG-001',
            'stock_quantity' => 20,
        ]);
    }

    public function test_authenticated_user_can_view_a_product(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Office Chair',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/products/{$product->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name', 'Office Chair');
    }

    public function test_authenticated_user_can_update_product_details(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Old Product Name',
            'stock_quantity' => 25,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/products/{$product->id}", [
            'category_id' => $category->id,
            'name' => 'Updated Product Name',
            'slug' => $product->slug,
            'sku' => $product->sku,
            'description' => 'Updated description.',
            'price' => 25000,
            'low_stock_threshold' => 10,
            'is_active' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Product Name')
            ->assertJsonPath('data.price', '25000.00')
            ->assertJsonPath('data.stock_quantity', 25);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Product Name',
            'stock_quantity' => 25,
        ]);
    }

    public function test_product_update_cannot_change_stock_quantity(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'stock_quantity' => 25,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/products/{$product->id}", [
            'category_id' => $category->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'description' => $product->description,
            'price' => $product->price,
            'low_stock_threshold' => $product->low_stock_threshold,
            'is_active' => $product->is_active,
            'stock_quantity' => 999,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['stock_quantity']);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 25,
        ]);
    }

    public function test_manager_cannot_delete_a_product(): void
    {
        $user = User::factory()->create([
            'role' => 'manager',
        ]);

        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_can_delete_a_product(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Product deleted successfully.',
            ]);

        $this->assertSoftDeleted('products', [
            'id' => $product->id,
        ]);
    }

    public function test_product_creation_rejects_invalid_category(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/products', [
            'category_id' => 999999,
            'name' => 'Invalid Product',
            'slug' => 'invalid-product',
            'sku' => 'INVALID-001',
            'price' => 10000,
            'stock_quantity' => 10,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_unauthenticated_user_cannot_access_products(): void
    {
        $response = $this->getJson('/api/products');

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }
}