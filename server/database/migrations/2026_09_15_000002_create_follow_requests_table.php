<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignId('followed_profile_id')->index()->constrained('profiles')->cascadeOnDelete();
            $table->timestamps();

            // The leading column also indexes outgoing requests.
            $table->unique(['follower_profile_id', 'followed_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_requests');
    }
};
