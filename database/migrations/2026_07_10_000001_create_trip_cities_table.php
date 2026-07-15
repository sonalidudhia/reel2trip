<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_cities', function (Blueprint $table) {
            $table->id();
            $table->string('name');                      // "Lisbon"
            $table->string('country');                   // "Portugal"
            $table->unsignedTinyInteger('days')->default(1);
            $table->date('arrival_date')->nullable();
            $table->decimal('center_lat', 10, 7)->nullable(); // for clustering + map center
            $table->decimal('center_lng', 10, 7)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_cities');
    }
};
