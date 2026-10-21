<?php

use App\Models\User;

it('builds initials for the avatar chip', function (string $name, string $initials) {
    expect((new User(['name' => $name]))->initials())->toBe($initials);
})->with([
    ['Ada Lovelace', 'AL'],
    ['grace', 'G'],
    ['Jean  Paul  Sartre', 'JP'],
    ['Émile Zola', 'ÉZ'],
]);

it('knows admins and suspended users', function () {
    $user = new User;
    expect($user->isAdmin())->toBeFalse()->and($user->isSuspended())->toBeFalse();

    $user->role = User::ROLE_ADMIN;
    $user->suspended_at = now();
    expect($user->isAdmin())->toBeTrue()->and($user->isSuspended())->toBeTrue();
});

it('does not allow mass assignment of role or suspension', function () {
    $user = new User(['name' => 'x', 'role' => 'admin', 'suspended_at' => now()]);

    expect($user->role)->toBeNull()->and($user->suspended_at)->toBeNull();
});
