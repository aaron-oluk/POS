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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            // Store info
            $table->string('store_name')->default('Nexus Coffee & Co.');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('currency', 8)->default('USD');
            $table->string('currency_symbol', 8)->default('$');
            $table->decimal('exchange_rate', 12, 4)->default(1);
            $table->string('timezone')->default('UTC');
            // Appearance
            $table->boolean('dark_mode')->default(true);
            $table->boolean('compact_mode')->default(false);
            $table->boolean('sound_effects')->default(true);
            // Receipt
            $table->text('receipt_header')->nullable();
            $table->text('receipt_footer')->nullable();
            $table->string('paper_size')->default('80mm (Thermal)');
            $table->string('font_size')->default('Medium');
            $table->boolean('show_qr')->default(true);
            $table->boolean('auto_print')->default(false);
            // Payment
            $table->boolean('cash_enabled')->default(true);
            $table->boolean('card_enabled')->default(true);
            $table->boolean('mobile_enabled')->default(true);
            $table->boolean('gift_cards_enabled')->default(true);
            $table->boolean('split_payment_enabled')->default(true);
            $table->string('tip_options')->default('15, 18, 20, 25');
            $table->string('default_tip')->default('18%');
            $table->boolean('prompt_tips')->default(true);
            // Tax
            $table->decimal('tax_rate', 5, 2)->default(8.5);
            $table->string('tax_name')->default('Sales Tax');
            $table->boolean('tax_included')->default(false);
            $table->boolean('round_tax')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
