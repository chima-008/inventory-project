<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_product_stock_movements(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'stock_quantity' => 20,
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'type' => 'in',
            'quantity' => 10,
            'reason' => 'Initial stock',
            'notes' => 'Received from supplier',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/products/{$product->id}/stock-movements"
        );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'product_id',
                        'user_id',
                        'type',
                        'quantity',
                        'reason',
                        'notes',
                        'product',
                        'user',
                        'created_at',
                    ],
                ],
            ])
            ->assertJsonFragment([
                'type' => 'in',
                'quantity' => 10,
                'reason' => 'Initial stock',
            ])

            ->assertJsonPath('data.0.product.id', $product->id)
            ->assertJsonPath('data.0.product.name', $product->name)
            ->assertJsonPath('data.0.user.id', $user->id)
            ->assertJsonPath('data.0.user.name', $user->name)
            ->assertJsonMissingPath('data.0.user.password')
            ->assertJsonMissingPath('data.0.user.remember_token');
    }

    public function test_authenticated_user_can_record_stock_in(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'stock_quantity' => 20,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/products/{$product->id}/stock-movements",
            [
                'type' => 'in',
                'quantity' => 15,
                'reason' => 'Supplier delivery',
                'notes' => 'March delivery',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', 'in')
            ->assertJsonPath('data.quantity', 15)
            ->assertJsonPath('data.reason', 'Supplier delivery')
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath(
                'message',
                'Stock movement recorded successfully.'
            );

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 35,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'user_id' => $user->id,
            'type' => 'in',
            'quantity' => 15,
            'reason' => 'Supplier delivery',
        ]);
    }

    public function test_stock_movement_creation_rejects_unexpected_fields(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'stock_quantity' => 20,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/products/{$product->id}/stock-movements",
            [
                'type' => 'in',
                'quantity' => 10,
                'reason' => 'Supplier delivery',
                'notes' => 'March delivery',
                'product_id' => 999,
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id']);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 20,
        ]);

        $this->assertDatabaseMissing('stock_movements', [
            'product_id' => $product->id,
            'quantity' => 10,
        ]);
    }

    public function test_authenticated_user_can_record_stock_out(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'stock_quantity' => 50,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/products/{$product->id}/stock-movements",
            [
                'type' => 'out',
                'quantity' => 20,
                'reason' => 'Customer order',
                'notes' => 'Order #1001',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', 'out')
            ->assertJsonPath('data.quantity', 20)
            ->assertJsonPath('data.reason', 'Customer order')
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.product_id', $product->id);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 30,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'user_id' => $user->id,
            'type' => 'out',
            'quantity' => 20,
            'reason' => 'Customer order',
        ]);
    }

    public function test_stock_cannot_go_below_zero(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'stock_quantity' => 10,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/products/{$product->id}/stock-movements",
            [
                'type' => 'out',
                'quantity' => 11,
                'reason' => 'Customer order',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Insufficient stock for this operation.',
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 10,
        ]);

        $this->assertDatabaseMissing('stock_movements', [
            'product_id' => $product->id,
            'type' => 'out',
            'quantity' => 11,
        ]);
    }

    public function test_invalid_movement_type_is_rejected(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'stock_quantity' => 20,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/products/{$product->id}/stock-movements",
            [
                'type' => 'invalid',
                'quantity' => 5,
                'reason' => 'Test movement',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 20,
        ]);
    }

    public function test_negative_quantity_is_rejected(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'stock_quantity' => 20,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/products/{$product->id}/stock-movements",
            [
                'type' => 'in',
                'quantity' => -5,
                'reason' => 'Invalid quantity',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 20,
        ]);
    }

    public function test_zero_quantity_is_rejected(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'stock_quantity' => 20,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/products/{$product->id}/stock-movements",
            [
                'type' => 'in',
                'quantity' => 0,
                'reason' => 'Invalid quantity',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 20,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_stock_movements(): void
    {
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
        ]);

        $response = $this->getJson(
            "/api/products/{$product->id}/stock-movements"
        );

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_manager_can_record_stock_movement(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
        ]);

        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'stock_quantity' => 20,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson(
            "/api/products/{$product->id}/stock-movements",
            [
                'type' => 'in',
                'quantity' => 10,
                'reason' => 'Supplier delivery',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.user_id', $manager->id);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 30,
        ]);
    }

    public function test_admin_can_record_stock_movement(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'stock_quantity' => 20,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson(
            "/api/products/{$product->id}/stock-movements",
            [
                'type' => 'out',
                'quantity' => 5,
                'reason' => 'Customer order',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.user_id', $admin->id);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 15,
        ]);
    }
}