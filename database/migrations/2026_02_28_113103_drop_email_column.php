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
        $driver = Schema::getConnection()->getDriverName();

        // SQLite can fail dropping an indexed column in this project setup.
        // Keep email during sqlite-based tests; MySQL/MariaDB still drop it.
        if ($driver === 'sqlite') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            //drop the email column since we are not using it anymore
            if (Schema::hasColumn('users', 'email')) {
                $table->dropColumn('email');
            }
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'email')) {
                $table->string('email')->unique()->after('name');
            }
        });
    }
};
