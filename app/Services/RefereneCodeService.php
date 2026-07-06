<?php
namespace App\Services;

use App\Models\Task;
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
        $prefix = "{$orgType}-{$year}";

        $last = where('reference_code', 'like', "{$prefix}%")
            ->max('reference_code');

        $nextNumber = 1;

        if($last){
            $lastNumber = (int) substr($last, strlen($prefix));
            $nextNumber = $lastNumber + 1;
        }

        return $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }
}
