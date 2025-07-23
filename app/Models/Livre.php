<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Livre extends Model
{
    use HasFactory;

    protected $fillable = [
        'titre',
        'auteur',
        'isbn',
        'editeur',
        'annee_publication',
        'nombre_pages',
        'langue',
        'resume',
        'image_couverture',
        'category_id',
        'nombre_exemplaires',
        'nombre_disponibles',
        'emplacement',
        'statut'
    ];

    protected $casts = [
        'annee_publication' => 'integer',
        'nombre_pages' => 'integer',
        'nombre_exemplaires' => 'integer',
        'nombre_disponibles' => 'integer',
    ];

    // Relations
    public function categorie()
    {
        return $this->belongsTo(Categorie::class, 'category_id');
    }

    public function emprunts()
    {
        return $this->hasMany(Emprunt::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function empruntsActifs()
    {
        return $this->hasMany(Emprunt::class)->where('statut', 'en_cours');
    }

    public function reservationsActives()
    {
        return $this->hasMany(Reservation::class)->where('statut', 'active');
    }

    // Scopes
    public function scopeDisponibles($query)
    {
        return $query->where('statut', 'disponible')
                    ->where('nombre_disponibles', '>', 0);
    }

    public function scopeRecherche($query, $terme)
    {
        return $query->where(function ($q) use ($terme) {
            $q->where('titre', 'LIKE', "%{$terme}%")
              ->orWhere('auteur', 'LIKE', "%{$terme}%")
              ->orWhere('isbn', 'LIKE', "%{$terme}%")
              ->orWhere('resume', 'LIKE', "%{$terme}%");
        });
    }

    public function scopeParCategorie($query, $categorieId)
    {
        return $query->where('category_id', $categorieId);
    }

    public function scopeParAuteur($query, $auteur)
    {
        return $query->where('auteur', 'LIKE', "%{$auteur}%");
    }

    public function scopeParAnnee($query, $annee)
    {
        return $query->where('annee_publication', $annee);
    }

    // Accesseurs
    public function getDisponibiliteAttribute()
    {
        if ($this->statut !== 'disponible') {
            return 'indisponible';
        }
        
        return $this->nombre_disponibles > 0 ? 'disponible' : 'emprunte';
    }

    public function getImageCouvertureUrlAttribute()
    {
        if ($this->image_couverture) {
            return asset('storage/' . $this->image_couverture);
        }
        return asset('images/livre-default.png');
    }

    // Méthodes métier
    public function estDisponible(): bool
    {
        return $this->statut === 'disponible' && $this->nombre_disponibles > 0;
    }

    public function emprunter(): bool
    {
        if (!$this->estDisponible()) {
            return false;
        }

        $this->decrement('nombre_disponibles');
        return true;
    }

    public function retourner(): bool
    {
        if ($this->nombre_disponibles >= $this->nombre_exemplaires) {
            return false;
        }

        $this->increment('nombre_disponibles');
        return true;
    }

    public function nombreReservations(): int
    {
        return $this->reservations()
            ->where('statut', 'active')
            ->count();
    }

    public function nombreEmpruntsActifs(): int
    {
        return $this->emprunts()
            ->where('statut', 'en_cours')
            ->count();
    }

    public function prochainUtilisateurEnAttente()
    {
        return $this->reservations()
            ->where('statut', 'active')
            ->orderBy('position_file')
            ->first();
    }

    public function estPopulaire(): bool
    {
        $nombreEmprunts = $this->emprunts()->count();
        return $nombreEmprunts >= 10; // Seuil configurable
    }

    public function tauxDisponibilite(): float
    {
        if ($this->nombre_exemplaires == 0) {
            return 0;
        }
        
        return ($this->nombre_disponibles / $this->nombre_exemplaires) * 100;
    }
}

