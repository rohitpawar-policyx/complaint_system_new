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
        Schema::create('complaint_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('original_name', 255);
            $table->string('stored_name', 255)->unique();
            $table->string('file_path', 500);
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('file_size');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaint_attachments');
    }
};
