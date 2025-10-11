<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AIController;

Route::post('/session/start', [AIController::class, 'startSession']);
Route::post('/chat', [AIController::class, 'chat']);
Route::post('/scorecard', [AIController::class, 'scorecard']);
Route::post('/text-to-speech', [AIController::class, 'textToSpeech']);
Route::post('/speech-to-text', [AIController::class, 'speechToText']);