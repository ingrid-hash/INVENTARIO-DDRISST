<?php

use Illuminate\Support\Facades\Schedule;


Schedule::command('inventario:limpiar-importaciones')->dailyAt('02:00');
