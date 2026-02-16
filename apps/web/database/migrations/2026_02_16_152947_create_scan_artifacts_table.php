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

        Schema::create('scan_artifacts', function (Blueprint $table) use ($isPgsql) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 50);
            $table->string('path', 2048);
            $isPgsql ? $table->jsonb('meta_jsonb')->nullable() : $table->json('meta_jsonb')->nullable();
            $table->timestamps();
            $table->index(['scan_id', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_artifacts');
    }
};
