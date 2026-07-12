<?php

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;

it('logs a login_logs row when a teacher signs in successfully', function () {
    $user = User::factory()->create();

    event(new Login('web', $user, false));

    expect(LoginLog::query()->where('user_id', $user->id)->where('successful', true)->exists())->toBeTrue();
});

it('logs a login_logs row when a login attempt fails', function () {
    event(new Failed('web', null, ['email' => 'nobody@example.test', 'password' => 'wrong']));

    expect(LoginLog::query()->where('email', 'nobody@example.test')->where('successful', false)->exists())->toBeTrue();
});
