<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VideoController;
use App\Http\Controllers\Api\TranscriptController;
use App\Http\Controllers\Api\SummaryController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\AdminController;

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'version' => '1.0.0',
    ]);
});

// Videos
Route::post('/videos', [VideoController::class, 'store'])->middleware('throttle:10,1');
Route::get('/videos', [VideoController::class, 'index'])->middleware('throttle:60,1');
Route::get('/videos/{id}', [VideoController::class, 'show'])->middleware('throttle:60,1');

// Transcript
Route::get('/videos/{id}/transcript', [TranscriptController::class, 'show'])->middleware('throttle:60,1');
Route::get('/videos/{id}/transcript/download', [TranscriptController::class, 'download'])->middleware('throttle:30,1');
Route::post('/videos/{id}/translate', [TranscriptController::class, 'translate'])->middleware('throttle:5,1');

// Summaries
Route::post('/videos/{id}/summarize', [SummaryController::class, 'store'])->middleware('throttle:5,1');
Route::get('/videos/{id}/summaries', [SummaryController::class, 'index'])->middleware('throttle:60,1');

// Chat
Route::post('/videos/{id}/chat', [ChatController::class, 'store'])->middleware('throttle:10,1');
Route::get('/videos/{id}/chat', [ChatController::class, 'index'])->middleware('throttle:60,1');

// Admin
Route::post('/admin/login', [AdminController::class, 'login'])->middleware('throttle:5,1');
Route::middleware(['admin.auth', 'throttle:30,1'])->group(function () {
    Route::get('/admin/stats', [AdminController::class, 'stats']);
    Route::get('/admin/videos', [AdminController::class, 'videos']);
    Route::post('/admin/youtube-cookies', [AdminController::class, 'uploadYoutubeCookies']);
    Route::post('/admin/youtube-cookies/test', [AdminController::class, 'testYoutubeCookies']);
    Route::delete('/admin/youtube-cookies', [AdminController::class, 'deleteYoutubeCookies']);
    Route::delete('/admin/videos', [AdminController::class, 'purgeAll']);
    Route::delete('/admin/videos/{id}', [AdminController::class, 'deleteVideo']);
    Route::post('/admin/videos/{id}/retry', [AdminController::class, 'retryVideo']);
});
