<?php

namespace App\Services;

/**
 * Research-based vaccination scheduling + status engine.
 *
 * Protocols follow established veterinary guidelines (WSAVA 2024, AAHA, AAHA/AAFP):
 *  - Core puppy/kitten series (DHPP/DAPP, FVRCP) continue until ~16 weeks of age,
 *    dosed every 3-4 weeks; dose COUNT is derived from start age + interval, not hard-coded.
 *  - Leptospirosis, Canine Influenza, Lyme: 2 initial doses, 2-4 week interval, then booster.
 *  - Bordetella / FeLV / Chlamydia felis: product/risk dependent (2 doses typical, 3-4 wk).
 *  - Rabies: 1 initial dose; booster interval is product-label + local-regulation dependent
 *    (default 365 days, flagged requires_vet_confirmation).
 *
 * The engine TRACKS and CALCULATES only. It never diagnoses, prescribes, restarts a series,
 * or overrides a veterinarian. Missed intervals / unknown history => "Veterinary Review Required".
 */
class VaccinationEngine
{
    public const STATUS_NOT_STARTED = 'NOT_STARTED';
    public const STATUS_UPCOMING = 'UPCOMING';
    public const STATUS_DUE = 'DUE';
    public const STATUS_OVERDUE = 'OVERDUE';
    public const STATUS_SERIES_IN_PROGRESS = 'SERIES_IN_PROGRESS';
    public const STATUS_SERIES_COMPLETE = 'SERIES_COMPLETE';
    public const STATUS_BOOSTER_DUE = 'BOOSTER_DUE';
    public const STATUS_TOO_EARLY = 'TOO_EARLY';

    private const WEEK_DAYS = 7;

    public static function protocolFor(string $species, string $vaccine): ?array
    {
        return VaccinationProtocols::protocolFor($species, $vaccine);
    }

    /** Protocols relevant to a species (for UI reference + selects). */
    public static function protocolsForSpecies(string $species): array
    {
        return VaccinationProtocols::protocolsForSpecies($species);
    }

