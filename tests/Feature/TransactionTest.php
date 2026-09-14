<?php

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('verified user can crud own transactions with isolation', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $create = $this->postJson('/api/transactions', [
        'type' => 'income',
        'category' => 'Salary',
        'amount' => '2500.50',
        'description' => 'Monthly pay',
        'transaction_date' => now()->toDateString(),
    ])->assertCreated();

    $id = $create->json('data.id');

    $this->getJson('/api/transactions')->assertOk()->assertJsonCount(1, 'data.data');

    $this->getJson("/api/transactions/$id")->assertOk()->assertJsonPath('data.category', 'Salary');

    $this->putJson("/api/transactions/$id", [
        'type' => 'income',
        'category' => 'Freelance',
        'amount' => '3000.00',
        'description' => 'Updated',
        'transaction_date' => now()->toDateString(),
    ])->assertOk()->assertJsonPath('data.category', 'Freelance');

    $this->deleteJson("/api/transactions/$id")->assertOk();

    $this->getJson("/api/transactions/$id")->assertNotFound();
});

test('transactions are isolated per user', function (): void {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $tx = Transaction::factory()->for($alice)->create();

    Sanctum::actingAs($bob);

    $this->getJson("/api/transactions/{$tx->id}")->assertNotFound();
    $this->getJson('/api/transactions')->assertOk()->assertJsonCount(0, 'data.data');
});

test('transaction validation is enforced', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/transactions', [])->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'category', 'amount', 'transaction_date']);

    $this->postJson('/api/transactions', [
        'type' => 'gift',
        'category' => 'X',
        'amount' => -5,
        'transaction_date' => now()->addDay()->toDateString(),
    ])->assertUnprocessable();
});
