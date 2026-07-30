<?php

namespace App\Filament\Pages;

use App\Models\Place;
use App\Models\Trip;
use App\Models\TripCity;
use App\Support\PlaceCategories;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Everywhere you're actually visiting, as a nested list you can plan days from:
 * city -> category (or day) -> places. Deliberately not a Filament table — a
 * table can only group one level deep, and the whole point here is the second
 * level.
 */
class VisitingPlaces extends Page
{
    protected string $view = 'filament.pages.visiting-places';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::MapPin;

    protected static ?string $navigationLabel = 'Visiting Places';

    protected static ?int $navigationSort = 2;

    /** Empty string = every trip. */
    public string $tripId = '';

    public bool $mustDoOnly = false;

    /** category | day */
    public string $groupBy = 'category';

    public string $search = '';

    /** @var array<int, string> */
    protected $queryString = ['tripId', 'mustDoOnly', 'groupBy', 'search'];

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

        // Keyed separately rather than eager-loaded: trip_city_id is nullable,
        // and a lookup keeps "no city" an honest null instead of a relation.
        $cities = TripCity::query()
            ->whereKey($places->pluck('trip_city_id')->filter()->unique())
            ->get()
            ->keyBy('id')
            ->all();

        return $places
            ->groupBy(fn (Place $place) => (int) $place->trip_city_id) // null -> 0, the "unassigned" bucket
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

            // Always render every day the city has, so empty days read as "still to fill".
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

    /** Never trust an id off the wire — re-scope it to the signed-in user. */
    protected function ownedPlace(int $placeId): Place
    {
        return Place::query()
            ->whereKey($placeId)
            ->whereHas('reel.trip', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->firstOrFail();
    }
}
