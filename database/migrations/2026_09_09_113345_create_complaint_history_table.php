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
        Schema::create('complaint_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('action', 50)->index();
            $table->enum('old_status', ['pending', 'in_progress', 'resolved', 'closed', 'rejected'])->nullable();
            $table->enum('new_status', ['pending', 'in_progress', 'resolved', 'closed', 'rejected'])->nullable();
            $table->foreignId('assigned_from')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('description', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaint_history');
    }
};
