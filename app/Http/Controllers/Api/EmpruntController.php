<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Emprunt;
use App\Models\Livre;
use App\Models\User;
use App\Models\Notification;
use App\Models\Sanction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmpruntController extends Controller
{
    /**
     * Liste des emprunts avec filtres
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $query = Emprunt::with(['user', 'livre.categorie']);

            // Si c'est un emprunteur, ne montrer que ses emprunts
            if ($user->role === 'emprunteur') {
                $query->parUtilisateur($user->id);
            }

            // Filtres
            if ($request->has('user_id') && $user->role !== 'emprunteur') {
                $query->parUtilisateur($request->user_id);
            }

            if ($request->has('livre_id')) {
                $query->parLivre($request->livre_id);
            }

            if ($request->has('statut')) {
                $query->where('statut', $request->statut);
            }

            if ($request->has('en_retard') && $request->en_retard === 'true') {
                $query->enRetard();
            }

            // Période
            if ($request->has('date_debut') && $request->has('date_fin')) {
                $query->parPeriode($request->date_debut, $request->date_fin);
            }

            // Tri
            $sortBy = $request->get('sort_by', 'date_emprunt');
            $sortOrder = $request->get('sort_order', 'desc');
            
            if (in_array($sortBy, ['date_emprunt', 'date_retour_prevue', 'date_retour_effective'])) {
                $query->orderBy($sortBy, $sortOrder);
            }

            $perPage = min($request->get('per_page', 15), 50);
            $emprunts = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'emprunts' => $emprunts->items(),
                    'pagination' => [
                        'current_page' => $emprunts->currentPage(),
                        'last_page' => $emprunts->lastPage(),
                        'per_page' => $emprunts->perPage(),
                        'total' => $emprunts->total()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des emprunts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher un emprunt spécifique
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            $query = Emprunt::with(['user', 'livre.categorie', 'sanctions']);

            // Si c'est un emprunteur, vérifier qu'il s'agit de son emprunt
            if ($user->role === 'emprunteur') {
                $query->where('user_id', $user->id);
            }

            $emprunt = $query->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'emprunt' => $emprunt,
                    'peut_etre_prolonge' => $emprunt->peutEtreProlonge(),
                    'amende_calculee' => $emprunt->calculerAmende()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Emprunt non trouvé',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Créer un nouvel emprunt
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'livre_id' => 'required|exists:livres,id',
            'user_id' => 'sometimes|exists:users,id' // Optionnel pour les bibliothécaires
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = $request->user();
            $emprunteurId = $request->get('user_id', $user->id);
            
            // Si un bibliothécaire emprunte pour quelqu'un d'autre
            if ($emprunteurId !== $user->id && !in_array($user->role, ['bibliothecaire', 'administrateur'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé à emprunter pour un autre utilisateur'
                ], 403);
            }

            $emprunteur = User::findOrFail($emprunteurId);
            $livre = Livre::findOrFail($request->livre_id);

            // Vérifications
            if (!$emprunteur->peutEmprunter()) {
                return response()->json([
                    'success' => false,
                    'message' => 'L\'utilisateur ne peut pas emprunter (compte suspendu ou sanctions actives)'
                ], 400);
            }

            if ($emprunteur->nombreEmpruntsActifs() >= $emprunteur->limiteEmprunts()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Limite d\'emprunts atteinte'
                ], 400);
            }

            if (!$livre->estDisponible()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Livre non disponible'
                ], 400);
            }

            // Vérifier si l'utilisateur a déjà emprunté ce livre
            $empruntExistant = Emprunt::where('user_id', $emprunteurId)
                                    ->where('livre_id', $livre->id)
                                    ->where('statut', 'en_cours')
                                    ->exists();

            if ($empruntExistant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous avez déjà emprunté ce livre'
                ], 400);
            }

            // Créer l'emprunt
            $dateRetourPrevue = now()->addDays($emprunteur->dureeEmpruntJours());
            
            $emprunt = Emprunt::create([
                'user_id' => $emprunteurId,
                'livre_id' => $livre->id,
                'date_emprunt' => now(),
                'date_retour_prevue' => $dateRetourPrevue,
                'statut' => 'en_cours'
            ]);

            // Mettre à jour la disponibilité du livre
            $livre->emprunter();

            // Créer une notification
            Notification::creerConfirmationEmprunt($emprunteur, $emprunt);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Emprunt créé avec succès',
                'data' => [
                    'emprunt' => $emprunt->load(['user', 'livre.categorie'])
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'emprunt',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Prolonger un emprunt
     */
    public function prolonger(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'jours' => 'sometimes|integer|min:1|max:14'
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
            $query = Emprunt::with(['user', 'livre']);

            // Si c'est un emprunteur, vérifier qu'il s'agit de son emprunt
            if ($user->role === 'emprunteur') {
                $query->where('user_id', $user->id);
            }

            $emprunt = $query->findOrFail($id);
            $jours = $request->get('jours', 7);

            if (!$emprunt->peutEtreProlonge()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet emprunt ne peut pas être prolongé'
                ], 400);
            }

            $emprunt->prolonger($jours);

            return response()->json([
                'success' => true,
                'message' => "Emprunt prolongé de {$jours} jour(s)",
                'data' => [
                    'emprunt' => $emprunt->fresh(['user', 'livre.categorie'])
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la prolongation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retourner un livre (Bibliothécaire/Admin)
     */
    public function retourner(Request $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $emprunt = Emprunt::with(['user', 'livre'])->findOrFail($id);

            if ($emprunt->statut !== 'en_cours') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet emprunt n\'est pas en cours'
                ], 400);
            }

            // Calculer l'amende si en retard
            $amende = 0;
            if ($emprunt->estEnRetard()) {
                $amende = $emprunt->calculerAmende();
                
                if ($amende > 0) {
                    // Créer une sanction d'amende
                    $sanction = Sanction::create([
                        'user_id' => $emprunt->user_id,
                        'emprunt_id' => $emprunt->id,
                        'type' => 'amende',
                        'montant' => $amende,
                        'raison' => "Retard de {$emprunt->jours_retard} jour(s) pour le livre \"{$emprunt->livre->titre}\"",
                        'appliquee_par' => $request->user()->id
                    ]);

                    // Notifier l'utilisateur
                    Notification::creerNotificationSanction($emprunt->user, $sanction);
                }
            }

            // Effectuer le retour
            $emprunt->retourner();

            // Notifier le prochain utilisateur en file d'attente s'il y en a un
            $prochaineReservation = $emprunt->livre->prochainUtilisateurEnAttente();
            if ($prochaineReservation) {
                Notification::creerNotificationDisponibilite(
                    $prochaineReservation->user, 
                    $emprunt->livre
                );
                $prochaineReservation->marquerCommeNotifiee();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Livre retourné avec succès',
                'data' => [
                    'emprunt' => $emprunt->fresh(['user', 'livre.categorie']),
                    'amende_appliquee' => $amende
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du retour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marquer un livre comme perdu (Bibliothécaire/Admin)
     */
    public function marquerPerdu(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'sometimes|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $emprunt = Emprunt::with(['user', 'livre'])->findOrFail($id);

            if ($emprunt->statut !== 'en_cours') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet emprunt n\'est pas en cours'
                ], 400);
            }

            // Marquer comme perdu
            $emprunt->marquerCommePerdu();
            
            if ($request->has('notes')) {
                $emprunt->update(['notes' => $request->notes]);
            }

            // Créer une sanction pour livre perdu
            $montantSanction = 50.00; // Montant configurable
            $sanction = Sanction::create([
                'user_id' => $emprunt->user_id,
                'emprunt_id' => $emprunt->id,
                'type' => 'amende',
                'montant' => $montantSanction,
                'raison' => "Livre perdu: \"{$emprunt->livre->titre}\"",
                'appliquee_par' => $request->user()->id
            ]);

            // Notifier l'utilisateur
            Notification::creerNotificationSanction($emprunt->user, $sanction);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Livre marqué comme perdu',
                'data' => [
                    'emprunt' => $emprunt->fresh(['user', 'livre.categorie']),
                    'sanction' => $sanction
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du marquage comme perdu',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Statistiques des emprunts
     */
    public function statistiques(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if ($user->role === 'emprunteur') {
                // Statistiques personnelles
                $stats = [
                    'emprunts_actifs' => $user->nombreEmpruntsActifs(),
                    'emprunts_total' => $user->emprunts()->count(),
                    'emprunts_en_retard' => $user->emprunts()->enRetard()->count(),
                    'amendes_impayees' => $user->montantAmendesImpayees(),
                    'limite_emprunts' => $user->limiteEmprunts()
                ];
            } else {
                // Statistiques globales pour bibliothécaires/admins
                $stats = [
                    'emprunts_actifs' => Emprunt::enCours()->count(),
                    'emprunts_en_retard' => Emprunt::enRetard()->count(),
                    'emprunts_aujourd_hui' => Emprunt::whereDate('date_emprunt', today())->count(),
                    'retours_aujourd_hui' => Emprunt::whereDate('date_retour_effective', today())->count(),
                    'emprunts_ce_mois' => Emprunt::whereMonth('date_emprunt', now()->month)->count(),
                    'amendes_actives' => Sanction::amendes()->actives()->sum('montant')
                ];
            }

            return response()->json([
                'success' => true,
                'data' => ['statistiques' => $stats]
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
     * Historique des emprunts d'un utilisateur
     */
    public function historique(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $userId = $request->get('user_id', $user->id);

            // Vérifier les permissions
            if ($userId != $user->id && !in_array($user->role, ['bibliothecaire', 'administrateur'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé'
                ], 403);
            }

            $emprunts = Emprunt::with(['livre.categorie'])
                              ->where('user_id', $userId)
                              ->orderBy('date_emprunt', 'desc')
                              ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => [
                    'emprunts' => $emprunts->items(),
                    'pagination' => [
                        'current_page' => $emprunts->currentPage(),
                        'last_page' => $emprunts->lastPage(),
                        'total' => $emprunts->total()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'historique',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

