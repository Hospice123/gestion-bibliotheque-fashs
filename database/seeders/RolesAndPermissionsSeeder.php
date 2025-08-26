<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 1. Réinitialiser le cache des permissions de Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('🚀 Début de la création des rôles, permissions et utilisateurs...');

        // 2. Créer les permissions de base
        $this->createPermissions();

        // 3. Créer les rôles et assigner les permissions
        $roles = $this->createRoles();

        // 4. Créer les utilisateurs de base avec leurs rôles
        $this->createBaseUsers($roles);

        $this->command->info('✅ Rôles, permissions et utilisateurs créés avec succès !');
    }

    /**
     * Créer les permissions de base
     */
    private function createPermissions()
    {
        $this->command->info('📋 Création des permissions...');

        $permissions = [
            // Gestion des utilisateurs
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            'manage_user_roles',

            // Gestion des livres
            'view_books',
            'create_books',
            'edit_books',
            'delete_books',
            'manage_categories',

            // Gestion des emprunts
            'view_loans',
            'create_loans',
            'return_books',
            'extend_loans',
            'view_loan_history',

            // Gestion de la bibliothèque
            'view_dashboard',
            'view_reports',
            'manage_library_settings',
            'backup_data',

            // Permissions spéciales
            'access_admin_panel',
            'view_system_logs',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate([
                'name' => $name, 
                'guard_name' => 'web'
            ]);
        }

        $this->command->info("✅ " . count($permissions) . " permissions créées");
    }

    /**
     * Créer les rôles et assigner les permissions
     */
    private function createRoles()
    {
        $this->command->info('👥 Création des rôles...');

        // Rôle Administrateur - Accès complet
        $adminRole = Role::firstOrCreate(['name' => 'administrateur', 'guard_name' => 'web']);
        $adminRole->givePermissionTo(Permission::all()); // Toutes les permissions

        // Rôle Bibliothécaire - Gestion des livres et emprunts
        $bibliothecaireRole = Role::firstOrCreate(['name' => 'bibliothecaire', 'guard_name' => 'web']);
        $bibliothecaireRole->givePermissionTo([
            'view_users',
            'view_books', 'create_books', 'edit_books', 'delete_books', 'manage_categories',
            'view_loans', 'create_loans', 'return_books', 'extend_loans', 'view_loan_history',
            'view_dashboard', 'view_reports',
        ]);

        // Rôle Emprunteur - Consultation et emprunt uniquement
        $emprunteurRole = Role::firstOrCreate(['name' => 'emprunteur', 'guard_name' => 'web']);
        $emprunteurRole->givePermissionTo([
            'view_books',
            'view_loans', 'view_loan_history',
            'view_dashboard',
        ]);

        $this->command->info('✅ 3 rôles créés avec leurs permissions');

        return [
            'administrateur' => $adminRole,
            'bibliothecaire' => $bibliothecaireRole,
            'emprunteur' => $emprunteurRole,
        ];
    }

    /**
     * Créer les utilisateurs de base
     */
    private function createBaseUsers($roles)
    {
        $this->command->info('👤 Création des utilisateurs de base...');

        // Utilisateur Administrateur Principal
        $adminUser = User::firstOrCreate(
            ['email' => 'soderoselio@gmail.com'],
            [
                'nom' => 'SODE',
                'prenom' => 'Hospice',
                'email' => 'soderoselio@gmail.com',
                'password' => Hash::make('sode123'),
                'numero_etudiant' => null,
                'telephone' => '01 90 57 38 95',
                'adresse' => 'Université d\'Abomey-Calavi',
                'statut' => 'actif', // Utilisation de la colonne 'statut' existante
                'email_verified_at' => now(),
            ]
        );

        if (!$adminUser->hasRole('administrateur')) {
            $adminUser->assignRole('administrateur');
            $this->command->info("✅ Rôle 'administrateur' assigné à {$adminUser->prenom} {$adminUser->nom}");
        }

        // Utilisateur Bibliothécaire Principal
        $bibliothecaireUser = User::firstOrCreate(
            ['email' => 'viallysode@gmail.com'],
            [
                'nom' => 'SODE',
                'prenom' => 'Vially',
                'email' => 'viallysode@gmail.com',
                'password' => Hash::make('vially123'),
                'numero_etudiant' => null,
                'telephone' => '01 23 45 67 90',
                'adresse' => 'Université d\'Abomey-Calavi',
                'statut' => 'actif', // Utilisation de la colonne 'statut' existante
                'email_verified_at' => now(),
            ]
        );

        if (!$bibliothecaireUser->hasRole('bibliothecaire')) {
            $bibliothecaireUser->assignRole('bibliothecaire');
            $this->command->info("✅ Rôle 'bibliothecaire' assigné à {$bibliothecaireUser->prenom} {$bibliothecaireUser->nom}");
        }

        // Utilisateur Emprunteur de Test
        $emprunteurUser = User::firstOrCreate(
            ['email' => 'etudiant.test@uac.bj'],
            [
                'nom' => 'ETUDIANT',
                'prenom' => 'Test',
                'email' => 'etudiant.test@uac.bj',
                'password' => Hash::make('etudiant123'),
                'numero_etudiant' => '2024001',
                'telephone' => '01 11 22 33 44',
                'adresse' => 'Campus Universitaire d\'Abomey-Calavi',
                'statut' => 'actif', // Utilisation de la colonne 'statut' existante
                'email_verified_at' => now(),
            ]
        );

        if (!$emprunteurUser->hasRole('emprunteur')) {
            $emprunteurUser->assignRole('emprunteur');
            $this->command->info("✅ Rôle 'emprunteur' assigné à {$emprunteurUser->prenom} {$emprunteurUser->nom}");
        }

        // Utilisateurs supplémentaires pour les tests
        $this->createAdditionalTestUsers();

        $this->command->info('✅ Utilisateurs de base créés avec succès');
    }

    /**
     * Créer des utilisateurs supplémentaires pour les tests
     */
    private function createAdditionalTestUsers()
    {
        $this->command->info('👥 Création d\'utilisateurs de test supplémentaires...');

        $testUsers = [
            [
                'nom' => 'SODE',
                'prenom' => 'Valdano',
                'email' => 'sodevaldano@gmail.com',
                'password' => 'valdano123@',
                'role' => 'administrateur',
                'telephone' => '95993235',
                'statut' => 'actif',
            ],
            [
                'nom' => 'BIBLIOTHECAIRE',
                'prenom' => 'Marie',
                'email' => 'marie@gmail.com',
                'password' => 'marie123',
                'role' => 'bibliothecaire',
                'telephone' => '01 00 00 00 02',
                'statut' => 'actif',
            ],
            [
                'nom' => 'KOUASSI',
                'prenom' => 'Jean',
                'email' => 'jean.kouassi@etudiant.uac.bj',
                'password' => 'jean123',
                'role' => 'emprunteur',
                'numero_etudiant' => '2024002',
                'telephone' => '01 00 00 00 03',
                'statut' => 'actif',
            ],
            [
                'nom' => 'ADJOVI',
                'prenom' => 'Fatima',
                'email' => 'fatima.adjovi@etudiant.uac.bj',
                'password' => 'fatima123',
                'role' => 'emprunteur',
                'numero_etudiant' => '2024003',
                'telephone' => '01 00 00 00 04',
                'statut' => 'actif',
            ],
            [
                'nom' => 'UTILISATEUR',
                'prenom' => 'Inactif',
                'email' => 'inactif.test@uac.bj',
                'password' => 'inactif123',
                'role' => 'emprunteur',
                'numero_etudiant' => '2024004',
                'telephone' => '01 00 00 00 05',
                'statut' => 'inactif', // Exemple d'utilisateur inactif pour les tests
            ],
        ];

        foreach ($testUsers as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'nom' => $userData['nom'],
                    'prenom' => $userData['prenom'],
                    'email' => $userData['email'],
                    'password' => Hash::make($userData['password']),
                    'numero_etudiant' => $userData['numero_etudiant'] ?? null,
                    'telephone' => $userData['telephone'],
                    'adresse' => 'Université d\'Abomey-Calavi',
                    'statut' => $userData['statut'], // Utilisation de la colonne 'statut' existante
                    'email_verified_at' => now(),
                ]
            );

            if (!$user->hasRole($userData['role'])) {
                $user->assignRole($userData['role']);
            }
        }

        $this->command->info('✅ ' . count($testUsers) . ' utilisateurs de test créés');
    }

    /**
     * Afficher un résumé des créations
     */
    private function displaySummary()
    {
        $this->command->info('');
        $this->command->info('📊 RÉSUMÉ DES CRÉATIONS :');
        $this->command->info('========================');
        
        $roleCount = Role::count();
        $permissionCount = Permission::count();
        $userCount = User::count();
        
        $this->command->info("👥 Rôles créés : {$roleCount}");
        $this->command->info("📋 Permissions créées : {$permissionCount}");
        $this->command->info("👤 Utilisateurs créés : {$userCount}");
        
        $this->command->info('');
        $this->command->info('🔐 COMPTES DE CONNEXION :');
        $this->command->info('========================');
        $this->command->info('Administrateur : soderoselio@gmail.com / sode123');
        $this->command->info('Bibliothécaire : viallysode@gmail.com / vially123');
        $this->command->info('Étudiant Test : etudiant.test@uac.bj / etudiant123');
        $this->command->info('Admin Valdano : sodevaldano@gmail.com / valdano123@');
        $this->command->info('');
    }
}

