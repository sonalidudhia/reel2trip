<?php

namespace App\Exports;

use App\Models\Place;
use App\Support\PlaceCategories;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GoogleMyMapsCsv
{
    public const BYTE_ORDER_MARK = "\xEF\xBB\xBF";

    public const HEADER = ['Name', 'Latitude', 'Longitude', 'Category', 'Description', 'Address', 'MustDo', 'Reel'];

    private const MUST_DO_LABEL = 'Must do';

    public function __construct(private readonly ExportablePlaces $places) {}

    public function response(): StreamedResponse
    {
        $places = $this->places->get();
        $filename = $this->places->filename('csv');

        return response()->streamDownload(function () use ($places): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, self::BYTE_ORDER_MARK);
            fputcsv($handle, self::HEADER);

            foreach ($places as $place) {
                fputcsv($handle, $this->row($place));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<int, string> */
    public function row(Place $place): array
    {
        return [
            $place->name,
            $place->isEnriched() ? (string) $place->lat : '',
            $place->isEnriched() ? (string) $place->lng : '',
            PlaceCategories::label($place->category),
            (string) $place->description,
            $this->address($place),
            $place->must_do ? self::MUST_DO_LABEL : '',
            (string) $place->reel?->url,
        ];
    }

    private function address(Place $place): string
    {
        return $place->isEnriched()
            ? (string) $place->address
            : $this->geocodableAddress($place);
    }

    private function geocodableAddress(Place $place): string
    {
        return implode(', ', array_filter([
            $place->name,
            $place->tripCity?->name,
            $place->tripCity?->country,
        ]));
    }
}
