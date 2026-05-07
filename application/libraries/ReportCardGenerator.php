<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ReportCardGenerator
{
    public function calculate_score($attendance = [], $calls = [], $leads = [])
    {
        // Safe defaults if data missing
        $workSeconds     = $attendance['work_seconds'] ?? 0;
        $callSeconds     = $attendance['call_seconds'] ?? 0;
        $breakSeconds    = $attendance['break_seconds'] ?? 0;
        $idleSeconds     = $attendance['idle_seconds'] ?? 0;

        $totalCalls      = $calls['total_calls'] ?? 0;
        $successfulCalls = $calls['successful_calls'] ?? 0;

        $totalLeads      = $leads['total_leads'] ?? 0;
        $convertedLeads  = $leads['converted_leads'] ?? 0;

        // Attendance score (8 hours/day, 5 days)
        $attendanceScore = min(100, ($workSeconds / (8 * 3600 * 5)) * 100);

        // Conversion rate
        $conversionRate = $totalLeads > 0 ? ($convertedLeads / $totalLeads) * 100 : 0;

        // Call success rate
        $callSuccessRate = $totalCalls > 0 ? ($successfulCalls / $totalCalls) * 100 : 0;

        // Weighted score
        return round(
            ($attendanceScore * 0.4) +
            ($conversionRate * 0.35) +
            ($callSuccessRate * 0.25),
            2
        );
    }

    public function grade($score)
    {
        return match (true) {
            $score >= 85 => 'A',
            $score >= 70 => 'B',
            $score >= 55 => 'C',
            default => 'D'
        };
    }
}
