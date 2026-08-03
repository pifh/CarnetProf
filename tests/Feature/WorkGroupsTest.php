<?php

use App\Filament\Pages\GroupGenerator;
use App\Filament\Resources\StudentSubgroups\Pages\ListStudentSubgroups;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\GroupGeneration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentSubgroup;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Livewire\Livewire;

it("only lists a teacher's own work groups", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    $class = SchoolClass::factory()->for($teacher)->create();
    $ownGroup = StudentSubgroup::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    StudentSubgroup::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    $this->actingAs($teacher);

    Livewire::test(ListStudentSubgroups::class)
        ->assertCanSeeTableRecords([$ownGroup])
        ->assertCountTableRecords(1);
});

it("prevents a teacher from updating or deleting another teacher's group", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $group = StudentSubgroup::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    expect($teacher->can('update', $group))->toBeFalse()
        ->and($teacher->can('delete', $group))->toBeFalse();
});

it('splits a class into a balanced number of groups', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->count(10)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(GroupGenerator::class)
        ->set('schoolClassId', $class->id)
        ->set('mode', 'count')
        ->set('groupCount', 3)
        ->call('generate');

    $groups = $component->get('generatedGroups');

    expect($groups)->toHaveCount(3);

    $sizes = array_map('count', $groups);
    expect(max($sizes) - min($sizes))->toBeLessThanOrEqual(1)
        ->and(array_sum($sizes))->toBe(10);

    $allIds = array_merge(...$groups);
    expect($allIds)->toHaveCount(10)
        ->and(array_unique($allIds))->toHaveCount(10);
});

it('splits a class by target group size', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->count(9)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(GroupGenerator::class)
        ->set('schoolClassId', $class->id)
        ->set('mode', 'size')
        ->set('groupSize', 3)
        ->call('generate');

    $groups = $component->get('generatedGroups');

    expect($groups)->toHaveCount(3);

    foreach ($groups as $group) {
        expect(count($group))->toBe(3);
    }
});

it('excludes marked students from the generated groups', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $absent = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->count(3)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(GroupGenerator::class)
        ->set('schoolClassId', $class->id)
        ->set('mode', 'count')
        ->set('groupCount', 2)
        ->call('toggleExcluded', $absent->id)
        ->call('generate');

    $allIds = array_merge(...$component->get('generatedGroups'));

    expect($allIds)->not->toContain($absent->id)
        ->and($allIds)->toHaveCount(3);
});

it('saves generated groups without deleting manually-created or previously-generated ones', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $students = Student::factory()->for($teacher)->for($class, 'schoolClass')->count(4)->create();
    $manualGroup = StudentSubgroup::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    Livewire::test(GroupGenerator::class)
        ->set('schoolClassId', $class->id)
        ->set('mode', 'count')
        ->set('groupCount', 2)
        ->set('activityName', 'Exposé chapitre 3')
        ->call('generate')
        ->call('save');

    $firstGeneration = GroupGeneration::query()->where('school_class_id', $class->id)->sole();
    $firstGenerationGroups = StudentSubgroup::query()->where('group_generation_id', $firstGeneration->id)->get();

    expect($firstGenerationGroups)->toHaveCount(2)
        ->and($firstGenerationGroups->pluck('name')->all())->toBe(['Exposé chapitre 3 — Groupe 1', 'Exposé chapitre 3 — Groupe 2']);

    Livewire::test(GroupGenerator::class)
        ->set('schoolClassId', $class->id)
        ->set('mode', 'count')
        ->set('groupCount', 2)
        ->set('activityName', 'TP sciences')
        ->call('generate')
        ->call('save');

    expect(StudentSubgroup::query()->find($manualGroup->id))->not->toBeNull()
        ->and(StudentSubgroup::query()->where('group_generation_id', $firstGeneration->id)->count())->toBe(2)
        ->and(GroupGeneration::query()->where('school_class_id', $class->id)->count())->toBe(2);

    // manual group + first generation's 2 groups + second generation's 2 groups
    $allGroups = StudentSubgroup::query()->where('school_class_id', $class->id)->get();
    expect($allGroups)->toHaveCount(5);

    $secondGeneration = GroupGeneration::query()->where('school_class_id', $class->id)->latest()->first();
    $secondGenerationGroups = StudentSubgroup::query()->where('group_generation_id', $secondGeneration->id)->get();
    $memberIds = $secondGenerationGroups->flatMap(fn (StudentSubgroup $group) => $group->students()->pluck('students.id'));

    expect($memberIds->sort()->values()->all())->toBe($students->pluck('id')->sort()->values()->all());
});

