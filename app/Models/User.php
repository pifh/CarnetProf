<?php

namespace App\Models;

use App\Support\DisciplineCategories;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\Email\Concerns\InteractsWithEmailAuthentication;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'avatar', 'role', 'ecole_directe_ics_url', 'calendar_feed_categories', 'sidebar_order', 'sidebar_hidden', 'discipline_thresholds'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasAvatar, HasEmailAuthentication, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, InteractsWithEmailAuthentication, Notifiable, SoftDeletes;

    const ROLE_TEACHER = 'teacher';

    const ROLE_ADMIN = 'admin';

    const ROLE_SUPERADMIN = 'superadmin';

    const CALENDAR_FEED_CATEGORIES = [
        'cours',
        'ecole_directe',
        'reunions_eleves',
        'reunions_rdv',
        'etablissement',
        'vacances',
        'anniversaires_eleves',
        'anniversaires_personnels',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'consent_accepted_at' => 'datetime',
            'password' => 'hashed',
            'ecole_directe_ics_url' => 'encrypted',
            'ecole_directe_synced_at' => 'datetime',
            'calendar_feed_categories' => 'array',
            'sidebar_order' => 'array',
            'sidebar_hidden' => 'array',
            'discipline_thresholds' => 'array',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_SUPERADMIN], true);
    }

    public function isSuperadmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function isTeacher(): bool
    {
        return $this->role === self::ROLE_TEACHER;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar ? Storage::disk('public')->url($this->avatar) : null;
    }

    public function ensureCalendarToken(): string
    {
        if (blank($this->calendar_token)) {
            $this->regenerateCalendarToken();
        }

        return $this->calendar_token;
    }

    public function regenerateCalendarToken(): string
    {
        $this->forceFill(['calendar_token' => Str::random(40)])->save();

        return $this->calendar_token;
    }

    public function ensureApiToken(): string
    {
        if (blank($this->api_token)) {
            $this->regenerateApiToken();
        }

        return $this->api_token;
    }

    public function regenerateApiToken(): string
    {
        $this->forceFill(['api_token' => Str::random(40)])->save();

        return $this->api_token;
    }

    public function calendarFeedCategoriesOrDefault(): array
    {
        return $this->calendar_feed_categories ?? self::CALENDAR_FEED_CATEGORIES;
    }

    /**
     * @return array<int, string>
     */
    public function sidebarOrder(): array
    {
        return $this->sidebar_order ?? [];
    }

    /**
     * @return array<int, string>
     */
    public function sidebarHidden(): array
    {
        return $this->sidebar_hidden ?? [];
    }

    /**
     * @return array<string, int>
     */
    public function disciplineThresholdsOrDefault(): array
    {
        return array_merge(DisciplineCategories::defaultThresholds(), $this->discipline_thresholds ?? []);
    }
}
