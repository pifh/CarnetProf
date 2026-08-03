<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Classe</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="schoolClassId">
                        @foreach ($this->schoolClasses as $schoolClass)
                            <option value="{{ $schoolClass->id }}">{{ $schoolClass->name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            @if ($this->subjects->isNotEmpty())
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Matière</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="subjectId">
                            @foreach ($this->subjects as $subject)
                                <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            @endif

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Période</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="termId">
                        <option value="">Année complète</option>
                        @foreach ($this->terms as $term)
                            <option value="{{ $term->id }}">{{ $term->label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <x-filament::button color="gray" wire:click="exportCsv">
                Exporter CSV
            </x-filament::button>
        </div>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">Notes et moyennes</x-slot>
        <x-slot name="description">
            @if ($termId)
                Chaque évaluation du trimestre sélectionné est affichée en colonne. Cliquez sur « Progression » pour voir la courbe de l'élève.
            @else
                Une moyenne par trimestre plus la moyenne annuelle pour chaque élève — sélectionnez un trimestre précis pour voir le détail de chaque note.
            @endif
        </x-slot>

        @php($rows = $this->summaryRows)

        @if ($rows->isEmpty())
            <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                Aucun élève dans cette classe, ou aucune classe créée.
            </div>
        @elseif ($termId)
            @php($evaluations = $this->termEvaluations)
            @php($classSummary = $this->classSummary)

            @if ($evaluations->isEmpty())
                <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                    Aucune évaluation créée pour ce trimestre.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left">
                                <th class="p-2">Élève</th>
                                @foreach ($evaluations as $evaluation)
                                    <th class="p-2">
                                        {{ $evaluation->title }}
                                        <div class="text-xs font-normal text-gray-400">{{ $evaluation->exam_date?->format('d/m/Y') }}</div>
                                    </th>
                                @endforeach
                                <th class="p-2">Moyenne</th>
                                <th class="p-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                @php($student = $row['student'])
                                <tr class="border-t border-gray-100 dark:border-white/5">
                                    <td class="p-2">
                                        <div class="flex items-center gap-2">
                                            <div class="h-8 w-8 shrink-0 overflow-hidden rounded-full">
                                                @include('filament.pages.partials.student-photo', ['student' => $student, 'class' => 'h-8 w-8 rounded-full object-cover'])
                                            </div>
                                            <span>{{ $student->first_name }} {{ $student->last_name }}</span>
                                        </div>
                                    </td>
                                    @foreach ($evaluations as $evaluation)
                                        @php($grade = $row['grades'][$evaluation->id] ?? null)
                                        <td class="p-2">
                                            @if ($grade && $grade->status === 'graded' && $grade->score !== null)
                                                {{ rtrim(rtrim(number_format($grade->score, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($evaluation->max_score, 2), '0'), '.') }}
                                            @elseif ($grade)
                                                {{ [
                                                    'absent' => 'Absent',
                                                    'exempted' => 'Dispensé',
                                                    'to_retake' => 'À rattraper',
                                                    'not_graded' => 'Non noté',
                                                ][$grade->status] ?? 'Non noté' }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="p-2 font-medium">
                                        {{ $row['termAverage'] !== null ? number_format($row['termAverage'], 2).'/20' : '—' }}
                                    </td>
                                    <td class="p-2">
                                        <x-filament::button size="xs" color="gray" wire:click="toggleStudent({{ $student->id }})">
                                            {{ $selectedStudentId === $student->id ? 'Fermer' : 'Progression' }}
                                        </x-filament::button>
                                    </td>
                                </tr>

                                @if ($selectedStudentId === $student->id)
                                    <tr>
                                        <td colspan="{{ $evaluations->count() + 3 }}" class="border-t border-gray-100 p-4 dark:border-white/5">
                                            <div class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Progression — {{ $this->terms->firstWhere('id', $termId)?->label }}</div>
                                            @include('filament.pages.partials.grade-progression-chart', ['points' => $this->selectedStudentChartPoints])
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-gray-200 font-medium dark:border-white/10">
                                <td class="p-2">Moyenne de classe</td>
                                @foreach ($evaluations as $evaluation)
                                    <td class="p-2">
                                        {{ $classSummary['evaluationAverages'][$evaluation->id] !== null ? number_format($classSummary['evaluationAverages'][$evaluation->id], 2).'/20' : '—' }}
                                    </td>
                                @endforeach
                                <td class="p-2">
                                    {{ $classSummary['termAverage'] !== null ? number_format($classSummary['termAverage'], 2).'/20' : '—' }}
                                </td>
                                <td class="p-2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left">
                            <th class="p-2">Élève</th>
                            @foreach ($this->terms as $term)
                                <th class="p-2">{{ $term->label }}</th>
                            @endforeach
                            <th class="p-2">Moyenne annuelle</th>
                            <th class="p-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php($student = $row['student'])
                            <tr class="border-t border-gray-100 dark:border-white/5">
                                <td class="p-2">
                                    <div class="flex items-center gap-2">
                                        <div class="h-8 w-8 shrink-0 overflow-hidden rounded-full">
                                            @include('filament.pages.partials.student-photo', ['student' => $student, 'class' => 'h-8 w-8 rounded-full object-cover'])
                                        </div>
                                        <span>{{ $student->first_name }} {{ $student->last_name }}</span>
                                    </div>
                                </td>
                                @foreach ($this->terms as $term)
                                    <td class="p-2">
                                        {{ $row['termAverages'][$term->id] !== null ? number_format($row['termAverages'][$term->id], 2).'/20' : '—' }}
                                    </td>
                                @endforeach
                                <td class="p-2 font-medium">
                                    {{ $row['annualAverage'] !== null ? number_format($row['annualAverage'], 2).'/20' : '—' }}
                                </td>
                                <td class="p-2">
                                    <x-filament::button size="xs" color="gray" wire:click="toggleStudent({{ $student->id }})">
                                        {{ $selectedStudentId === $student->id ? 'Fermer' : 'Progression' }}
                                    </x-filament::button>
                                </td>
                            </tr>

                            @if ($selectedStudentId === $student->id)
                                <tr>
                                    <td colspan="{{ $this->terms->count() + 3 }}" class="border-t border-gray-100 p-4 dark:border-white/5">
                                        <div class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Progression — année complète</div>
                                        @include('filament.pages.partials.grade-progression-chart', ['points' => $this->selectedStudentChartPoints])
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                    <tfoot>
                        @php($classSummary = $this->classSummary)
                        <tr class="border-t border-gray-200 font-medium dark:border-white/10">
                            <td class="p-2">Moyenne de classe</td>
                            @foreach ($this->terms as $term)
                                <td class="p-2">
                                    {{ $classSummary['termAverages'][$term->id] !== null ? number_format($classSummary['termAverages'][$term->id], 2).'/20' : '—' }}
                                </td>
                            @endforeach
                            <td class="p-2">
                                {{ $classSummary['annualAverage'] !== null ? number_format($classSummary['annualAverage'], 2).'/20' : '—' }}
                            </td>
                            <td class="p-2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
