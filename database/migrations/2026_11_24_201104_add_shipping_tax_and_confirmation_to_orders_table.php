<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_carrier')->nullable()->after('subtotal_cents');
            $table->string('shipping_timeframe')->nullable()->after('shipping_carrier');
            $table->unsignedBigInteger('shipping_cents')->default(0)->after('shipping_timeframe');
            $table->string('tax_label')->nullable()->after('shipping_cents');
            $table->decimal('tax_rate', 6, 3)->default(0)->after('tax_label');
            $table->unsignedBigInteger('tax_cents')->default(0)->after('tax_rate');
            $table->timestamp('confirmation_sent_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_carrier', 'shipping_timeframe', 'shipping_cents',
                'tax_label', 'tax_rate', 'tax_cents', 'confirmation_sent_at',
            ]);
        });
    }
};
