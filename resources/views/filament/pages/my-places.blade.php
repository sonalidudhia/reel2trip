<x-filament-panels::page>
    @php
        $countries = $this->getCountries();
        $unassigned = $this->getUnassigned();
        $categoryIcons = [
            'food' => '🍴',
            'sight' => '🏛️',
            'viewpoint' => '📷',
            'activity' => '🎟️',
            'area' => '📍',
        ];
        $hasAnyRows = $countries->sum(fn ($country) => $country['cities']->sum(fn ($city) => $city['places']->count() + $city['tips']->count()))
            + $unassigned['places']->count() + $unassigned['tips']->count();
    @endphp

    <div class="flex gap-2">
        @foreach (['all' => 'All', 'visiting' => 'Visiting', 'must_do' => 'Must do'] as $value => $label)
            <button
                wire:click="setFilter('{{ $value }}')"
                @class([
                    'px-3 py-1.5 rounded-lg text-sm font-medium',
                    'bg-primary-600 text-white' => $filter === $value,
                    'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300' => $filter !== $value,
                ])
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if (! $hasAnyRows)
        <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-700 p-8 text-center text-gray-500">
            @if ($filter === 'all')
                No places yet — add some reels to this trip first.
            @else
                Nothing matches "{{ $filter === 'visiting' ? 'Visiting' : 'Must do' }}" right now.
            @endif
        </div>
    @endif

    @foreach ($countries as $country)
        @php
            $countryHasRows = $country['cities']->sum(fn ($city) => $city['places']->count() + $city['tips']->count());
        @endphp
        @continue(! $countryHasRows)

        <h2 class="text-lg font-semibold mt-6">{{ $country['country'] }}</h2>

        @foreach ($country['cities'] as $cityData)
            @continue($cityData['places']->isEmpty() && $cityData['tips']->isEmpty())
            @php $cityCounts = $this->counts($cityData['city']->places); @endphp

            <details open class="mt-3 rounded-xl border border-gray-200 dark:border-gray-700">
                <summary class="cursor-pointer select-none px-4 py-3 font-medium flex items-center justify-between">
                    <span>{{ $cityData['city']->name }}</span>
                    <span class="text-sm text-gray-500 font-normal">
                        {{ $cityCounts['selected'] }} visiting · {{ $cityCounts['mustDo'] }} must-do
                    </span>
                </summary>

                <div class="border-t border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($cityData['places'] as $place)
                        @include('filament.pages.partials.place-row', ['place' => $place, 'categoryIcons' => $categoryIcons])
                    @empty
                        <p class="px-4 py-3 text-sm text-gray-500">Nothing matches this filter in {{ $cityData['city']->name }}.</p>
                    @endforelse
                </div>

                @if ($cityData['tips']->isNotEmpty())
                    <details class="border-t border-gray-200 dark:border-gray-700">
                        <summary class="cursor-pointer select-none px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400">
                            Tips ({{ $cityData['tips']->count() }})
                        </summary>
                        <div class="px-4 pb-3 space-y-2">
                            @foreach ($cityData['tips'] as $tip)
                                <div class="text-sm">
                                    <span class="font-medium">{{ $tip->name }}</span>
                                    @if ($tip->tip)
                                        <span class="text-gray-500">— {{ $tip->tip }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif
            </details>
        @endforeach
    @endforeach

    @if ($unassigned['places']->isNotEmpty() || $unassigned['tips']->isNotEmpty())
        <h2 class="text-lg font-semibold mt-6">Unassigned</h2>

        <div class="mt-3 rounded-xl border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-800">
            @forelse ($unassigned['places'] as $place)
                @include('filament.pages.partials.place-row', ['place' => $place, 'categoryIcons' => $categoryIcons, 'showCitySelect' => true])
            @empty
                <p class="px-4 py-3 text-sm text-gray-500">Nothing matches this filter.</p>
            @endforelse
        </div>

        @if ($unassigned['tips']->isNotEmpty())
            <details class="mt-2">
                <summary class="cursor-pointer select-none text-sm font-medium text-gray-600 dark:text-gray-400">
                    Tips ({{ $unassigned['tips']->count() }})
                </summary>
                <div class="pt-2 space-y-2">
                    @foreach ($unassigned['tips'] as $tip)
                        <div class="text-sm">
                            <span class="font-medium">{{ $tip->name }}</span>
                            @if ($tip->tip)
                                <span class="text-gray-500">— {{ $tip->tip }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
    @endif
</x-filament-panels::page>
