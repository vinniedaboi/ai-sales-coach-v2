<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AIController;
use App\Http\Controllers\TemporaryInsightController;

Route::post('/session/start', [AIController::class, 'startSession']);
Route::post('/chat', [AIController::class, 'chat']);
Route::post('/scorecard', [AIController::class, 'scorecard']);
Route::post('/text-to-speech', [AIController::class, 'textToSpeech']);
Route::post('/speech-to-text', [AIController::class, 'speechToText']);
Route::post('/store-insight-temp', [TemporaryInsightController::class, 'store']);
Route::get('/get-insights-temp', [TemporaryInsightController::class, 'index']);
Route::delete('/store-insight-temp/{id}', [TemporaryInsightController::class, 'destroy']);
Route::put('/store-insight-temp/{id}', [TemporaryInsightController::class, 'update']);