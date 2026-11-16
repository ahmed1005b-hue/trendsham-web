<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DateTimeZone;

class BusinessHoursStatus
{
    private const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public function forSchedule(?array $schedule, ?CarbonImmutable $now = null): array
    {
        $timezone = $schedule['timezone'] ?? config('app.timezone');
        try {
            new DateTimeZone($timezone);
        } catch (\Throwable) {
            $timezone = config('app.timezone');
        }

        $now = ($now ?? CarbonImmutable::now($timezone))->setTimezone($timezone);
        $days = $schedule['days'] ?? null;
        if (! is_array($days)) {
            return ['state' => 'unknown', 'label' => 'أوقات العمل غير محددة', 'next_change_at' => null];
        }

        $dayKey = strtolower($now->format('D'));
        $minute = ((int) $now->format('H')) * 60 + (int) $now->format('i');
        $todayIntervals = $this->intervals($days[$dayKey] ?? []);
        foreach ($todayIntervals as [$open, $close]) {
            if ($minute >= $open && $minute < $close) {
                $closeAt = $now->startOfDay()->addMinutes($close);
                return ['state' => 'open', 'label' => 'مفتوح الآن', 'next_change_at' => $closeAt->toIso8601String()];
            }
        }

        foreach ($todayIntervals as [$open]) {
            if ($minute < $open) {
                $openAt = $now->startOfDay()->addMinutes($open);
                return ['state' => 'closed', 'label' => 'مغلق الآن', 'next_change_at' => $openAt->toIso8601String()];
            }
        }

        for ($offset = 1; $offset <= 7; $offset++) {
            $date = $now->addDays($offset);
            $key = strtolower($date->format('D'));
            $intervals = $this->intervals($days[$key] ?? []);
            if ($intervals !== []) {
                $openAt = $date->startOfDay()->addMinutes($intervals[0][0]);
                return ['state' => 'closed', 'label' => 'مغلق الآن', 'next_change_at' => $openAt->toIso8601String()];
            }
        }

        return ['state' => 'closed', 'label' => 'مغلق الآن', 'next_change_at' => null];
    }

    private function intervals(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $valid = [];
        foreach ($value as $interval) {
            if (! is_array($interval)
                || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) ($interval['open'] ?? ''))
                || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) ($interval['close'] ?? ''))) {
                continue;
            }
            [$openHour, $openMinute] = array_map('intval', explode(':', $interval['open']));
            [$closeHour, $closeMinute] = array_map('intval', explode(':', $interval['close']));
            $open = $openHour * 60 + $openMinute;
            $close = $closeHour * 60 + $closeMinute;
            if ($close > $open) {
                $valid[] = [$open, $close];
            }
        }

        usort($valid, fn ($left, $right) => $left[0] <=> $right[0]);
        return $valid;
    }
}
