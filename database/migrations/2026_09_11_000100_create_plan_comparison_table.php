<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The plans comparison table, and the words above it.
 *
 * Everything a shop owner reads on the plans page lives here so platform
 * staff can write it themselves: the sections, the rows in them, and what
 * each plan says in each row.
 *
 * None of it belongs to a shop — these are the platform's own plans, like
 * `packages` — so there is deliberately no tenant_id.
 *
 * A row may name a real feature from config/features.php. When it does, the
 * value shown is read from what the plan actually allows and cannot be typed
 * over, so the table can never promise a ceiling the platform does not keep.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_feature_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon', 40)->nullable();
            $table->string('note')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index('position');
        });

        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_feature_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('note')->nullable();

            // A key from config/features.php, or null for a row that is only
            // words. Not a foreign key: features live in configuration.
            $table->string('feature', 60)->nullable();

            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['plan_feature_group_id', 'position']);
        });

        Schema::create('plan_feature_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_feature_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('value')->nullable();
            $table->timestamps();

            $table->unique(['plan_feature_id', 'package_id']);
        });

        // The heading, the line under it and the promises across the top.
        // One row, ever.
        Schema::create('plan_page_settings', function (Blueprint $table) {
            $table->id();
            $table->string('eyebrow')->nullable();
            $table->string('heading')->nullable();
            $table->string('heading_accent')->nullable();
            $table->text('blurb')->nullable();
            $table->json('promises')->nullable();
            $table->timestamps();
        });

        Schema::table('packages', function (Blueprint $table) {
            // The one column with "Most Popular" over it, and what its button
            // says. Both are the platform's own presentation, not billing.
            $table->boolean('highlight')->default(false);
            $table->string('badge', 40)->nullable();
            $table->string('cta', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['highlight', 'badge', 'cta']);
        });

        Schema::dropIfExists('plan_page_settings');
        Schema::dropIfExists('plan_feature_values');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plan_feature_groups');
    }
};
