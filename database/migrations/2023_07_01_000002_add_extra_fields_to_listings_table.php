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
        Schema::table('listings', function (Blueprint $table) {
            $table->boolean('is_furnished')->default(false)->after('listing_type');
            $table->integer('floor_number')->nullable()->after('is_furnished');
            $table->integer('insurance_months')->nullable()->after('floor_number');
            $table->json('features')->nullable()->after('insurance_months');
        });

        Schema::table('listing_images', function (Blueprint $table) {
            $table->boolean('is_ownership_proof')->default(false)->after('is_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['is_furnished', 'floor_number', 'insurance_months', 'features']);
        });

        Schema::table('listing_images', function (Blueprint $table) {
            $table->dropColumn('is_ownership_proof');
        });
    }
}; 