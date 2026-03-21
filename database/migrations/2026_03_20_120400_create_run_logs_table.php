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
        Schema::create('run_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_run_id')->nullable()->constrained('agent_runs')->nullOnDelete();
            $table->string('level')->default('info');
            $table->string('event_type');
            $table->text('message')->nullable();
            $table->json('context_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['agent_run_id', 'created_at']);
            $table->index(['task_id', 'created_at']);
            $table->index(['level', 'created_at']);
            $table->index('event_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('run_logs');
    }
};

