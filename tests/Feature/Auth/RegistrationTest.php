<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

it('shows the registration screen', function () {
    $this->get('/register')->assertOk()->assertSee('Create your account');
});

it('registers a user, signs them in and sends a verification email', function () {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Ada Lovelace',
        'email' => 'Ada@Example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertRedirect('/dashboard');

    $user = User::firstWhere('email', 'ada@example.com');

    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(User::ROLE_USER)
        ->and($user->hasVerifiedEmail())->toBeFalse();
    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rejects a registration that tries to set its own role', function () {
    $this->post('/register', [
        'name' => 'Mallory',
        'email' => 'mallory@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        'role' => 'admin',
    ]);

    expect(User::firstWhere('email', 'mallory@example.com')->role)->toBe(User::ROLE_USER);
});

it('requires matching passwords and a unique email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->post('/register', [
        'name' => 'Someone',
        'email' => 'taken@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'different-password',
    ])->assertSessionHasErrors(['email', 'password']);

    $this->assertGuest();
});
