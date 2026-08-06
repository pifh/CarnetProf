<?php

namespace App\Livewire;

use App\Models\StudentEvent;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Mounted once globally (see AppPanelProvider's body-end render hook) and
 * opened from anywhere in the app via `$dispatch('open-student-event-detail',
 * { eventId })`. Loading by id and re-scoping through BelongsToTeacher on
 * every open means a stale/foreign id just renders nothing rather than
 * leaking another teacher's event.
 */
class StudentEventDetailModal extends Component
{
    public ?int $eventId = null;

    #[On('open-student-event-detail')]
    public function open(int $eventId): void
    {
        $this->eventId = $eventId;
    }

    public function close(): void
    {
        $this->eventId = null;
    }

    public function getEventProperty(): ?StudentEvent
    {
        if (! $this->eventId) {
            return null;
        }

        return StudentEvent::query()
            ->where('user_id', Auth::id())
            ->with(['student', 'attachments'])
            ->find($this->eventId);
    }

    public function render()
    {
        return view('livewire.student-event-detail-modal');
    }
}
