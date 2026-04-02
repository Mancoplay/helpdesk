<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;

class ReviewRangeService
{
    /**
     * @return array{0:string,1:string,2:string,3:Carbon,4:Carbon}
     */
    public function resolveFromRequest(Request $request): array
    {
        $now = Carbon::now();
        $period = (string) $request->get('period', 'month');
        $allowedPeriods = ['week', 'month', 'previous_month', 'year', 'custom'];

        if (!in_array($period, $allowedPeriods, true)) {
            $period = 'month';
        }

        $fromInput = (string) $request->get('from', '');
        $toInput = (string) $request->get('to', '');

        if ($period === 'week') {
            $fromDate = $now->copy()->startOfWeek();
            $toDate = $now->copy()->endOfWeek();
        } elseif ($period === 'previous_month') {
            $fromDate = $now->copy()->subMonthNoOverflow()->startOfMonth();
            $toDate = $now->copy()->subMonthNoOverflow()->endOfMonth();
        } elseif ($period === 'year') {
            $fromDate = $now->copy()->startOfYear();
            $toDate = $now->copy()->endOfYear();
        } elseif ($period === 'custom') {
            $fromDate = $this->safeParseDate($fromInput) ?? $now->copy()->startOfMonth();
            $toDate = $this->safeParseDate($toInput) ?? $now->copy()->endOfMonth();

            if ($fromDate->gt($toDate)) {
                [$fromDate, $toDate] = [$toDate, $fromDate];
            }
        } else {
            $fromDate = $now->copy()->startOfMonth();
            $toDate = $now->copy()->endOfMonth();
            $period = 'month';
        }

        if ($period !== 'custom') {
            $fromInput = $fromDate->toDateString();
            $toInput = $toDate->toDateString();
        }

        return [$period, $fromInput, $toInput, $fromDate, $toDate];
    }

    private function safeParseDate(string $value): ?Carbon
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}