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
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();

            // NOT NULL and used for the uniqueness check below, deliberately
            // instead of the nullable user_id: both MySQL and Postgres treat
            // NULL <> NULL in a unique index, so a UNIQUE(user_id, ...)
            // constraint would silently NOT prevent two guest requests
            // (user_id both NULL) using the same key from racing each other
            // - exactly the bug this table exists to prevent. owner_key is
            // always a real string: "user:{id}" when authenticated, or
            // "guest:{session_id}" for a future guest-accessible endpoint.
            $table->string('owner_key', 191);

            // Kept alongside owner_key purely for display/debugging/cleanup
            // convenience (e.g. "show me this user's idempotency records") -
            // not part of the uniqueness guarantee.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('idempotency_key', 255);

            // Route name (or path, if unnamed) rather than a raw URL, so the
            // same key can't accidentally collide across two different
            // endpoints, and so this table stays meaningful if a route's
            // URL changes later.
            $table->string('endpoint', 191);

            // sha256 hex digest of the request fingerprint (64 chars).
            $table->char('request_hash', 64);

            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');

            $table->unsignedSmallInteger('response_status')->nullable();

            // Generic replay payload - shape is {"type":"redirect",...} or
            // {"type":"json",...} - see IdempotencyManager. Never store
            // anything here that isn't already safe to show back to the
            // same user who made the original request.
            $table->text('response_body')->nullable();

            $table->string('resource_type', 100)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();

            $table->timestamp('expires_at');

            $table->timestamps();

            // The actual race-safety guarantee: a second INSERT attempting
            // the same (owner, key, endpoint) tuple fails at the database
            // level, not via an application-level exists() check that two
            // concurrent requests could both pass.
            $table->unique(['owner_key', 'idempotency_key', 'endpoint'], 'idempotency_keys_owner_key_endpoint_unique');

            // For the cleanup command's WHERE expires_at < ? DELETE query.
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
