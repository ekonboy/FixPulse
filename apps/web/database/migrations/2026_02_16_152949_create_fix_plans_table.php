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
        $isPgsql = Schema::getConnection()->getDriverName() === 'pgsql';

        Schema::create('fix_plans', function (Blueprint $table) use ($isPgsql) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $isPgsql ? $table->jsonb('plan_jsonb') : $table->json('plan_jsonb');
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->unique('scan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fix_plans');
    }
};
