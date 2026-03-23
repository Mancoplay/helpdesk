<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_remote_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('support_code', 20)->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_remote_sessions');
    }
};

