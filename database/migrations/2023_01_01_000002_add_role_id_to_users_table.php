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
            // First add the column without constraints
            $table->unsignedBigInteger('role_id')->after('id')->default(3); // Default to user role (assuming 3 is user)
            $table->string('phone')->after('email')->nullable();
            $table->string('profile_image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('google_id')->nullable();
            $table->string('facebook_id')->nullable();
        });

        // Then add the foreign key constraint
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role_id')->references('id')->on('roles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn(['role_id', 'phone', 'profile_image', 'is_active', 'google_id', 'facebook_id']);
        });
    }
}; 