<?php
namespace App\Services;

use App\Models\User;

class RefereneCodeService
{
    /**
     * Generate the next unique reference code for a task.
     *
     * Format: {TYPE}-{YEAR}-{NUMBER}
     * Example: CFG-2026-001
     *
     * The number is based on the MAX existing number for the
     * given type and year, incremented by 1.
     */

    public function generate(string $orgType, int $year):string
    {
        return 'cou';
    }
}
