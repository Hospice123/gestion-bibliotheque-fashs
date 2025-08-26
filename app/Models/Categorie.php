<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categorie extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'description',
        'code'
    ];

    // Relations
    public function livres()
    {
        return $this->hasMany(Livre::class, 'category_id');
    }

    // Scopes
    public function scopeParCode($query, $code)
    {
        return $query->where('code', $code);
    }

    // Méthodes métier
    public function nombreLivres(): int
    {
        return $this->livres()->count();
    }

    public function nombreLivresDisponibles(): int
    {
        return $this->livres()
            ->where('statut', 'disponible')
            ->where('nombre_disponibles', '>', 0)
            ->count();
    }
}

