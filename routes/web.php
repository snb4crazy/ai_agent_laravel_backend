<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home', [
        'appName' => config('app.name'),
        'appVersion' => app()->version(),
    ]);
});

require __DIR__.'/auth.php';
