<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('shows profile and security settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/settings/profile')->assertOk()->assertSee('Appearance');
    $this->actingAs($user)->get('/settings/security')->assertOk()->assertSee('Two-factor authentication');
});

it('updates the profile name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/user/profile-information', ['name' => 'New Name', 'email' => $user->email])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('New Name');
});

it('requires re-verification after an email change', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/user/profile-information', ['name' => $user->name, 'email' => 'changed@example.com'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('updates the password only with the current password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/user/password', [
        'current_password' => 'wrong',
        'password' => 'another-new-password',
        'password_confirmation' => 'another-new-password',
    ])->assertSessionHasErrorsIn('updatePassword', 'current_password');

    $this->actingAs($user)->put('/user/password', [
        'current_password' => 'password',
        'password' => 'another-new-password',
        'password_confirmation' => 'another-new-password',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('another-new-password', $user->fresh()->password))->toBeTrue();
});

it('asks for the password before enabling two-factor authentication', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/user/two-factor-authentication')->assertRedirect('/user/confirm-password');
});

it('enables two-factor authentication after password confirmation and shows the QR code', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post('/user/two-factor-authentication')
        ->assertSessionHasNoErrors();

    expect($user->fresh()->two_factor_secret)->not->toBeNull();

    $this->actingAs($user->fresh())->get('/settings/security')
        ->assertSee('Setup pending')
        ->assertSee('<svg', false);
});
