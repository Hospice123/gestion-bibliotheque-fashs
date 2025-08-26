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
        Schema::create('livres', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 500);
            $table->string('auteur');
            $table->string('isbn', 20)->unique()->nullable();
            $table->string('editeur')->nullable();
            $table->integer('annee_publication')->nullable();
            $table->integer('nombre_pages')->nullable();
            $table->string('langue', 10)->default('fr');
            $table->text('resume')->nullable();
            $table->string('image_couverture', 500)->nullable();
            $table->foreignId('category_id')->constrained('categories')->onDelete('restrict');
            $table->integer('nombre_exemplaires')->default(1);
            $table->integer('nombre_disponibles')->default(1);
            $table->string('emplacement', 100)->nullable();
            $table->enum('statut', ['disponible', 'indisponible', 'maintenance','reserve','emprunte','perdu'])->default('disponible');
            $table->timestamps();
            
            $table->index(['titre']);
            $table->index(['auteur']);
            $table->index(['isbn']);
            $table->index(['category_id']);
            $table->index(['statut']);
            $table->fullText(['titre', 'auteur', 'resume']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('livres');
    }
};
