<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extends the default users table (kept as Laravel generated it) with
     * the RankPro-specific profile/role fields, added here rather than by
     * editing the original migration file.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('password');
            $table->foreignId('role_id')->nullable()->after('avatar')->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('role_id');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['avatar', 'is_active', 'last_login_at']);
        });
    }
};
