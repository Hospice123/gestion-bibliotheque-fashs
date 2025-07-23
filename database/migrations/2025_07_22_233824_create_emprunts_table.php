<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('emprunts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('livre_id')->constrained('livres')->onDelete('cascade');
            $table->timestamp('date_emprunt')->useCurrent();
            $table->timestamp('date_retour_prevue')->nullable(); // 
            $table->timestamp('date_retour_effective')->nullable();
            $table->enum('statut', ['en_cours', 'retourne', 'en_retard', 'perdu'])->default('en_cours');
            $table->integer('nombre_prolongations')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['user_id']);
            $table->index(['livre_id']);
            $table->index(['statut']);
            $table->index(['date_emprunt', 'date_retour_prevue']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emprunts');
    }
};
