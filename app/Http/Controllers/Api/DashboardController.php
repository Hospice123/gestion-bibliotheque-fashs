<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Livre;
use App\Models\Emprunt;
use App\Models\Reservation;
use App\Models\Sanction;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Tableau de bord principal
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if ($user->role === 'emprunteur') {
                return $this->getDashboardEmprunteur($user);
            } else {
                return $this->getDashboardAdmin($user);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du tableau de bord',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tableau de bord pour les emprunteurs
     */
    private function getDashboardEmprunteur(User $user): JsonResponse
    {
        $data = [
            'emprunts_actifs' => $user->emprunts()->enCours()->count(),
            'emprunts_en_retard' => $user->emprunts()->enRetard()->count(),
            'reservations_actives' => $user->reservations()->actives()->count(),
            'amendes_impayees' => $user->sanctions()->amendes()->actives()->sum('montant'),
            'limite_emprunts' => $user->limiteEmprunts(),
            'peut_emprunter' => $user->peutEmprunter(),
            
            // Emprunts récents
            'emprunts_recents' => $user->emprunts()
                ->with(['livre.categorie'])
                ->orderBy('date_emprunt', 'desc')
                ->limit(5)
                ->get(),
            
            // Réservations en attente
            'reservations_en_attente' => $user->reservations()
                ->with(['livre.categorie'])
                ->actives()
                ->orderBy('date_reservation', 'desc')
                ->get(),
            
            // Livres populaires
            'livres_populaires' => Livre::with(['categorie'])
                ->withCount('emprunts')
                ->orderBy('emprunts_count', 'desc')
                ->limit(5)
                ->get(),
            
            // Notifications non lues
            'notifications_non_lues' => $user->notifications()
                ->nonLues()
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
            
            // Prochaines échéances
            'prochaines_echeances' => $user->emprunts()
                ->with(['livre'])
                ->enCours()
                ->where('date_retour_prevue', '<=', now()->addDays(3))
                ->orderBy('date_retour_prevue')
                ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Tableau de bord pour les bibliothécaires et administrateurs
     */
    private function getDashboardAdmin(User $user): JsonResponse
    {
        $data = [
            // Statistiques générales
            'emprunts_actifs' => Emprunt::enCours()->count(),
            'emprunts_en_retard' => Emprunt::enRetard()->count(),
            'emprunts_aujourd_hui' => Emprunt::whereDate('date_emprunt', today())->count(),
            'retours_aujourd_hui' => Emprunt::whereDate('date_retour_effective', today())->count(),
            'emprunts_ce_mois' => Emprunt::whereMonth('date_emprunt', now()->month)->count(),
            
            'utilisateurs_actifs' => User::where('statut', 'actif')->count(),
            'nouveaux_utilisateurs_ce_mois' => User::whereMonth('created_at', now()->month)->count(),
            
            'livres_total' => Livre::count(),
            'livres_disponibles' => Livre::where('nombre_disponibles', '>', 0)->count(),
            'livres_empruntes' => Livre::where('nombre_disponibles', '<', \DB::raw('nombre_exemplaires'))->count(),
            
            'reservations_actives' => Reservation::actives()->count(),
            'reservations_en_attente' => Reservation::enAttente()->count(),
            
            'amendes_actives' => Sanction::amendes()->actives()->sum('montant'),
            'sanctions_actives' => Sanction::actives()->count(),
            
            // Activité récente
            'activite_recente' => $this->getActiviteRecente(),
            
            // Livres populaires
            'livres_populaires' => Livre::with(['categorie'])
                ->withCount('emprunts')
                ->orderBy('emprunts_count', 'desc')
                ->limit(10)
                ->get(),
            
            // Utilisateurs avec le plus d'emprunts
            'utilisateurs_actifs_emprunts' => User::with(['emprunts' => function($query) {
                    $query->enCours();
                }])
                ->whereHas('emprunts', function($query) {
                    $query->enCours();
                })
                ->withCount(['emprunts as emprunts_actifs_count' => function($query) {
                    $query->enCours();
                }])
                ->orderBy('emprunts_actifs_count', 'desc')
                ->limit(10)
                ->get(),
            
            // Emprunts en retard par utilisateur
            'emprunts_en_retard_details' => Emprunt::with(['user', 'livre'])
                ->enRetard()
                ->orderBy('date_retour_prevue')
                ->limit(20)
                ->get(),
            
            // Statistiques par catégorie
            'statistiques_categories' => \DB::table('categories')
                ->leftJoin('livres', 'categories.id', '=', 'livres.category_id')
                ->leftJoin('emprunts', 'livres.id', '=', 'emprunts.livre_id')
                ->select(
                    'categories.nom',
                    \DB::raw('COUNT(DISTINCT livres.id) as nombre_livres'),
                    \DB::raw('COUNT(emprunts.id) as nombre_emprunts')
                )
                ->groupBy('categories.id', 'categories.nom')
                ->orderBy('nombre_emprunts', 'desc')
                ->get(),
            
            // Évolution des emprunts (7 derniers jours)
            'evolution_emprunts' => $this->getEvolutionEmprunts(),
            
            // Alertes importantes
            'alertes' => $this->getAlertes()
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Statistiques détaillées
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!in_array($user->role, ['bibliothecaire', 'administrateur'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            $stats = [
                // Statistiques générales
                'general' => [
                    'total_livres' => Livre::count(),
                    'total_utilisateurs' => User::count(),
                    'total_emprunts' => Emprunt::count(),
                    'total_reservations' => Reservation::count(),
                    'total_sanctions' => Sanction::count(),
                ],
                
                // Statistiques par période
                'par_periode' => [
                    'emprunts_aujourd_hui' => Emprunt::whereDate('date_emprunt', today())->count(),
                    'emprunts_cette_semaine' => Emprunt::whereBetween('date_emprunt', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                    'emprunts_ce_mois' => Emprunt::whereMonth('date_emprunt', now()->month)->count(),
                    'emprunts_cette_annee' => Emprunt::whereYear('date_emprunt', now()->year)->count(),
                ],
                
                // Taux et ratios
                'taux' => [
                    'taux_occupation' => $this->calculerTauxOccupation(),
                    'taux_retard' => $this->calculerTauxRetard(),
                    'duree_moyenne_emprunt' => $this->calculerDureeMoyenneEmprunt(),
                    'livres_par_utilisateur' => $this->calculerLivresParUtilisateur(),
                ],
                
                // Top 10
                'top_livres' => Livre::withCount('emprunts')
                    ->orderBy('emprunts_count', 'desc')
                    ->limit(10)
                    ->get(['id', 'titre', 'auteur', 'emprunts_count']),
                
                'top_categories' => \DB::table('categories')
                    ->leftJoin('livres', 'categories.id', '=', 'livres.category_id')
                    ->leftJoin('emprunts', 'livres.id', '=', 'emprunts.livre_id')
                    ->select('categories.nom', \DB::raw('COUNT(emprunts.id) as emprunts_count'))
                    ->groupBy('categories.id', 'categories.nom')
                    ->orderBy('emprunts_count', 'desc')
                    ->limit(10)
                    ->get(),
                
                'top_utilisateurs' => User::withCount('emprunts')
                    ->where('role', 'emprunteur')
                    ->orderBy('emprunts_count', 'desc')
                    ->limit(10)
                    ->get(['id', 'nom', 'prenom', 'emprunts_count']),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir l'activité récente
     */
    private function getActiviteRecente(): array
    {
        $activites = [];
        
        // Emprunts récents
        $emprunts = Emprunt::with(['user', 'livre'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        foreach ($emprunts as $emprunt) {
            $activites[] = [
                'type' => 'emprunt',
                'description' => "{$emprunt->user->nom_complet} a emprunté \"{$emprunt->livre->titre}\"",
                'date' => $emprunt->created_at->diffForHumans(),
                'timestamp' => $emprunt->created_at
            ];
        }
        
        // Retours récents
        $retours = Emprunt::with(['user', 'livre'])
            ->where('statut', 'retourne')
            ->whereNotNull('date_retour_effective')
            ->orderBy('date_retour_effective', 'desc')
            ->limit(5)
            ->get();
        
        foreach ($retours as $retour) {
            $activites[] = [
                'type' => 'retour',
                'description' => "{$retour->user->nom_complet} a retourné \"{$retour->livre->titre}\"",
                'date' => $retour->date_retour_effective->diffForHumans(),
                'timestamp' => $retour->date_retour_effective
            ];
        }
        
        // Trier par timestamp et limiter
        usort($activites, function($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });
        
        return array_slice($activites, 0, 10);
    }

    /**
     * Obtenir l'évolution des emprunts
     */
    private function getEvolutionEmprunts(): array
    {
        $evolution = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = Emprunt::whereDate('date_emprunt', $date)->count();
            
            $evolution[] = [
                'date' => $date->format('Y-m-d'),
                'jour' => $date->format('D'),
                'emprunts' => $count
            ];
        }
        
        return $evolution;
    }

    /**
     * Obtenir les alertes importantes
     */
    private function getAlertes(): array
    {
        $alertes = [];
        
        // Livres en retard
        $retards = Emprunt::enRetard()->count();
        if ($retards > 0) {
            $alertes[] = [
                'type' => 'warning',
                'message' => "{$retards} livre(s) en retard",
                'action' => 'Voir les emprunts en retard'
            ];
        }
        
        // Amendes impayées
        $amendes = Sanction::amendes()->actives()->sum('montant');
        if ($amendes > 0) {
            $alertes[] = [
                'type' => 'info',
                'message' => "{$amendes}FCFA d'amendes à recouvrer",
                'action' => 'Voir les sanctions'
            ];
        }
        
        // Livres peu disponibles
        $livresPeuDisponibles = Livre::where('nombre_disponibles', '<=', 1)
            ->where('nombre_disponibles', '>', 0)
            ->count();
        
        if ($livresPeuDisponibles > 0) {
            $alertes[] = [
                'type' => 'warning',
                'message' => "{$livresPeuDisponibles} livre(s) avec peu d'exemplaires disponibles",
                'action' => 'Voir le catalogue'
            ];
        }
        
        return $alertes;
    }

    /**
     * Calculer le taux d'occupation
     */
    private function calculerTauxOccupation(): float
    {
        $totalExemplaires = Livre::sum('nombre_exemplaires');
        $exemplairesEmpruntes = Livre::sum(\DB::raw('nombre_exemplaires - nombre_disponibles'));
        
        return $totalExemplaires > 0 ? round(($exemplairesEmpruntes / $totalExemplaires) * 100, 2) : 0;
    }

    /**
     * Calculer le taux de retard
     */
    private function calculerTauxRetard(): float
    {
        $totalEmprunts = Emprunt::count();
        $empruntsEnRetard = Emprunt::enRetard()->count();
        
        return $totalEmprunts > 0 ? round(($empruntsEnRetard / $totalEmprunts) * 100, 2) : 0;
    }

    /**
     * Calculer la durée moyenne d'emprunt
     */
    private function calculerDureeMoyenneEmprunt(): float
    {
        $empruntsRetournes = Emprunt::where('statut', 'retourne')
            ->whereNotNull('date_retour_effective')
            ->get();
        
        if ($empruntsRetournes->isEmpty()) {
            return 0;
        }
        
        $totalJours = $empruntsRetournes->sum(function ($emprunt) {
            return $emprunt->date_emprunt->diffInDays($emprunt->date_retour_effective);
        });
        
        return round($totalJours / $empruntsRetournes->count(), 1);
    }

    /**
     * Calculer le nombre moyen de livres par utilisateur
     */
    private function calculerLivresParUtilisateur(): float
    {
        $totalUtilisateurs = User::where('role', 'emprunteur')->count();
        $totalEmprunts = Emprunt::count();
        
        return $totalUtilisateurs > 0 ? round($totalEmprunts / $totalUtilisateurs, 1) : 0;
    }
}

