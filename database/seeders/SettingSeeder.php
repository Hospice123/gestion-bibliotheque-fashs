<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::set("library_name", "Bibliothèque Universitaire", "general", "string");
        Setting::set("contact_email", "contact@bibliotheque.edu", "general", "string");
        Setting::set("max_borrow_days", 21, "general", "integer");

        Setting::set("max_active_reservations", 5, "reservations", "integer");
        Setting::set("reservation_hold_days", 3, "reservations", "integer");

        Setting::set("daily_fine_amount", 0.50, "fines", "float");
        Setting::set("max_fine_amount", 50.00, "fines", "float");

        // Le cast 'array' dans le modèle Setting gérera l'encodage/décodage JSON.
        // Nous passons directement le tableau ici.
        Setting::set("social_media_links", [
            "facebook" => "https://facebook.com/yourlibrary",
            "twitter" => "https://twitter.com/yourlibrary"
        ], "general", "json");

        // Ajoutez d'autres paramètres par défaut ici
    }
}