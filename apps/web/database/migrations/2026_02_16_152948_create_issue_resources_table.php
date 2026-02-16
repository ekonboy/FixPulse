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

        Schema::create('issue_resources', function (Blueprint $table) use ($isPgsql) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->string('resource_type', 30);
            $table->string('url', 2048);
            $table->unsignedInteger('transfer_size_kb')->nullable();
            $isPgsql ? $table->jsonb('details_jsonb')->nullable() : $table->json('details_jsonb')->nullable();
            $table->timestamps();
            $table->index(['issue_id', 'resource_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issue_resources');
    }
};
