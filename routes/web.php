<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API Bibliothèque Universitaire - Laravel 11',
        'version' => '1.0.0',
        'timestamp' => now()->toISOString()
    ]);
});
