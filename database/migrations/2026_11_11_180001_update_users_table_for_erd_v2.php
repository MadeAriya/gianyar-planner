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
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'nama')) {
                $table->string('nama')->after('id')->nullable();
            }
            if (! Schema::hasColumn('users', 'no_hp')) {
                $table->string('no_hp', 30)->nullable()->after('email');
            }
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 30)->default('user')->index()->after('password');
            }
            if (! Schema::hasColumn('users', 'status')) {
                $table->string('status', 30)->default('active')->index()->after('role');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nama', 'no_hp', 'role', 'status']);
        });
    }
};
