<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isPgsql = Schema::getConnection()->getDriverName() === 'pgsql';

        Schema::create('issue_actions', function (Blueprint $table) use ($isPgsql): void {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30); // generate_patch, create_pr, revert_pr
            $table->string('status', 20)->default('queued'); // queued, ready, completed, failed
            $table->string('branch_name', 190)->nullable();
            $table->string('commit_message', 255)->nullable();
            $table->string('commit_sha', 100)->nullable();
            $table->string('pr_url', 2048)->nullable();
            $table->unsignedBigInteger('pr_number')->nullable();
            $isPgsql ? $table->jsonb('patch_jsonb')->nullable() : $table->json('patch_jsonb')->nullable();
            $table->longText('diff_text')->nullable();
            $isPgsql ? $table->jsonb('meta_jsonb')->nullable() : $table->json('meta_jsonb')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['issue_id', 'kind']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_actions');
    }
};

