<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_categories(): void
    {
        $user = User::factory()->create();

        Category::factory()->create([
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/categories');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'products_count',
                        'description',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ])
            ->assertJsonFragment([
                'name' => 'Electronics',
                'slug' => 'electronics',
            ]);
    }

    public function test_authenticated_user_can_create_a_category(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/categories', [
            'name' => 'Office Equipment',
            'slug' => 'office-equipment',
            'description' => 'Equipment used in an office environment.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Office Equipment')
            ->assertJsonPath('data.slug', 'office-equipment');

        $this->assertDatabaseHas('categories', [
            'name' => 'Office Equipment',
            'slug' => 'office-equipment',
        ]);
    }

    public function test_category_creation_rejects_unexpected_fields(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/categories', [
            'name' => 'Office Equipment',
            'slug' => 'office-equipment',
            'description' => 'Equipment used in an office environment.',
            'id' => 999,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id']);

        $this->assertDatabaseMissing('categories', [
            'name' => 'Office Equipment',
        ]);
    }

    public function test_authenticated_user_can_view_a_category(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create([
            'name' => 'Furniture',
            'slug' => 'furniture',
        ]);

        Product::factory()->create([
            'category_id' => $category->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/categories/{$category->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.name', 'Furniture')
            ->assertJsonPath('data.slug', 'furniture');
    }

    public function test_authenticated_user_can_update_a_category(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create([
            'name' => 'Old Category',
            'slug' => 'old-category',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/categories/{$category->id}", [
            'name' => 'Updated Category',
            'slug' => 'updated-category',
            'description' => 'Updated category description.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Category')
            ->assertJsonPath('data.slug', 'updated-category');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Category',
            'slug' => 'updated-category',
        ]);
    }

    public function test_category_update_rejects_unexpected_fields(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create([
            'name' => 'Old Category',
            'slug' => 'old-category',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/categories/{$category->id}", [
            'name' => 'Updated Category',
            'slug' => 'updated-category',
            'description' => 'Updated category description.',
            'id' => 999,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id']);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Old Category',
            'slug' => 'old-category',
        ]);
    }

    public function test_manager_cannot_delete_a_category(): void
    {
        $user = User::factory()->create([
            'role' => 'manager',
        ]);

        $category = Category::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_admin_can_delete_a_category_without_products(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $category = Category::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Category deleted successfully.',
            ]);

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $category = Category::factory()->create();

        Product::factory()->create([
            'category_id' => $category->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(409);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_category_creation_rejects_duplicate_name(): void
    {
        $user = User::factory()->create();

        Category::factory()->create([
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/categories', [
            'name' => 'Electronics',
            'slug' => 'electronics-new',
            'description' => 'Duplicate category name.',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_category_creation_rejects_duplicate_slug(): void
    {
        $user = User::factory()->create();

        Category::factory()->create([
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/categories', [
            'name' => 'Consumer Electronics',
            'slug' => 'electronics',
            'description' => 'Duplicate category slug.',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_unauthenticated_user_cannot_access_categories(): void
    {
        $response = $this->getJson('/api/categories');

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }
}