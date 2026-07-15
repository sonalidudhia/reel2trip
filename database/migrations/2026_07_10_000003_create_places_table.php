<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_city_id')->nullable()->constrained()->nullOnDelete();

            // From Claude extraction
            $table->string('name');
            $table->string('city_guess')->nullable();     // city as mentioned in the reel
            $table->string('category');                   // food | sight | viewpoint | activity | tip | area
            $table->text('description')->nullable();      // short "what it is"
            $table->text('tip')->nullable();              // "go before 9am", "cash only"
            $table->string('price_hint')->nullable();     // free-text from the reel, e.g. "€1 pastel de nata"

            // From Google Places enrichment
            $table->string('google_place_id')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->decimal('rating', 3, 1)->nullable();
            $table->unsignedTinyInteger('price_level')->nullable(); // 0-4 from Google
            $table->string('address')->nullable();
            $table->json('opening_hours')->nullable();

            // Planning
            $table->unsignedTinyInteger('planned_day')->nullable(); // day N within the city
            $table->boolean('must_do')->default(false);
            $table->boolean('dismissed')->default(false); // "not interested" without deleting

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};
