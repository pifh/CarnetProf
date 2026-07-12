<?php

namespace App\Services;

use App\Imports\GenericArrayImport;
use App\Models\Guardian;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentSubgroup;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class StudentImporter
{
    /**
     * Importable fields, grouped for display, with their French label and whether they are mandatory.
     *
     * @var array<string, array<string, array{label: string, required: bool}>>
     */
    public const FIELDS = [
        'Élève' => [
            'full_name' => ['label' => 'Nom complet (« NOM Prénom »)', 'required' => false],
            'last_name' => ['label' => 'Nom', 'required' => false],
            'first_name' => ['label' => 'Prénom', 'required' => false],
            'sex' => ['label' => 'Sexe', 'required' => false],
            'birth_date' => ['label' => 'Date de naissance', 'required' => false],
            'class_name' => ['label' => 'Classe', 'required' => false],
            'subgroup_names' => ['label' => 'Groupes (séparés par une virgule)', 'required' => false],
            'address' => ['label' => 'Adresse de l\'élève', 'required' => false],
            'phone' => ['label' => 'Téléphone de l\'élève', 'required' => false],
            'email' => ['label' => 'Email de l\'élève', 'required' => false],
            'private_notes' => ['label' => 'Commentaires', 'required' => false],
        ],
        'Responsable légal 1' => [
            'guardian1_full_name' => ['label' => 'Responsable 1 — Nom complet', 'required' => false],
            'guardian1_last_name' => ['label' => 'Responsable 1 — Nom', 'required' => false],
            'guardian1_first_name' => ['label' => 'Responsable 1 — Prénom', 'required' => false],
            'guardian1_relationship' => ['label' => 'Responsable 1 — Lien avec l\'élève', 'required' => false],
            'guardian1_phone' => ['label' => 'Responsable 1 — Téléphone', 'required' => false],
            'guardian1_email' => ['label' => 'Responsable 1 — Email', 'required' => false],
            'guardian1_address' => ['label' => 'Responsable 1 — Adresse', 'required' => false],
        ],
        'Responsable légal 2' => [
            'guardian2_full_name' => ['label' => 'Responsable 2 — Nom complet', 'required' => false],
            'guardian2_last_name' => ['label' => 'Responsable 2 — Nom', 'required' => false],
            'guardian2_first_name' => ['label' => 'Responsable 2 — Prénom', 'required' => false],
            'guardian2_relationship' => ['label' => 'Responsable 2 — Lien avec l\'élève', 'required' => false],
            'guardian2_phone' => ['label' => 'Responsable 2 — Téléphone', 'required' => false],
            'guardian2_email' => ['label' => 'Responsable 2 — Email', 'required' => false],
            'guardian2_address' => ['label' => 'Responsable 2 — Adresse', 'required' => false],
        ],
    ];

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    public function parseFile(string $absolutePath): array
    {
        $sheets = Excel::toArray(new GenericArrayImport, $absolutePath);
        $sheet = $sheets[0] ?? [];

        $headers = array_map(
            fn ($value) => trim((string) $value),
            $sheet[0] ?? [],
        );

        $rows = array_slice($sheet, 1);

        $rows = array_values(array_filter(
            $rows,
            fn ($row) => collect($row)->contains(fn ($value) => trim((string) $value) !== ''),
        ));

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Split a combined "NOM(S) Prénom(s)" value into its last/first name parts.
     * Assumes the surname is written in capitals, as is standard in French school exports.
     */
    public function splitFullName(string $value): array
    {
        $tokens = preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($tokens) < 2) {
            return ['last_name' => $value, 'first_name' => ''];
        }

        $splitIndex = null;
        foreach ($tokens as $index => $token) {
            if ($this->isTitleCaseToken($token)) {
                $splitIndex = $index;
                break;
            }
        }

        if ($splitIndex === null || $splitIndex === 0) {
            $splitIndex = count($tokens) - 1;
        }

        return [
            'last_name' => implode(' ', array_slice($tokens, 0, $splitIndex)),
            'first_name' => implode(' ', array_slice($tokens, $splitIndex)),
        ];
    }

    /**
     * Interpret raw rows against a column mapping, without persisting anything.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<string, int|null>  $mapping  field key => column index
     * @return array<int, array{row_number:int, data: array, school_class_id: ?int, is_duplicate: bool, errors: array}>
     */
    public function preview(User $user, array $rows, array $mapping, ?int $defaultSchoolClassId): array
    {
        $classesByName = SchoolClass::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy(fn (SchoolClass $class) => Str::lower($class->name));

        $seen = [];
        $result = [];

        foreach ($rows as $index => $row) {
            $data = $this->normalizeRowData($this->extractRowData($row, $mapping));
            $errors = [];

            if (blank($data['first_name'] ?? null)) {
                $errors[] = 'Prénom manquant';
            }
            if (blank($data['last_name'] ?? null)) {
                $errors[] = 'Nom manquant';
            }

            $schoolClassId = $defaultSchoolClassId;
            if (! blank($data['class_name'] ?? null)) {
                $match = $classesByName->get(Str::lower(trim($data['class_name'])));
                if ($match) {
                    $schoolClassId = $match->id;
                } elseif (! $schoolClassId) {
                    $errors[] = "Classe « {$data['class_name']} » introuvable";
                }
            }

            if (! $schoolClassId) {
                $errors[] = 'Aucune classe déterminée';
            }

            $isDuplicate = false;

            if (empty($errors)) {
                $dedupeKey = $schoolClassId.'|'.Str::lower(trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')));

                if (isset($seen[$dedupeKey]) || $this->findExistingStudent($user, $schoolClassId, $data['first_name'], $data['last_name'])) {
                    $isDuplicate = true;
                }

                $seen[$dedupeKey] = true;
            }

            $result[] = [
                'row_number' => $index + 1,
                'data' => $data,
                'school_class_id' => $schoolClassId,
                'is_duplicate' => $isDuplicate,
                'errors' => $errors,
            ];
        }

        return $result;
    }

    public function findExistingStudent(User $user, int $schoolClassId, string $firstName, string $lastName): ?Student
    {
        return Student::query()
            ->where('user_id', $user->id)
            ->where('school_class_id', $schoolClassId)
            ->whereRaw('LOWER(first_name) = ?', [Str::lower(trim($firstName))])
            ->whereRaw('LOWER(last_name) = ?', [Str::lower(trim($lastName))])
            ->first();
    }

    /**
     * @param  array<string, int|null>  $mapping
     * @param  array<int, array{row_number:int, data:array, school_class_id:?int, is_duplicate:bool, errors:array}>  $previewRows
     */
    public function execute(User $user, string $originalFilename, array $mapping, ?int $defaultSchoolClassId, array $previewRows): Import
    {
        return DB::transaction(function () use ($user, $originalFilename, $mapping, $defaultSchoolClassId, $previewRows) {
            $import = new Import([
                'school_class_id' => $defaultSchoolClassId,
                'original_filename' => $originalFilename,
                'column_mapping' => $mapping,
                'status' => 'completed',
                'total_rows' => count($previewRows),
            ]);
            $import->user_id = $user->id;
            $import->save();

            $imported = 0;
            $duplicates = 0;
            $errors = 0;

            foreach ($previewRows as $row) {
                if (! empty($row['errors'])) {
                    $errors++;
                    ImportRow::create([
                        'import_id' => $import->id,
                        'row_number' => $row['row_number'],
                        'raw_data' => $row['data'],
                        'status' => 'error',
                        'error_message' => implode(', ', $row['errors']),
                    ]);

                    continue;
                }

                if ($row['is_duplicate']) {
                    $duplicates++;
                    ImportRow::create([
                        'import_id' => $import->id,
                        'row_number' => $row['row_number'],
                        'raw_data' => $row['data'],
                        'status' => 'duplicate',
                    ]);

                    continue;
                }

                $student = new Student([
                    'school_class_id' => $row['school_class_id'],
                    'first_name' => trim($row['data']['first_name']),
                    'last_name' => trim($row['data']['last_name']),
                    'sex' => $this->normalizeSex($row['data']['sex'] ?? null),
                    'birth_date' => $this->parseDate($row['data']['birth_date'] ?? null),
                    'address' => $row['data']['address'] ?? null,
                    'phone' => $row['data']['phone'] ?? null,
                    'email' => $row['data']['email'] ?? null,
                    'private_notes' => $row['data']['private_notes'] ?? null,
                ]);
                $student->user_id = $user->id;
                $student->save();

                $this->attachSubgroups($student, $row['school_class_id'], $row['data']['subgroup_names'] ?? null);
                $this->syncGuardian($student, $row['data'], 'guardian1', isPrimary: true);
                $this->syncGuardian($student, $row['data'], 'guardian2', isPrimary: false);

                ImportRow::create([
                    'import_id' => $import->id,
                    'row_number' => $row['row_number'],
                    'raw_data' => $row['data'],
                    'student_id' => $student->id,
                    'status' => 'imported',
                ]);

                $imported++;
            }

            $import->update([
                'imported_rows' => $imported,
                'duplicate_rows' => $duplicates,
                'error_rows' => $errors,
            ]);

            return $import;
        });
    }

    public function cancel(Import $import): void
    {
        DB::transaction(function () use ($import) {
            $studentIds = $import->rows()->whereNotNull('student_id')->pluck('student_id');
            Student::query()->whereIn('id', $studentIds)->get()->each->delete();
            $import->update(['status' => 'cancelled']);
        });
    }

    public function normalizeSex(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $value = Str::lower(trim($value));

        return match (true) {
            in_array($value, ['f', 'fille', 'féminin', 'femme'], true) => 'f',
            in_array($value, ['m', 'garçon', 'garcon', 'masculin', 'homme'], true) => 'm',
            default => 'other',
        };
    }

    public function parseDate(?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'd/m/y'] as $format) {
            try {
                return Carbon::createFromFormat($format, trim($value));
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * Fill in first_name/last_name (for the student and each guardian) from their
     * "full_name" counterpart whenever the split fields were not mapped directly.
     */
    private function normalizeRowData(array $data): array
    {
        foreach (['', 'guardian1_', 'guardian2_'] as $prefix) {
            $fullNameKey = $prefix.'full_name';
            $lastNameKey = $prefix.'last_name';
            $firstNameKey = $prefix.'first_name';

            if (blank($data[$lastNameKey] ?? null) && blank($data[$firstNameKey] ?? null) && ! blank($data[$fullNameKey] ?? null)) {
                $split = $this->splitFullName($data[$fullNameKey]);
                $data[$lastNameKey] = $split['last_name'];
                $data[$firstNameKey] = $split['first_name'];
            }
        }

        return $data;
    }

    private function isTitleCaseToken(string $token): bool
    {
        return mb_strtoupper($token) !== $token && mb_strtolower($token) !== $token;
    }

    private function syncGuardian(Student $student, array $data, string $prefix, bool $isPrimary): void
    {
        $lastName = trim($data["{$prefix}_last_name"] ?? '');
        $firstName = trim($data["{$prefix}_first_name"] ?? '');

        if ($lastName === '' && $firstName === '') {
            return;
        }

        $guardian = Guardian::query()
            ->where('user_id', $student->user_id)
            ->whereRaw('LOWER(first_name) = ?', [Str::lower($firstName)])
            ->whereRaw('LOWER(last_name) = ?', [Str::lower($lastName)])
            ->first();

        if (! $guardian) {
            $guardian = new Guardian([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'relationship' => $data["{$prefix}_relationship"] ?? null,
                'phone' => $data["{$prefix}_phone"] ?? null,
                'email' => $data["{$prefix}_email"] ?? null,
                'address' => $data["{$prefix}_address"] ?? null,
            ]);
            $guardian->user_id = $student->user_id;
            $guardian->save();
        }

        $student->guardians()->syncWithoutDetaching([$guardian->id => ['is_primary' => $isPrimary]]);
    }

    private function attachSubgroups(Student $student, ?int $schoolClassId, ?string $subgroupNames): void
    {
        if (blank($subgroupNames) || ! $schoolClassId) {
            return;
        }

        $names = collect(explode(',', $subgroupNames))
            ->map(fn ($name) => trim($name))
            ->filter();

        foreach ($names as $name) {
            $subgroup = StudentSubgroup::query()
                ->where('user_id', $student->user_id)
                ->where('school_class_id', $schoolClassId)
                ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
                ->first();

            if (! $subgroup) {
                $subgroup = new StudentSubgroup([
                    'school_class_id' => $schoolClassId,
                    'name' => $name,
                ]);
                $subgroup->user_id = $student->user_id;
                $subgroup->save();
            }

            $student->subgroups()->syncWithoutDetaching([$subgroup->id]);
        }
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int|null>  $mapping
     */
    private function extractRowData(array $row, array $mapping): array
    {
        $data = [];

        foreach ($mapping as $field => $columnIndex) {
            if ($columnIndex === null) {
                continue;
            }

            $data[$field] = trim((string) ($row[$columnIndex] ?? ''));
        }

        return $data;
    }
}
