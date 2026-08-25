<?php

namespace App\Filament\Pages;

use App\Exports\ExportablePlaces;
use App\Exports\GoogleMyMapsCsv;
use App\Models\Place;
use App\Models\Trip;
use App\Models\TripCity;
use App\Support\PlaceCategories;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisitingPlaces extends Page
{
    protected string $view = 'filament.pages.visiting-places';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::MapPin;

    protected static ?string $navigationLabel = 'Visiting Places';

    protected static ?int $navigationSort = 2;

    public string $tripId = '';

    public bool $mustDoOnly = false;

    public string $groupBy = 'category';

    public string $search = '';

    /** @var array<int, string> */
    protected $queryString = ['tripId', 'mustDoOnly', 'groupBy', 'search'];

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export for Google My Maps')
                ->icon('heroicon-m-arrow-down-tray')
                ->modalHeading('Export for Google My Maps')
                ->modalSubmitActionLabel('Download CSV')
                ->schema([
                    Select::make('trip_city_id')
                        ->label('City')
                        ->options(fn () => $this->exportCityOptions())
                        ->placeholder('All cities')
                        ->helperText('Import the CSV at mymaps.google.com, then Create a new map and choose Import. Pick Latitude and Longitude as the position columns and Name as the title column.'),
                ])
                ->action(fn (array $data): StreamedResponse => $this->exportCsv($data['trip_city_id'] ?? null)),
        ];
    }

    /** @return array<int, string> */
    public function exportCityOptions(): array
    {
        return TripCity::query()
            ->whereHas('trip', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->when($this->tripId !== '', fn (Builder $query) => $query->where('trip_id', $this->tripId))
            ->orderBy('position')
            ->pluck('name', 'id')
            ->all();
    }

    public function exportCsv(int|string|null $tripCityId): StreamedResponse
    {
        return (new GoogleMyMapsCsv($this->exportablePlaces($tripCityId)))->response();
    }

    private function exportablePlaces(int|string|null $tripCityId): ExportablePlaces
    {
        return new ExportablePlaces(
            (int) auth()->id(),
            $tripCityId === null || $tripCityId === '' ? null : (int) $tripCityId,
        );
    }

    /** @return array<int|string, string> */
    public function getTripOptionsProperty(): array
    {
        return Trip::query()
            ->where('user_id', auth()->id())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * City -> group -> places, already ordered for reading top to bottom.
     *
     * @return array<int, array{key: string, name: string, subtitle: ?string, days: int, count: int, groups: array<int, array{key: string, label: string, icon: string, places: Collection<int, Place>}>}>
     */
    public function getCitiesProperty(): array
    {
        $places = Place::query()
            ->where('selected', true)
            ->whereHas('reel.trip', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->when($this->tripId !== '', fn (Builder $query) => $query->whereHas('reel', fn (Builder $r) => $r->where('trip_id', $this->tripId)))
            ->when($this->mustDoOnly, fn (Builder $query) => $query->where('must_do', true))
            ->when($this->search !== '', function (Builder $query) {
                $term = '%'.$this->search.'%';
                $query->where(fn (Builder $q) => $q
                    ->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('tip', 'like', $term));
            })
            ->orderByDesc('must_do')
            ->orderByRaw('rating IS NULL, rating DESC')
            ->orderBy('name')
            ->get();

        $cities = TripCity::query()
            ->whereKey($places->pluck('trip_city_id')->filter()->unique())
            ->get()
            ->keyBy('id')
            ->all();

        return $places
            ->groupBy(fn (Place $place) => (int) $place->trip_city_id)
            ->sortBy(fn (Collection $group, int $key) => isset($cities[$key]) ? $cities[$key]->position : PHP_INT_MAX)
            ->map(function (Collection $group, int $key) use ($cities) {
                $city = $cities[$key] ?? null;
                $days = $city ? (int) $city->days : 0;

                return [
                    'key' => (string) $key,
                    'name' => $city ? $city->name : 'Unassigned',
                    'subtitle' => $city?->country,
                    'days' => $days,
                    'count' => $group->count(),
                    'groups' => $this->groupsFor($group, $days),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Place>  $places
     * @return array<int, array{key: string, label: string, icon: string, places: Collection<int, Place>}>
     */
    protected function groupsFor(Collection $places, int $cityDays): array
    {
        if ($this->groupBy === 'day') {
            $byDay = $places->groupBy(fn (Place $place) => $place->planned_day ?: 0);

            $days = collect(range(1, max($cityDays, (int) $places->max('planned_day'), 1)))
                ->map(fn (int $day) => [
                    'key' => "day-{$day}",
                    'label' => "Day {$day}",
                    'icon' => 'heroicon-o-calendar-days',
                    'places' => $byDay->get($day, collect()),
                ])
                ->all();

            $days[] = [
                'key' => 'unscheduled',
                'label' => 'Not scheduled yet',
                'icon' => 'heroicon-o-inbox',
                'places' => $byDay->get(0, collect()),
            ];

            return $days;
        }

        return $places
            ->groupBy('category')
            ->sortBy(fn (Collection $group, string $category) => PlaceCategories::position($category))
            ->map(fn (Collection $group, string $category) => [
                'key' => $category,
                'label' => PlaceCategories::label($category),
                'icon' => PlaceCategories::icon($category),
                'places' => $group,
            ])
            ->values()
            ->all();
    }

    public function toggleMustDo(int $placeId): void
    {
        $place = $this->ownedPlace($placeId);
        $place->update(['must_do' => ! $place->must_do]);
    }

    public function setDay(int $placeId, string $day): void
    {
        $this->ownedPlace($placeId)->update(['planned_day' => $day === '' ? null : (int) $day]);
    }

    public function unselect(int $placeId): void
    {
        $place = $this->ownedPlace($placeId);
        $place->update(['selected' => false, 'planned_day' => null]);

        Notification::make()
            ->title("Removed {$place->name} from your list")
            ->success()
            ->send();
    }

    protected function ownedPlace(int $placeId): Place
    {
        return Place::query()
            ->whereKey($placeId)
            ->whereHas('reel.trip', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->firstOrFail();
    }
}
