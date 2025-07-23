<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\LivreController;
use App\Http\Controllers\Api\EmpruntController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\SanctionController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\DashboardController;

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

// Routes publiques
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Routes protégées par authentification
Route::middleware('auth:sanctum')->group(function () {
    
    // Authentification
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::put('password', [AuthController::class, 'changePassword']);
        Route::get('check-token', [AuthController::class, 'checkToken']);
    });

    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('dashboard/statistiques', [DashboardController::class, 'statistiques']);

    // Livres
    Route::prefix('livres')->group(function () {
        Route::get('/', [LivreController::class, 'index']);
        Route::get('search', [LivreController::class, 'search']);
        Route::get('populaires', [LivreController::class, 'populaires']);
        Route::get('nouveautes', [LivreController::class, 'nouveautes']);
        Route::get('categories', [LivreController::class, 'categories']);
        Route::get('{id}', [LivreController::class, 'show']);
        
        // Routes pour bibliothécaires et administrateurs
        Route::middleware('check.role:bibliothecaire,admin')->group(function () {
            Route::post('/', [LivreController::class, 'store']);
            Route::put('{id}', [LivreController::class, 'update']);
        });
        
        // Routes pour administrateurs uniquement
        Route::middleware('check.role:admin')->group(function () {
            Route::delete('{id}', [LivreController::class, 'destroy']);
        });
    });

    // Emprunts
    Route::prefix('emprunts')->group(function () {
        Route::get('/', [EmpruntController::class, 'index']);
        Route::get('statistiques', [EmpruntController::class, 'statistiques']);
        Route::get('historique', [EmpruntController::class, 'historique']);
        Route::get('{id}', [EmpruntController::class, 'show']);
        Route::post('/', [EmpruntController::class, 'store']);
        Route::put('{id}/prolonger', [EmpruntController::class, 'prolonger']);
        
        // Routes pour bibliothécaires et administrateurs
        Route::middleware('check.role:bibliothecaire,admin')->group(function () {
            Route::put('{id}/retourner', [EmpruntController::class, 'retourner']);
            Route::put('{id}/marquer-perdu', [EmpruntController::class, 'marquerPerdu']);
        });
    });

    // Réservations
    Route::prefix('reservations')->group(function () {
        Route::get('/', [ReservationController::class, 'index']);
        Route::get('{id}', [ReservationController::class, 'show']);
        Route::post('/', [ReservationController::class, 'store']);
        Route::put('{id}/cancel', [ReservationController::class, 'cancel']);
        Route::get('statistics', [ReservationController::class, 'statistics']);
        
        // Routes pour bibliothécaires et administrateurs
        Route::middleware('check.role:bibliothecaire,admin')->group(function () {
            Route::put('{id}/confirm', [ReservationController::class, 'confirm']);
        });
    });

    // Sanctions
    Route::prefix('sanctions')->group(function () {
        Route::get('/', [SanctionController::class, 'index']);
        Route::get('{id}', [SanctionController::class, 'show']);
        Route::put('{id}/pay', [SanctionController::class, 'pay']);
        
        // Routes pour bibliothécaires et administrateurs
        Route::middleware('check.role:bibliothecaire,admin')->group(function () {
            Route::post('/', [SanctionController::class, 'store']);
            Route::put('{id}', [SanctionController::class, 'update']);
            Route::put('{id}/lift', [SanctionController::class, 'lift']);
            Route::get('statistics', [SanctionController::class, 'statistics']);
            Route::post('check-expired', [SanctionController::class, 'checkExpired']);
        });
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('unread', [NotificationController::class, 'unread']);
        Route::post('/', [NotificationController::class, 'store']);
        Route::put('{id}/mark-as-read', [NotificationController::class, 'markAsRead']);
        Route::put('mark-all-as-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('{id}', [NotificationController::class, 'destroy']);
        Route::delete('read', [NotificationController::class, 'deleteRead']);
    });

    // Utilisateurs
    Route::prefix('users')->group(function () {
        Route::get('{id}', [UserController::class, 'show']);
        Route::put('{id}', [UserController::class, 'update']);
        Route::put('{id}/change-password', [UserController::class, 'changePassword']);
        
        // Routes pour bibliothécaires et administrateurs
        Route::middleware('check.role:bibliothecaire,admin')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::get('statistics', [UserController::class, 'statistics']);
            Route::get('roles', [UserController::class, 'roles']);
            
            // Routes pour administrateurs uniquement
            Route::middleware('check.role:admin')->group(function () {
                Route::post('/', [UserController::class, 'store']);
                Route::put('{id}/toggle-status', [UserController::class, 'toggleStatus']);
                Route::delete('{id}', [UserController::class, 'destroy']);
            });
        });
    });
});

// Route de test
Route::get('test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API Bibliothèque Universitaire - Laravel 11',
        'version' => '1.0.0',
        'timestamp' => now()->toISOString()
    ]);
});

// Route pour gérer les erreurs 404
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Endpoint non trouvé',
        'error' => 'Route not found'
    ], 404);
});

