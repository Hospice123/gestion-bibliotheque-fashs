<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    /**
     * Types de notifications autorisés selon le schéma de la table
     */
    const TYPES_AUTORISES = ['info', 'rappel', 'alerte', 'sanction'];

    /**
     * Récupérer toutes les notifications de l'utilisateur connecté
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $query = Notification::where('user_id', $user->id);

            // Filtres optionnels
            if ($request->has('type') && in_array($request->type, self::TYPES_AUTORISES)) {
                $query->where('type', $request->type);
            }

            if ($request->has('lue')) {
                $query->where('lue', filter_var($request->lue, FILTER_VALIDATE_BOOLEAN));
            }

            // Tri par date de création (plus récent en premier)
            $notifications = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'message' => 'Notifications récupérées avec succès'
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur récupération notifications:', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les notifications non lues
     */
    public function unread(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $notifications = Notification::where('user_id', $user->id)
                ->where('lue', false) // Utilisation de la colonne 'lue' (boolean)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'count' => $notifications->count(),
                'message' => 'Notifications non lues récupérées avec succès'
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur récupération notifications non lues:', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des notifications non lues',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer une nouvelle notification
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'titre' => 'required|string|max:255',
                'message' => 'required|string',
                'type' => 'required|in:' . implode(',', self::TYPES_AUTORISES), // Types conformes au schéma
                'donnees_supplementaires' => 'nullable|array' // Support des données supplémentaires
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données de validation invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $notification = Notification::create([
                'user_id' => $request->user_id,
                'titre' => $request->titre,
                'message' => $request->message,
                'type' => $request->type,
                'lue' => false, // Utilisation de la colonne 'lue' (boolean)
                'date_envoi' => now(),
                'donnees_supplementaires' => $request->donnees_supplementaires
            ]);

            return response()->json([
                'success' => true,
                'data' => $notification,
                'message' => 'Notification créée avec succès'
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Erreur création notification:', [
                'data' => $request->except(['donnees_supplementaires']),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher une notification spécifique
     */
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $notification = Notification::where('id', $id)
                ->where('user_id', $user->id) // Sécurité : seules les notifications de l'utilisateur
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification non trouvée'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $notification,
                'message' => 'Notification récupérée avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marquer une notification comme lue
     */
    public function markAsRead($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $notification = Notification::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification non trouvée'
                ], 404);
            }

            // Éviter les mises à jour inutiles
            if ($notification->lue) {
                return response()->json([
                    'success' => true,
                    'data' => $notification,
                    'message' => 'Notification déjà marquée comme lue'
                ]);
            }

            $notification->update([
                'lue' => true, // Utilisation de la colonne 'lue' (boolean)
                'date_lecture' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => $notification,
                'message' => 'Notification marquée comme lue'
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur marquage notification comme lue:', [
                'notification_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marquer une notification comme non lue
     */
    public function markAsUnread($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $notification = Notification::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification non trouvée'
                ], 404);
            }

            // Éviter les mises à jour inutiles
            if (!$notification->lue) {
                return response()->json([
                    'success' => true,
                    'data' => $notification,
                    'message' => 'Notification déjà marquée comme non lue'
                ]);
            }

            $notification->update([
                'lue' => false, // Utilisation de la colonne 'lue' (boolean)
                'date_lecture' => null
            ]);

            return response()->json([
                'success' => true,
                'data' => $notification,
                'message' => 'Notification marquée comme non lue'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marquer toutes les notifications comme lues
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $updated = Notification::where('user_id', $user->id)
                ->where('lue', false) // Utilisation de la colonne 'lue' (boolean)
                ->update([
                    'lue' => true,
                    'date_lecture' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => "Toutes les notifications ont été marquées comme lues",
                'updated_count' => $updated
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur marquage toutes notifications comme lues:', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour des notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer une notification
     */
    public function destroy($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $notification = Notification::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification non trouvée'
                ], 404);
            }

            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur suppression notification:', [
                'notification_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer toutes les notifications lues
     */
    public function deleteRead(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $deleted = Notification::where('user_id', $user->id)
                ->where('lue', true) // Utilisation de la colonne 'lue' (boolean)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notifications lues supprimées avec succès',
                'deleted_count' => $deleted
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur suppression notifications lues:', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression des notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques des notifications pour l'utilisateur connecté
     */
    public function getStats(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $stats = [
                'total' => Notification::where('user_id', $user->id)->count(),
                'non_lues' => Notification::where('user_id', $user->id)->where('lue', false)->count(),
                'lues' => Notification::where('user_id', $user->id)->where('lue', true)->count(),
                'par_type' => Notification::where('user_id', $user->id)
                    ->selectRaw('type, COUNT(*) as count')
                    ->groupBy('type')
                    ->pluck('count', 'type'),
                'recentes' => Notification::where('user_id', $user->id)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count()
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
     * Méthodes utilitaires pour créer des notifications spécifiques
     */

    /**
     * Créer une notification d'information
     */
    public static function creerInfo($userId, $titre, $message, $donneesSupplementaires = null): ?Notification
    {
        return self::creerNotification($userId, $titre, $message, 'info', $donneesSupplementaires);
    }

    /**
     * Créer une notification de rappel
     */
    public static function creerRappel($userId, $titre, $message, $donneesSupplementaires = null): ?Notification
    {
        return self::creerNotification($userId, $titre, $message, 'rappel', $donneesSupplementaires);
    }

    /**
     * Créer une notification d'alerte
     */
    public static function creerAlerte($userId, $titre, $message, $donneesSupplementaires = null): ?Notification
    {
        return self::creerNotification($userId, $titre, $message, 'alerte', $donneesSupplementaires);
    }

    /**
     * Créer une notification de sanction
     */
    public static function creerSanction($userId, $titre, $message, $donneesSupplementaires = null): ?Notification
    {
        return self::creerNotification($userId, $titre, $message, 'sanction', $donneesSupplementaires);
    }

    /**
     * Méthode privée pour créer une notification
     */
    private static function creerNotification($userId, $titre, $message, $type, $donneesSupplementaires = null): ?Notification
    {
        try {
            return Notification::create([
                'user_id' => $userId,
                'titre' => $titre,
                'message' => $message,
                'type' => $type,
                'lue' => false,
                'date_envoi' => now(),
                'donnees_supplementaires' => $donneesSupplementaires
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur création notification:', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}

