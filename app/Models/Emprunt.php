<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Emprunt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'livre_id',
        'date_emprunt',
        'date_retour_prevue',
        'date_retour_effective',
        'statut',
        'nombre_prolongations',
        'notes'
    ];

    protected $casts = [
        'date_emprunt' => 'datetime',
        'date_retour_prevue' => 'datetime',
        'date_retour_effective' => 'datetime',
        'nombre_prolongations' => 'integer',
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

    public function sanctions()
    {
        return $this->hasMany(Sanction::class);
    }

    // Scopes
    public function scopeEnCours($query)
    {
        return $query->where('statut', 'en_cours');
    }

    public function scopeEnRetard($query)
    {
        return $query->where('statut', 'en_cours')
                    ->where('date_retour_prevue', '<', now());
    }

    public function scopeRetournes($query)
    {
        return $query->where('statut', 'retourne');
    }

    public function scopeParUtilisateur($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeParLivre($query, $livreId)
    {
        return $query->where('livre_id', $livreId);
    }

    public function scopeParPeriode($query, $dateDebut, $dateFin)
    {
        return $query->whereBetween('date_emprunt', [$dateDebut, $dateFin]);
    }

    // Accesseurs
    public function getJoursRestantsAttribute()
    {
        if ($this->statut !== 'en_cours') {
            return null;
        }

        return now()->diffInDays($this->date_retour_prevue, false);
    }

    public function getJoursRetardAttribute()
    {
        if ($this->statut !== 'en_cours' || !$this->estEnRetard()) {
            return 0;
        }

        return now()->diffInDays($this->date_retour_prevue);
    }

    public function getDureeEmpruntAttribute()
    {
        $dateFin = $this->date_retour_effective ?? now();
        return $this->date_emprunt->diffInDays($dateFin);
    }

    // Méthodes métier
    public function estEnRetard(): bool
    {
        return $this->statut === 'en_cours' && now()->isAfter($this->date_retour_prevue);
    }

    public function peutEtreProlonge(): bool
    {
        if ($this->statut !== 'en_cours') {
            return false;
        }

        if ($this->nombre_prolongations >= 2) {
            return false;
        }

        if ($this->estEnRetard()) {
            return false;
        }

        // Vérifier si le livre est réservé
        if ($this->livre->nombreReservations() > 0) {
            return false;
        }

        return true;
    }

    public function prolonger(int $jours = 7): bool
    {
        if (!$this->peutEtreProlonge()) {
            return false;
        }

        $this->update([
            'date_retour_prevue' => $this->date_retour_prevue->addDays($jours),
            'nombre_prolongations' => $this->nombre_prolongations + 1
        ]);

        return true;
    }

    public function retourner(): bool
    {
        if ($this->statut !== 'en_cours') {
            return false;
        }

        $this->update([
            'date_retour_effective' => now(),
            'statut' => 'retourne'
        ]);

        // Remettre le livre en disponibilité
        $this->livre->retourner();

        return true;
    }

    public function calculerAmende(): float
    {
        if (!$this->estEnRetard()) {
            return 0;
        }

        $joursRetard = $this->jours_retard;
        $tarifParJour = 0.50; // Configurable

        return $joursRetard * $tarifParJour;
    }

    public function marquerCommePerdu(): bool
    {
        if ($this->statut !== 'en_cours') {
            return false;
        }

        $this->update([
            'statut' => 'perdu',
            'date_retour_effective' => now()
        ]);

        return true;
    }

    public function estProcheDeLEcheance(int $joursAvant = 3): bool
    {
        if ($this->statut !== 'en_cours') {
            return false;
        }

        return now()->addDays($joursAvant)->isAfter($this->date_retour_prevue);
    }
}

