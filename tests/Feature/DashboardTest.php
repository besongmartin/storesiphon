<?php

use App\Livewire\Dashboard;
use App\Models\User;
use Livewire\Livewire;

it('redirects guests to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('shows the empty dashboard to a verified user', function () {
    $user = User::factory()->create(['name' => 'Grace Hopper']);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertSeeLivewire(Dashboard::class)
        ->assertSee('Welcome, Grace Hopper')
        ->assertSee('No extractions yet');
});

it('renders the dashboard component', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Dashboard::class)
        ->assertSee('Recent extractions')
        ->assertSee('Free');
});

it('shows the admin section only to admins', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/dashboard')->assertDontSee('admin/payments');

    $admin = User::factory()->create();
    $admin->forceFill(['role' => User::ROLE_ADMIN])->save();
    $this->actingAs($admin)->get('/dashboard')->assertSee('admin/payments');
});
