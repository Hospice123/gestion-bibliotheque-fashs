<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Sanction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'emprunt_id',
        'type',
        'montant',
        'date_debut',
        'date_fin',
        'raison',
        'statut',
        'appliquee_par',
        'notes'
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_debut' => 'datetime',
        'date_fin' => 'datetime',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function emprunt()
    {
        return $this->belongsTo(Emprunt::class);
    }

    public function appliqueeParUtilisateur()
    {
        return $this->belongsTo(User::class, 'appliquee_par');
    }

    // Scopes
    public function scopeActives($query)
    {
        return $query->where('statut', 'active');
    }

    public function scopePayees($query)
    {
        return $query->where('statut', 'payee');
    }

    public function scopeLevees($query)
    {
        return $query->where('statut', 'levee');
    }

    public function scopeExpirees($query)
    {
        return $query->where('statut', 'expiree');
    }

    public function scopeAmendes($query)
    {
        return $query->where('type', 'amende');
    }

    public function scopeSuspensions($query)
    {
        return $query->where('type', 'suspension');
    }

    public function scopeAvertissements($query)
    {
        return $query->where('type', 'avertissement');
    }

    public function scopeParUtilisateur($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeParPeriode($query, $dateDebut, $dateFin)
    {
        return $query->whereBetween('date_debut', [$dateDebut, $dateFin]);
    }

    public function scopeEnCours($query)
    {
        return $query->where('statut', 'active')
                    ->where(function ($q) {
                        $q->whereNull('date_fin')
                          ->orWhere('date_fin', '>', now());
                    });
    }

    // Accesseurs
    public function getJoursRestantsAttribute()
    {
        if ($this->statut !== 'active' || !$this->date_fin) {
            return null;
        }

        return now()->diffInDays($this->date_fin, false);
    }

    public function getDureeAttribute()
    {
        if (!$this->date_fin) {
            return null;
        }

        return $this->date_debut->diffInDays($this->date_fin);
    }

    public function getEstActiveAttribute()
    {
        return $this->statut === 'active' && 
               (!$this->date_fin || now()->isBefore($this->date_fin));
    }

    // Méthodes métier
    public function estActive(): bool
    {
        return $this->statut === 'active' && 
               (!$this->date_fin || now()->isBefore($this->date_fin));
    }

    public function estExpiree(): bool
    {
        return $this->date_fin && now()->isAfter($this->date_fin);
    }

    public function peutEtreLevee(): bool
    {
        return $this->statut === 'active';
    }

    public function peutEtrePayee(): bool
    {
        return $this->type === 'amende' && $this->statut === 'active';
    }

    public function lever(User $utilisateur, string $raison = null): bool
    {
        if (!$this->peutEtreLevee()) {
            return false;
        }

        $this->update([
            'statut' => 'levee',
            'date_fin' => now(),
            'notes' => $this->notes . "\nLevée par {$utilisateur->nom_complet}" . 
                      ($raison ? " - Raison: {$raison}" : '')
        ]);

        return true;
    }

    public function payer(float $montantPaye = null): bool
    {
        if (!$this->peutEtrePayee()) {
            return false;
        }

        $montantPaye = $montantPaye ?? $this->montant;

        if ($montantPaye < $this->montant) {
            return false; // Paiement partiel non autorisé
        }

        $this->update([
            'statut' => 'payee',
            'notes' => $this->notes . "\nPayée le " . now()->format('d/m/Y à H:i') . 
                      " - Montant: {$montantPaye}FCFA"
        ]);

        return true;
    }

    public function expirer(): bool
    {
        if (!$this->estExpiree()) {
            return false;
        }

        $this->update(['statut' => 'expiree']);
        return true;
    }

    public function prolonger(int $jours, string $raison = null): bool
    {
        if ($this->statut !== 'active') {
            return false;
        }

        $nouvelleDateFin = $this->date_fin ? 
            $this->date_fin->addDays($jours) : 
            now()->addDays($jours);

        $this->update([
            'date_fin' => $nouvelleDateFin,
            'notes' => $this->notes . "\nProlongée de {$jours} jour(s)" . 
                      ($raison ? " - Raison: {$raison}" : '')
        ]);

        return true;
    }

    public function calculerMontantTotal(): float
    {
        if ($this->type !== 'amende') {
            return 0;
        }

        // Pour les amendes de retard, calculer en fonction des jours
        if ($this->emprunt && $this->emprunt->estEnRetard()) {
            $joursRetard = $this->emprunt->jours_retard;
            $tarifParJour = 0.50;
            return $joursRetard * $tarifParJour;
        }

        return $this->montant ?? 0;
    }

    public function getTypeLibelleAttribute(): string
    {
        return match($this->type) {
            'amende' => 'Amende',
            'suspension' => 'Suspension',
            'avertissement' => 'Avertissement',
            default => 'Inconnu'
        };
    }

    public function getStatutLibelleAttribute(): string
    {
        return match($this->statut) {
            'active' => 'Active',
            'payee' => 'Payée',
            'levee' => 'Levée',
            'expiree' => 'Expirée',
            default => 'Inconnu'
        };
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sanction) {
            // Définir la date de début si non spécifiée
            if (!$sanction->date_debut) {
                $sanction->date_debut = now();
            }

            // Pour les suspensions, définir une durée par défaut
            if ($sanction->type === 'suspension' && !$sanction->date_fin) {
                $sanction->date_fin = now()->addDays(30);
            }
        });

        static::updating(function ($sanction) {
            // Vérifier l'expiration automatique
            if ($sanction->estExpiree() && $sanction->statut === 'active') {
                $sanction->statut = 'expiree';
            }
        });
    }
}

