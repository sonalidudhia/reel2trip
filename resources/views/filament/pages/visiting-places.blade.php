<x-filament-panels::page>
    {{-- Controls --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <label class="flex-1">
            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Search</span>
            <x-filament::input.wrapper>
                <x-filament::input type="search" wire:model.live.debounce.300ms="search" placeholder="Place, tip or note…" />
            </x-filament::input.wrapper>
        </label>

        <label class="sm:w-48">
            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Trip</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="tripId">
                    <option value="">All trips</option>
                    @foreach ($this->tripOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>

        <label class="sm:w-48">
            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Group by</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="groupBy">
                    <option value="category">Category</option>
                    <option value="day">Day</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>

        <x-filament::button
            :color="$mustDoOnly ? 'primary' : 'gray'"
            icon="heroicon-m-star"
            wire:click="$toggle('mustDoOnly')"
        >
            Must-do only
        </x-filament::button>
    </div>

    @forelse ($this->cities as $city)
        <x-filament::section
            :heading="$city['name']"
            :description="collect([$city['subtitle'], $city['days'] ? $city['days'] . ' ' . str('day')->plural($city['days']) : null, $city['count'] . ' ' . str('place')->plural($city['count'])])->filter()->implode(' · ')"
            icon="heroicon-o-map-pin"
            collapsible
            persist-collapsed
            :id="'city-' . $city['key']"
        >
            <div class="space-y-6">
                @foreach ($city['groups'] as $group)
                    <div>
                        <h3 class="flex items-center gap-2 border-b border-gray-200 pb-2 text-sm font-semibold text-gray-950 dark:border-white/10 dark:text-white">
                            <x-filament::icon :icon="$group['icon']" class="h-5 w-5 text-gray-400 dark:text-gray-500" />
                            {{ $group['label'] }}
                            <x-filament::badge size="sm">{{ $group['places']->count() }}</x-filament::badge>
                        </h3>

                        @if ($group['places']->isEmpty())
                            <p class="py-3 text-sm text-gray-500 dark:text-gray-400">Nothing here yet.</p>
                        @else
                            <ul class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach ($group['places'] as $place)
                                    <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-medium text-gray-950 dark:text-white">{{ $place->name }}</span>

                                                @if ($place->must_do)
                                                    <x-filament::badge color="warning" size="sm" icon="heroicon-m-star">Must do</x-filament::badge>
                                                @endif

                                                @if ($place->rating)
                                                    <x-filament::badge color="gray" size="sm">★ {{ $place->rating }}</x-filament::badge>
                                                @endif

                                                @if ($place->priceLabel())
                                                    <x-filament::badge color="gray" size="sm">{{ $place->priceLabel() }}</x-filament::badge>
                                                @endif

                                                @if ($groupBy === 'category' && $place->planned_day)
                                                    <x-filament::badge color="info" size="sm">Day {{ $place->planned_day }}</x-filament::badge>
                                                @endif

                                                @if ($groupBy === 'day')
                                                    <x-filament::badge color="gray" size="sm">{{ \App\Support\PlaceCategories::label($place->category) }}</x-filament::badge>
                                                @endif
                                            </div>

                                            @if ($place->description)
                                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $place->description }}</p>
                                            @endif

                                            @if ($place->tip)
                                                <p class="mt-1 text-sm text-amber-700 dark:text-amber-500">💡 {{ $place->tip }}</p>
                                            @endif

                                            @if ($place->address)
                                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ $place->address }}</p>
                                            @endif
                                        </div>

                                        <div class="flex shrink-0 items-center gap-1">
                                            @if ($city['days'] > 0)
                                                <x-filament::input.wrapper class="w-28">
                                                    <x-filament::input.select
                                                        wire:change="setDay({{ $place->id }}, $event.target.value)"
                                                    >
                                                        <option value="" @selected(! $place->planned_day)>No day</option>
                                                        @foreach (range(1, $city['days']) as $day)
                                                            <option value="{{ $day }}" @selected($place->planned_day === $day)>Day {{ $day }}</option>
                                                        @endforeach
                                                    </x-filament::input.select>
                                                </x-filament::input.wrapper>
                                            @endif

                                            <x-filament::icon-button
                                                :icon="$place->must_do ? 'heroicon-s-star' : 'heroicon-o-star'"
                                                :color="$place->must_do ? 'warning' : 'gray'"
                                                label="Toggle must do"
                                                wire:click="toggleMustDo({{ $place->id }})"
                                            />

                                            @if ($place->lat && $place->lng)
                                                <x-filament::icon-button
                                                    tag="a"
                                                    icon="heroicon-o-map"
                                                    color="gray"
                                                    label="Open in Google Maps"
                                                    href="https://www.google.com/maps/search/?api=1&query={{ $place->lat }},{{ $place->lng }}{{ $place->google_place_id ? '&query_place_id=' . $place->google_place_id : '' }}"
                                                    target="_blank"
                                                />
                                            @endif

                                            <x-filament::icon-button
                                                icon="heroicon-o-x-mark"
                                                color="danger"
                                                label="Remove from list"
                                                wire:click="unselect({{ $place->id }})"
                                                wire:confirm="Remove {{ $place->name }} from your visiting list?"
                                            />
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @empty
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Nothing selected yet. Mark places as <strong>Visiting</strong> on the Places page and they'll show up here, grouped by city.
            </p>
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
