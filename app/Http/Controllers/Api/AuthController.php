<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Inscription d'un nouvel utilisateur
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'numero_etudiant' => 'nullable|string|max:50|unique:users',
            'telephone' => 'nullable|string|max:20',
            'adresse' => 'nullable|string',
            'role' => 'sometimes|in:emprunteur,bibliothecaire,administrateur'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::create([
                'nom' => $request->nom,
                'prenom' => $request->prenom,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'numero_etudiant' => $request->numero_etudiant,
                'telephone' => $request->telephone,
                'adresse' => $request->adresse,
                'role' => $request->role ?? 'emprunteur',
                'statut' => 'actif'
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur créé avec succès',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'nom' => $user->nom,
                        'prenom' => $user->prenom,
                        'email' => $user->email,
                        'role' => $user->role,
                        'numero_etudiant' => $user->numero_etudiant,
                        'statut' => $user->statut,
                        'nom_complet' => $user->nom_complet
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'utilisateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Connexion d'un utilisateur
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Identifiants invalides'
                ], 401);
            }

            if ($user->statut !== 'actif') {
                return response()->json([
                    'success' => false,
                    'message' => 'Compte suspendu ou inactif'
                ], 403);
            }

            // Supprimer les anciens tokens
            $user->tokens()->delete();

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'nom' => $user->nom,
                        'prenom' => $user->prenom,
                        'email' => $user->email,
                        'role' => $user->role,
                        'numero_etudiant' => $user->numero_etudiant,
                        'statut' => $user->statut,
                        'nom_complet' => $user->nom_complet,
                        'peut_emprunter' => $user->peutEmprunter(),
                        'emprunts_actifs' => $user->nombreEmpruntsActifs(),
                        'limite_emprunts' => $user->limiteEmprunts(),
                        'sanctions_actives' => $user->aDesSanctionsActives(),
                        'amendes_impayees' => $user->montantAmendesImpayees()
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la connexion',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Déconnexion de l'utilisateur
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les informations de l'utilisateur connecté
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'nom' => $user->nom,
                        'prenom' => $user->prenom,
                        'email' => $user->email,
                        'role' => $user->role,
                        'numero_etudiant' => $user->numero_etudiant,
                        'telephone' => $user->telephone,
                        'adresse' => $user->adresse,
                        'statut' => $user->statut,
                        'nom_complet' => $user->nom_complet,
                        'date_inscription' => $user->date_inscription,
                        'peut_emprunter' => $user->peutEmprunter(),
                        'emprunts_actifs' => $user->nombreEmpruntsActifs(),
                        'limite_emprunts' => $user->limiteEmprunts(),
                        'sanctions_actives' => $user->aDesSanctionsActives(),
                        'amendes_impayees' => $user->montantAmendesImpayees()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des informations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour le profil de l'utilisateur
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|required|string|max:255',
            'prenom' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $request->user()->id,
            'numero_etudiant' => 'sometimes|nullable|string|max:50|unique:users,numero_etudiant,' . $request->user()->id,
            'telephone' => 'sometimes|nullable|string|max:20',
            'adresse' => 'sometimes|nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            $user->update($request->only([
                'nom', 'prenom', 'email', 'numero_etudiant', 'telephone', 'adresse'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Profil mis à jour avec succès',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'nom' => $user->nom,
                        'prenom' => $user->prenom,
                        'email' => $user->email,
                        'role' => $user->role,
                        'numero_etudiant' => $user->numero_etudiant,
                        'telephone' => $user->telephone,
                        'adresse' => $user->adresse,
                        'statut' => $user->statut,
                        'nom_complet' => $user->nom_complet
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du profil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Changer le mot de passe
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mot de passe actuel incorrect'
                ], 400);
            }

            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            // Supprimer tous les tokens existants pour forcer une nouvelle connexion
            $user->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe changé avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de mot de passe',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vérifier la validité du token
     */
    public function checkToken(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Token valide',
            'data' => [
                'user_id' => $request->user()->id,
                'email' => $request->user()->email,
                'role' => $request->user()->role
            ]
        ]);
    }
}

