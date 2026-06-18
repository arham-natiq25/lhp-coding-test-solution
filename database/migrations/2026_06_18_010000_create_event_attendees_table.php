<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamp('reminder_3_days_sent_at')->nullable();
            $table->timestamp('reminder_24_hours_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'email']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendees');
    }
};
