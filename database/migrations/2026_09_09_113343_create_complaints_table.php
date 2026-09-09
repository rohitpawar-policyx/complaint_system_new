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
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('reason_id')->constrained('complaint_reasons')->restrictOnDelete()->cascadeOnUpdate();
            $table->text('message');
            $table->enum('priority', ['LOW', 'MEDIUM', 'HIGH'])->index();
            $table->enum('status', ['pending', 'in_progress', 'resolved', 'closed', 'rejected'])
                ->default('pending')
                ->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
