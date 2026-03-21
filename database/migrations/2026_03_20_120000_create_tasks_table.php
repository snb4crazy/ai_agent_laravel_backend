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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('queued');
            $table->unsignedTinyInteger('priority')->default(5);
            $table->string('idempotency_key')->nullable()->unique();
            $table->json('input_json')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index('scheduled_for');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};

