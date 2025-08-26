<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;

class AdminController extends Controller
{
    /**
     * Vider le cache de l'application.
     * Accessible uniquement aux administrateurs.
     */
    public function clearCache(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasRole("administrateur")) {
                return response()->json([
                    "success" => false,
                    "message" => "Accès non autorisé"
                ], 403);
            }

            Artisan::call("cache:clear");
            Artisan::call("config:clear");
            Artisan::call("route:clear");
            Artisan::call("view:clear");

            return response()->json([
                "success" => true,
                "message" => "Cache vidé avec succès"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Erreur lors du vidage du cache",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Effectuer une sauvegarde de la base de données.
     * Accessible uniquement aux administrateurs.
     */
    public function backupDatabase(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasRole("administrateur")) {
                return response()->json([
                    "success" => false,
                    "message" => "Accès non autorisé"
                ], 403);
            }

            // Assurez-vous d'avoir configuré un package de sauvegarde comme spatie/laravel-backup
            // et que la commande 'backup:run' est disponible.
            Artisan::call("backup:run");

            return response()->json([
                "success" => true,
                "message" => "Sauvegarde de la base de données effectuée avec succès"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Erreur lors de la sauvegarde de la base de données",
                "error" => $e->getMessage()
            ], 500);
        }
    }
}