<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->restrictOnDelete();
            $table->text('caption')->nullable();
            $table->foreignId('in_reply_to_id')->nullable()->index()->constrained('statuses')->restrictOnDelete();
            $table->foreignId('reblog_of_id')->nullable()->index()->constrained('statuses')->restrictOnDelete();
            $table->timestamps();

            // Also indexes profile_id as the leading column; NULL permits ordinary statuses.
            $table->unique(['profile_id', 'reblog_of_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};
