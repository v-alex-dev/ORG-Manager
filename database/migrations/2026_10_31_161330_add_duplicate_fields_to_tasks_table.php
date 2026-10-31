<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // True on the original task AND on its copy once duplicated
            $table->boolean('has_duplicate')->default(false)->after('status');

            // Only the copy points to the original task.
            // unique() guarantees a task can be duplicated only once, even on a double tap.
            $table->foreignId('reference_task_id')
                ->nullable()
                ->unique()
                ->after('has_duplicate')
                ->constrained('tasks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reference_task_id');
            $table->dropColumn('has_duplicate');
        });
    }
};
