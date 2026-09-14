<?php

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('verified user can crud own categories with per-user uniqueness', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/categories', ['name' => 'Food', 'type' => 'expense'])
        ->assertCreated()->assertJsonPath('data.name', 'Food');

    // Duplicate name for same user fails
    $this->postJson('/api/categories', ['name' => 'Food'])->assertUnprocessable();

    // Same name allowed for different user
    $other = User::factory()->create();
    Sanctum::actingAs($other);
    $this->postJson('/api/categories', ['name' => 'Food'])->assertCreated();

    Sanctum::actingAs($user);
    $id = $user->categories()->where('name', 'Food')->first()->id;

    $this->putJson("/api/categories/$id", ['name' => 'Groceries', 'type' => 'expense'])
        ->assertOk()->assertJsonPath('data.name', 'Groceries');

    $this->deleteJson("/api/categories/$id")->assertOk();
});

test('categories are isolated per user', function (): void {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $cat = $alice->categories()->create(['name' => 'Secret']);

    Sanctum::actingAs($bob);
    $this->getJson("/api/categories/{$cat->id}")->assertNotFound();
    $this->getJson('/api/categories')->assertOk()->assertJsonCount(0, 'data');
});

test('dashboard returns correct totals and blocks guests', function (): void {
    $this->getJson('/api/dashboard')->assertUnauthorized();

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    Transaction::factory()->for($user)->create(['type' => 'income', 'amount' => 1000, 'category' => 'Salary']);
    Transaction::factory()->for($user)->create(['type' => 'income', 'amount' => 500, 'category' => 'Freelance']);
    Transaction::factory()->for($user)->create(['type' => 'expense', 'amount' => 300, 'category' => 'Food']);

    // Other user's data must not leak
    $other = User::factory()->create();
    Transaction::factory()->for($other)->create(['type' => 'income', 'amount' => 9999]);

    $this->getJson('/api/dashboard')->assertOk()
        ->assertJsonPath('data.total_income', '1500.00')
        ->assertJsonPath('data.total_expense', '300.00')
        ->assertJsonPath('data.balance', '1200.00')
        ->assertJsonPath('data.transaction_count', 3);
});
