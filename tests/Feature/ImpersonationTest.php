<?php

use App\Models\ImpersonationLog;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ImpersonationManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

it('lets an admin impersonate a teacher and logs it', function () {
    $admin = User::factory()->admin()->create();
    $teacher = User::factory()->create();

    app(ImpersonationManager::class)->start($admin, $teacher);

    expect(Auth::id())->toBe($teacher->id)
        ->and(session('impersonator_id'))->toBe($admin->id);

    $log = ImpersonationLog::first();
    expect($log->admin_id)->toBe($admin->id)
        ->and($log->target_user_id)->toBe($teacher->id)
        ->and($log->ended_at)->toBeNull();
});

it('denies an admin impersonating another admin', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();

    expect(Gate::forUser($admin)->denies('impersonate', $otherAdmin))->toBeTrue();
});

it('denies an admin impersonating themselves', function () {
    $admin = User::factory()->admin()->create();

    expect(Gate::forUser($admin)->denies('impersonate', $admin))->toBeTrue();
});

it('denies a plain teacher from impersonating anyone', function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    expect(Gate::forUser($teacher)->denies('impersonate', $otherTeacher))->toBeTrue();
});

it('makes the impersonated teacher\'s own data visible with no extra code', function () {
    $admin = User::factory()->admin()->create();
    $teacher = User::factory()->create();
    $schoolClass = SchoolClass::factory()->for($teacher)->create();

    app(ImpersonationManager::class)->start($admin, $teacher);

    expect(SchoolClass::query()->find($schoolClass->id))->not->toBeNull();
});

it('restores the admin and records ended_at when stopping', function () {
    $admin = User::factory()->admin()->create();
    $teacher = User::factory()->create();

    app(ImpersonationManager::class)->start($admin, $teacher);
    $logId = session('impersonation_log_id');

    ImpersonationLog::find($logId)->update(['ended_at' => now()]);
    $adminId = session('impersonator_id');
    session()->forget(['impersonator_id', 'impersonation_log_id']);
    Auth::loginUsingId($adminId);

    expect(Auth::id())->toBe($admin->id)
        ->and(ImpersonationLog::find($logId)->ended_at)->not->toBeNull();
});
