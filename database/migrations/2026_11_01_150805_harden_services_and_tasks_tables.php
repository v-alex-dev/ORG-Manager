<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A service name must be unique at database level, not only in validation
        Schema::table('services', function (Blueprint $table) {
            $table->unique('name');
        });

        // A service that still has tasks can no longer be deleted
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->foreign('service_id')
                ->references('id')
                ->on('services')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->foreign('service_id')
                ->references('id')
                ->on('services')
                ->cascadeOnDelete();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};
