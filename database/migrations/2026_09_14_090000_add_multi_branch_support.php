<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns `locations` from a name-only referential list into real branches:
 * branch details, a home branch per user, location_id on every transactional
 * table that was still company-wide, per-branch stock levels, and stock
 * transfers to move stock between branches. Existing data is backfilled onto
 * a single "main" location so nothing already saved loses its branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('name');
            $table->text('address')->nullable()->after('description');
            $table->string('phone')->nullable()->after('address');
            $table->string('email')->nullable()->after('phone');
            $table->boolean('is_main')->default(false)->after('email');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('home_location_id')->nullable()->after('email')->constrained('locations')->nullOnDelete();
        });

        foreach (['invoices', 'payments', 'banking_batches'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        foreach ([
            'stock_movements', 'stock_receipts', 'stock_takes',
            'inventory_adjustments', 'inventory_returns', 'inventory_orders',
        ] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        Schema::create('product_stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty_on_hand', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'location_id']);
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('from_location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('to_location_id')->constrained('locations')->cascadeOnDelete();
            $table->date('transfer_date');
            $table->string('status')->default('draft'); // draft | posted
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 12, 2);
            $table->timestamps();
        });

        $this->backfill();
    }

    private function backfill(): void
    {
        // Nothing to backfill on a genuinely fresh install — the app's own
        // seeders create real branches, so don't invent a placeholder one.
        $hasExistingData = DB::table('locations')->exists()
            || DB::table('users')->exists()
            || DB::table('products')->exists();

        if (! $hasExistingData) {
            return;
        }

        $mainId = DB::table('locations')->where('is_main', true)->value('id');

        if (! $mainId) {
            $firstId = DB::table('locations')->orderBy('id')->value('id');

            $mainId = $firstId ?: DB::table('locations')->insertGetId([
                'name' => 'Main Branch',
                'is_active' => true,
                'is_main' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('locations')->where('id', $mainId)->update(['is_main' => true]);
        }

        DB::table('users')->whereNull('home_location_id')->update(['home_location_id' => $mainId]);

        foreach ([
            'invoices', 'payments', 'banking_batches',
            'stock_movements', 'stock_receipts', 'stock_takes',
            'inventory_adjustments', 'inventory_returns', 'inventory_orders',
        ] as $t) {
            DB::table($t)->whereNull('location_id')->update(['location_id' => $mainId]);
        }

        $now = now();
        $rows = DB::table('products')->select('id', 'qty_on_hand')->get()->map(fn ($p) => [
            'product_id' => $p->id,
            'location_id' => $mainId,
            'qty_on_hand' => $p->qty_on_hand,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('product_stock_levels')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('product_stock_levels');

        foreach ([
            'stock_movements', 'stock_receipts', 'stock_takes',
            'inventory_adjustments', 'inventory_returns', 'inventory_orders',
            'invoices', 'payments', 'banking_batches',
        ] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropConstrainedForeignId('location_id');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('home_location_id');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['code', 'address', 'phone', 'email', 'is_main']);
        });
    }
};
