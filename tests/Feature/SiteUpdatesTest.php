<?php

use App\Filament\Pages\SiteUpdates;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

it('denies a plain teacher access to the site updates page', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(SiteUpdates::class)->assertForbidden();
});

it('denies an admin (non-superadmin) access to the site updates page', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(SiteUpdates::class)->assertForbidden();
});

it('shows the currently deployed commit to a superadmin', function () {
    Process::fake([
        '*git*log*-1*' => Process::result(output: "abc1234|Fix something|2026-08-01 10:00:00 +0200\n"),
    ]);

    $superadmin = User::factory()->superadmin()->create();
    $this->actingAs($superadmin);

    Livewire::test(SiteUpdates::class)
        ->assertSet('currentHash', 'abc1234')
        ->assertSet('currentSummary', 'Fix something');
});

it('reports up to date when there are no pending commits', function () {
    Process::fake([
        '*git*log*-1*' => Process::result(output: "abc1234|Fix something|2026-08-01 10:00:00 +0200\n"),
        '*git*fetch*' => Process::result(),
        '*git*rev-list*--count*' => Process::result(output: "0\n"),
        '*git*log*HEAD..*' => Process::result(output: ''),
    ]);

    $superadmin = User::factory()->superadmin()->create();
    $this->actingAs($superadmin);

    Livewire::test(SiteUpdates::class)
        ->call('checkForUpdates')
        ->assertSet('hasChecked', true)
        ->assertSet('behindBy', 0)
        ->assertSet('checkError', null);
});

it('lists pending commits when the site is behind origin', function () {
    Process::fake([
        '*git*log*-1*' => Process::result(output: "abc1234|Fix something|2026-08-01 10:00:00 +0200\n"),
        '*git*fetch*' => Process::result(),
        '*git*rev-list*--count*' => Process::result(output: "2\n"),
        '*git*log*HEAD..*' => Process::result(output: "def5678 Add feature\nabc9999 Fix bug\n"),
    ]);

    $superadmin = User::factory()->superadmin()->create();
    $this->actingAs($superadmin);

    Livewire::test(SiteUpdates::class)
        ->call('checkForUpdates')
        ->assertSet('behindBy', 2)
        ->assertSet('pendingCommits', ['def5678 Add feature', 'abc9999 Fix bug']);
});

it('surfaces a friendly error when the update check cannot reach GitHub', function () {
    Process::fake([
        '*git*log*-1*' => Process::result(output: "abc1234|Fix something|2026-08-01 10:00:00 +0200\n"),
        '*git*fetch*' => Process::result(errorOutput: 'Could not resolve host', exitCode: 1),
    ]);

    $superadmin = User::factory()->superadmin()->create();
    $this->actingAs($superadmin);

    Livewire::test(SiteUpdates::class)
        ->call('checkForUpdates')
        ->assertSet('checkError', 'Could not resolve host')
        ->assertSet('behindBy', 0);
});

it('runs the update script and reports success', function () {
    Process::fake([
        '*git*log*-1*' => Process::result(output: "abc1234|Fix something|2026-08-01 10:00:00 +0200\n"),
        '*bash*deploy/update.sh*' => Process::result(output: "==> Mise à jour terminée.\n"),
    ]);

    $superadmin = User::factory()->superadmin()->create();
    $this->actingAs($superadmin);

    Livewire::test(SiteUpdates::class)
        ->call('runUpdate')
        ->assertSet('lastUpdateSuccessful', true)
        ->assertSet('lastUpdateOutput', '==> Mise à jour terminée.');
});

it('runs the update script and reports failure, keeping the site in maintenance', function () {
    Process::fake([
        '*git*log*-1*' => Process::result(output: "abc1234|Fix something|2026-08-01 10:00:00 +0200\n"),
        '*bash*deploy/update.sh*' => Process::result(
            output: "==> Passage en mode maintenance\n==> Récupération de la dernière version\n",
            errorOutput: "error: Your local changes would be overwritten by merge\n",
            exitCode: 1,
        ),
    ]);

    $superadmin = User::factory()->superadmin()->create();
    $this->actingAs($superadmin);

    Livewire::test(SiteUpdates::class)
        ->call('runUpdate')
        ->assertSet('lastUpdateSuccessful', false)
        ->assertSee('would be overwritten');
});
