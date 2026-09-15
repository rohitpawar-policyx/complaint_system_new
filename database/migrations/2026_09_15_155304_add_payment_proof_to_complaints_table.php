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
        Schema::table('complaints', function (Blueprint $table) {
            $table->string('payment_proof_path', 500)->after('assigned_to');
            // Nullable: OCR may not find a transaction ID at all, or Tesseract
            // itself may fail - neither case blocks complaint creation.
            // Deliberately NOT unique: OCR misreads and legitimate duplicate
            // submissions both need admin review, not a hard DB rejection.
            $table->string('transaction_id', 100)->nullable()->index();
            $table->enum('ocr_status', ['pending', 'extracted', 'not_found', 'failed'])
                ->default('pending');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn(['payment_proof_path', 'transaction_id', 'ocr_status']);
        });
    }
};
