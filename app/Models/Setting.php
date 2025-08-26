<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        "key",
        "value",
        "category",
        "type",
    ];

    protected $casts = [
        'value' => 'array',
    ];

    /**
     * Récupère la valeur d'un paramètre par sa clé.
     *
     * @param string $key La clé du paramètre.
     * @param mixed $default La valeur par défaut si le paramètre n'existe pas.
     * @return mixed La valeur du paramètre, castée si un type est spécifié.
     */
    public static function get($key, $default = null)
    {
        $setting = static::where("key", $key)->first();
        if (!$setting) {
            return $default;
        }

        // Si le cast 'array' est défini, Laravel gérera déjà la désérialisation.
        // Cette logique peut être simplifiée si le cast est actif.
        if ($setting->type === "json") {
            return $setting->value; // Laravel gère déjà le décodage grâce au cast 'array'
        } elseif ($setting->type === "integer") {
            return (int) $setting->value;
        } elseif ($setting->type === "boolean") {
            return (bool) $setting->value;
        } else {
            return $setting->value;
        }
    }

    /**
     * Définit ou met à jour la valeur d'un paramètre.
     *
     * @param string $key La clé du paramètre.
     * @param mixed $value La valeur à stocker.
     * @param string|null $category La catégorie du paramètre.
     * @param string|null $type Le type de la valeur (string, integer, boolean, json). Détecté automatiquement si null.
     * @return void
     */
    public static function set($key, $value, $category = null, $type = null)
    {
        // Déterminer le type si non spécifié
        if (is_null($type)) {
            if (is_int($value)) {
                $type = "integer";
            } elseif (is_bool($value)) {
                $type = "boolean";
            } elseif (is_array($value) || is_object($value)) {
                $type = "json";
                // Laravel gérera l'encodage JSON si 'value' est casté en 'array'
                // Donc, pas besoin d'encoder ici si le cast est en place.
            } else {
                $type = "string";
            }
        }

        static::updateOrCreate(
            ["key" => $key],
            ["value" => $value, "category" => $category, "type" => $type]
        );
    }
}