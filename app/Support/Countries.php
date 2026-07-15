<?php

namespace App\Support;

class Countries
{
    /**
     * Plain list for a datalist, not a hardcoded enum — country isn't a closed set here.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            'Portugal', 'Spain', 'France', 'Italy', 'Greece', 'Croatia', 'Morocco',
            'United Kingdom', 'Ireland', 'Germany', 'Netherlands', 'Belgium',
            'Switzerland', 'Austria', 'Czechia', 'Poland', 'Hungary', 'Turkey',
            'United States', 'Mexico', 'Japan', 'Thailand', 'Vietnam', 'Indonesia',
            'India', 'United Arab Emirates', 'Iceland', 'Norway', 'Sweden', 'Denmark',
        ];
    }
}
