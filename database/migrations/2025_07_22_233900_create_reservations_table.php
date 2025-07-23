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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('livre_id')->constrained('livres')->onDelete('cascade');
            $table->timestamp('date_reservation')->useCurrent();
            $table->timestamp('date_expiration')->nullable();
            $table->enum('statut', ['active', 'confirmee', 'expiree', 'annulee'])->default('active');
            $table->integer('position_file')->default(1);
            $table->boolean('notifie')->default(false);
            $table->timestamps();
            
            $table->index(['user_id']);
            $table->index(['livre_id']);
            $table->index(['statut']);
            $table->index(['position_file']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
