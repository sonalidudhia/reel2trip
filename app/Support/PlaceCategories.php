<?php

namespace App\Support;

/**
 * The six categories the extractor emits, in the order they're shown when
 * planning a city: what you go see first, then where you eat around it.
 */
class PlaceCategories
{
    public const LABELS = [
        'sight' => 'Attractions',
        'food' => 'Food & Drink',
        'viewpoint' => 'Viewpoints',
        'activity' => 'Activities',
        'area' => 'Areas & Neighbourhoods',
        'tip' => 'Tips',
    ];

    public const ICONS = [
        'sight' => 'heroicon-o-building-library',
        'food' => 'heroicon-o-cake',
        'viewpoint' => 'heroicon-o-camera',
        'activity' => 'heroicon-o-ticket',
        'area' => 'heroicon-o-map',
        'tip' => 'heroicon-o-light-bulb',
    ];

    /** Filament badge colours, so a category reads the same in every table. */
    public const COLORS = [
        'sight' => 'info',
        'food' => 'warning',
        'viewpoint' => 'success',
        'activity' => 'primary',
        'area' => 'gray',
        'tip' => 'danger',
    ];

    /** @return array<string, string> value => label, for selects and filters */
    public static function options(): array
    {
        return self::LABELS;
    }

    public static function color(?string $category): string
    {
        return self::COLORS[$category] ?? 'gray';
    }

    public static function label(?string $category): string
    {
        return self::LABELS[$category] ?? ucfirst((string) $category);
    }

    public static function icon(?string $category): string
    {
        return self::ICONS[$category] ?? 'heroicon-o-map-pin';
    }

    /** Sort key for a category, so unknown values fall to the bottom. */
    public static function position(?string $category): int
    {
        $index = array_search($category, array_keys(self::LABELS), strict: true);

        return $index === false ? PHP_INT_MAX : $index;
    }
}
