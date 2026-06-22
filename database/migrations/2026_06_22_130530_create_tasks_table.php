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
            $table->foreignId('service_id')
                ->constrained('org_instances')
                ->cascadeOnDelete();
            $table->foreignId('service_id')
                ->constrained('services');
            $table->string('poj_title');
            $table->text('poj_description')->nullable();
            $table->enum('status', ['TODO','DONE'])->default('DONE');
            $table->string('reference_code',20)->unique()->index();
            $table->timestamps();
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
