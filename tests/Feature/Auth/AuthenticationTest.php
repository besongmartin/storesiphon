<?php

use App\Models\User;

it('shows the login screen', function () {
    $this->get('/login')->assertOk()->assertSee('Sign in');
});

it('signs in with valid credentials', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('rejects an invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('does not let a suspended account sign in', function () {
    $user = User::factory()->create();
    $user->forceFill(['suspended_at' => now()])->save();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('signs out a user who is suspended mid-session', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->forceFill(['suspended_at' => now()])->save();

    $this->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();
});

it('throttles repeated failed logins', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $_) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(429);
});

it('signs out', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});
