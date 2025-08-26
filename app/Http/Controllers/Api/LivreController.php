<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Livre;
use App\Models\Categorie;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class LivreController extends Controller
{
    /**
     * Liste des livres avec pagination et filtres
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Livre::with(['categorie']);
             if ($request->has('statut') && $request->statut) {
        $query->where('statut', $request->statut);
    }


            // Recherche par terme
            if ($request->has('search') && !empty($request->search)) {
                $query->recherche($request->search);
            }

            // Filtre par catégorie
            if ($request->has('category_id') && !empty($request->category_id)) {
                $query->parCategorie($request->category_id);
            }

            // Filtre par auteur
            if ($request->has('auteur') && !empty($request->auteur)) {
                $query->parAuteur($request->auteur);
            }

            // Filtre par année
            if ($request->has('annee') && !empty($request->annee)) {
                $query->parAnnee($request->annee);
            }

            // Filtre par disponibilité
            if ($request->has('disponible') && $request->disponible === 'true') {
                $query->disponibles();
            }

            // Tri
            $sortBy = $request->get('sort_by', 'titre');
            $sortOrder = $request->get('sort_order', 'asc');
            
            if (in_array($sortBy, ['titre', 'auteur', 'annee_publication', 'created_at'])) {
                $query->orderBy($sortBy, $sortOrder);
            }

            $perPage = min($request->get('per_page', 15), 50);
            $livres = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'livres' => $livres->items(),
                    'pagination' => [
                        'current_page' => $livres->currentPage(),
                        'last_page' => $livres->lastPage(),
                        'per_page' => $livres->perPage(),
                        'total' => $livres->total(),
                        'from' => $livres->firstItem(),
                        'to' => $livres->lastItem()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livres',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher un livre spécifique
     */
    public function show($id): JsonResponse
    {
        try {
            $livre = Livre::with(['categorie', 'empruntsActifs.user', 'reservationsActives.user'])
                          ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'livre' => $livre,
                    'statistiques' => [
                        'nombre_emprunts_total' => $livre->emprunts()->count(),
                        'nombre_emprunts_actifs' => $livre->nombreEmpruntsActifs(),
                        'nombre_reservations' => $livre->nombreReservations(),
                        'taux_disponibilite' => $livre->tauxDisponibilite(),
                        'est_populaire' => $livre->estPopulaire()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Livre non trouvé',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Créer un nouveau livre (Bibliothécaire/Admin)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'titre' => 'required|string|max:500',
            'auteur' => 'required|string|max:255',
            'isbn' => 'nullable|string|max:20|unique:livres',
            'editeur' => 'nullable|string|max:255',
            'annee_publication' => 'nullable|integer|min:1000|max:' . date('Y'),
            'nombre_pages' => 'nullable|integer|min:1',
            'langue' => 'nullable|string|max:10',
            'resume' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'nombre_exemplaires' => 'required|integer|min:1',
            'emplacement' => 'nullable|string|max:100',
            'image_couverture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->except('image_couverture');
            $data['nombre_disponibles'] = $data['nombre_exemplaires'];

            // Gestion de l'image de couverture
            if ($request->hasFile('image_couverture')) {
                $imagePath = $request->file('image_couverture')->store('livres/couvertures', 'public');
                $data['image_couverture'] = $imagePath;
            }

            $livre = Livre::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Livre créé avec succès',
                'data' => ['livre' => $livre->load('categorie')]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du livre',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour un livre (Bibliothécaire/Admin)
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'titre' => 'sometimes|required|string|max:500',
            'auteur' => 'sometimes|required|string|max:255',
            'isbn' => 'sometimes|nullable|string|max:20|unique:livres,isbn,' . $id,
            'editeur' => 'sometimes|nullable|string|max:255',
            'annee_publication' => 'sometimes|nullable|integer|min:1000|max:' . date('Y'),
            'nombre_pages' => 'sometimes|nullable|integer|min:1',
            'langue' => 'sometimes|nullable|string|max:10',
            'resume' => 'sometimes|nullable|string',
            'category_id' => 'sometimes|required|exists:categories,id',
            'nombre_exemplaires' => 'sometimes|required|integer|min:1',
            'emplacement' => 'sometimes|nullable|string|max:100',
            'statut' => 'sometimes|in:disponible,indisponible,maintenance,emprunte,reserve,perdu',
            'image_couverture' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $livre = Livre::findOrFail($id);
            $data = $request->except('image_couverture');

            // Vérifier la cohérence des exemplaires
            if (isset($data['nombre_exemplaires'])) {
                $empruntsActifs = $livre->nombreEmpruntsActifs();
                if ($data['nombre_exemplaires'] < $empruntsActifs) {
                    return response()->json([
                        'success' => false,
                        'message' => "Impossible de réduire le nombre d'exemplaires en dessous du nombre d'emprunts actifs ({$empruntsActifs})"
                    ], 400);
                }
                $data['nombre_disponibles'] = $data['nombre_exemplaires'] - $empruntsActifs;
            }

            // Gestion de l'image de couverture
            if ($request->hasFile('image_couverture')) {
                // Supprimer l'ancienne image
                if ($livre->image_couverture) {
                    Storage::disk('public')->delete($livre->image_couverture);
                }
                $imagePath = $request->file('image_couverture')->store('livres/couvertures', 'public');
                $data['image_couverture'] = $imagePath;
            }

            $livre->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Livre mis à jour avec succès',
                'data' => ['livre' => $livre->load('categorie')]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du livre',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un livre (Admin uniquement)
     */
    public function destroy($id): JsonResponse
    {
        try {
            $livre = Livre::findOrFail($id);

            // Vérifier s'il y a des emprunts actifs
            if ($livre->nombreEmpruntsActifs() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer un livre avec des emprunts actifs'
                ], 400);
            }

            // Supprimer l'image de couverture
            if ($livre->image_couverture) {
                Storage::disk('public')->delete($livre->image_couverture);
            }

            $livre->delete();

            return response()->json([
                'success' => true,
                'message' => 'Livre supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du livre',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recherche avancée de livres
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:2',
            'type' => 'sometimes|in:titre,auteur,isbn,all',
            'category_id' => 'sometimes|exists:categories,id',
            'disponible_seulement' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = Livre::with(['categorie']);
            $terme = $request->q;
            $type = $request->get('type', 'all');

            switch ($type) {
                case 'titre':
                    $query->where('titre', 'LIKE', "%{$terme}%");
                    break;
                case 'auteur':
                    $query->where('auteur', 'LIKE', "%{$terme}%");
                    break;
                case 'isbn':
                    $query->where('isbn', 'LIKE', "%{$terme}%");
                    break;
                default:
                    $query->recherche($terme);
            }

            if ($request->has('category_id')) {
                $query->parCategorie($request->category_id);
            }

            if ($request->get('disponible_seulement', false)) {
                $query->disponibles();
            }

            $livres = $query->limit(20)->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'livres' => $livres,
                    'total' => $livres->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les livres populaires
     */
    public function populaires(): JsonResponse
    {
        try {
            $livres = Livre::with(['categorie'])
                          ->withCount('emprunts')
                          ->orderBy('emprunts_count', 'desc')
                          ->limit(10)
                          ->get();

            return response()->json([
                'success' => true,
                'data' => ['livres' => $livres]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livres populaires',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les nouveautés
     */
    public function nouveautes(): JsonResponse
    {
        try {
            $livres = Livre::with(['categorie'])
                          ->orderBy('created_at', 'desc')
                          ->limit(10)
                          ->get();

            return response()->json([
                'success' => true,
                'data' => ['livres' => $livres]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des nouveautés',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les catégories
     */
    public function categories(): JsonResponse
    {
        try {
            $categories = Categorie::withCount('livres')->get();

            return response()->json([
                'success' => true,
                'data' => ['categories' => $categories]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des catégories',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

