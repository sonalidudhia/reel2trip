<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reels', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->string('shortcode')->unique()->nullable(); // e.g. DEx1AbCdEfG from the URL
            $table->string('status')->default('pending');
            // pending -> downloading -> transcribing -> extracting -> done | failed
            $table->text('caption')->nullable();
            $table->longText('transcript')->nullable();       // Whisper output
            $table->longText('ocr_text')->nullable();         // frame OCR output (phase 2)
            $table->string('video_path')->nullable();         // storage/app/reels/...
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reels');
    }
};
