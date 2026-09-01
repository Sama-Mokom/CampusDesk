<?php

namespace Database\Seeders\Support;

use InvalidArgumentException;

class FacultyMatriculeMapper
{
    // Not algorithmically derivable from faculty codes (FET → FE drops the last
    // letter; FED → ED drops the first letter — no consistent rule). Hard-coded
    // and throws loudly on any unmapped code so the problem surfaces immediately
    // rather than silently producing wrong matricules.
    private const MAP = [
        'ASTI' => 'AS',
        'COT'  => 'CT',
        'FAVM' => 'AV',
        'FA'   => 'AR',
        'FED'  => 'ED',
        'FET'  => 'FE',
        'FHS'  => 'HS',
        'FLPS' => 'LP',
        'FS'   => 'SC',
        'FSMS' => 'SM',
        // Synthetic Records Office faculty — never appears on a real student matricule.
        'RO'   => 'RO',
    ];

    /**
     * Return the two-letter matricule prefix for a faculty code.
     *
     * @throws InvalidArgumentException  If the code is not in the map.
     */
    public static function getPrefix(string $facultyCode): string
    {
        if (!isset(self::MAP[$facultyCode])) {
            throw new InvalidArgumentException(
                "Unmapped faculty code '{$facultyCode}'. Matricule prefixes cannot be derived " .
                "algorithmically. Add '{$facultyCode}' to FacultyMatriculeMapper::MAP."
            );
        }

        return self::MAP[$facultyCode];
    }
}
