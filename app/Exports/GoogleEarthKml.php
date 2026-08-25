<?php

namespace App\Exports;

use App\Models\Place;
use Symfony\Component\HttpFoundation\StreamedResponse;
use XMLWriter;

class GoogleEarthKml
{
    private const KML_NAMESPACE = 'http://www.opengis.net/kml/2.2';

    private const UNASSIGNED_FOLDER = 'Unassigned';

    public function __construct(private readonly ExportablePlaces $places) {}

    public function response(): StreamedResponse
    {
        $document = $this->document();
        $filename = $this->places->filename('kml');

        return response()->streamDownload(
            fn () => print ($document),
            $filename,
            ['Content-Type' => 'application/vnd.google-earth.kml+xml'],
        );
    }

    public function document(): string
    {
        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('kml');
        $writer->writeAttribute('xmlns', self::KML_NAMESPACE);
        $writer->startElement('Document');

        foreach ($this->mappablePlacesByCity() as $cityName => $places) {
            $this->writeFolder($writer, (string) $cityName, $places);
        }

        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }

    /** @return array<string, array<int, Place>> */
    private function mappablePlacesByCity(): array
    {
        $byCity = [];

        foreach ($this->places->get() as $place) {
            if (! $place->isEnriched()) {
                continue;
            }

            $byCity[$this->folderName($place)][] = $place;
        }

        return $byCity;
    }

    private function folderName(Place $place): string
    {
        $city = $place->tripCity;

        return $city ? $city->name : self::UNASSIGNED_FOLDER;
    }

    /** @param  array<int, Place>  $places */
    private function writeFolder(XMLWriter $writer, string $cityName, array $places): void
    {
        $writer->startElement('Folder');
        $writer->writeElement('name', $cityName);

        foreach ($places as $place) {
            $this->writePlacemark($writer, $place);
        }

        $writer->endElement();
    }

    private function writePlacemark(XMLWriter $writer, Place $place): void
    {
        $writer->startElement('Placemark');
        $writer->writeElement('name', $place->name);
        $writer->writeElement('description', $this->description($place));
        $writer->startElement('Point');
        $writer->writeElement('coordinates', "{$place->lng},{$place->lat},0");
        $writer->endElement();
        $writer->endElement();
    }

    private function description(Place $place): string
    {
        return collect([$place->description, $place->tip, $place->reel?->url])
            ->filter()
            ->implode("\n\n");
    }
}