it('records the criteria used in the archived generation', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $students = Student::factory()->for($teacher)->for($class, 'schoolClass')->count(6)->create();

    $this->actingAs($teacher);

    Livewire::test(GroupGenerator::class)
        ->set('schoolClassId', $class->id)
        ->set('mode', 'count')
        ->set('groupCount', 2)
        ->set('levelMode', 'balance')
        ->set('genderMode', 'mixed_balanced')
        ->set('avoidRepeats', true)
        ->set('keepTogetherPairs', [[$students[0]->id, $students[1]->id]])
        ->set('keepApartPairs', [[$students[2]->id, $students[3]->id]])
        ->set('activityName', 'Exposé chapitre 3')
        ->call('generate')
        ->call('save');

    $generation = GroupGeneration::query()->where('school_class_id', $class->id)->sole();

    expect($generation->criteria['level_mode'])->toBe('balance')
        ->and($generation->criteria['gender_mode'])->toBe('mixed_balanced')
        ->and($generation->criteria['avoid_repeats'])->toBeTrue()
        ->and($generation->criteria['keep_together_pairs'])->toBe([[$students[0]->id, $students[1]->id]])
        ->and($generation->criteria['keep_apart_pairs'])->toBe([[$students[2]->id, $students[3]->id]])
        ->and($generation->groups)->toHaveCount(2);
});

it('deletes a generation from history without deleting its groups', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->count(4)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(GroupGenerator::class)
        ->set('schoolClassId', $class->id)
        ->set('mode', 'count')
        ->set('groupCount', 2)
        ->set('activityName', 'Exposé chapitre 3')
        ->call('generate')
        ->call('save');

    $generation = GroupGeneration::query()->where('school_class_id', $class->id)->sole();
    $groupIds = StudentSubgroup::query()->where('group_generation_id', $generation->id)->pluck('id');

    $component->call('deleteGeneration', $generation->id);

    expect(GroupGeneration::query()->find($generation->id))->toBeNull();

    $survivingGroups = StudentSubgroup::query()->whereIn('id', $groupIds)->get();
    expect($survivingGroups)->toHaveCount(2)
        ->and($survivingGroups->pluck('group_generation_id')->filter()->all())->toBe([]);
});

it("prevents a teacher from deleting another teacher's group generation", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $otherGeneration = GroupGeneration::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    $class = SchoolClass::factory()->for($teacher)->create();

    $this->actingAs($teacher);

    Livewire::test(GroupGenerator::class)
        ->set('schoolClassId', $class->id)
        ->call('deleteGeneration', $otherGeneration->id);

    expect(GroupGeneration::withoutGlobalScopes()->find($otherGeneration->id))->not->toBeNull();
});

it('scopes generation history by class and subject', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $subjectA = Subject::factory()->for($teacher)->create();
    $subjectB = Subject::factory()->for($teacher)->create();
    $class->subjects()->attach([$subjectA->id, $subjectB->id]);
    Student::factory()->for($teacher)->for($class, 'schoolClass')->count(4)->create();

    $this->actingAs($teacher);

    Livewire::test(GroupGenerator::class)
        ->set('schoolClassId', $class->id)
        ->set('subjectId', $subjectA->id)
        ->set('mode', 'count')
        ->set('groupCount', 2)
        ->set('activityName', 'Exposé chapitre 3')
        ->call('generate')
        ->call('save');

    $componentB = Livewire::test(GroupGenerator::class)
        ->set('schoolClassId', $class->id)
        ->set('subjectId', $subjectB->id);

    expect($componentB->get('history'))->toHaveCount(0);

    $componentA = Livewire::test(GroupGenerator::class)
        ->set('schoolClassId', $class->id)
        ->set('subjectId', $subjectA->id);

    expect($componentA->get('history'))->toHaveCount(1);
});

