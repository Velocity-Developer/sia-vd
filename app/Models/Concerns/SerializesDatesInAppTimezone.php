<?php

namespace App\Models\Concerns;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Kirim tanggal ke frontend dalam zona waktu aplikasi beserta offset-nya (mis. 2026-09-22T23:59:00+07:00).
 *
 * Bawaan Laravel mengubahnya ke UTC (…Z). Frontend banyak memotong string tanggal apa adanya
 * (tampilan tenggat, isian form), sehingga format UTC membuat jam dan tanggal bergeser.
 */
trait SerializesDatesInAppTimezone
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        return Carbon::instance($date)->setTimezone(config('app.timezone'))->toIso8601String();
    }
}
