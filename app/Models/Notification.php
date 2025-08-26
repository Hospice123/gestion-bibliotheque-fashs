<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Notification extends Model
{
    use HasFactory;

    /**
     * Champs autorisés pour l'assignation en masse
     * Conformes au schéma de la table
     */
    protected $fillable = [
        'user_id',
        'titre',
        'message',
        'type',
        'lue',
        'date_envoi',
        'date_lecture',
        'donnees_supplementaires'
    ];

    /**
     * Types de données pour les attributs
     */
    protected $casts = [
        'lue' => 'boolean',
        'date_envoi' => 'datetime',
        'date_lecture' => 'datetime',
        'donnees_supplementaires' => 'array',
    ];

    /**
     * Types de notifications autorisés selon le schéma
     */
    const TYPES_AUTORISES = ['info', 'rappel', 'alerte', 'sanction','success'];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeLues($query)
    {
        return $query->where('lue', true);
    }

    public function scopeNonLues($query)
    {
        return $query->where('lue', false);
    }

    public function scopeParType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeParUtilisateur($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecentes($query, $jours = 7)
    {
        return $query->where('date_envoi', '>=', now()->subDays($jours));
    }

    public function scopeParPeriode($query, $dateDebut, $dateFin)
    {
        return $query->whereBetween('date_envoi', [$dateDebut, $dateFin]);
    }

    public function scopeImportantes($query)
    {
        return $query->whereIn('type', ['alerte', 'sanction']);
    }

    public function scopeInformatives($query)
    {
        return $query->whereIn('type', ['info', 'rappel']);
    }

    // Accesseurs
    public function getTempsEcouleAttribute()
    {
        return $this->date_envoi->diffForHumans();
    }

    public function getTypeLibelleAttribute(): string
    {
        return match($this->type) {
            'info' => 'Information',
            'rappel' => 'Rappel',
            'alerte' => 'Alerte',
            'sanction' => 'Sanction',
            default => 'Notification'
        };
    }

    public function getIconeAttribute(): string
    {
        return match($this->type) {
            'info' => 'info-circle',
            'rappel' => 'clock',
            'alerte' => 'exclamation-triangle',
            'sanction' => 'ban',
            default => 'bell'
        };
    }

    public function getCouleurAttribute(): string
    {
        return match($this->type) {
            'info' => 'blue',
            'rappel' => 'yellow',
            'alerte' => 'red',
            'sanction' => 'red',
            default => 'gray'
        };
    }

    public function getPrioriteAttribute(): string
    {
        // Dériver la priorité du type puisque la colonne n'existe pas dans le schéma
        return match($this->type) {
            'sanction' => 'urgente',
            'alerte' => 'haute',
            'rappel' => 'normale',
            'info' => 'basse',
            default => 'normale'
        };
    }

    public function getClasseCssAttribute(): string
    {
        return match($this->type) {
            'info' => 'notification-info',
            'rappel' => 'notification-rappel',
            'alerte' => 'notification-alerte',
            'sanction' => 'notification-sanction',
            default => 'notification-default'
        };
    }

    // Méthodes métier
    public function marquerCommeLue(): bool
    {
        if ($this->lue) {
            return false; // Déjà lue
        }

        $this->update([
            'lue' => true,
            'date_lecture' => now()
        ]);

        return true;
    }

    public function marquerCommeNonLue(): bool
    {
        if (!$this->lue) {
            return false; // Déjà non lue
        }

        $this->update([
            'lue' => false,
            'date_lecture' => null
        ]);

        return true;
    }

    public function estRecente(int $heures = 24): bool
    {
        return $this->date_envoi->isAfter(now()->subHours($heures));
    }

    public function estImportante(): bool
    {
        return in_array($this->type, ['alerte', 'sanction']);
    }

    public function estInformative(): bool
    {
        return in_array($this->type, ['info', 'rappel','success']);
    }

    public function ajouterDonnees(array $donnees): void
    {
        $donneesExistantes = $this->donnees_supplementaires ?? [];
        $this->update([
            'donnees_supplementaires' => array_merge($donneesExistantes, $donnees)
        ]);
    }

    public function obtenirDonnee(string $cle, $defaut = null)
    {
        return $this->donnees_supplementaires[$cle] ?? $defaut;
    }

    public function supprimerDonnee(string $cle): void
    {
        $donnees = $this->donnees_supplementaires ?? [];
        unset($donnees[$cle]);
        $this->update(['donnees_supplementaires' => $donnees]);
    }

    // Méthodes statiques pour créer des notifications spécifiques

    /**
     * Créer un rappel de retour de livre
     */
    public static function creerRappelRetour($user, $emprunt): self
    {
        return static::create([
            'user_id' => $user->id,
            'titre' => 'Rappel de retour',
            'message' => "N'oubliez pas de retourner le livre \"{$emprunt->livre->titre}\" avant le {$emprunt->date_retour_prevue->format('d/m/Y')}.",
            'type' => 'rappel',
            'lue' => false,
            'date_envoi' => now(),
            'donnees_supplementaires' => [
                'emprunt_id' => $emprunt->id,
                'livre_id' => $emprunt->livre->id,
                'date_retour_prevue' => $emprunt->date_retour_prevue->toISOString(),
                'jours_restants' => $emprunt->date_retour_prevue->diffInDays(now())
            ]
        ]);
    }

    /**
     * Créer une alerte de retard
     */
    public static function creerAlerteRetard($user, $emprunt): self
    {
        $joursRetard = $emprunt->jours_retard ?? now()->diffInDays($emprunt->date_retour_prevue);
        
        return static::create([
            'user_id' => $user->id,
            'titre' => 'Livre en retard',
            'message' => "Le livre \"{$emprunt->livre->titre}\" est en retard de {$joursRetard} jour(s). Veuillez le retourner rapidement pour éviter des sanctions supplémentaires.",
            'type' => 'alerte',
            'lue' => false,
            'date_envoi' => now(),
            'donnees_supplementaires' => [
                'emprunt_id' => $emprunt->id,
                'livre_id' => $emprunt->livre->id,
                'jours_retard' => $joursRetard,
                'amende_potentielle' => $emprunt->calculerAmende ?? ($joursRetard * 0.5) // 0.5€ par jour de retard
            ]
        ]);
    }

    /**
     * Créer une notification de disponibilité
     */
    public static function creerNotificationDisponibilite($user, $livre): self
    {
        return static::create([
            'user_id' => $user->id,
            'titre' => 'Livre disponible',
            'message' => "Le livre \"{$livre->titre}\" que vous avez réservé est maintenant disponible. Vous avez 7 jours pour venir le récupérer.",
            'type' => 'info',
            'lue' => false,
            'date_envoi' => now(),
            'donnees_supplementaires' => [
                'livre_id' => $livre->id,
                'date_limite_recuperation' => now()->addDays(7)->toISOString(),
                'lieu_recuperation' => 'Bibliothèque universitaire'
            ]
        ]);
    }

    /**
     * Créer une notification de sanction
     */
    public static function creerNotificationSanction($user, $sanction): self
    {
        $message = match($sanction->type) {
            'amende' => "Une amende de {$sanction->montant}FCFA a été appliquée à votre compte. Raison: {$sanction->raison}",
            'suspension' => "Votre compte a été suspendu jusqu'au {$sanction->date_fin?->format('d/m/Y')}. Raison: {$sanction->raison}",
            'avertissement' => "Un avertissement a été émis sur votre compte. Raison: {$sanction->raison}",
            default => "Une sanction a été appliquée à votre compte. Raison: {$sanction->raison}"
        };

        return static::create([
            'user_id' => $user->id,
            'titre' => 'Sanction appliquée',
            'message' => $message,
            'type' => 'sanction',
            'lue' => false,
            'date_envoi' => now(),
            'donnees_supplementaires' => [
                'sanction_id' => $sanction->id,
                'type_sanction' => $sanction->type,
                'montant' => $sanction->montant ?? null,
                'date_fin' => $sanction->date_fin?->toISOString(),
                'raison' => $sanction->raison
            ]
        ]);
    }

    /**
     * Créer une confirmation d'emprunt
     */
    public static function creerConfirmationEmprunt($user, $emprunt): self
    {
        return static::create([
            'user_id' => $user->id,
            'titre' => 'Emprunt confirmé',
            'message' => "Vous avez emprunté le livre \"{$emprunt->livre->titre}\". Date de retour prévue: {$emprunt->date_retour_prevue->format('d/m/Y')}.",
            'type' => 'info',
            'lue' => false,
            'date_envoi' => now(),
            'donnees_supplementaires' => [
                'emprunt_id' => $emprunt->id,
                'livre_id' => $emprunt->livre->id,
                'date_retour_prevue' => $emprunt->date_retour_prevue->toISOString(),
                'duree_emprunt' => $emprunt->duree_emprunt ?? 14 // 14 jours par défaut
            ]
        ]);
    }

    /**
     * Créer une confirmation de réservation
     */
    public static function creerConfirmationReservation($user, $reservation): self
    {
        return static::create([
            'user_id' => $user->id,
            'titre' => 'Réservation confirmée',
            'message' => "Votre réservation pour le livre \"{$reservation->livre->titre}\" a été confirmée. Position dans la file: {$reservation->position_file}.",
            'type' => 'info',
            'lue' => false,
            'date_envoi' => now(),
            'donnees_supplementaires' => [
                'reservation_id' => $reservation->id,
                'livre_id' => $reservation->livre->id,
                'position_file' => $reservation->position_file,
                'temps_attente_estime' => $reservation->tempsAttenteEstime ?? '1-2 semaines'
            ]
        ]);
    }

    /**
     * Créer une notification de bienvenue
     */
    public static function creerNotificationBienvenue($user, $role = 'emprunteur'): self
    {
        $roleLibelle = match($role) {
            'administrateur' => 'Administrateur',
            'bibliothecaire' => 'Bibliothécaire',
            'emprunteur' => 'Emprunteur',
            default => 'Utilisateur'
        };

        return static::create([
            'user_id' => $user->id,
            'titre' => 'Bienvenue dans la bibliothèque universitaire',
            'message' => "Votre compte a été créé avec succès avec le rôle '{$roleLibelle}'. Vous pouvez maintenant accéder à tous les services de la bibliothèque.",
            'type' => 'info',
            'lue' => false,
            'date_envoi' => now(),
            'donnees_supplementaires' => [
                'role' => $role,
                'date_creation_compte' => $user->created_at->toISOString(),
                'premiere_connexion' => true
            ]
        ]);
    }

    /**
     * Créer une notification de changement de rôle
     */
    public static function creerNotificationChangementRole($user, $nouveauRole, $administrateur): self
    {
        $roleLibelle = match($nouveauRole) {
            'administrateur' => 'Administrateur',
            'bibliothecaire' => 'Bibliothécaire',
            'emprunteur' => 'Emprunteur',
            default => 'Utilisateur'
        };

        return static::create([
            'user_id' => $user->id,
            'titre' => 'Modification de votre rôle',
            'message' => "Votre rôle a été modifié en '{$roleLibelle}' par {$administrateur->nom} {$administrateur->prenom}.",
            'type' => 'info',
            'lue' => false,
            'date_envoi' => now(),
            'donnees_supplementaires' => [
                'ancien_role' => $user->roles->first()?->name ?? 'emprunteur',
                'nouveau_role' => $nouveauRole,
                'administrateur_id' => $administrateur->id,
                'administrateur_nom' => "{$administrateur->nom} {$administrateur->prenom}"
            ]
        ]);
    }

    /**
     * Créer une notification de changement de statut
     */
    public static function creerNotificationChangementStatut($user, $nouveauStatut): self
    {
        $message = $nouveauStatut === 'actif'
            ? 'Votre compte a été réactivé. Vous pouvez maintenant accéder à tous les services de la bibliothèque.'
            : 'Votre compte a été désactivé. Contactez l\'administration pour plus d\'informations.';

        return static::create([
            'user_id' => $user->id,
            'titre' => 'Changement de statut de compte',
            'message' => $message,
            'type' => $nouveauStatut === 'actif' ? 'info' : 'alerte',
            'lue' => false,
            'date_envoi' => now(),
            'donnees_supplementaires' => [
                'ancien_statut' => $user->statut,
                'nouveau_statut' => $nouveauStatut,
                'date_changement' => now()->toISOString()
            ]
        ]);
    }

    // Méthodes de validation
    public static function validerType($type): bool
    {
        return in_array($type, self::TYPES_AUTORISES);
    }

    // Événements du modèle
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($notification) {
            // Définir la date d'envoi si non spécifiée
            if (!$notification->date_envoi) {
                $notification->date_envoi = now();
            }

            // Valider le type
            if (!self::validerType($notification->type)) {
                throw new \InvalidArgumentException("Type de notification invalide: {$notification->type}");
            }

            // S'assurer que lue est un booléen
            if (!isset($notification->lue)) {
                $notification->lue = false;
            }
        });

        static::updating(function ($notification) {
            // Si on marque comme lue et qu'il n'y a pas de date de lecture, l'ajouter
            if ($notification->lue && !$notification->date_lecture) {
                $notification->date_lecture = now();
            }

            // Si on marque comme non lue, supprimer la date de lecture
            if (!$notification->lue && $notification->date_lecture) {
                $notification->date_lecture = null;
            }
        });
    }
}

