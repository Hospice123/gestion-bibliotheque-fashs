<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sanction;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SanctionController extends Controller
{
    /**
     * Récupérer toutes les sanctions (admin/bibliothécaire) ou les sanctions de l'utilisateur
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = Sanction::with(['user']);

            // Si l'utilisateur n'est pas admin ou bibliothécaire, ne montrer que ses sanctions
            if (!$user->hasAnyRole(['administrateur', 'bibliothecaire'])) {
                $query->where('user_id', $user->id);
            }

            // Filtres optionnels
            if ($request->has('statut')) {
                $query->where('statut', $request->statut);
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('user_id') && $user->hasAnyRole(['administrateur', 'bibliothecaire'])) {
                $query->where('user_id', $request->user_id);
            }

            $sanctions = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $sanctions,
                'message' => 'Sanctions récupérées avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des sanctions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer une nouvelle sanction (admin/bibliothécaire)
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

            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'type' => 'required|in:amende,avertissement,suspension',
                'raison' => 'required|string|max:500',
                'montant' => 'nullable|numeric|min:0',
                'duree_jours' => 'nullable|integer|min:1|max:365',
                'date_debut' => 'nullable|date',
                'emprunt_id' => 'nullable|exists:emprunts,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données de validation invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $dateDebut = $request->date_debut ? \Carbon\Carbon::parse($request->date_debut) : now();
            $dateFin = null;

            if ($request->duree_jours) {
                $dateFin = $dateDebut->copy()->addDays($request->duree_jours);
            }

            DB::beginTransaction();

            $sanction = Sanction::create([
                'user_id' => $request->user_id,
                'type' => $request->type,
                'raison' => $request->raison,
                'montant' => $request->montant ?? 0,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'statut' => 'active',
                'emprunt_id' => $request->emprunt_id,
                'appliquee_par' => $user->id
            ]);

            // Créer une notification pour l'utilisateur sanctionné
            $sanctionnedUser = User::find($request->user_id);
            Notification::create([
                'user_id' => $request->user_id,
                'titre' => 'Nouvelle sanction appliquée',
                'message' => "Une sanction de type \"{$sanction->type}\" a été appliquée à votre compte. Raison : {$sanction->raison}",
                'type' => 'alerte',
                'priorite' => 'haute',
                'statut' => 'non_lue',
                'date_envoi' => now()
            ]);

            DB::commit();

            $sanction->load(['user', 'appliqueeParUser']);

            return response()->json([
                'success' => true,
                'data' => $sanction,
                'message' => 'Sanction appliquée avec succès'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'application de la sanction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher une sanction spécifique
     */
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = Sanction::with(['user', 'appliqueeParUser', 'emprunt.livre']);

            // Si l'utilisateur n'est pas admin ou bibliothécaire, ne montrer que ses sanctions
            if (!$user->hasAnyRole(['administrateur', 'bibliothecaire'])) {
                $query->where('user_id', $user->id);
            }

            $sanction = $query->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $sanction,
                'message' => 'Sanction récupérée avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sanction non trouvée',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Lever une sanction (admin/bibliothécaire)
     */
    public function lift($id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasAnyRole(['administrateur', 'bibliothecaire'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $sanction = Sanction::with(['user'])->findOrFail($id);

            if ($sanction->statut !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette sanction ne peut pas être levée'
                ], 400);
            }

            DB::beginTransaction();

            $sanction->update([
                'statut' => 'levee',
                'date_levee' => now(),
                'levee_par' => $user->id
            ]);

            // Créer une notification pour l'utilisateur
            Notification::create([
                'user_id' => $sanction->user_id,
                'titre' => 'Sanction levée',
                'message' => "La sanction de type \"{$sanction->type}\" a été levée de votre compte.",
                'type' => 'success',
                'priorite' => 'normale',
                'statut' => 'non_lue',
                'date_envoi' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $sanction,
                'message' => 'Sanction levée avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la levée de la sanction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Modifier une sanction (admin/bibliothécaire)
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasAnyRole(['administrateur', 'bibliothecaire'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'raison' => 'sometimes|string|max:500',
                'montant' => 'sometimes|numeric|min:0',
                'duree_jours' => 'sometimes|integer|min:1|max:365',
                'date_fin' => 'sometimes|date|after:date_debut'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données de validation invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $sanction = Sanction::findOrFail($id);

            if ($sanction->statut !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette sanction ne peut pas être modifiée'
                ], 400);
            }

            $updateData = $request->only(['raison', 'montant']);

            if ($request->has('duree_jours')) {
                $updateData['date_fin'] = $sanction->date_debut->copy()->addDays($request->duree_jours);
            } elseif ($request->has('date_fin')) {
                $updateData['date_fin'] = $request->date_fin;
            }

            $sanction->update($updateData);

            return response()->json([
                'success' => true,
                'data' => $sanction,
                'message' => 'Sanction modifiée avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification de la sanction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Payer une sanction (utilisateur)
     */
    public function pay($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $sanction = Sanction::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$sanction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sanction non trouvée'
                ], 404);
            }

            if ($sanction->statut !== 'active' || $sanction->montant <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette sanction ne peut pas être payée'
                ], 400);
            }

            DB::beginTransaction();

            $sanction->update([
                'statut' => 'payee',
                'date_paiement' => now()
            ]);

            // Créer une notification de confirmation
            Notification::create([
                'user_id' => $user->id,
                'titre' => 'Paiement confirmé',
                'message' => "Le paiement de {$sanction->montant}FCFA pour la sanction \"{$sanction->type}\" a été confirmé.",
                'type' => 'success',
                'priorite' => 'normale',
                'statut' => 'non_lue',
                'date_envoi' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $sanction,
                'message' => 'Paiement effectué avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du paiement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques des sanctions (admin/bibliothécaire)
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasAnyRole(['administrateur', 'bibliothecaire'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $stats = [
                'total_sanctions' => Sanction::count(),
                'sanctions_actives' => Sanction::where('statut', 'active')->count(),
                'sanctions_levees' => Sanction::where('statut', 'levee')->count(),
                'sanctions_payees' => Sanction::where('statut', 'payee')->count(),
                'sanctions_expirees' => Sanction::where('statut', 'expiree')->count(),
                'montant_total_amendes' => Sanction::where('statut', 'payee')->sum('montant'),
                'sanctions_par_type' => Sanction::select('type', DB::raw('count(*) as total'))
                    ->groupBy('type')
                    ->get(),
                'sanctions_ce_mois' => Sanction::whereMonth('created_at', now()->month)->count()
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
     * Vérifier les sanctions expirées et les marquer automatiquement
     */
    public function checkExpired(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasAnyRole(['administrateur', 'bibliothecaire'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $expiredCount = Sanction::where('statut', 'active')
                ->where('date_fin', '<', now())
                ->update(['statut' => 'expiree']);

            return response()->json([
                'success' => true,
                'message' => "Vérification terminée. {$expiredCount} sanctions expirées mises à jour.",
                'expired_count' => $expiredCount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification des sanctions expirées',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function mySanctions(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = Sanction::with(["user"]);

            $sanctions = $query->where("user_id", $user->id)->orderBy("created_at", "desc")->paginate(15);

            return response()->json([
                "success" => true,
                "data" => $sanctions,
                "message" => "Mes sanctions récupérées avec succès"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Erreur lors de la récupération de mes sanctions",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Prolonger une sanction (admin/bibliothécaire)
     */
    public function prolonger(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasAnyRole(["administrateur", "bibliothecaire"])) {
                return response()->json([
                    "success" => false,
                    "message" => "Accès non autorisé"
                ], 403);
            }

            $sanction = Sanction::findOrFail($id);

            if ($sanction->statut !== "active") {
                return response()->json([
                    "success" => false,
                    "message" => "Cette sanction ne peut pas être prolongée"
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                "jours" => "required|integer|min:1",
                "raison" => "nullable|string|max:255",
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "success" => false,
                    "message" => "Données de validation invalides",
                    "errors" => $validator->errors()
                ], 422);
            }

            $jours = $request->input("jours");
            $raison = $request->input("raison");

            DB::beginTransaction();

            // Prolonger la date de fin
            $sanction->date_fin = \Carbon\Carbon::parse($sanction->date_fin)->addDays($jours);
            $sanction->notes = $sanction->notes . "\nProlongation de {$jours} jours: {$raison}";
            $sanction->save();

            // Créer une notification pour l'utilisateur
            Notification::create([
                "user_id" => $sanction->user_id,
                "titre" => "Sanction prolongée",
                "message" => "Votre sanction de type \"{$sanction->type}\" a été prolongée de {$jours} jours. Raison: {$raison}",
                "type" => "info",
                "priorite" => "normale",
                "statut" => "non_lue",
                "date_envoi" => now()
            ]);

            DB::commit();

            return response()->json([
                "success" => true,
                "data" => $sanction,
                "message" => "Sanction prolongée avec succès"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "success" => false,
                "message" => "Erreur lors de la prolongation de la sanction",
                "error" => $e->getMessage()
            ], 500);
        }
    }

}

