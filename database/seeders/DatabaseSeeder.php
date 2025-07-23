<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Categorie;
use App\Models\Livre;
use App\Models\Emprunt;
use App\Models\Reservation;
use App\Models\Sanction;
use App\Models\Notification;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Créer les utilisateurs
        $this->createUsers();
        
        // Créer les catégories
        $this->createCategories();
        
        // Créer les livres
        $this->createLivres();
        
        // Créer quelques emprunts de test
        $this->createEmprunts();
        
        // Créer quelques réservations de test
        $this->createReservations();
        
        // Créer quelques sanctions de test
        $this->createSanctions();
        
        // Créer quelques notifications de test
        $this->createNotifications();
    }

    private function createUsers(): void
    {
        // Administrateur
        User::create([
            'nom' => 'Admin',
            'prenom' => 'Système',
            'email' => 'admin@bibliotheque.fr',
            'password' => Hash::make('admin123'),
            'role' => 'administrateur',
            'numero_etudiant' => null,
            'telephone' => '01 23 45 67 89',
            'adresse' => 'Université de Paris, 75005 Paris',
            'statut' => 'actif'
        ]);

        // Bibliothécaire
        User::create([
            'nom' => 'Martin',
            'prenom' => 'Sophie',
            'email' => 'sophie.martin@bibliotheque.fr',
            'password' => Hash::make('biblio123'),
            'role' => 'bibliothecaire',
            'numero_etudiant' => null,
            'telephone' => '01 23 45 67 90',
            'adresse' => 'Université de Paris, 75005 Paris',
            'statut' => 'actif'
        ]);

        // Emprunteurs (étudiants)
        $etudiants = [
            [
                'nom' => 'Dupont',
                'prenom' => 'Jean',
                'email' => 'jean.dupont@etudiant.fr',
                'numero_etudiant' => 'ETU001',
                'telephone' => '06 12 34 56 78',
                'adresse' => '123 Rue de la Paix, 75001 Paris'
            ],
            [
                'nom' => 'Durand',
                'prenom' => 'Marie',
                'email' => 'marie.durand@etudiant.fr',
                'numero_etudiant' => 'ETU002',
                'telephone' => '06 12 34 56 79',
                'adresse' => '456 Avenue des Champs, 75008 Paris'
            ],
            [
                'nom' => 'Moreau',
                'prenom' => 'Pierre',
                'email' => 'pierre.moreau@etudiant.fr',
                'numero_etudiant' => 'ETU003',
                'telephone' => '06 12 34 56 80',
                'adresse' => '789 Boulevard Saint-Germain, 75006 Paris'
            ],
            [
                'nom' => 'Leroy',
                'prenom' => 'Claire',
                'email' => 'claire.leroy@etudiant.fr',
                'numero_etudiant' => 'ETU004',
                'telephone' => '06 12 34 56 81',
                'adresse' => '321 Rue de Rivoli, 75004 Paris'
            ],
            [
                'nom' => 'Bernard',
                'prenom' => 'Lucas',
                'email' => 'lucas.bernard@etudiant.fr',
                'numero_etudiant' => 'ETU005',
                'telephone' => '06 12 34 56 82',
                'adresse' => '654 Rue de la République, 75011 Paris'
            ]
        ];

        foreach ($etudiants as $etudiant) {
            User::create([
                'nom' => $etudiant['nom'],
                'prenom' => $etudiant['prenom'],
                'email' => $etudiant['email'],
                'password' => Hash::make('password123'),
                'role' => 'emprunteur',
                'numero_etudiant' => $etudiant['numero_etudiant'],
                'telephone' => $etudiant['telephone'],
                'adresse' => $etudiant['adresse'],
                'statut' => 'actif'
            ]);
        }
    }

    private function createCategories(): void
    {
        $categories = [
            ['nom' => 'Informatique', 'code' => 'INFO', 'description' => 'Livres sur l\'informatique, programmation, réseaux'],
            ['nom' => 'Mathématiques', 'code' => 'MATH', 'description' => 'Livres de mathématiques, statistiques, algèbre'],
            ['nom' => 'Physique', 'code' => 'PHYS', 'description' => 'Livres de physique, mécanique, thermodynamique'],
            ['nom' => 'Littérature', 'code' => 'LITT', 'description' => 'Romans, poésie, théâtre, littérature française et étrangère'],
            ['nom' => 'Histoire', 'code' => 'HIST', 'description' => 'Livres d\'histoire, géographie, civilisations'],
            ['nom' => 'Sciences', 'code' => 'SCI', 'description' => 'Sciences générales, biologie, chimie'],
            ['nom' => 'Économie', 'code' => 'ECO', 'description' => 'Économie, gestion, finance, management'],
            ['nom' => 'Philosophie', 'code' => 'PHIL', 'description' => 'Philosophie, éthique, logique']
        ];

        foreach ($categories as $categorie) {
            Categorie::create($categorie);
        }
    }

    private function createLivres(): void
    {
        $livres = [
            // Informatique
            [
                'titre' => 'Clean Code: A Handbook of Agile Software Craftsmanship',
                'auteur' => 'Robert C. Martin',
                'isbn' => '9780132350884',
                'editeur' => 'Prentice Hall',
                'annee_publication' => 2008,
                'nombre_pages' => 464,
                'langue' => 'en',
                'resume' => 'Un guide pour écrire du code propre et maintenable.',
                'category_id' => 1,
                'nombre_exemplaires' => 3,
                'nombre_disponibles' => 3,
                'emplacement' => 'A1-INFO-001'
            ],
            [
                'titre' => 'Design Patterns: Elements of Reusable Object-Oriented Software',
                'auteur' => 'Gang of Four',
                'isbn' => '9780201633610',
                'editeur' => 'Addison-Wesley',
                'annee_publication' => 1994,
                'nombre_pages' => 395,
                'langue' => 'en',
                'resume' => 'Les patterns de conception fondamentaux en programmation orientée objet.',
                'category_id' => 1,
                'nombre_exemplaires' => 2,
                'nombre_disponibles' => 2,
                'emplacement' => 'A1-INFO-002'
            ],
            [
                'titre' => 'Algorithmes et structures de données',
                'auteur' => 'Thomas H. Cormen',
                'isbn' => '9782100545261',
                'editeur' => 'Dunod',
                'annee_publication' => 2010,
                'nombre_pages' => 1292,
                'langue' => 'fr',
                'resume' => 'Référence complète sur les algorithmes et structures de données.',
                'category_id' => 1,
                'nombre_exemplaires' => 4,
                'nombre_disponibles' => 4,
                'emplacement' => 'A1-INFO-003'
            ],

            // Mathématiques
            [
                'titre' => 'Analyse mathématique I',
                'auteur' => 'Vladimir Zorich',
                'isbn' => '9782759800414',
                'editeur' => 'EDP Sciences',
                'annee_publication' => 2002,
                'nombre_pages' => 632,
                'langue' => 'fr',
                'resume' => 'Cours complet d\'analyse mathématique niveau universitaire.',
                'category_id' => 2,
                'nombre_exemplaires' => 5,
                'nombre_disponibles' => 5,
                'emplacement' => 'B1-MATH-001'
            ],
            [
                'titre' => 'Algèbre linéaire',
                'auteur' => 'Serge Lang',
                'isbn' => '9782100043637',
                'editeur' => 'Dunod',
                'annee_publication' => 2004,
                'nombre_pages' => 352,
                'langue' => 'fr',
                'resume' => 'Introduction complète à l\'algèbre linéaire.',
                'category_id' => 2,
                'nombre_exemplaires' => 3,
                'nombre_disponibles' => 3,
                'emplacement' => 'B1-MATH-002'
            ],

            // Physique
            [
                'titre' => 'Mécanique quantique',
                'auteur' => 'Claude Cohen-Tannoudji',
                'isbn' => '9782705658861',
                'editeur' => 'Hermann',
                'annee_publication' => 1997,
                'nombre_pages' => 890,
                'langue' => 'fr',
                'resume' => 'Cours de référence en mécanique quantique.',
                'category_id' => 3,
                'nombre_exemplaires' => 2,
                'nombre_disponibles' => 2,
                'emplacement' => 'C1-PHYS-001'
            ],

            // Littérature
            [
                'titre' => 'Les Misérables',
                'auteur' => 'Victor Hugo',
                'isbn' => '9782070409228',
                'editeur' => 'Gallimard',
                'annee_publication' => 1862,
                'nombre_pages' => 1664,
                'langue' => 'fr',
                'resume' => 'Chef-d\'œuvre de la littérature française du XIXe siècle.',
                'category_id' => 4,
                'nombre_exemplaires' => 6,
                'nombre_disponibles' => 6,
                'emplacement' => 'D1-LITT-001'
            ],
            [
                'titre' => 'L\'Étranger',
                'auteur' => 'Albert Camus',
                'isbn' => '9782070360024',
                'editeur' => 'Gallimard',
                'annee_publication' => 1942,
                'nombre_pages' => 186,
                'langue' => 'fr',
                'resume' => 'Roman emblématique de la littérature existentialiste.',
                'category_id' => 4,
                'nombre_exemplaires' => 4,
                'nombre_disponibles' => 4,
                'emplacement' => 'D1-LITT-002'
            ],

            // Histoire
            [
                'titre' => 'Histoire de France',
                'auteur' => 'Jacques Bainville',
                'isbn' => '9782213594500',
                'editeur' => 'Fayard',
                'annee_publication' => 1924,
                'nombre_pages' => 512,
                'langue' => 'fr',
                'resume' => 'Synthèse de l\'histoire de France des origines au XXe siècle.',
                'category_id' => 5,
                'nombre_exemplaires' => 3,
                'nombre_disponibles' => 3,
                'emplacement' => 'E1-HIST-001'
            ],

            // Sciences
            [
                'titre' => 'Biologie moléculaire de la cellule',
                'auteur' => 'Bruce Alberts',
                'isbn' => '9782257206909',
                'editeur' => 'Médecine Sciences Flammarion',
                'annee_publication' => 2011,
                'nombre_pages' => 1392,
                'langue' => 'fr',
                'resume' => 'Référence en biologie cellulaire et moléculaire.',
                'category_id' => 6,
                'nombre_exemplaires' => 2,
                'nombre_disponibles' => 2,
                'emplacement' => 'F1-SCI-001'
            ],

            // Économie
            [
                'titre' => 'Principes d\'économie',
                'auteur' => 'Gregory Mankiw',
                'isbn' => '9782804162825',
                'editeur' => 'De Boeck',
                'annee_publication' => 2019,
                'nombre_pages' => 896,
                'langue' => 'fr',
                'resume' => 'Manuel de référence en économie pour étudiants.',
                'category_id' => 7,
                'nombre_exemplaires' => 4,
                'nombre_disponibles' => 4,
                'emplacement' => 'G1-ECO-001'
            ],

            // Philosophie
            [
                'titre' => 'Critique de la raison pure',
                'auteur' => 'Emmanuel Kant',
                'isbn' => '9782070108930',
                'editeur' => 'Gallimard',
                'annee_publication' => 1781,
                'nombre_pages' => 584,
                'langue' => 'fr',
                'resume' => 'Œuvre majeure de la philosophie moderne.',
                'category_id' => 8,
                'nombre_exemplaires' => 2,
                'nombre_disponibles' => 2,
                'emplacement' => 'H1-PHIL-001'
            ]
        ];

        foreach ($livres as $livre) {
            Livre::create($livre);
        }
    }

    private function createEmprunts(): void
    {
        // Quelques emprunts en cours
        Emprunt::create([
            'user_id' => 3, // Jean Dupont
            'livre_id' => 1, // Clean Code
            'date_emprunt' => now()->subDays(5),
            'date_retour_prevue' => now()->addDays(9),
            'statut' => 'en_cours'
        ]);

        Emprunt::create([
            'user_id' => 4, // Marie Durand
            'livre_id' => 8, // Les Misérables
            'date_emprunt' => now()->subDays(10),
            'date_retour_prevue' => now()->addDays(4),
            'statut' => 'en_cours'
        ]);

        // Un emprunt en retard
        Emprunt::create([
            'user_id' => 5, // Pierre Moreau
            'livre_id' => 4, // Analyse mathématique
            'date_emprunt' => now()->subDays(20),
            'date_retour_prevue' => now()->subDays(6),
            'statut' => 'en_cours'
        ]);

        // Quelques emprunts retournés
        Emprunt::create([
            'user_id' => 6, // Claire Leroy
            'livre_id' => 9, // L'Étranger
            'date_emprunt' => now()->subDays(30),
            'date_retour_prevue' => now()->subDays(16),
            'date_retour_effective' => now()->subDays(18),
            'statut' => 'retourne'
        ]);

        // Mettre à jour la disponibilité des livres empruntés
        $livre1 = Livre::find(1);
        $livre1->decrement('nombre_disponibles');

        $livre8 = Livre::find(8);
        $livre8->decrement('nombre_disponibles');

        $livre4 = Livre::find(4);
        $livre4->decrement('nombre_disponibles');
    }

    private function createReservations(): void
    {
        // Réservation pour un livre emprunté
        Reservation::create([
            'user_id' => 7, // Lucas Bernard
            'livre_id' => 1, // Clean Code (déjà emprunté)
            'date_reservation' => now()->subDays(2),
            'date_expiration' => now()->addDays(5),
            'statut' => 'active',
            'position_file' => 1
        ]);

        Reservation::create([
            'user_id' => 6, // Claire Leroy
            'livre_id' => 1, // Clean Code
            'date_reservation' => now()->subDays(1),
            'date_expiration' => now()->addDays(6),
            'statut' => 'active',
            'position_file' => 2
        ]);
    }

    private function createSanctions(): void
    {
        // Amende pour retard
        Sanction::create([
            'user_id' => 5, // Pierre Moreau (qui a un livre en retard)
            'emprunt_id' => 3,
            'type' => 'amende',
            'montant' => 3.00,
            'raison' => 'Retard de 6 jours pour le livre "Analyse mathématique I"',
            'statut' => 'active',
            'appliquee_par' => 2 // Sophie Martin (bibliothécaire)
        ]);

        // Avertissement
        Sanction::create([
            'user_id' => 4, // Marie Durand
            'type' => 'avertissement',
            'raison' => 'Retards répétés dans les retours de livres',
            'statut' => 'active',
            'appliquee_par' => 2
        ]);
    }

    private function createNotifications(): void
    {
        // Notification de rappel
        Notification::create([
            'user_id' => 4, // Marie Durand
            'titre' => 'Rappel de retour',
            'message' => 'N\'oubliez pas de retourner le livre "Les Misérables" avant le ' . now()->addDays(4)->format('d/m/Y') . '.',
            'type' => 'rappel',
            'lue' => false
        ]);

        // Notification d'alerte pour retard
        Notification::create([
            'user_id' => 5, // Pierre Moreau
            'titre' => 'Livre en retard',
            'message' => 'Le livre "Analyse mathématique I" est en retard de 6 jour(s). Une amende de 3,00€ a été appliquée.',
            'type' => 'alerte',
            'lue' => false
        ]);

        // Notification de disponibilité
        Notification::create([
            'user_id' => 7, // Lucas Bernard
            'titre' => 'Livre bientôt disponible',
            'message' => 'Le livre "Clean Code" que vous avez réservé sera bientôt disponible. Vous êtes en position 1 dans la file d\'attente.',
            'type' => 'info',
            'lue' => false
        ]);

        // Notification de bienvenue pour les nouveaux utilisateurs
        foreach ([3, 4, 5, 6, 7] as $userId) {
            Notification::create([
                'user_id' => $userId,
                'titre' => 'Bienvenue à la bibliothèque universitaire',
                'message' => 'Votre compte a été créé avec succès. Vous pouvez maintenant emprunter jusqu\'à 5 livres simultanément.',
                'type' => 'info',
                'lue' => true,
                'date_lecture' => now()->subDays(rand(1, 30))
            ]);
        }
    }
}

