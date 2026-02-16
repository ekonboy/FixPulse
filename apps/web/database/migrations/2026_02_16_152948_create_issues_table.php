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

        Schema::create('issues', function (Blueprint $table) use ($isPgsql) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->string('key', 120);
            $table->string('category', 40);
            $table->string('title', 255);
            $table->string('severity', 20);
            $table->unsignedSmallInteger('impact_score');
            $table->unsignedSmallInteger('effort_score');
            $table->unsignedInteger('estimated_saving_ms')->nullable();
            $table->unsignedInteger('estimated_saving_kb')->nullable();
            $isPgsql ? $table->jsonb('evidence_jsonb')->nullable() : $table->json('evidence_jsonb')->nullable();
            $isPgsql ? $table->jsonb('fix_jsonb')->nullable() : $table->json('fix_jsonb')->nullable();
            $table->integer('priority_score')->default(0);
            $table->timestamps();
            $table->index(['scan_id', 'priority_score']);
            $table->unique(['scan_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
