@php $showCitySelect ??= false; @endphp

<div class="flex items-center gap-3 px-4 py-3">
    <button
        type="button"
        wire:click="toggleSelected({{ $place->id }})"
        @class([
            'h-5 w-5 rounded border flex items-center justify-center shrink-0',
            'bg-primary-600 border-primary-600 text-white' => $place->selected,
            'border-gray-300 dark:border-gray-600' => ! $place->selected,
        ])
        title="Visiting"
    >
        @if ($place->selected)
            <span class="text-xs leading-none">✓</span>
        @endif
    </button>

    <button
        type="button"
        wire:click="toggleMustDo({{ $place->id }})"
        @disabled(! $place->selected)
        @class([
            'shrink-0 text-lg leading-none',
            'text-amber-500' => $place->must_do,
            'text-gray-300 dark:text-gray-700' => ! $place->must_do,
            'opacity-40 cursor-not-allowed' => ! $place->selected,
        ])
        title="Must do"
    >
        ★
    </button>

    <span class="shrink-0">{{ $categoryIcons[$place->category] ?? '' }}</span>

    <span class="flex-1 truncate">{{ $place->name }}</span>

    @if ($place->rating)
        <span class="text-sm text-gray-500 shrink-0">⭐ {{ $place->rating }}</span>
    @endif

    @if ($showCitySelect)
        <select
            wire:change="assignToCity({{ $place->id }}, $event.target.value || null)"
            class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800"
        >
            <option value="">Assign to city…</option>
            @foreach ($this->trip->tripCities as $city)
                <option value="{{ $city->id }}">{{ $city->name }}</option>
            @endforeach
        </select>
    @endif

    @if ($place->reel?->url)
        <a href="{{ $place->reel->url }}" target="_blank" rel="noopener" class="text-gray-400 hover:text-gray-600 shrink-0 text-sm" title="Open source reel">
            ↗
        </a>
    @endif
</div>
