<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Setting; // Assurez-vous que ce modèle est importé

class SettingController extends Controller
{
    /**
     * Récupérer les paramètres de l'application.
     * Accessible uniquement aux administrateurs.
     */
    public function index(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasRole("administrateur")) {
                return response()->json([
                    "success" => false,
                    "message" => "Accès non autorisé"
                ], 403);
            }

            // Récupérer tous les paramètres de la base de données via le modèle Setting
            $settings = Setting::all()->mapWithKeys(function ($item) {
                return [$item->key => Setting::get($item->key)];
            })->toArray();

            $categorizedSettings = [];
            foreach ($settings as $key => $value) {
                $setting = Setting::where("key", $key)->first();
                $category = $setting->category ?? "uncategorized";
                $categorizedSettings[$category][$key] = $value;
            }

            return response()->json([
                "success" => true,
                "data" => $categorizedSettings,
                "message" => "Paramètres récupérés avec succès"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Erreur lors de la récupération des paramètres",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour les paramètres par catégorie.
     * Accessible uniquement aux administrateurs.
     */
    public function update(Request $request, $category): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasRole("administrateur")) {
                return response()->json([
                    "success" => false,
                    "message" => "Accès non autorisé"
                ], 403);
            }

            $validCategories = ["general", "reservations", "fines"]; // Exemple, à adapter
            if (!in_array($category, $validCategories)) {
                return response()->json([
                    "success" => false,
                    "message" => "Catégorie de paramètres invalide"
                ], 400);
            }

            $rules = [];
            // Définir les règles de validation spécifiques à chaque catégorie
            if ($category === 'general') {
                $rules = [
                    'library_name' => 'sometimes|string|max:255',
                    'contact_email' => 'sometimes|email|max:255',
                    'max_borrow_days' => 'sometimes|integer|min:1',
                ];
            }
            // ... autres catégories

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Données de validation invalides",
                    "errors" => $validator->errors()
                ], 422);
            }

            foreach ($request->all() as $key => $value) {
                Setting::set($key, $value, $category);
            }

            return response()->json([
                "success" => true,
                "message" => "Paramètres de la catégorie '{$category}' mis à jour avec succès"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Erreur lors de la mise à jour des paramètres",
                "error" => $e->getMessage()
            ], 500);
        }
    }
}