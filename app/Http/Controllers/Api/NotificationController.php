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
     * Récupérer toutes les notifications de l'utilisateur connecté
     */
    public function index(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $notifications = Notification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'message' => 'Notifications récupérées avec succès'
            ]);
        } catch (\Exception $e) {
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
                ->where('statut', 'non_lue')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'count' => $notifications->count(),
                'message' => 'Notifications non lues récupérées avec succès'
            ]);
        } catch (\Exception $e) {
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
                'type' => 'required|in:info,warning,success,error',
                'priorite' => 'nullable|in:basse,normale,haute,urgente'
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
                'priorite' => $request->priorite ?? 'normale',
                'statut' => 'non_lue',
                'date_envoi' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => $notification,
                'message' => 'Notification créée avec succès'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la notification',
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

            $notification->update([
                'statut' => 'lue',
                'date_lecture' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => $notification,
                'message' => 'Notification marquée comme lue'
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
                ->where('statut', 'non_lue')
                ->update([
                    'statut' => 'lue',
                    'date_lecture' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => "Toutes les notifications ont été marquées comme lues",
                'updated_count' => $updated
            ]);
        } catch (\Exception $e) {
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
                ->where('statut', 'lue')
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notifications lues supprimées avec succès',
                'deleted_count' => $deleted
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression des notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

