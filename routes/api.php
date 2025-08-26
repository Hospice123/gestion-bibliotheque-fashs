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
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\AdminController;

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
Route::prefix("auth")->group(function () {
    Route::post("register", [AuthController::class, "register"]);
    Route::post("login", [AuthController::class, "login"]);
});

// Routes protégées par authentification
Route::middleware("auth:sanctum")->group(function () {
    
    // Authentification
    Route::prefix("auth")->group(function () {
        Route::post("logout", [AuthController::class, "logout"]);
        Route::get("me", [AuthController::class, "me"]);
        Route::put("profile", [AuthController::class, "updateProfile"]);
        Route::put("password", [AuthController::class, "changePassword"]);
        Route::get("check-token", [AuthController::class, "checkToken"]);
    });

    // Dashboard
    Route::get("dashboard", [DashboardController::class, "index"]);
    Route::get("dashboard/statistics", [DashboardController::class, "statistics"]);

    // Livres
    Route::prefix("livres")->group(function () {
        Route::get("/", [LivreController::class, "index"]);
        Route::get("search", [LivreController::class, "search"]);
        Route::get("populaires", [LivreController::class, "populaires"]);
        Route::get("nouveautes", [LivreController::class, "nouveautes"]);
        Route::get("categories", [LivreController::class, "categories"]);
        Route::get("{id}", [LivreController::class, "show"]);
        
        // Routes pour bibliothécaires et administrateurs
        Route::middleware("check.role:bibliothecaire,administrateur")->group(function () {
            Route::post("/", [LivreController::class, "store"]);
            Route::put("{id}", [LivreController::class, "update"]);
        });
        
        // Routes pour administrateurs uniquement
        Route::middleware("check.role:administrateur")->group(function () {
            Route::delete("{id}", [LivreController::class, "destroy"]);
        });
    });

    // Emprunts
    Route::prefix("emprunts")->group(function () {
        Route::get("/", [EmpruntController::class, "index"]);
        Route::get("statistiques", [EmpruntController::class, "statistiques"]);
        Route::get("historique", [EmpruntController::class, "historique"]);
        Route::get("{id}", [EmpruntController::class, "show"]);
        Route::post("/", [EmpruntController::class, "store"]);
        Route::put("{id}/prolonger", [EmpruntController::class, "prolonger"]);
        
        // Routes pour bibliothécaires et administrateurs
        Route::middleware("check.role:bibliothecaire,administrateur")->group(function () {
            Route::put("{id}/retourner", [EmpruntController::class, "retourner"]);
            Route::put("{id}/marquer-perdu", [EmpruntController::class, "marquerPerdu"]);
        });
    });

    // Réservations
    Route::prefix("reservations")->group(function () {
        Route::get("/", [ReservationController::class, "index"]);
        Route::get("{id}", [ReservationController::class, "show"]);
        Route::post("/", [ReservationController::class, "store"]);
        Route::put("{id}/annuler", [ReservationController::class, "cancel"]);
        Route::get("statistics", [ReservationController::class, "statistics"]);
        
        // Routes pour bibliothécaires et administrateurs
        Route::middleware("check.role:bibliothecaire,administrateur")->group(function () {
            Route::put("{id}/confirmer", [ReservationController::class, "confirm"]);
            Route::put("{id}/expirer", [ReservationController::class, "expirer"]);
        });
    });

    // Sanctions
    Route::prefix("sanctions")->group(function () {
        Route::get("/", [SanctionController::class, "index"]);
        Route::get("my", [SanctionController::class, "mySanctions"]);
        Route::get("{id}", [SanctionController::class, "show"]);
        Route::put("{id}/pay", [SanctionController::class, "pay"]);
        
        // Routes pour bibliothécaires et administrateurs
        Route::middleware("check.role:bibliothecaire,administrateur")->group(function () {
            Route::post("/", [SanctionController::class, "store"]);
            Route::put("{id}", [SanctionController::class, "update"]);
            Route::put("{id}/cancel", [SanctionController::class, "lift"]);
            Route::put("{id}/prolonger", [SanctionController::class, "prolonger"]);
            Route::get("statistiques", [SanctionController::class, "statistics"]);
            Route::post("check-expired", [SanctionController::class, "checkExpired"]);
        });
    });

    // Notifications
    Route::prefix("notifications")->group(function () {
        Route::get("/", [NotificationController::class, "index"]);
        Route::get("non-lues", [NotificationController::class, "unread"]);
        Route::get("{id}", [NotificationController::class, "show"]);
        Route::post("/", [NotificationController::class, "store"]);
        Route::put("{id}/marquer-lue", [NotificationController::class, "markAsRead"]);
        Route::put("marquer-toutes-lues", [NotificationController::class, "markAllAsRead"]);
        Route::delete("{id}", [NotificationController::class, "destroy"]);
        Route::delete("read", [NotificationController::class, "deleteRead"]);
    });

    // Utilisateurs
    Route::prefix("users")->group(function () {
        Route::get("{id}", [UserController::class, "show"]);
        Route::put("{id}", [UserController::class, "update"]);
        Route::put("{id}/change-password", [UserController::class, "changePassword"]);
        
        // Routes pour bibliothécaires et administrateurs
        Route::middleware("check.role:bibliothecaire,administrateur")->group(function () {
            Route::get("/", [UserController::class, "index"]);
            Route::get("search", [UserController::class, "search"]);
            Route::get("statistics", [UserController::class, "statistics"]);
            Route::get("roles", [UserController::class, "roles"]);
            
            // Routes pour administrateurs uniquement
            Route::middleware("check.role:administrateur")->group(function () {
                Route::post("/", [UserController::class, "store"]);
                Route::put("{id}/status", [UserController::class, "toggleStatus"]);
                Route::put("{id}/role", [UserController::class, "changeRole"]);
                Route::delete("{id}", [UserController::class, "destroy"]);
            });
        });
    });


    // Paramètres
    Route::prefix("settings")->group(function () {
        Route::middleware("check.role:administrateur")->group(function () {
            Route::get("/", [SettingController::class, "index"]);
            Route::put("{category}", [SettingController::class, "update"]);
        });
    });

    // Administration
    Route::prefix("administrateur")->group(function () {
        Route::middleware("check.role:administrateur")->group(function () {
            Route::post("clear-cache", [AdminController::class, "clearCache"]);
            Route::post("backup", [AdminController::class, "backupDatabase"]);
        });
    });
});

// Route de test
Route::get("test", function () {
    return response()->json([
        "success" => true,
        "message" => "API Bibliothèque Universitaire - Laravel 11",
        "version" => "1.0.0",
        "timestamp" => now()->toISOString()
    ]);
});
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    // Routes de gestion des utilisateurs
    Route::apiResource('users', UserController::class);
    
    // Routes spéciales pour la gestion des utilisateurs
    Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
    Route::patch('users/{user}/role', [UserController::class, 'updateRole']); // Nouvelle route pour modifier le rôle
    Route::get('roles', [UserController::class, 'getRoles']); // Route pour récupérer les rôles disponibles
    Route::get('users-stats', [UserController::class, 'getStats']); // Route pour les statistiques
});

// Route pour gérer les erreurs 404
Route::fallback(function () {
    return response()->json([
        "success" => false,
        "message" => "Endpoint non trouvé",
        "error" => "Route not found"
    ], 404);
});
