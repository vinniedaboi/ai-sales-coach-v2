<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AIController;
use App\Http\Controllers\TemporaryInsightController;
use App\Http\Controllers\CsvFileController;
use App\Http\Controllers\GoogleController;

Route::post('/session/start', [AIController::class, 'startSession']);
Route::post('/chat', [AIController::class, 'chat']);
Route::post('/scorecard', [AIController::class, 'scorecard']);
Route::post('/text-to-speech', [AIController::class, 'textToSpeech']);
Route::post('/speech-to-text', [AIController::class, 'speechToText']);
Route::post('/store-insight-temp', [TemporaryInsightController::class, 'store']);
Route::get('/get-insights-temp', [TemporaryInsightController::class, 'index']);
Route::delete('/store-insight-temp/{id}', [TemporaryInsightController::class, 'destroy']);
Route::put('/store-insight-temp/{id}', [TemporaryInsightController::class, 'update']);
Route::post('/csv/upload', [CsvFileController::class, 'upload']);

// 2. Endpoint to retrieve the list of files (GET /api/csv/list)
Route::get('/csv/list', [CsvFileController::class, 'listFiles']);

// 3. Endpoint to handle processing a selected file (POST /api/csv/process)
// This is the route the frontend will hit after selecting a file from the dropdown
Route::post('/csv/process', [CsvFileController::class, 'processSelectedFile']);

use App\Http\Controllers\AuthController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/me', [AuthController::class, 'me']);

use App\Http\Controllers\OpenAIController;

Route::post('/generate-ai', [OpenAIController::class, 'generate']);
Route::get('/google/redirect', [GoogleController::class, 'redirectToGoogle']);
Route::get('/google/callback', [GoogleController::class, 'handleCallback']);
Route::get('/google/emails', [GoogleController::class, 'getEmails']);
Route::post('/google/disconnect', [GoogleController::class, 'disconnect']);
Route::post('/google/send', [GoogleController::class, 'sendEmail']);