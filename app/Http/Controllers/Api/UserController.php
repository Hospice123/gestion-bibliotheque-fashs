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
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Récupérer tous les utilisateurs (administrateur/bibliothecaire)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user->hasAnyRole(['administrateur', 'bibliothecaire'])) {
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
                    $q->where('nom', 'like', "%{$search}%")
                      ->orWhere('prenom', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('numero_etudiant', 'like', "%{$search}%");
                });
            }

            // Filtrer par statut (actif/inactif)
            if ($request->has('statut')) {
                $query->where('statut', $request->statut);
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Ajouter le rôle principal à chaque utilisateur pour l'affichage
            $users->getCollection()->transform(function ($user) {
                $user->role = $user->roles->first()?->name ?? 'emprunteur';
                // Ajouter un attribut 'actif' basé sur le statut pour la compatibilité
                $user->actif = $user->statut === 'actif';
                return $user;
            });

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
     * Créer un nouvel utilisateur
     * Le rôle est automatiquement défini à 'emprunteur' par défaut.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasAnyRole(['administrateur', 'bibliothecaire'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            // Validation des données (sans le champ role)
            $validator = Validator::make($request->all(), [
                'nom' => 'required|string|max:255',
                'prenom' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
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

            // Créer l'utilisateur avec statut 'actif' par défaut
            $newUser = User::create([
                'nom' => $request->nom,
                'prenom' => $request->prenom,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'numero_etudiant' => $request->numero_etudiant,
                'telephone' => $request->telephone,
                'adresse' => $request->adresse,
                'date_naissance' => $request->date_naissance,
                'niveau_etude' => $request->niveau_etude,
                'filiere' => $request->filiere,
                'statut' => 'actif', // Utilisation de la colonne 'statut' existante
                'email_verified_at' => now()
            ]);

            // Assigner automatiquement le rôle 'emprunteur' par défaut
            $defaultRole = 'emprunteur';
            
            // Vérifier que le rôle existe
            $role = Role::where('name', $defaultRole)->where('guard_name', 'web')->first();
            if (!$role) {
                // Si le rôle n'existe pas, le créer
                $role = Role::create(['name' => $defaultRole, 'guard_name' => 'web']);
                \Log::warning("Rôle '{$defaultRole}' créé automatiquement lors de la création d'un utilisateur");
            }
            
            $newUser->assignRole($defaultRole);

            // Créer une notification de bienvenue avec le type compatible
            Notification::creerNotificationBienvenue($newUser, $defaultRole);

            DB::commit();

            $newUser->load('roles');
            $newUser->role = $newUser->roles->first()?->name ?? $defaultRole;
            // Ajouter l'attribut 'actif' pour la compatibilité
            $newUser->actif = $newUser->statut === 'actif';

            return response()->json([
                'success' => true,
                'data' => $newUser,
                'message' => 'Utilisateur créé avec succès avec le rôle par défaut : ' . $defaultRole
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur création utilisateur:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->except(['password', 'password_confirmation'])
            ]);

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
            if (!$currentUser->hasAnyRole(['administrateur', 'bibliothecaire']) && $currentUser->id != $id) {
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

            $user->role = $user->roles->first()?->name ?? 'emprunteur';
            // Ajouter l'attribut 'actif' pour la compatibilité
            $user->actif = $user->statut === 'actif';

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
     * Le rôle ne peut être modifié que par un administrateur via une route séparée.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $currentUser = Auth::user();

            // Vérifier les permissions
            if (!$currentUser->hasAnyRole(['administrateur', 'bibliothecaire']) && $currentUser->id != $id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $user = User::findOrFail($id);

            $rules = [
                'nom' => 'required|string|max:255',
                'prenom' => 'required|string|max:255',
                'email' => ['required', 'email', Rule::unique('users')->ignore($id)],
                'password' => 'nullable|string|min:8|confirmed',
                'telephone' => 'nullable|string|max:20',
                'adresse' => 'nullable|string|max:500',
                'date_naissance' => 'nullable|date',
                'numero_etudiant' => 'nullable|string|max:50',
                'niveau_etude' => 'nullable|string|max:100',
                'filiere' => 'nullable|string|max:100',
                'statut' => 'in:actif,inactif', // Validation du statut
            ];

            // Seuls les administrateurs peuvent modifier certains champs
            if ($currentUser->hasRole('administrateur')) {
                $rules['numero_etudiant'] = 'nullable|string|max:20|unique:users,numero_etudiant,' . $id;
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
                'nom', 'prenom', 'email', 'telephone', 'adresse', 
                'date_naissance', 'niveau_etude', 'filiere'
            ]);

            // Si un nouveau mot de passe est fourni, le hacher
            if (!empty($request->password)) {
                $updateData['password'] = Hash::make($request->password);
            }

            if ($currentUser->hasRole('administrateur')) {
                $updateData = array_merge($updateData, $request->only([
                    'numero_etudiant', 'statut'
                ]));
            }

            $user->update($updateData);

            DB::commit();

            $user->load('roles');
            $user->role = $user->roles->first()?->name ?? 'emprunteur';
            // Ajouter l'attribut 'actif' pour la compatibilité
            $user->actif = $user->statut === 'actif';

            return response()->json([
                'success' => true,
                'data' => $user,
                'message' => 'Utilisateur mis à jour avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur modification utilisateur:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $id,
                'data' => $request->except(['password', 'password_confirmation'])
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'utilisateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user role (accessible uniquement aux administrateurs).
     */
    public function updateRole(Request $request, $id): JsonResponse
    {
        try {
            $currentUser = Auth::user();

            // Vérifier que l'utilisateur connecté est administrateur
            if (!$currentUser->hasRole('administrateur')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé. Seuls les administrateurs peuvent modifier les rôles.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'role' => 'required|in:administrateur,bibliothecaire,emprunteur',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données de validation invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findOrFail($id);

            // Vérifier que le rôle existe
            $role = Role::where('name', $request->role)->where('guard_name', 'web')->first();
            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => "Le rôle '{$request->role}' n'existe pas.",
                    'error' => "Role '{$request->role}' not found for guard 'web'"
                ], 400);
            }

            DB::beginTransaction();

            // Synchroniser les rôles (remplace tous les rôles existants)
            $user->syncRoles([$request->role]);

            // Créer une notification de changement de rôle avec le type compatible
            Notification::creerNotificationChangementRole($user, $request->role, $currentUser);

            DB::commit();

            // Recharger l'utilisateur avec ses rôles
            $user->load('roles');
            $user->role = $user->roles->first()?->name ?? 'emprunteur';
            // Ajouter l'attribut 'actif' pour la compatibilité
            $user->actif = $user->statut === 'actif';

            return response()->json([
                'success' => true,
                'message' => "Rôle de l'utilisateur modifié avec succès : {$request->role}",
                'data' => $user
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur modification rôle utilisateur:', [
                'message' => $e->getMessage(),
                'user_id' => $id,
                'new_role' => $request->role ?? 'N/A'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification du rôle',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle user status (actif/inactif) en utilisant la colonne 'statut'.
     */
    public function toggleStatus(Request $request, $id): JsonResponse
    {
        try {
            $currentUser = Auth::user();

            if (!$currentUser->hasRole('administrateur')) {
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

            // Déterminer le nouveau statut
            $newStatus = $request->has('statut') ? $request->statut : ($user->statut === 'actif' ? 'inactif' : 'actif');
            
            // Validation du statut
            if (!in_array($newStatus, ['actif', 'inactif'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Statut invalide. Doit être "actif" ou "inactif".'
                ], 400);
            }

            DB::beginTransaction();

            $user->update(['statut' => $newStatus]);

            // Créer une notification de changement de statut avec le type compatible
            Notification::creerNotificationChangementStatut($user, $newStatus);

            DB::commit();

            // Ajouter l'attribut 'actif' pour la compatibilité
            $user->actif = $user->statut === 'actif';

            return response()->json([
                'success' => true,
                'data' => $user,
                'message' => $newStatus === 'actif' ? 'Utilisateur activé avec succès' : 'Utilisateur désactivé avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur modification statut utilisateur:', [
                'message' => $e->getMessage(),
                'user_id' => $id,
                'new_status' => $newStatus ?? 'N/A'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification du statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un utilisateur (administrateur)
     */
    public function destroy($id): JsonResponse
    {
        try {
            $currentUser = Auth::user();

            if (!$currentUser->hasRole('administrateur')) {
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
     * Get available roles (pour les administrateurs).
     */
    public function getRoles(): JsonResponse
    {
        try {
            $currentUser = Auth::user();

            // Vérifier que l'utilisateur connecté est administrateur
            if (!$currentUser->hasRole('administrateur')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé.'
                ], 403);
            }

            $roles = Role::where('guard_name', 'web')->get(['name', 'id']);

            return response()->json([
                'success' => true,
                'data' => $roles
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des rôles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user statistics.
     */
    public function getStats(): JsonResponse
    {
        try {
            $currentUser = Auth::user();

            if (!$currentUser->hasAnyRole(['administrateur', 'bibliothecaire'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $stats = [
                'total_users' => User::count(),
                'active_users' => User::where('statut', 'actif')->count(),
                'inactive_users' => User::where('statut', 'inactif')->count(),
                'administrators' => User::role('administrateur')->count(),
                'librarians' => User::role('bibliothecaire')->count(),
                'borrowers' => User::role('emprunteur')->count(),
                'new_this_month' => User::whereMonth('created_at', now()->month)->count(),
                'repartition_roles' => User::join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                    ->select('roles.name as role', DB::raw('count(*) as total'))
                    ->groupBy('roles.name')
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
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
     * Rechercher des utilisateurs
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $currentUser = Auth::user();

            if (!$currentUser->hasAnyRole(['administrateur', 'bibliothecaire'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $query = $request->get('q', '');
            
            if (empty($query)) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            $users = User::with('roles')
                ->where(function($q) use ($query) {
                    $q->where('nom', 'like', "%{$query}%")
                      ->orWhere('prenom', 'like', "%{$query}%")
                      ->orWhere('email', 'like', "%{$query}%")
                      ->orWhere('numero_etudiant', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get();

            // Ajouter le rôle principal et l'attribut 'actif' à chaque utilisateur
            $users->transform(function ($user) {
                $user->role = $user->roles->first()?->name ?? 'emprunteur';
                $user->actif = $user->statut === 'actif';
                return $user;
            });

            return response()->json([
                'success' => true,
                'data' => $users
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

