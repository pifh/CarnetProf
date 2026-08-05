<?php

namespace App\Support;

class DisciplineCategories
{
    public const ALL = [
        'oubli_materiel', 'travail_non_fait', 'bavardage', 'discipline',
    ];

    public static function labels(): array
    {
        return [
            'oubli_materiel' => 'Oublis de matériel',
            'travail_non_fait' => 'Travail non fait',
            'bavardage' => 'Bavardages',
            'discipline' => 'Problèmes de discipline',
        ];
    }

    public static function label(string $key): string
    {
        return self::labels()[$key] ?? $key;
    }

    /**
     * How many occurrences (since the last reset) before a category's count
     * turns red to flag a sanction — 2 for oublis matches the teacher's own
     * paper process (a cross in the carnet at the second forgotten item).
     *
     * @return array<string, int>
     */
    public static function defaultThresholds(): array
    {
        return [
            'oubli_materiel' => 2,
            'travail_non_fait' => 3,
            'bavardage' => 3,
            'discipline' => 3,
        ];
    }
}
