<?php

use App\Http\Controllers\TelnyxController;
use Illuminate\Support\Facades\Route;

Route::get('{any}', function () {
    return view('welcome');
})->where('any', '.*');


