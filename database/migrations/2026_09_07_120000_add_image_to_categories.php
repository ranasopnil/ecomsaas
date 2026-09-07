<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // One picture per category, kept the same way product photos are:
            // the disk is recorded so moving to cloud storage later is a
            // settings change rather than a rewrite.
            $table->string('image_disk')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_thumbnail_path')->nullable();
            $table->unsignedBigInteger('image_size_bytes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['image_disk', 'image_path', 'image_thumbnail_path', 'image_size_bytes']);
        });
    }
};
