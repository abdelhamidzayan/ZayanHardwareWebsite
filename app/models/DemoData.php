<?php
declare(strict_types=1);

namespace App\Models;

/**
 * TEMPORARY sample data taken from the Figma Home screen.
 * Delete this file when Issue and Category models read from MySQL.
 */
final class DemoData
{
    public static function categories(): array
    {
        return [
            ['name' => 'Power',      'slug' => 'power'],
            ['name' => 'Charging',   'slug' => 'charging'],
            ['name' => 'Network',    'slug' => 'network'],
            ['name' => 'Display',    'slug' => 'display'],
            ['name' => 'Schematics', 'slug' => 'schematics'],
        ];
    }

    public static function issues(): array
    {
        return [
            [
                'id'       => 1,
                'author'   => 'Alex Morgan',
                'initials' => 'AM',
                'expert'   => true,
                'role'     => 'Board-level technician',
                'time'     => 'منذ 12 دقيقة',
                'device'   => 'iPhone 13 Pro',
                'title'    => 'VDD_MAIN Short',
                'excerpt'  => '0.02Ω to ground. Isolate the rail before lifting the PMIC.',
                'category' => 'Power',
                'solved'   => false,
                'comments' => 24,
                'audio'    => '0:03',
                'audio_src' => '/assets/demo/sample.wav',
            ],
            [
                'id'       => 2,
                'author'   => 'Priya Shah',
                'initials' => 'PS',
                'expert'   => true,
                'role'     => 'Board-level technician',
                'time'     => 'منذ 38 دقيقة',
                'device'   => 'Samsung S23 Ultra',
                'title'    => 'No charge after water damage',
                'excerpt'  => 'Corrosion at the USB-C sub-board. Here’s what to check first.',
                'category' => 'Charging',
                'solved'   => true,
                'comments' => 18,
                'audio'    => '0:03',
                'audio_src' => '/assets/demo/sample.wav',
            ],
        ];
    }
}
