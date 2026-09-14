<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('guests cannot access financial data', function (): void {
    $this->getJson('/api/transactions')->assertUnauthorized();
    $this->getJson('/api/categories')->assertUnauthorized();
    $this->getJson('/api/dashboard')->assertUnauthorized();
    $this->getJson('/api/user')->assertUnauthorized();
});

test('user can register and must verify email before login', function (): void {
    Notification::fake();

    $this->postJson('/api/register', [
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated()->assertJsonPath('message', 'Registration successful. Please verify your email.');

    $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);

    // Login blocked before verification
    $this->postJson('/api/login', [
        'email' => 'jane@example.com',
        'password' => 'password123',
    ])->assertForbidden()->assertJsonPath('message', 'Please verify your email address first.');
});

test('registration validates input', function (): void {
    $this->postJson('/api/register', [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
});

test('user can verify email via signed URL then login and logout', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create(['email' => 'bob@example.com']);

    $verifyUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $path = parse_url($verifyUrl, PHP_URL_PATH).'?'.parse_url($verifyUrl, PHP_URL_QUERY);

    $this->getJson($path)->assertOk()->assertJsonPath('message', 'Email verified successfully. You can now log in.');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    $login = $this->postJson('/api/login', [
        'email' => 'bob@example.com',
        'password' => 'password',
    ])->assertOk();

    $token = $login->json('token');
    expect($token)->not->toBeEmpty();

    $this->getJson('/api/user', ['Authorization' => "Bearer $token"])->assertOk();

    $this->postJson('/api/logout', [], ['Authorization' => "Bearer $token"])->assertOk();

    // Token revoked from the database.
    expect(DB::table('personal_access_tokens')->count())->toBe(0);

    // Forget the in-test auth state so the next request re-authenticates fresh.
    Auth::forgetGuards();

    // Revoked token no longer works.
    $this->getJson('/api/user', ['Authorization' => "Bearer $token"])->assertUnauthorized();
});

test('invalid verification hash is rejected', function (): void {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => 'invalid-hash',
    ]);
    $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);

    $this->getJson($path)->assertForbidden();
});

test('unverified token cannot access financial routes', function (): void {
    $user = User::factory()->unverified()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/dashboard')->assertForbidden();
    $this->getJson('/api/transactions')->assertForbidden();
});
