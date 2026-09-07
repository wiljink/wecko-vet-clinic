<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('job_position_id')->nullable()->after('email'); // FK added with reference tables
            $table->string('licence_no')->nullable()->after('job_position_id');
            $table->boolean('is_provider')->default(false)->after('licence_no');
            $table->boolean('is_principal')->default(false)->after('is_provider');
            $table->boolean('can_login')->default(true)->after('is_principal');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['job_position_id', 'licence_no', 'is_provider', 'is_principal', 'can_login']);
        });
    }
};
