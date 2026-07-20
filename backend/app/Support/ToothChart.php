<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Static FDI (ISO 3950) tooth-numbering reference. Deliberately not a database table or a model —
 * this is fixed, non-editable reference data (52 codes total), the same "no DB table for a small
 * fixed value set" principle docs/database-design.md already states for enums, just implemented as
 * a plain helper here since 52 backed-enum cases would be unwieldy. Mirrored, hand-written (not
 * code-generated — same accepted BE/FE duplication as every other type in this project) by
 * frontend/src/lib/teeth.ts.
 *
 * FDI code = quadrant digit + position digit:
 *   Quadrants 1-4: permanent dentition (1 upper right, 2 upper left, 3 lower left, 4 lower right),
 *     positions 1-8 (1-3 anterior: central/lateral incisor, canine; 4-8 posterior: premolars, molars).
 *   Quadrants 5-8: primary/deciduous dentition (5 upper right, 6 upper left, 7 lower left, 8 lower
 *     right), positions 1-5 (1-3 anterior; 4-5 posterior — primary dentition has no premolars).
 *
 * See docs/modules/dental-chart-design-draft.md §6-§7 for the full reasoning.
 */
final class ToothChart
{
    private const QUADRANT_NAMES = [
        1 => 'Upper Right', 2 => 'Upper Left', 3 => 'Lower Left', 4 => 'Lower Right',
        5 => 'Upper Right', 6 => 'Upper Left', 7 => 'Lower Left', 8 => 'Lower Right',
    ];

    private const PERMANENT_POSITION_NAMES = [
        1 => 'Central Incisor',
        2 => 'Lateral Incisor',
        3 => 'Canine',
        4 => 'First Premolar',
        5 => 'Second Premolar',
        6 => 'First Molar',
        7 => 'Second Molar',
        8 => 'Third Molar',
    ];

    private const PRIMARY_POSITION_NAMES = [
        1 => 'Central Incisor',
        2 => 'Lateral Incisor',
        3 => 'Canine',
        4 => 'First Molar',
        5 => 'Second Molar',
    ];

    /**
     * All 52 valid FDI codes: 32 permanent (11-18, 21-28, 31-38, 41-48) then 20 primary
     * (51-55, 61-65, 71-75, 81-85).
     *
     * @return list<string>
     */
    public static function allCodes(): array
    {
        $codes = [];

        foreach ([1, 2, 3, 4] as $quadrant) {
            for ($position = 1; $position <= 8; $position++) {
                $codes[] = "{$quadrant}{$position}";
            }
        }

        foreach ([5, 6, 7, 8] as $quadrant) {
            for ($position = 1; $position <= 5; $position++) {
                $codes[] = "{$quadrant}{$position}";
            }
        }

        return $codes;
    }

    public static function isValidCode(string $code): bool
    {
        return in_array($code, self::allCodes(), true);
    }

    /**
     * True for permanent dentition (quadrants 1-4), false for primary/deciduous (quadrants 5-8).
     */
    public static function isPermanent(string $code): bool
    {
        return self::quadrant($code) <= 4;
    }

    /**
     * True for anterior teeth (incisors/canines — positions 1-3 in either dentition), false for
     * posterior (premolars/molars). Drives the Occlusal-vs-Incisal surface validation rule
     * (design draft §4): `O` only valid on a posterior tooth, `I` only on an anterior one.
     */
    public static function isAnterior(string $code): bool
    {
        return self::position($code) <= 3;
    }

    /**
     * Human-readable tooth name, e.g. "Upper Right First Molar" (permanent) or
     * "Upper Right Second Molar (Primary)".
     */
    public static function displayName(string $code): string
    {
        if (! self::isValidCode($code)) {
            throw new InvalidArgumentException("Invalid FDI tooth code: {$code}");
        }

        $quadrant = self::quadrant($code);
        $position = self::position($code);
        $permanent = self::isPermanent($code);

        $positionName = $permanent
            ? self::PERMANENT_POSITION_NAMES[$position]
            : self::PRIMARY_POSITION_NAMES[$position];

        $name = self::QUADRANT_NAMES[$quadrant].' '.$positionName;

        return $permanent ? $name : "{$name} (Primary)";
    }

    private static function quadrant(string $code): int
    {
        return (int) substr($code, 0, 1);
    }

    private static function position(string $code): int
    {
        return (int) substr($code, 1, 1);
    }
}
