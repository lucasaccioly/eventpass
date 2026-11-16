<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('email');
            $table->string('code', 10)->unique();
            $table->string('ticket_status')->default('pending');
            $table->string('ticket_path')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'email']);
            $table->index(['event_id', 'checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
