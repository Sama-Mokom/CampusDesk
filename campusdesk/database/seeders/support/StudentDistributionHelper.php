<?php

namespace Database\Seeders\Support;

class StudentDistributionHelper
{
    // 500 and 600 are valid level values (extended-duration undergraduates) but are
    // intentionally excluded from seeding distribution — seeded populations represent
    // the standard 4-year cohort only.
    private const LEVELS = ['100', '200', '300', '400'];

    /**
     * Returns an array of $n level assignments, cycling evenly through LEVELS.
     * Exact evenness is not guaranteed when $n is not a multiple of count(LEVELS).
     *
     * Pure, static, stateless — knows nothing about departments or the database.
     *
     * @return list<string>
     */
    public static function distribute(int $n): array
    {
        $levels = [];
        $count  = count(self::LEVELS);

        for ($i = 0; $i < $n; $i++) {
            $levels[] = self::LEVELS[$i % $count];
        }

        return $levels;
    }
}
