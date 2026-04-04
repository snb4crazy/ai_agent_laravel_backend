<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->json('output_json')->nullable()->after('meta_json');
        });

        Schema::create('task_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('action_name');
            $table->integer('sequence_order')->default(0);
            $table->json('input_json')->nullable();
            $table->json('output_json')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'sequence_order']);
            $table->index(['status', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_steps');
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('output_json');
        });
    }
};
