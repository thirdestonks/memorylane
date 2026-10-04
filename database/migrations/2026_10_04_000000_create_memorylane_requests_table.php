<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('memorylane.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('memorylane_requests', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10);
            $table->string('path', 500);
            $table->string('route')->nullable();
            $table->unsignedSmallInteger('status');
            $table->float('duration_ms');
            $table->float('peak_memory_mb');
            $table->unsignedInteger('query_count')->default(0);
            $table->float('query_time_ms')->default(0);
            $table->unsignedInteger('n_plus_one_count')->default(0);
            // Plain text, not json(): SQL Server has no JSON type and we never query inside it.
            $table->longText('payload');
            $table->timestamp('created_at')->index();

            $table->index('duration_ms');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('memorylane_requests');
    }
};
