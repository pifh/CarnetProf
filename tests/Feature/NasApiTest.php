<?php

use App\Filament\Pages\ApiIntegration;
use App\Models\CalendarEvent;
use App\Models\LogbookEntry;
use App\Models\PersonalBirthday;
use App\Models\Reminder;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it("returns today's student and personal birthdays for a valid token", function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'first_name' => 'Camille',
        'last_name' => 'Dupont',
        'birth_date' => Carbon::today()->subYears(11),
    ]);
    Student::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'first_name' => 'Autre',
        'last_name' => 'Jour',
        'birth_date' => Carbon::today()->addDay()->subYears(11),
    ]);
    $personal = PersonalBirthday::factory()->for($teacher)->create([
        'name' => 'Grand-mère',
        'date' => Carbon::today()->subYears(70),
    ]);

    $token = $teacher->ensureApiToken();

    $response = $this->getJson("/api/nas/{$token}/birthdays");

    $response->assertOk()
        ->assertJsonPath('count', 2)
        ->assertJsonFragment(['type' => 'eleve', 'name' => 'Camille Dupont', 'class' => $class->name, 'age' => 11])
        ->assertJsonFragment(['type' => 'personnel', 'name' => 'Grand-mère', 'age' => 70]);

    $names = collect($response->json('birthdays'))->pluck('name');
    expect($names)->not->toContain('Autre Jour');
});

it("excludes another teacher's birthdays and rejects an invalid token", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create([
        'birth_date' => Carbon::today()->subYears(9),
    ]);

    $token = $teacher->ensureApiToken();

    $this->getJson("/api/nas/{$token}/birthdays")->assertOk()->assertJsonPath('count', 0);
    $this->getJson('/api/nas/not-a-real-token/birthdays')->assertNotFound();
});

it("returns today's schedule, excluding birthdays", function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'birth_date' => Carbon::today()->subYears(10),
    ]);

    CalendarEvent::factory()->for($teacher)->create([
        'type' => CalendarEvent::TYPE_RDV,
        'title' => 'RDV parent',
        'starts_at' => Carbon::today()->setTime(14, 0),
        'ends_at' => Carbon::today()->setTime(14, 30),
        'all_day' => false,
    ]);

    LogbookEntry::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'date' => Carbon::today(),
    ]);

    CalendarEvent::factory()->for($teacher)->create([
        'type' => CalendarEvent::TYPE_RDV,
        'title' => 'RDV demain',
        'starts_at' => Carbon::tomorrow()->setTime(9, 0),
        'all_day' => false,
    ]);

    $token = $teacher->ensureApiToken();

    $response = $this->getJson("/api/nas/{$token}/schedule");

    $response->assertOk()->assertJsonPath('count', 2);

    $types = collect($response->json('schedule'))->pluck('type');
    expect($types)->not->toContain('anniversaires_eleves')
        ->and($types)->toContain('reunions_rdv', 'cours');
});

it('returns pending (not done) reminders ordered by due date, undated last', function () {
    $teacher = User::factory()->create();
    $done = Reminder::factory()->for($teacher)->done()->create(['title' => 'Déjà fait']);
    $undated = Reminder::factory()->for($teacher)->create(['title' => 'Sans date', 'due_date' => null]);
    $soon = Reminder::factory()->for($teacher)->create(['title' => 'Urgent', 'due_date' => Carbon::today()]);
    $later = Reminder::factory()->for($teacher)->create(['title' => 'Plus tard', 'due_date' => Carbon::today()->addWeek()]);

    $token = $teacher->ensureApiToken();

    $response = $this->getJson("/api/nas/{$token}/reminders");

    $response->assertOk()->assertJsonPath('count', 3);

    $titles = collect($response->json('reminders'))->pluck('title')->all();
    expect($titles)->toBe(['Urgent', 'Plus tard', 'Sans date'])
        ->and($titles)->not->toContain('Déjà fait');
});

it('returns homework due today, excluding entries without homework or due tomorrow', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $subject = Subject::factory()->for($teacher)->create(['name' => 'Mathématiques']);
    $class->subjects()->attach($subject);

    LogbookEntry::factory()->for($teacher)->for($class, 'schoolClass')->for($subject)->create([
        'date' => Carbon::today(),
        'homework' => 'Exercices 12 à 15 p.42',
    ]);
    LogbookEntry::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'date' => Carbon::today(),
        'homework' => null,
    ]);
    LogbookEntry::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'date' => Carbon::tomorrow(),
        'homework' => 'Pour demain',
    ]);

    $token = $teacher->ensureApiToken();

    $response = $this->getJson("/api/nas/{$token}/homework");

    $response->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonFragment(['class' => $class->name, 'subject' => 'Mathématiques', 'homework' => 'Exercices 12 à 15 p.42']);
});

it('invalidates the old token when a new one is regenerated', function () {
    $teacher = User::factory()->create();
    $oldToken = $teacher->ensureApiToken();

    $this->getJson("/api/nas/{$oldToken}/reminders")->assertOk();

    $newToken = $teacher->regenerateApiToken();

    $this->getJson("/api/nas/{$oldToken}/reminders")->assertNotFound();
    $this->getJson("/api/nas/{$newToken}/reminders")->assertOk();
});

it('authenticates via Authorization: Bearer on the tokenless routes', function () {
    $teacher = User::factory()->create();
    $done = Reminder::factory()->for($teacher)->done()->create();
    $pending = Reminder::factory()->for($teacher)->create(['title' => 'Urgent']);

    $token = $teacher->ensureApiToken();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/nas/reminders')
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonFragment(['title' => 'Urgent']);
});

it('rejects the tokenless route with no bearer token and with an invalid one', function () {
    $this->getJson('/api/nas/reminders')->assertStatus(401);

    $this->withHeader('Authorization', 'Bearer not-a-real-token')
        ->getJson('/api/nas/reminders')
        ->assertNotFound();
});

it('shows both URL forms (path token and bearer) on the Intégration API page and lets a teacher regenerate the token', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    $oldToken = $teacher->ensureApiToken();

    $component = Livewire::test(ApiIntegration::class);

    expect($component->get('token'))->toBe($oldToken)
        ->and($component->get('birthdaysUrl'))->toContain($oldToken)
        ->and($component->get('scheduleUrl'))->toContain($oldToken)
        ->and($component->get('remindersUrl'))->toContain($oldToken)
        ->and($component->get('homeworkUrl'))->toContain($oldToken)
        ->and($component->get('birthdaysBearerUrl'))->not->toContain($oldToken)
        ->and($component->get('scheduleBearerUrl'))->not->toContain($oldToken)
        ->and($component->get('remindersBearerUrl'))->not->toContain($oldToken)
        ->and($component->get('homeworkBearerUrl'))->not->toContain($oldToken);

    $component->call('regenerateToken');

    expect($component->get('birthdaysUrl'))->not->toContain($oldToken)
        ->and($component->get('token'))->not->toBe($oldToken);
});
