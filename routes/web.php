<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;

// Pages statiques
Route::get('/', [WebController::class, 'home']);
Route::get('/admin', [WebController::class, 'admin']);

// Page détail vidéo (doit être AVANT la catch-all)
Route::get('/video/{id}', [WebController::class, 'video']);

// Catch-all SPA (optionnel)
Route::get('/{any?}', [WebController::class, 'home'])->where('any', '.*');
