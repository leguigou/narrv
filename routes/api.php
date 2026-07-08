<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VideoController;
use App\Http\Controllers\Api\TranscriptController;
use App\Http\Controllers\Api\SummaryController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\AdminController;

// Health check
Route::get('/api/health', function () {
    return response()->json([
        'status' => 'ok',
        'version' => '1.0.0',
    ]);
});

// Videos
Route::post('/api/videos', [VideoController::class, 'store']);
Route::get('/api/videos', [VideoController::class, 'index']);
Route::get('/api/videos/{id}', [VideoController::class, 'show']);

// Transcript
Route::get('/api/videos/{id}/transcript', [TranscriptController::class, 'show']);
Route::get('/api/videos/{id}/transcript/download', [TranscriptController::class, 'download']);
Route::post('/api/videos/{id}/translate', [TranscriptController::class, 'translate']);

// Summaries
Route::post('/api/videos/{id}/summarize', [SummaryController::class, 'store']);
Route::get('/api/videos/{id}/summaries', [SummaryController::class, 'index']);

// Chat
Route::post('/api/videos/{id}/chat', [ChatController::class, 'store']);
Route::get('/api/videos/{id}/chat', [ChatController::class, 'index']);

// Admin
Route::post('/api/admin/login', [AdminController::class, 'login']);
Route::get('/api/admin/stats', [AdminController::class, 'stats'])->middleware('admin.auth');
Route::delete('/api/admin/videos', [AdminController::class, 'purgeAll'])->middleware('admin.auth');
Route::delete('/api/admin/videos/{id}', [AdminController::class, 'deleteVideo'])->middleware('admin.auth');
Route::post('/api/admin/videos/{id}/retry', [AdminController::class, 'retryVideo'])->middleware('admin.auth');
