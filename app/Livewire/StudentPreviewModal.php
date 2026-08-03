<?php

namespace App\Livewire;

use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Mounted once globally (see AppPanelProvider's body-end render hook) and
 * opened from anywhere in the app via `$dispatch('open-student-preview', {
 * studentId })` — clicking a student's name or photo on any page. Loading
 * by id and re-scoping through BelongsToTeacher on every open means a
 * stale/foreign id just renders nothing rather than leaking another
 * teacher's student.
 */
class StudentPreviewModal extends Component
{
    public ?int $studentId = null;

    #[On('open-student-preview')]
    public function open(int $studentId): void
    {
        $this->studentId = $studentId;
    }

    public function close(): void
    {
        $this->studentId = null;
    }

    public function getStudentProperty(): ?Student
    {
        if (! $this->studentId) {
            return null;
        }

        return Student::query()
            ->where('user_id', Auth::id())
            ->with('schoolClass')
            ->find($this->studentId);
    }

    public function render()
    {
        return view('livewire.student-preview-modal');
    }
}
