<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'livre_id',
        'date_reservation',
        'date_expiration',
        'statut',
        'position_file',
        'notifie'
    ];

    protected $casts = [
        'date_reservation' => 'datetime',
        'date_expiration' => 'datetime',
        'position_file' => 'integer',
        'notifie' => 'boolean',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function livre()
    {
        return $this->belongsTo(Livre::class);
    }

    // Scopes
    public function scopeActives($query)
    {
        return $query->where('statut', 'active');
    }

    public function scopeExpirees($query)
    {
        return $query->where('statut', 'active')
                    ->where('date_expiration', '<', now());
    }

    public function scopeConfirmees($query)
    {
        return $query->where('statut', 'confirmee');
    }

    public function scopeAnnulees($query)
    {
        return $query->where('statut', 'annulee');
    }

    public function scopeParUtilisateur($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeParLivre($query, $livreId)
    {
        return $query->where('livre_id', $livreId);
    }

    public function scopeParPosition($query, $position)
    {
        return $query->where('position_file', $position);
    }

    public function scopeNonNotifiees($query)
    {
        return $query->where('notifie', false);
    }

    // Accesseurs
    public function getJoursRestantsAttribute()
    {
        if ($this->statut !== 'active') {
            return null;
        }

        return now()->diffInDays($this->date_expiration, false);
    }

    public function getTempsAttenteAttribute()
    {
        return $this->date_reservation->diffForHumans();
    }

    // Méthodes métier
    public function estActive(): bool
    {
        return $this->statut === 'active' && now()->isBefore($this->date_expiration);
    }

    public function estExpiree(): bool
    {
        return $this->statut === 'active' && now()->isAfter($this->date_expiration);
    }

    public function peutEtreAnnulee(): bool
    {
        return in_array($this->statut, ['active']);
    }

    public function annuler(): bool
    {
        if (!$this->peutEtreAnnulee()) {
            return false;
        }

        $this->update(['statut' => 'annulee']);

        // Réorganiser la file d'attente
        $this->reorganiserFileAttente();

        return true;
    }

    public function confirmer(): bool
    {
        if ($this->statut !== 'active') {
            return false;
        }

        $this->update([
            'statut' => 'confirmee',
            'notifie' => true
        ]);

        return true;
    }

    public function expirer(): bool
    {
        if ($this->statut !== 'active') {
            return false;
        }

        $this->update(['statut' => 'expiree']);

        // Notifier le prochain utilisateur en file
        $this->notifierProchainUtilisateur();

        return true;
    }

    public function marquerCommeNotifiee(): bool
    {
        $this->update(['notifie' => true]);
        return true;
    }

    public function calculerPositionFile(): int
    {
        return static::where('livre_id', $this->livre_id)
            ->where('statut', 'active')
            ->where('date_reservation', '<', $this->date_reservation)
            ->count() + 1;
    }

    public function reorganiserFileAttente(): void
    {
        $reservationsActives = static::where('livre_id', $this->livre_id)
            ->where('statut', 'active')
            ->orderBy('date_reservation')
            ->get();

        foreach ($reservationsActives as $index => $reservation) {
            $reservation->update(['position_file' => $index + 1]);
        }
    }

    public function notifierProchainUtilisateur(): void
    {
        $prochaineReservation = static::where('livre_id', $this->livre_id)
            ->where('statut', 'active')
            ->orderBy('position_file')
            ->first();

        if ($prochaineReservation) {
            // Logique de notification (email, notification interne, etc.)
            $prochaineReservation->marquerCommeNotifiee();
        }
    }

    public function estPremierEnFile(): bool
    {
        return $this->position_file === 1;
    }

    public function tempsAttenteEstime(): string
    {
        if ($this->position_file <= 1) {
            return 'Disponible maintenant';
        }

        // Estimation basée sur la durée moyenne d'emprunt (14 jours)
        $joursEstimes = ($this->position_file - 1) * 14;
        
        if ($joursEstimes < 7) {
            return 'Moins d\'une semaine';
        } elseif ($joursEstimes < 30) {
            return 'Environ ' . ceil($joursEstimes / 7) . ' semaine(s)';
        } else {
            return 'Environ ' . ceil($joursEstimes / 30) . ' mois';
        }
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($reservation) {
            // Calculer automatiquement la position dans la file
            $reservation->position_file = $reservation->calculerPositionFile();
            
            // Définir la date d'expiration (7 jours par défaut)
            if (!$reservation->date_expiration) {
                $reservation->date_expiration = now()->addDays(7);
            }
        });

        static::deleted(function ($reservation) {
            // Réorganiser la file d'attente après suppression
            $reservation->reorganiserFileAttente();
        });
    }
}

