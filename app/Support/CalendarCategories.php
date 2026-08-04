<?php

namespace App\Support;

/**
 * Single source of truth for the 7 calendar category keys — their French
 * label (used both in the ICS feed CATEGORIES property and the Réglages
 * checkboxes) and their Tailwind chip classes (used only by the in-app
 * day/week/month views). Class names are written out in full below rather
 * than interpolated (e.g. "bg-{$color}-100"), since this app's CSS is a
 * static pre-built Tailwind bundle — interpolated class names never make it
 * into the compiled stylesheet.
 */
class CalendarCategories
{
    public const ALL = [
        'cours',
        'reunions_eleves',
        'reunions_rdv',
        'etablissement',
        'vacances',
        'anniversaires_eleves',
        'anniversaires_personnels',
    ];

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'cours' => 'Cours (cahier de texte)',
            'reunions_eleves' => 'Réunions liées à un élève',
            'reunions_rdv' => 'Réunions & rendez-vous',
            'etablissement' => 'Événements de l\'établissement',
            'vacances' => 'Vacances',
            'anniversaires_eleves' => 'Anniversaires des élèves',
            'anniversaires_personnels' => 'Anniversaires personnels',
        ];
    }

    public static function label(string $key): string
    {
        return self::labels()[$key] ?? $key;
    }

    public static function chipClasses(string $key): string
    {
        return match ($key) {
            'cours' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
            'reunions_eleves' => 'bg-orange-100 text-orange-700 dark:bg-orange-500/10 dark:text-orange-400',
            'reunions_rdv' => 'bg-purple-100 text-purple-700 dark:bg-purple-500/10 dark:text-purple-400',
            'etablissement' => 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300',
            'vacances' => 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400',
            'anniversaires_eleves' => 'bg-pink-100 text-pink-700 dark:bg-pink-500/10 dark:text-pink-400',
            'anniversaires_personnels' => 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400',
            default => 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300',
        };
    }
}
