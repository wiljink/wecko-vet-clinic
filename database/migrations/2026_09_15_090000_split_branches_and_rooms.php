<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Splits the single `locations` list into branches and rooms: a `type`
 * column tells them apart, and appointments — the one place that genuinely
 * needs both — get a separate `room_id` alongside their existing
 * (branch) `location_id`. Every other branch picker in the app stays
 * scoped to type=branch (see LocationContext::selectField()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('type')->default('room')->after('name');
        });

        // Anything that already looks like a branch (has branch-only fields
        // filled in, or is flagged main) is a branch; everything else is a room.
        DB::table('locations')
            ->where('is_main', true)
            ->orWhereNotNull('code')
            ->orWhereNotNull('address')
            ->orWhereNotNull('phone')
            ->orWhereNotNull('email')
            ->update(['type' => 'branch']);

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('room_id')->nullable()->after('location_id')->constrained('locations')->nullOnDelete();
        });

        // Existing appointments: if their location was actually a room, move
        // it to room_id and fall back location_id to the main branch.
        $mainBranchId = DB::table('locations')->where('type', 'branch')->where('is_main', true)->value('id')
            ?? DB::table('locations')->where('type', 'branch')->orderBy('id')->value('id');

        $roomLocationIds = DB::table('locations')->where('type', 'room')->pluck('id');

        if ($mainBranchId) {
            DB::table('appointments')
                ->whereIn('location_id', $roomLocationIds)
                ->update(['room_id' => DB::raw('location_id'), 'location_id' => $mainBranchId]);
        }
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_id');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
