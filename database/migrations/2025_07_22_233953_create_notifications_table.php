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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('titre');
            $table->text('message');
            $table->enum('type', ['info', 'rappel', 'alerte', 'sanction','success']);
            $table->boolean('lue')->default(false);
            $table->timestamp('date_envoi')->useCurrent();
            $table->timestamp('date_lecture')->nullable();
            $table->json('donnees_supplementaires')->nullable();
            $table->timestamps();
            
            $table->index(['user_id']);
            $table->index(['type']);
            $table->index(['lue']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
