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
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('ad_type_id')->constrained();
            $table->string('title');
            $table->text('description');
            $table->decimal('price', 15, 2);
            $table->string('address');
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('bedrooms')->nullable();
            $table->integer('bathrooms')->nullable();
            $table->decimal('area', 10, 2)->comment('in square feet/meters');
            $table->enum('property_type', ['apartment', 'house', 'villa', 'land', 'commercial', 'other']);
            $table->enum('listing_type', ['rent', 'sale']);
            $table->boolean('is_approved')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_paid')->default(false);
            $table->date('expiry_date');
            $table->boolean('is_featured')->default(false);
            $table->integer('views')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
}; 