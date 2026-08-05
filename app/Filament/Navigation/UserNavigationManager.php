<?php

namespace App\Filament\Navigation;

use App\Filament\Pages\SidebarPreferences;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Navigation\NavigationManager;
use Illuminate\Support\Facades\Auth;

/**
 * Wraps Filament's default navigation building (auto-discovered from every
 * Resource/Page's own navigationSort/shouldRegisterNavigation) with a final
 * per-user pass: reorders items according to the teacher's saved
 * sidebar_order, and drops any item listed in sidebar_hidden. The settings
 * page itself (SidebarPreferences) is exempt from hiding, so a teacher can
 * never accidentally lock themselves out of the customization screen.
 */
class UserNavigationManager extends NavigationManager
{
    private bool $bypassPersonalization = false;

    /**
     * @return array<NavigationGroup>
     */
    public function get(): array
    {
        $groups = parent::get();

        return $this->bypassPersonalization ? $groups : $this->personalize($groups);
    }

    /**
     * Filament's own navigation, with no per-user personalization applied —
     * every registered item, in its default order. Used by SidebarPreferences
     * to list every possible item (even ones this user currently hides).
     *
     * Must re-enter through Panel::getNavigation() rather than calling
     * get()/parent::get() straight on $this: registerNavigationItems() on
     * each Resource/Page routes through Panel::navigationItems(), which
     * only forwards to whatever NavigationManager instance the panel has
     * currently stashed as $this->navigationManager — a link that
     * Panel::getNavigation() itself sets up right before calling ->get().
     * Skipping that entry point leaves items appended to the panel's own
     * array instead of this manager's, silently producing an empty
     * navigation for the rest of the request (including the real sidebar).
     *
     * @return array<NavigationGroup>
     */
    public function getRawNavigation(): array
    {
        $this->bypassPersonalization = true;

        try {
            return Filament::getCurrentOrDefaultPanel()->getNavigation();
        } finally {
            $this->bypassPersonalization = false;
        }
    }

    /**
     * @param  array<NavigationGroup>  $groups
     * @return array<NavigationGroup>
     */
    private function personalize(array $groups): array
    {
        $user = Auth::user();

        if (! $user) {
            return $groups;
        }

        $order = $user->sidebarOrder();
        $hidden = $user->sidebarHidden();

        if ($order === [] && $hidden === []) {
            return $groups;
        }

        $orderIndex = array_flip($order);
        $exemptKey = SidebarPreferences::getUrl();

        foreach ($groups as $group) {
            $items = collect($group->getItems())
                ->reject(fn (NavigationItem $item) => static::key($item) !== $exemptKey && in_array(static::key($item), $hidden, true))
                ->values()
                ->sort(fn (NavigationItem $a, NavigationItem $b) => static::comparePositions(
                    $orderIndex[static::key($a)] ?? null,
                    $orderIndex[static::key($b)] ?? null,
                ))
                ->values()
                ->all();

            $group->items($items);
        }

        return array_values(array_filter(
            $groups,
            fn (NavigationGroup $group) => filled($group->getItems()),
        ));
    }

    public static function key(NavigationItem $item): string
    {
        return $item->getUrl() ?: $item->getLabel();
    }

    /**
     * Items with a saved position sort by it; items without one (new since
     * the user last customized their menu) are pushed after all of those,
     * keeping their existing relative order among themselves.
     */
    public static function comparePositions(?int $aPos, ?int $bPos): int
    {
        return match (true) {
            $aPos === null && $bPos === null => 0,
            $aPos === null => 1,
            $bPos === null => -1,
            default => $aPos <=> $bPos,
        };
    }
}
