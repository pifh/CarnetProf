<?php

use App\Models\User;

it('redirects guests from the root to the login page', function () {
    $this->get('/')->assertRedirect('/login');
});

it('lets a verified teacher reach the dashboard after logging in', function () {
    $user = User::factory()->create([
        'email' => 'prof@example.test',
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertOk();
});

it('blocks unverified teachers behind the email verification prompt', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect('/email-verification/prompt');
});
