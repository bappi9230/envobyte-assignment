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
        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tag_id');
            $table->unsignedBigInteger('taggable_id');
            $table->string('taggable_type');
            $table->timestamps();

            $table->index('tag_id', 'idx_taggables_tag_id');
            $table->index(['taggable_id', 'taggable_type'], 'idx_taggables_morphs');
            $table->unique(['tag_id', 'taggable_id', 'taggable_type'], 'uq_taggables_tag_taggable');

            $table->foreign('tag_id')
                ->references('id')
                ->on('tags')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taggables');
    }
};
