<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the clinic barcode-ready, POS-ready and import-ready:
 *  - a lookup index on products.barcode for fast scanning
 *  - receipt / POS preferences on the single company_settings row
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index('barcode');
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('auto_generate_barcode')->default(false)->after('auto_generate_product_code');
            $table->string('barcode_symbology')->default('C128')->after('auto_generate_barcode');
            $table->text('receipt_header')->nullable();
            $table->text('receipt_footer')->nullable();
            $table->string('pos_default_payment_type')->default('cash');
            $table->boolean('pos_print_receipt')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['barcode']);
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'auto_generate_barcode', 'barcode_symbology', 'receipt_header',
                'receipt_footer', 'pos_default_payment_type', 'pos_print_receipt',
            ]);
        });
    }
};
