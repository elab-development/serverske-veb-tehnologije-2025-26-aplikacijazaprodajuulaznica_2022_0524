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
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'orders_user_created_index');
            $table->index(['ticket_type_id', 'status'], 'orders_ticket_type_status_index');
            $table->index(['status', 'queue_number'], 'orders_status_queue_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_user_created_index');
            $table->dropIndex('orders_ticket_type_status_index');
            $table->dropIndex('orders_status_queue_index');
        });
    }
};
