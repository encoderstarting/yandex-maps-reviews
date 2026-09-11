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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('author_name');
            $table->unsignedTinyInteger('rating');
            $table->text('text')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'external_id']);
            $table->index(['organization_id', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
