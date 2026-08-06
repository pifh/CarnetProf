@if ($studentId)
    @livewire('student-record-card', ['studentId' => $studentId, 'allowClassSwitch' => true, 'appreciationReadOnly' => true], key('event-fiche-'.$studentId))
@endif
