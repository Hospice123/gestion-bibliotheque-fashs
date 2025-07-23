<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Récupérer tous les utilisateurs (admin/bibliothécaire)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasAnyRole(['admin', 'bibliothecaire'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $query = User::with(['roles', 'emprunts', 'reservations', 'sanctions']);

            // Filtres optionnels
            if ($request->has('role')) {
                $query->role($request->role);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('numero_etudiant', 'like', "%{$search}%");
                });
            }

            if ($request->has('statut')) {
                $query->where('statut', $request->statut);
            }

            $users = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $users,
                'message' => 'Utilisateurs récupérés avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des utilisateurs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer un nouvel utilisateur (admin)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'required|in:etudiant,bibliothecaire,admin',
                'numero_etudiant' => 'nullable|string|max:20|unique:users',
                'telephone' => 'nullable|string|max:20',
                'adresse' => 'nullable|string|max:500',
                'date_naissance' => 'nullable|date',
                'niveau_etude' => 'nullable|string|max:100',
                'filiere' => 'nullable|string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données de validation invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $newUser = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'numero_etudiant' => $request->numero_etudiant,
                'telephone' => $request->telephone,
                'adresse' => $request->adresse,
                'date_naissance' => $request->date_naissance,
                'niveau_etude' => $request->niveau_etude,
                'filiere' => $request->filiere,
                'statut' => 'actif',
                'email_verified_at' => now()
            ]);

            // Assigner le rôle
            $newUser->assignRole($request->role);

            // Créer une notification de bienvenue
            Notification::create([
                'user_id' => $newUser->id,
                'titre' => 'Bienvenue dans la bibliothèque universitaire',
                'message' => "Votre compte a été créé avec succès. Vous pouvez maintenant accéder à tous les services de la bibliothèque.",
                'type' => 'success',
                'priorite' => 'normale',
                'statut' => 'non_lue',
                'date_envoi' => now()
            ]);

            DB::commit();

            $newUser->load('roles');

            return response()->json([
                'success' => true,
                'data' => $newUser,
                'message' => 'Utilisateur créé avec succès'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'utilisateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher un utilisateur spécifique
     */
    public function show($id): JsonResponse
    {
        try {
            $currentUser = Auth::user();

            // Vérifier les permissions
            if (!$currentUser->hasAnyRole(['admin', 'bibliothecaire']) && $currentUser->id != $id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $user = User::with([
                'roles', 
                'emprunts.livre', 
                'reservations.livre', 
                'sanctions'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $user,
                'message' => 'Utilisateur récupéré avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Mettre à jour un utilisateur
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $currentUser = Auth::user();

            // Vérifier les permissions
            if (!$currentUser->hasAnyRole(['admin', 'bibliothecaire']) && $currentUser->id != $id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $user = User::findOrFail($id);

            $rules = [
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
                'telephone' => 'sometimes|nullable|string|max:20',
                'adresse' => 'sometimes|nullable|string|max:500',
                'date_naissance' => 'sometimes|nullable|date',
                'niveau_etude' => 'sometimes|nullable|string|max:100',
                'filiere' => 'sometimes|nullable|string|max:100'
            ];

            // Seuls les admins peuvent modifier certains champs
            if ($currentUser->hasRole('admin')) {
                $rules['role'] = 'sometimes|in:etudiant,bibliothecaire,admin';
                $rules['statut'] = 'sometimes|in:actif,suspendu,inactif';
                $rules['numero_etudiant'] = 'sometimes|nullable|string|max:20|unique:users,numero_etudiant,' . $id;
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données de validation invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $updateData = $request->only([
                'name', 'email', 'telephone', 'adresse', 
                'date_naissance', 'niveau_etude', 'filiere'
            ]);

            if ($currentUser->hasRole('admin')) {
                $updateData = array_merge($updateData, $request->only([
                    'statut', 'numero_etudiant'
                ]));
            }

            $user->update($updateData);

            // Mettre à jour le rôle si nécessaire (admin seulement)
            if ($request->has('role') && $currentUser->hasRole('admin')) {
                $user->syncRoles([$request->role]);
            }

            DB::commit();

            $user->load('roles');

            return response()->json([
                'success' => true,
                'data' => $user,
                'message' => 'Utilisateur mis à jour avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'utilisateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Changer le mot de passe
     */
    public function changePassword(Request $request, $id): JsonResponse
    {
        try {
            $currentUser = Auth::user();

            // Vérifier les permissions
            if (!$currentUser->hasRole('admin') && $currentUser->id != $id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $rules = [
                'new_password' => 'required|string|min:8|confirmed'
            ];

            // Si ce n'est pas un admin qui change le mot de passe d'un autre utilisateur
            if ($currentUser->id == $id) {
                $rules['current_password'] = 'required|string';
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données de validation invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findOrFail($id);

            // Vérifier le mot de passe actuel si nécessaire
            if ($currentUser->id == $id && !Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le mot de passe actuel est incorrect'
                ], 400);
            }

            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe mis à jour avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du mot de passe',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Suspendre/Activer un utilisateur (admin)
     */
    public function toggleStatus($id): JsonResponse
    {
        try {
            $currentUser = Auth::user();

            if (!$currentUser->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $user = User::findOrFail($id);

            if ($user->id === $currentUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas modifier votre propre statut'
                ], 400);
            }

            $newStatus = $user->statut === 'actif' ? 'suspendu' : 'actif';
            $user->update(['statut' => $newStatus]);

            // Créer une notification
            $message = $newStatus === 'actif' 
                ? 'Votre compte a été réactivé. Vous pouvez maintenant accéder à tous les services.'
                : 'Votre compte a été suspendu. Contactez l\'administration pour plus d\'informations.';

            Notification::create([
                'user_id' => $user->id,
                'titre' => 'Changement de statut de compte',
                'message' => $message,
                'type' => $newStatus === 'actif' ? 'success' : 'warning',
                'priorite' => 'haute',
                'statut' => 'non_lue',
                'date_envoi' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => $user,
                'message' => "Utilisateur {$newStatus} avec succès"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification du statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un utilisateur (admin)
     */
    public function destroy($id): JsonResponse
    {
        try {
            $currentUser = Auth::user();

            if (!$currentUser->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $user = User::findOrFail($id);

            if ($user->id === $currentUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas supprimer votre propre compte'
                ], 400);
            }

            // Vérifier s'il y a des emprunts en cours
            if ($user->emprunts()->where('statut', 'en_cours')->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer un utilisateur avec des emprunts en cours'
                ], 400);
            }

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'utilisateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques des utilisateurs (admin/bibliothécaire)
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasAnyRole(['admin', 'bibliothecaire'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $stats = [
                'total_utilisateurs' => User::count(),
                'utilisateurs_actifs' => User::where('statut', 'actif')->count(),
                'utilisateurs_suspendus' => User::where('statut', 'suspendu')->count(),
                'nouveaux_ce_mois' => User::whereMonth('created_at', now()->month)->count(),
                'repartition_roles' => User::join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                    ->select('roles.name as role', DB::raw('count(*) as total'))
                    ->groupBy('roles.name')
                    ->get(),
                'utilisateurs_avec_emprunts_actifs' => User::whereHas('emprunts', function($q) {
                    $q->where('statut', 'en_cours');
                })->count(),
                'utilisateurs_avec_sanctions_actives' => User::whereHas('sanctions', function($q) {
                    $q->where('statut', 'active');
                })->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistiques récupérées avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les rôles disponibles
     */
    public function roles(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasAnyRole(['admin', 'bibliothecaire'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $roles = Role::all(['id', 'name']);

            return response()->json([
                'success' => true,
                'data' => $roles,
                'message' => 'Rôles récupérés avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des rôles',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

