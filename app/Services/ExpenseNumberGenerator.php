<?php

namespace App\Services;

use App\Models\Expense;
use Carbon\CarbonInterface;

class ExpenseNumberGenerator
{
    public function generate(CarbonInterface $date): string
    {
        $prefix = 'EXP-'.$date->format('Y');
        $last = Expense::query()
            ->where('expense_number', 'like', $prefix.'-%')
            ->orderByDesc('id')
            ->value('expense_number');

        $next = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        do {
            $number = $prefix.'-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            $next++;
        } while (Expense::where('expense_number', $number)->exists());

        return $number;
    }
}
