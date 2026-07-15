<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>reel2trip</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @if($autoRefresh)
    <meta http-equiv="refresh" content="10">
    @endif
</head>
<body class="bg-stone-100 text-stone-900 min-h-screen">
<div class="max-w-5xl mx-auto p-6 space-y-8">

    <header class="flex items-baseline justify-between">
        <h1 class="text-2xl font-bold">reel2trip 🇪🇸🇵🇹</h1>
        @if($autoRefresh)
            <span class="text-xs text-amber-600 animate-pulse">processing… auto-refreshing</span>
        @endif
    </header>

    @if(session('status'))
        <div class="bg-emerald-100 border border-emerald-300 text-emerald-800 rounded-lg px-4 py-2 text-sm">
            {{ session('status') }}
        </div>
    @endif

    {{-- Paste reels --}}
    <section class="bg-white rounded-xl shadow-sm p-5">
        <h2 class="font-semibold mb-3">Add reels</h2>
        <form method="POST" action="{{ route('reels.store') }}" class="space-y-3">
            @csrf
            <textarea name="urls" rows="4" required
                placeholder="Paste Instagram reel URLs, one per line"
                class="w-full border border-stone-300 rounded-lg p-3 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-amber-400"></textarea>
            <button class="bg-stone-900 text-white text-sm font-medium rounded-lg px-4 py-2 hover:bg-stone-700">
                Queue for processing
            </button>
        </form>
    </section>

    {{-- Reel pipeline status --}}
    <section class="bg-white rounded-xl shadow-sm p-5">
        <h2 class="font-semibold mb-3">Reels ({{ $reels->count() }})</h2>
        <div class="divide-y divide-stone-100">
            @forelse($reels as $reel)
                <div class="py-2 flex items-center gap-3 text-sm">
                    <span @class([
                        'px-2 py-0.5 rounded-full text-xs font-medium shrink-0',
                        'bg-stone-200 text-stone-600'    => $reel->status === 'pending',
                        'bg-amber-100 text-amber-700'    => in_array($reel->status, ['downloading','transcribing','extracting']),
                        'bg-emerald-100 text-emerald-700'=> $reel->status === 'done',
                        'bg-red-100 text-red-700'        => $reel->status === 'failed',
                    ])>{{ $reel->status }}</span>
                    <a href="{{ $reel->url }}" target="_blank" class="text-stone-500 hover:text-stone-900 truncate">
                        {{ $reel->shortcode ?? $reel->url }}
                    </a>
                    <span class="ml-auto text-stone-400 shrink-0">{{ $reel->places_count }} places</span>
                    @if($reel->status === 'failed')
                        <span class="text-xs text-red-500 truncate max-w-xs" title="{{ $reel->error }}">{{ $reel->error }}</span>
                    @endif
                </div>
            @empty
                <p class="text-sm text-stone-400 py-2">No reels yet — paste some above.</p>
            @endforelse
        </div>
    </section>

    {{-- Places grouped by city --}}
    @foreach($cities as $city)
        <section class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-semibold mb-1">{{ $city->name }}, {{ $city->country }}
                <span class="text-stone-400 font-normal text-sm">— {{ $city->days }} days · {{ $city->places->count() }} places</span>
            </h2>
            <div class="mt-3 grid gap-2">
                @foreach($city->places->groupBy('category') as $category => $places)
                    <div>
                        <h3 class="text-xs uppercase tracking-wide text-stone-400 mb-1">{{ $category }}</h3>
                        @foreach($places as $place)
                            <div class="flex items-start gap-2 text-sm py-1">
                                <span>{{ ['food' => '🍽️', 'sight' => '🏛️', 'viewpoint' => '🌅', 'activity' => '🎟️', 'area' => '🗺️', 'tip' => '💡'][$place->category] ?? '📍' }}</span>
                                <div>
                                    <span class="font-medium">{{ $place->name }}</span>
                                    @if($place->rating) <span class="text-amber-600">★ {{ $place->rating }}</span> @endif
                                    @if($place->priceLabel()) <span class="text-stone-500">{{ $place->priceLabel() }}</span> @endif
                                    @if(!$place->isEnriched() && $place->category !== 'tip')
                                        <span class="text-xs text-red-400">(not found on Maps)</span>
                                    @endif
                                    <p class="text-stone-500">{{ $place->description }}</p>
                                    @if($place->tip)<p class="text-xs text-amber-700">💡 {{ $place->tip }}</p>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach

    {{-- Unassigned places --}}
    @if($unassigned->isNotEmpty())
        <section class="bg-white rounded-xl shadow-sm p-5 border border-amber-200">
            <h2 class="font-semibold mb-3">⚠️ Unassigned to a city ({{ $unassigned->count() }})</h2>
            @foreach($unassigned as $place)
                <div class="text-sm py-1">
                    <span class="font-medium">{{ $place->name }}</span>
                    <span class="text-stone-400">— reel said: {{ $place->city_guess ?? 'no city mentioned' }}</span>
                </div>
            @endforeach
        </section>
    @endif

</div>
</body>
</html>
