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
        Schema::create('sanctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('emprunt_id')->nullable()->constrained('emprunts')->onDelete('set null');
            $table->enum('type', ['amende', 'suspension', 'avertissement']);
            $table->decimal('montant', 8, 2)->nullable();
            $table->timestamp('date_debut')->useCurrent();
            $table->timestamp('date_fin')->nullable();
            $table->text('raison');
            $table->enum('statut', ['active', 'payee', 'levee', 'expiree'])->default('active');
            $table->foreignId('appliquee_par')->constrained('users')->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['user_id']);
            $table->index(['type']);
            $table->index(['statut']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sanctions');
    }
};