    /** Parse free-text age_estimate ("3 months", "12 weeks", "1 yr") into weeks. */
    public static function ageEstimateToWeeks(?string $ageEstimate): ?int
    {
        if (!$ageEstimate) return null;
        $s = strtolower(trim($ageEstimate));
        if (!preg_match('/(\d+(?:\.\d+)?)\s*(week|wk|w|month|mo|m|year|yr|y|day|d)s?/i', $s, $m)) {
            // bare number with no unit: treat as weeks if small, months if large
            if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*$/', $s, $m2)) {
                $n = (float) $m2[1];
                return $n <= 20 ? (int) round($n) : (int) round($n * self::WEEK_DAYS * 4.345);
            }
            return null;
        }
        $n = (float) $m[1];
        $unit = substr($m[2], 0, 2);
        return match (true) {
            str_starts_with($unit, 'we') || str_starts_with($unit, 'wk') || $unit === 'w' => (int) round($n),
            str_starts_with($unit, 'mo') || $unit === 'm' => (int) round($n * 4.345),
            str_starts_with($unit, 'ye') || str_starts_with($unit, 'yr') || $unit === 'y' => (int) round($n * 52.14),
            str_starts_with($unit, 'da') || $unit === 'd' => (int) round($n / self::WEEK_DAYS),
            default => null,
        };
    }

    /**
     * Resolve the animal's age in weeks, preferring an exact birth date when present
     * (so the age stays accurate as time passes) and falling back to the free-text
     * age_estimate otherwise.
     */
    public static function ageWeeks(?string $ageEstimate, ?string $birthDate = null, ?string $today = null): ?int
    {
        $today = $today ?? date('Y-m-d');
        if ($birthDate) {
            $b = \DateTime::createFromFormat('Y-m-d', substr((string) $birthDate, 0, 10));
            $t = \DateTime::createFromFormat('Y-m-d', $today);
            if ($b && $t && $b <= $t) {
                $weeks = (int) round(($t->getTimestamp() - $b->getTimestamp()) / (86400 * 7));
                return max(0, $weeks);
            }
        }
        return self::ageEstimateToWeeks($ageEstimate);
    }

    /**
     * Compute the next due window and status for one vaccine, given the animal's
     * age (weeks), species, and sorted vaccination records.
     *
     * @param array|null $protocol Protocol array from protocolFor()
     * @param array      $records  Vaccination records (each with administered_date, dose_number)
     * @param int|null   $ageWeeks Animal age in weeks
     * @param string     $today    ISO date (Y-m-d)
     *
     * @return array{status, dose_number, next_due_window, flags, series_complete, notes}
     */
    public static function evaluate(?array $protocol, array $records, ?int $ageWeeks, string $today): array
    {
        $todayTs = strtotime($today);
        // Normalize the administration date (may arrive as administered_date or dateGiven)
        // and capture any explicit user-set status (e.g. "Completed" chosen in the UI).
        $normalized = array_map(function ($r) {
            $date = $r['administered_date'] ?? $r['dateGiven'] ?? null;
            return [
                'vaccine' => $r['vaccine'] ?? null,
                'administered_date' => $date,
                'status' => $r['status'] ?? null,
            ];
        }, $records);
        $sorted = array_values(array_filter($normalized, fn($r) => !empty($r['administered_date'])));
        usort($sorted, fn($a, $b) => strcmp($a['administered_date'], $b['administered_date']));
        $completed = count($sorted);
        // If the user explicitly marked a recorded dose as completed, treat it as administered
        // even when the engine would otherwise consider the series not started.
        $explicitComplete = $completed > 0 && array_reduce($normalized, function ($carry, $r) {
            $s = strtolower(trim((string) ($r['status'] ?? '')));
            return $carry || $s === 'completed' || $s === 'complete' || str_contains($s, 'complete');
        }, false);

        if (!$protocol) {
            // Unknown vaccine: track but flag for vet review.
            if ($completed === 0 && !$explicitComplete) {
                return self::result(self::STATUS_NOT_STARTED, 0, null, ['Veterinary Review Required: unknown protocol']);
            }
            // A date is recorded, so the dose was administered. Treat it as
            // completed (not "Not Started") and surface a sensible booster window
            // without guessing a protocol-specific schedule.
            $last = end($sorted);
            $next = self::addDays($last['administered_date'], 365);
            return self::result(
                $todayTs > strtotime($next) ? self::STATUS_OVERDUE : self::STATUS_DUE,
                $completed,
                ['recommended' => $next],
                ['Veterinary Review Required: unknown protocol'],
                true
            );
        }

        [$minI, $recI, $maxI] = $protocol['interval'];
        $seriesCompleteAge = $protocol['series_completion_age_weeks'];
        $boosterDays = $protocol['booster_interval_days'];

        // Age-appropriateness flag: persisted across all return paths so a vaccine
        // given before the minimum recommended age is always detected — even when a
        // dose was manually recorded (e.g. marked "Completed" by an admin).
        $ageFlag = ($ageWeeks !== null && $protocol['minimum_age_weeks'] && $ageWeeks < $protocol['minimum_age_weeks'])
            ? "Not age-appropriate yet: minimum age is {$protocol['minimum_age_weeks']} weeks (animal is ~{$ageWeeks} weeks)"
            : null;

        if ($completed === 0 && !$explicitComplete) {
            // Animal is younger than the vaccine's minimum recommended age and has
            // not yet received a dose: the vaccine is not yet appropriate/allowed.
            if ($ageFlag) {
                return self::result(self::STATUS_TOO_EARLY, 0, null, [$ageFlag], false, $protocol['minimum_age_weeks']);
            }
            return self::result(self::STATUS_NOT_STARTED, 0, null, $protocol['requires_vet_confirmation'] ? ['Veterinary Review Required'] : []);
        }

        $last = end($sorted);
        $lastTs = strtotime($last['administered_date']);

        // Series completion check: age-based for core puppy/kitten series.
        $seriesComplete = true;
        if ($ageWeeks !== null && $seriesCompleteAge && $ageWeeks < $seriesCompleteAge) {
            $seriesComplete = false;
        }
        // For fixed/2-dose protocols, require at least 2 primary doses.
        if ($seriesComplete && $completed < 2 && $protocol['category'] !== 'core') {
            $seriesComplete = false;
        }

        if (!$seriesComplete) {
            // Next primary dose window from last dose.
            $win = self::window($last['administered_date'], $minI, $recI, $maxI);
            $status = self::STATUS_SERIES_IN_PROGRESS;
            if ($todayTs > strtotime($win['max'])) {
                $status = self::STATUS_OVERDUE;
            } elseif ($todayTs >= strtotime($win['min'])) {
                $status = self::STATUS_DUE;
            } else {
                $status = self::STATUS_UPCOMING;
            }
            $flags = [];
            // Missed-interval detection between last two doses.
            if ($completed >= 2) {
                $prev = $sorted[$completed - 2];
                $gap = (strtotime($last['administered_date']) - strtotime($prev['administered_date'])) / 86400;
                if ($gap < $minI || $gap > $maxI) {
                    $flags[] = 'INTERVAL_OUTSIDE_RECOMMENDED_RANGE';
                    $flags[] = 'Veterinary Review Required';
                }
            }
            if ($ageFlag) {
                $flags[] = $ageFlag;
                $status = self::STATUS_TOO_EARLY;
            }
            return self::result($status, $completed, $win, $flags, false);
        }

        // Series complete -> booster logic.
        $boosterDue = self::addDays($last['administered_date'], $boosterDays);
        $boosterTs = strtotime($boosterDue);
        if ($todayTs >= $boosterTs) {
            $status = self::STATUS_BOOSTER_DUE;
        } elseif ($todayTs >= strtotime(self::addDays($boosterDue, -14))) {
            $status = self::STATUS_DUE; // within 2 weeks of booster due
        } else {
            $status = self::STATUS_UPCOMING;
        }
        $flags = $protocol['requires_vet_confirmation'] ? ['Veterinary Review Required: product/regulation dependent booster'] : [];
        if ($ageFlag) {
            $flags[] = $ageFlag;
            $status = self::STATUS_TOO_EARLY;
        }
        return self::result($status, $completed, ['recommended' => $boosterDue], $flags, true);
    }

    private static function result(string $status, int $dose, ?array $window, array $flags, bool $seriesComplete = false, ?int $minimumAgeWeeks = null): array
    {
        return [
            'status' => $status,
            'dose_number' => $dose,
            'next_due_window' => $window,
            'flags' => $flags,
            'series_complete' => $seriesComplete,
            'minimum_age_weeks' => $minimumAgeWeeks,
        ];
    }

    private static function addDays(string $date, int $days): string
    {
        return date('Y-m-d', strtotime($date) + $days * 86400);
    }

    private static function window(string $from, int $min, int $rec, int $max): array
    {
        return [
            'min' => self::addDays($from, $min),
            'recommended' => self::addDays($from, $rec),
            'max' => self::addDays($from, $max),
        ];
    }
}
