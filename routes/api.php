<?php

use App\Http\Controllers\TelnyxController;
use App\Http\Controllers\TelnyxWebhooksController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::post('/make-call', [TelnyxController::class, 'makeCall']);
Route::post('/webhook/{uuid}', [TelnyxWebhooksController::class, 'callControlWebhook']);

Route::post('/start-call-recording', [TelnyxController::class, 'startCallRecording']);
Route::post('/end-call-recording', [TelnyxController::class, 'endCallRecording']);





