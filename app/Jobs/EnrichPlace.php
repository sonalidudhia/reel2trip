<?php

namespace App\Jobs;

use App\Models\Place;
use App\Services\GooglePlacesEnricher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnrichPlace implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public Place $place)
    {
    }

    public function handle(GooglePlacesEnricher $enricher): void
    {
        $enricher->enrich($this->place);
    }
}
