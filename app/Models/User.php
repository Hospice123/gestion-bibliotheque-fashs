<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'password',
        'role',
        'numero_etudiant',
        'telephone',
        'adresse',
        'statut'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'date_inscription' => 'datetime',
        'password' => 'hashed',
    ];

    // Relations
    public function emprunts()
    {
        return $this->hasMany(Emprunt::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function sanctions()
    {
        return $this->hasMany(Sanction::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function sanctionsAppliquees()
    {
        return $this->hasMany(Sanction::class, 'appliquee_par');
    }

    // Scopes
    public function scopeActifs($query)
    {
        return $query->where('statut', 'actif');
    }

    public function scopeEmprunteurs($query)
    {
        return $query->where('role', 'emprunteur');
    }

    public function scopeBibliothecaires($query)
    {
        return $query->where('role', 'bibliothecaire');
    }

    public function scopeAdministrateurs($query)
    {
        return $query->where('role', 'administrateur');
    }

    // Accesseurs
    public function getNomCompletAttribute()
    {
        return $this->prenom . ' ' . $this->nom;
    }

    // Méthodes métier
    public function peutEmprunter(): bool
    {
        if ($this->statut !== 'actif') {
            return false;
        }

        $sanctionsActives = $this->sanctions()
            ->where('statut', 'active')
            ->where('type', 'suspension')
            ->where(function ($query) {
                $query->whereNull('date_fin')
                      ->orWhere('date_fin', '>', now());
            })
            ->exists();

        return !$sanctionsActives;
    }

    public function nombreEmpruntsActifs(): int
    {
        return $this->emprunts()
            ->where('statut', 'en_cours')
            ->count();
    }

    public function limiteEmprunts(): int
    {
        return match($this->role) {
            'emprunteur' => 5,
            'bibliothecaire' => 10,
            'administrateur' => 15,
            default => 3
        };
    }

    public function dureeEmpruntJours(): int
    {
        return match($this->role) {
            'emprunteur' => 14,
            'bibliothecaire' => 30,
            'administrateur' => 30,
            default => 14
        };
    }

    public function aDesSanctionsActives(): bool
    {
        return $this->sanctions()
            ->where('statut', 'active')
            ->exists();
    }

    public function montantAmendesImpayees(): float
    {
        return $this->sanctions()
            ->where('type', 'amende')
            ->where('statut', 'active')
            ->sum('montant');
    }
}