it('requires an activity name before saving groups', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->count(4)->create();

    $this->actingAs($teacher);

    Livewire::test(GroupGenerator::class)
        ->set('schoolClassId', $class->id)
        ->set('mode', 'count')
        ->set('groupCount', 2)
        ->call('generate')
        ->call('save');

    expect(GroupGeneration::query()->where('school_class_id', $class->id)->exists())->toBeFalse()
        ->and(StudentSubgroup::query()->where('school_class_id', $class->id)->exists())->toBeFalse();
});

it('requires a term to grade the activity', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->count(4)->create();

    $this->actingAs($teacher);

    Livewire::test(GroupGenerator::class)
        ->set('schoolClassId', $class->id)
        ->set('mode', 'count')
        ->set('groupCount', 2)
        ->set('activityName', 'Exposé chapitre 3')
        ->set('isGraded', true)
        ->call('generate')
        ->call('save');

    expect(GroupGeneration::query()->where('school_class_id', $class->id)->exists())->toBeFalse()
        ->and(Evaluation::query()->where('school_class_id', $class->id)->exists())->toBeFalse();
});

it('gives every member of a group the same score when the activity is graded', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->count(4)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(GroupGenerator::class)
        ->set('schoolClassId', $class->id)
        ->set('termId', $term->id)
        ->set('mode', 'count')
        ->set('groupCount', 2)
        ->set('activityName', 'Exposé chapitre 3')
        ->set('isGraded', true)
        ->set('maxScore', 10)
        ->set('coefficient', 2)
        ->call('generate');

    $component
        ->set('groupScores.0', 8)
        ->set('groupScores.1', 6)
        ->call('save');

    $generation = GroupGeneration::query()->where('school_class_id', $class->id)->sole();
    $evaluation = Evaluation::query()->where('school_class_id', $class->id)->sole();

    expect($generation->evaluation_id)->toBe($evaluation->id)
        ->and($evaluation->title)->toBe('Exposé chapitre 3')
        ->and($evaluation->term_id)->toBe($term->id)
        ->and((float) $evaluation->max_score)->toBe(10.0)
        ->and((float) $evaluation->coefficient)->toBe(2.0);

    foreach ($generation->groups as $index => $studentIds) {
        $expectedScore = $index === 0 ? 8.0 : 6.0;

        foreach ($studentIds as $studentId) {
            $grade = Grade::query()->where('evaluation_id', $evaluation->id)->where('student_id', $studentId)->sole();
            expect((float) $grade->score)->toBe($expectedScore)
                ->and($grade->status)->toBe('graded');
        }
    }
});

it('leaves a group not_graded when no score was entered for it', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $students = Student::factory()->for($teacher)->for($class, 'schoolClass')->count(2)->create();

    $this->actingAs($teacher);

    Livewire::test(GroupGenerator::class)
        ->set('schoolClassId', $class->id)
        ->set('termId', $term->id)
        ->set('mode', 'count')
        ->set('groupCount', 1)
        ->set('activityName', 'Exposé chapitre 3')
        ->set('isGraded', true)
        ->call('generate')
        ->call('save');

    $evaluation = Evaluation::query()->where('school_class_id', $class->id)->sole();

    foreach ($students as $student) {
        $grade = Grade::query()->where('evaluation_id', $evaluation->id)->where('student_id', $student->id)->sole();
        expect($grade->score)->toBeNull()
            ->and($grade->status)->toBe('not_graded');
    }
});

it("keeps the group generator's student pool isolated from another teacher's class", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    $this->actingAs($teacher);

    $students = Livewire::test(GroupGenerator::class)
        ->set('schoolClassId', $class->id)
        ->get('students');

    expect($students)->toHaveCount(1)
        ->and($students->first()->id)->toBe($student->id);
});
