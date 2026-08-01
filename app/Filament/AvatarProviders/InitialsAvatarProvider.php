<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Database\Eloquent\Model;

/**
 * Generates an initials avatar as a local SVG data URI. Filament's default
 * UiAvatarsProvider calls out to ui-avatars.com, which fails to load in
 * environments without outbound internet access (local dev, restricted VPS
 * egress). This keeps avatars self-contained and always renderable.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $initials = str(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->map(fn (string $segment): string => filled($segment) ? mb_substr($segment, 0, 1) : '')
            ->join('');

        $initials = mb_strtoupper(mb_substr($initials, 0, 2));

        $background = Color::convertToHex(FilamentColor::getColor('primary')[600] ?? Color::Emerald[600]);

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40">
            <rect width="40" height="40" rx="20" fill="{$background}" />
            <text x="20" y="21" fill="#ffffff" font-family="sans-serif" font-size="16" font-weight="600" text-anchor="middle" dominant-baseline="central">{$initials}</text>
        </svg>
        SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
