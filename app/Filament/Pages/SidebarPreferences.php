<?php

namespace App\Filament\Pages;

use App\Filament\Navigation\UserNavigationManager;
use BackedEnum;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Navigation\NavigationManager;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class SidebarPreferences extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Personnaliser le menu';

    protected static ?string $title = 'Personnaliser le menu';

    protected static ?int $navigationSort = 1000;

    protected string $view = 'filament.pages.sidebar-preferences';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['items' => $this->currentItems()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Repeater::make('items')
                    ->label('')
                    ->schema([
                        Hidden::make('key'),
                        Hidden::make('label'),
                        Placeholder::make('label_display')
                            ->hiddenLabel()
                            ->content(fn (Get $get): string => $get('label'))
                            ->columnSpan(3),
                        Toggle::make('visible')
                            ->label('Afficher')
                            ->columnSpan(1),
                    ])
                    ->columns(4)
                    ->reorderable()
                    ->reorderableWithButtons()
                    ->addable(false)
                    ->deletable(false)
                    ->collapsible(false)
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $items = $this->form->getState()['items'] ?? [];

        $order = collect($items)->pluck('key')->values()->all();
        $hidden = collect($items)
            ->reject(fn (array $item) => $item['visible'] ?? true)
            ->pluck('key')
            ->values()
            ->all();

        Auth::user()->update([
            'sidebar_order' => $order,
            'sidebar_hidden' => $hidden,
        ]);

        Notification::make()->title('Préférences du menu enregistrées.')->success()->send();
    }

    /**
     * Every navigation item the panel would show with no personalization at
     * all (so a currently-hidden item still appears here to be re-enabled),
     * ordered by the user's saved preference and carrying their saved
     * visibility. This page's own item is excluded — it must stay
     * reachable regardless of what the user does here.
     *
     * @return array<int, array{key: string, label: string, visible: bool}>
     */
    private function currentItems(): array
    {
        $user = Auth::user();
        $order = $user->sidebarOrder();
        $hidden = $user->sidebarHidden();
        $orderIndex = array_flip($order);
        $ownUrl = static::getUrl();

        /** @var UserNavigationManager $manager */
        $manager = app(NavigationManager::class);

        return collect($manager->getRawNavigation())
            ->flatMap(fn (NavigationGroup $group) => collect($group->getItems()))
            ->reject(fn (NavigationItem $item) => UserNavigationManager::key($item) === $ownUrl)
            ->map(fn (NavigationItem $item) => [
                'key' => UserNavigationManager::key($item),
                'label' => $item->getLabel(),
                'visible' => ! in_array(UserNavigationManager::key($item), $hidden, true),
            ])
            ->sort(fn (array $a, array $b) => UserNavigationManager::comparePositions(
                $orderIndex[$a['key']] ?? null,
                $orderIndex[$b['key']] ?? null,
            ))
            ->values()
            ->all();
    }
}
