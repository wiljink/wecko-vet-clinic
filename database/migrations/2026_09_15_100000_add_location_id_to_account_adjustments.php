<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * account_adjustments was missed when branches were introduced — it stayed
 * company-wide, so a branch-pinned user could see every branch's manual
 * account adjustments on a client's ledger/statement/aging report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_adjustments', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
        });

        // Best-effort backfill: an adjustment tied to an invoice inherits that
        // invoice's branch; everything else (e.g. company-wide statement fees)
        // stays unassigned, which BelongsToLocation treats as visible to all.
        // Done in PHP (not a JOIN UPDATE) so it works on every DB driver, incl. SQLite in tests.
        DB::table('account_adjustments')
            ->join('invoices', 'invoices.id', '=', 'account_adjustments.invoice_id')
            ->whereNotNull('invoices.location_id')
            ->get(['account_adjustments.id as adjustment_id', 'invoices.location_id'])
            ->each(fn ($row) => DB::table('account_adjustments')
                ->where('id', $row->adjustment_id)
                ->update(['location_id' => $row->location_id]));
    }

    public function down(): void
    {
        Schema::table('account_adjustments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
        });
    }
};
