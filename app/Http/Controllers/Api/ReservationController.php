<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Livre;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    /**
     * Récupérer toutes les réservations (admin/bibliothécaire) ou les réservations de l'utilisateur
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = Reservation::with(['user', 'livre.categorie']);

            // Si l'utilisateur n'est pas admin ou bibliothécaire, ne montrer que ses réservations
            if (!$user->hasAnyRole(['admin', 'bibliothecaire'])) {
                $query->where('user_id', $user->id);
            }

            // Filtres optionnels
            if ($request->has('statut')) {
                $query->where('statut', $request->statut);
            }

            if ($request->has('user_id') && $user->hasAnyRole(['admin', 'bibliothecaire'])) {
                $query->where('user_id', $request->user_id);
            }

            $reservations = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $reservations,
                'message' => 'Réservations récupérées avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des réservations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer une nouvelle réservation
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'livre_id' => 'required|exists:livres,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données de validation invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $livre = Livre::findOrFail($request->livre_id);

            // Vérifier si le livre est disponible
            if ($livre->statut === 'disponible') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce livre est actuellement disponible. Vous pouvez l\'emprunter directement.'
                ], 400);
            }

            // Vérifier si l'utilisateur a déjà une réservation active pour ce livre
            $existingReservation = Reservation::where('user_id', $user->id)
                ->where('livre_id', $request->livre_id)
                ->where('statut', 'active')
                ->first();

            if ($existingReservation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous avez déjà une réservation active pour ce livre'
                ], 400);
            }

            // Vérifier le nombre maximum de réservations actives
            $activeReservations = Reservation::where('user_id', $user->id)
                ->where('statut', 'active')
                ->count();

            if ($activeReservations >= 5) { // Limite de 5 réservations actives
                return response()->json([
                    'success' => false,
                    'message' => 'Vous avez atteint le nombre maximum de réservations actives (5)'
                ], 400);
            }

            DB::beginTransaction();

            // Créer la réservation
            $reservation = Reservation::create([
                'user_id' => $user->id,
                'livre_id' => $request->livre_id,
                'date_reservation' => now(),
                'date_expiration' => now()->addDays(7), // Réservation valide 7 jours
                'statut' => 'active',
                'position_file' => $this->getNextPosition($request->livre_id)
            ]);

            // Créer une notification
            Notification::create([
                'user_id' => $user->id,
                'titre' => 'Réservation confirmée',
                'message' => "Votre réservation pour le livre \"{$livre->titre}\" a été confirmée. Position dans la file : {$reservation->position_file}",
                'type' => 'success',
                'priorite' => 'normale',
                'statut' => 'non_lue',
                'date_envoi' => now()
            ]);

            DB::commit();

            $reservation->load(['user', 'livre.categorie']);

            return response()->json([
                'success' => true,
                'data' => $reservation,
                'message' => 'Réservation créée avec succès'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la réservation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher une réservation spécifique
     */
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = Reservation::with(['user', 'livre.categorie']);

            // Si l'utilisateur n'est pas admin ou bibliothécaire, ne montrer que ses réservations
            if (!$user->hasAnyRole(['admin', 'bibliothecaire'])) {
                $query->where('user_id', $user->id);
            }

            $reservation = $query->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $reservation,
                'message' => 'Réservation récupérée avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Réservation non trouvée',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Annuler une réservation
     */
    public function cancel($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = Reservation::query();

            // Si l'utilisateur n'est pas admin ou bibliothécaire, ne peut annuler que ses réservations
            if (!$user->hasAnyRole(['admin', 'bibliothecaire'])) {
                $query->where('user_id', $user->id);
            }

            $reservation = $query->findOrFail($id);

            if ($reservation->statut !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette réservation ne peut pas être annulée'
                ], 400);
            }

            DB::beginTransaction();

            // Annuler la réservation
            $reservation->update([
                'statut' => 'annulee',
                'date_annulation' => now()
            ]);

            // Mettre à jour les positions dans la file d'attente
            $this->updateQueuePositions($reservation->livre_id);

            // Créer une notification
            Notification::create([
                'user_id' => $reservation->user_id,
                'titre' => 'Réservation annulée',
                'message' => "Votre réservation pour le livre \"{$reservation->livre->titre}\" a été annulée.",
                'type' => 'info',
                'priorite' => 'normale',
                'statut' => 'non_lue',
                'date_envoi' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Réservation annulée avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation de la réservation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirmer une réservation (bibliothécaire/admin)
     */
    public function confirm($id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->hasAnyRole(['admin', 'bibliothecaire'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $reservation = Reservation::with(['user', 'livre'])->findOrFail($id);

            if ($reservation->statut !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette réservation ne peut pas être confirmée'
                ], 400);
            }

            DB::beginTransaction();

            // Confirmer la réservation
            $reservation->update([
                'statut' => 'confirmee',
                'date_confirmation' => now(),
                'date_expiration' => now()->addDays(3) // 3 jours pour venir récupérer
            ]);

            // Créer une notification
            Notification::create([
                'user_id' => $reservation->user_id,
                'titre' => 'Réservation prête',
                'message' => "Le livre \"{$reservation->livre->titre}\" est maintenant disponible. Vous avez 3 jours pour le récupérer.",
                'type' => 'success',
                'priorite' => 'haute',
                'statut' => 'non_lue',
                'date_envoi' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $reservation,
                'message' => 'Réservation confirmée avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la confirmation de la réservation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir la prochaine position dans la file d'attente
     */
    private function getNextPosition($livreId): int
    {
        $maxPosition = Reservation::where('livre_id', $livreId)
            ->where('statut', 'active')
            ->max('position_file');

        return ($maxPosition ?? 0) + 1;
    }

    /**
     * Mettre à jour les positions dans la file d'attente après annulation
     */
    private function updateQueuePositions($livreId): void
    {
        $reservations = Reservation::where('livre_id', $livreId)
            ->where('statut', 'active')
            ->orderBy('position_file')
            ->get();

        foreach ($reservations as $index => $reservation) {
            $reservation->update(['position_file' => $index + 1]);
        }
    }

    /**
     * Obtenir les statistiques des réservations (admin/bibliothécaire)
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
                'total_reservations' => Reservation::count(),
                'reservations_actives' => Reservation::where('statut', 'active')->count(),
                'reservations_confirmees' => Reservation::where('statut', 'confirmee')->count(),
                'reservations_annulees' => Reservation::where('statut', 'annulee')->count(),
                'reservations_expirees' => Reservation::where('statut', 'expiree')->count(),
                'reservations_ce_mois' => Reservation::whereMonth('created_at', now()->month)->count(),
                'livres_les_plus_reserves' => Reservation::with('livre')
                    ->select('livre_id', DB::raw('count(*) as total'))
                    ->groupBy('livre_id')
                    ->orderBy('total', 'desc')
                    ->limit(5)
                    ->get()
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
}

