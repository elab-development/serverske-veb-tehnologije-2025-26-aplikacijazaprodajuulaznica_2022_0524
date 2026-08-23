<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('a visitor can register as a regular user and receives a token', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Petar Petrović',
        'email' => 'petar.petrovic@example.com',
        'password' => 'password123',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.name', 'Petar Petrović')
        ->assertJsonPath('data.email', 'petar.petrovic@example.com')
        ->assertJsonPath('data.role', User::ROLE_USER)
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonStructure(['access_token']);

    $user = User::query()->where('email', 'petar.petrovic@example.com')->firstOrFail();

    expect(Hash::check('password123', $user->password))->toBeTrue()
        ->and($user->tokens)->toHaveCount(1);
});

test('public registration cannot assign an administrator role', function () {
    $this->postJson('/api/register', [
        'name' => 'Invalid Admin',
        'email' => 'invalid.admin@example.com',
        'password' => 'password123',
        'role' => User::ROLE_ADMIN,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    $this->assertDatabaseMissing('users', [
        'email' => 'invalid.admin@example.com',
    ]);
});

test('registration validates required data and unique email addresses', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $this->postJson('/api/register', [
        'name' => '',
        'email' => 'existing@example.com',
        'password' => 'short',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

test('a user can log in and retrieve their profile', function () {
    $user = User::factory()->create([
        'email' => 'ana@example.com',
        'password' => Hash::make('password123'),
    ]);

    $loginResponse = $this->postJson('/api/login', [
        'email' => 'ana@example.com',
        'password' => 'password123',
    ]);

    $token = $loginResponse
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('token_type', 'Bearer')
        ->json('access_token');

    $this->withToken($token)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', 'ana@example.com');
});

test('login rejects incorrect credentials', function () {
    User::factory()->create([
        'email' => 'ana@example.com',
        'password' => Hash::make('password123'),
    ]);

    $this->postJson('/api/login', [
        'email' => 'ana@example.com',
        'password' => 'incorrect-password',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'The provided credentials are incorrect.');
});

test('profile and logout endpoints require authentication', function () {
    $this->getJson('/api/user')->assertUnauthorized();
    $this->postJson('/api/logout')->assertUnauthorized();
});

test('logout revokes only the token used for the request', function () {
    $user = User::factory()->create();
    $currentToken = $user->createToken('current')->plainTextToken;
    $user->createToken('other');

    $this->withToken($currentToken)
        ->postJson('/api/logout')
        ->assertOk()
        ->assertJsonPath('message', 'You have successfully logged out.');

    expect($user->tokens()->count())->toBe(1);

    $this->app['auth']->forgetGuards();

    $this->withToken($currentToken)
        ->getJson('/api/user')
        ->assertUnauthorized();
});
