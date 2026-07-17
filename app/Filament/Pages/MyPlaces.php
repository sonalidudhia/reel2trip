<?php

namespace App\Filament\Pages;

use App\Models\Place;
use App\Models\Trip;
use App\Models\TripCity;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class MyPlaces extends Page
{
    protected string $view = 'filament.pages.my-places';

    protected static bool $shouldRegisterNavigation = false;

    public Trip $trip;

    /** 'all' | 'visiting' | 'must_do' */
    public string $filter = 'all';

    public static function getRoutePath(Panel $panel): string
    {
        return '/'.static::getSlug($panel).'/{trip}';
    }

    public function mount(Trip $trip): void
    {
        abort_unless($trip->user_id === auth()->id(), 404);

        $this->trip = $trip;
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    public function toggleSelected(int $placeId): void
    {
        $place = $this->findOwnedPlace($placeId);
        $place->update(['selected' => ! $place->selected]);
    }

    public function toggleMustDo(int $placeId): void
    {
        $place = $this->findOwnedPlace($placeId);

        if (! $place->selected) {
            return; // star is disabled client-side too; guard server-side regardless
        }

        $place->update(['must_do' => ! $place->must_do]);
    }

    public function assignToCity(int $placeId, ?int $tripCityId): void
    {
        $place = $this->findOwnedPlace($placeId);

        if ($tripCityId !== null && ! $this->trip->tripCities->contains('id', $tripCityId)) {
            return;
        }

        $place->update(['trip_city_id' => $tripCityId]);
    }

    /** @return SupportCollection<int, array{country: string, minPosition: int, cities: SupportCollection<int, array{city: TripCity, places: SupportCollection<int, Place>, tips: SupportCollection<int, Place>}>}> */
    public function getCountries(): SupportCollection
    {
        return $this->trip->tripCities
            ->groupBy('country')
            ->map(fn (Collection $cities, string $country) => [
                'country' => $country,
                'minPosition' => (int) $cities->min('position'),
                'cities' => $cities->map(fn (TripCity $city) => [
                    'city' => $city,
                    'places' => $this->filtered($this->togglePlaces($city->places)),
                    'tips' => $this->tipPlaces($city->places),
                ])->values(),
            ])
            ->sortBy('minPosition')
            ->values();
    }

    /** @return array{places: SupportCollection<int, Place>, tips: SupportCollection<int, Place>} */
    public function getUnassigned(): array
    {
        $places = Place::query()
            ->whereHas('reel', fn ($q) => $q->where('trip_id', $this->trip->id))
            ->whereNull('trip_city_id')
            ->where('dismissed', false)
            ->orderByDesc('rating')
            ->get();

        return [
            'places' => $this->filtered($this->togglePlaces($places)),
            'tips' => $this->tipPlaces($places),
        ];
    }

    /**
     * @param  Collection<int, Place>|SupportCollection<int, Place>  $places
     * @return array{selected: int, mustDo: int}
     */
    public function counts(Collection|SupportCollection $places): array
    {
        return [
            'selected' => $places->where('selected', true)->count(),
            'mustDo' => $places->where('must_do', true)->count(),
        ];
    }

    /**
     * @param  Collection<int, Place>|SupportCollection<int, Place>  $places
     * @return SupportCollection<int, Place>
     */
    private function togglePlaces(Collection|SupportCollection $places): SupportCollection
    {
        return $places->where('dismissed', false)->where('category', '!=', 'tip')->values();
    }

    /**
     * @param  Collection<int, Place>|SupportCollection<int, Place>  $places
     * @return SupportCollection<int, Place>
     */
    private function tipPlaces(Collection|SupportCollection $places): SupportCollection
    {
        return $places->where('dismissed', false)->where('category', 'tip')->values();
    }

    /**
     * @param  SupportCollection<int, Place>  $places
     * @return SupportCollection<int, Place>
     */
    private function filtered(SupportCollection $places): SupportCollection
    {
        return match ($this->filter) {
            'visiting' => $places->where('selected', true)->values(),
            'must_do' => $places->where('must_do', true)->values(),
            default => $places,
        };
    }

    private function findOwnedPlace(int $placeId): Place
    {
        return Place::query()
            ->where('id', $placeId)
            ->whereHas('reel', fn ($q) => $q->where('trip_id', $this->trip->id))
            ->firstOrFail();
    }
}
