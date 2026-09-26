<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Lo corre `schedule:work` (proceso de supervisor, ver
// whatsapp/instalar-servidor.sh). El comando decide solo si toca mandar.
Schedule::command('transpaty:recordatorio-sin-gr')
    ->everyMinute()
    ->withoutOverlapping();
