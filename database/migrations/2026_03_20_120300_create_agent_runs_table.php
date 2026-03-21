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
        Schema::create('agent_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prompt_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('retry_of_run_id')->nullable()->constrained('agent_runs')->nullOnDelete();
            $table->unsignedInteger('run_number')->default(1);
            $table->string('status')->default('pending');
            $table->string('provider')->default('azure_openai');
            $table->string('model')->nullable();
            $table->string('deployment')->nullable();
            $table->string('queue')->nullable();
            $table->string('azure_request_id')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'run_number']);
            $table->index(['status', 'started_at']);
            $table->index(['provider', 'model', 'deployment']);
            $table->index('azure_request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_runs');
    }
};
