<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_instances', function (Blueprint $table) {
            // One meeting per body (type) and per day
            $table->unique(['type', 'date_meeting']);
        });
    }

    public function down(): void
    {
        Schema::table('org_instances', function (Blueprint $table) {
            $table->dropUnique(['type', 'date_meeting']);
        });
    }
};
