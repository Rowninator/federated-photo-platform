<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statuses', function (Blueprint $table) {
            $table->index(
                ['in_reply_to_id', 'reblog_of_id', 'created_at', 'id'],
                'statuses_top_level_timeline_index',
            );
        });

        Schema::table('statuses', function (Blueprint $table) {
            $table->dropIndex('statuses_in_reply_to_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('statuses', function (Blueprint $table) {
            $table->index('in_reply_to_id', 'statuses_in_reply_to_id_index');
        });

        Schema::table('statuses', function (Blueprint $table) {
            $table->dropIndex('statuses_top_level_timeline_index');
        });
    }
};
