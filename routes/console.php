<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\ReleaseExpiredReservationsJob;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// تشغيل الـ Job كل دقيقة
Schedule::job(new ReleaseExpiredReservationsJob)->everyMinute();