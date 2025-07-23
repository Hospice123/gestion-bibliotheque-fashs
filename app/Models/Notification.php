<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Notification extends Model
{
    use HasFactory;

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

    protected $casts = [
        'lue' => 'boolean',
        'date_envoi' => 'datetime',
        'date_lecture' => 'datetime',
        'donnees_supplementaires' => 'array',
    ];

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

    // Méthodes métier
    public function marquerCommeLue(): bool
    {
        if ($this->lue) {
            return false;
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
            return false;
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

    // Méthodes statiques pour créer des notifications spécifiques
    public static function creerRappelRetour(User $user, Emprunt $emprunt): self
    {
        return static::create([
            'user_id' => $user->id,
            'titre' => 'Rappel de retour',
            'message' => "N'oubliez pas de retourner le livre \"{$emprunt->livre->titre}\" avant le {$emprunt->date_retour_prevue->format('d/m/Y')}.",
            'type' => 'rappel',
            'donnees_supplementaires' => [
                'emprunt_id' => $emprunt->id,
                'livre_id' => $emprunt->livre->id,
                'date_retour_prevue' => $emprunt->date_retour_prevue->toISOString()
            ]
        ]);
    }

    public static function creerAlerteRetard(User $user, Emprunt $emprunt): self
    {
        $joursRetard = $emprunt->jours_retard;
        
        return static::create([
            'user_id' => $user->id,
            'titre' => 'Livre en retard',
            'message' => "Le livre \"{$emprunt->livre->titre}\" est en retard de {$joursRetard} jour(s). Veuillez le retourner rapidement pour éviter des sanctions supplémentaires.",
            'type' => 'alerte',
            'donnees_supplementaires' => [
                'emprunt_id' => $emprunt->id,
                'livre_id' => $emprunt->livre->id,
                'jours_retard' => $joursRetard,
                'amende_potentielle' => $emprunt->calculerAmende()
            ]
        ]);
    }

    public static function creerNotificationDisponibilite(User $user, Livre $livre): self
    {
        return static::create([
            'user_id' => $user->id,
            'titre' => 'Livre disponible',
            'message' => "Le livre \"{$livre->titre}\" que vous avez réservé est maintenant disponible. Vous avez 7 jours pour venir le récupérer.",
            'type' => 'info',
            'donnees_supplementaires' => [
                'livre_id' => $livre->id,
                'date_limite_recuperation' => now()->addDays(7)->toISOString()
            ]
        ]);
    }

    public static function creerNotificationSanction(User $user, Sanction $sanction): self
    {
        $message = match($sanction->type) {
            'amende' => "Une amende de {$sanction->montant}€ a été appliquée à votre compte. Raison: {$sanction->raison}",
            'suspension' => "Votre compte a été suspendu jusqu'au {$sanction->date_fin?->format('d/m/Y')}. Raison: {$sanction->raison}",
            'avertissement' => "Un avertissement a été émis sur votre compte. Raison: {$sanction->raison}",
            default => "Une sanction a été appliquée à votre compte."
        };

        return static::create([
            'user_id' => $user->id,
            'titre' => 'Sanction appliquée',
            'message' => $message,
            'type' => 'sanction',
            'donnees_supplementaires' => [
                'sanction_id' => $sanction->id,
                'type_sanction' => $sanction->type,
                'montant' => $sanction->montant,
                'date_fin' => $sanction->date_fin?->toISOString()
            ]
        ]);
    }

    public static function creerConfirmationEmprunt(User $user, Emprunt $emprunt): self
    {
        return static::create([
            'user_id' => $user->id,
            'titre' => 'Emprunt confirmé',
            'message' => "Vous avez emprunté le livre \"{$emprunt->livre->titre}\". Date de retour prévue: {$emprunt->date_retour_prevue->format('d/m/Y')}.",
            'type' => 'info',
            'donnees_supplementaires' => [
                'emprunt_id' => $emprunt->id,
                'livre_id' => $emprunt->livre->id,
                'date_retour_prevue' => $emprunt->date_retour_prevue->toISOString()
            ]
        ]);
    }

    public static function creerConfirmationReservation(User $user, Reservation $reservation): self
    {
        return static::create([
            'user_id' => $user->id,
            'titre' => 'Réservation confirmée',
            'message' => "Votre réservation pour le livre \"{$reservation->livre->titre}\" a été confirmée. Position dans la file: {$reservation->position_file}.",
            'type' => 'info',
            'donnees_supplementaires' => [
                'reservation_id' => $reservation->id,
                'livre_id' => $reservation->livre->id,
                'position_file' => $reservation->position_file,
                'temps_attente_estime' => $reservation->tempsAttenteEstime()
            ]
        ]);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($notification) {
            // Définir la date d'envoi si non spécifiée
            if (!$notification->date_envoi) {
                $notification->date_envoi = now();
            }
        });
    }
}

