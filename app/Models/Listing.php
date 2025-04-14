<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Listing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'ad_type_id',
        'title',
        'description',
        'price',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'bedrooms',
        'bathrooms',
        'area',
        'property_type',
        'listing_type',
        'is_furnished',
        'floor_number',
        'insurance_months',
        'features',
        'is_approved',
        'is_active',
        'is_paid',
        'expiry_date',
        'is_featured',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'area' => 'decimal:2',
        'is_approved' => 'boolean',
        'is_active' => 'boolean',
        'is_paid' => 'boolean',
        'is_featured' => 'boolean',
        'is_furnished' => 'boolean',
        'features' => 'array',
        'expiry_date' => 'date',
    ];

    /**
     * Get the user that owns the listing.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the ad type of the listing.
     */
    public function adType()
    {
        return $this->belongsTo(AdType::class);
    }

    /**
     * Get the images for the listing.
     */
    public function images()
    {
        return $this->hasMany(ListingImage::class);
    }

    /**
     * Get the property images (excluding ownership proof).
     */
    public function propertyImages()
    {
        return $this->hasMany(ListingImage::class)
            ->where('is_ownership_proof', false);
    }

    /**
     * Get the ownership proof images.
     */
    public function ownershipProofImages()
    {
        return $this->hasMany(ListingImage::class)
            ->where('is_ownership_proof', true);
    }

    /**
     * Get the comments for the listing.
     */
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get the users who favorited this listing.
     */
    public function favoriteByUsers()
    {
        return $this->belongsToMany(User::class, 'favorites')
            ->withTimestamps();
    }

    /**
     * Get the payments for the listing.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the primary image for the listing.
     */
    public function getPrimaryImage()
    {
        return $this->images()->where('is_primary', true)->first() 
            ?? $this->images->first();
    }

    /**
     * Increment the view count.
     */
    public function incrementViews()
    {
        $this->increment('views');
    }

    /**
     * Scope a query to only include active listings.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->where('is_approved', true)
                     ->where('is_paid', true)
                     ->where('expiry_date', '>=', now());
    }

    /**
     * Scope a query to only include featured listings.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope a query to filter by city.
     */
    public function scopeByCity($query, $city)
    {
        return $query->where('city', $city);
    }

    /**
     * Scope a query to filter by property type.
     */
    public function scopeByPropertyType($query, $propertyType)
    {
        return $query->where('property_type', $propertyType);
    }

    /**
     * Scope a query to filter by listing type.
     */
    public function scopeByListingType($query, $listingType)
    {
        return $query->where('listing_type', $listingType);
    }

    /**
     * Scope a query to filter by price range.
     */
    public function scopeByPriceRange($query, $min, $max)
    {
        return $query->where('price', '>=', $min)
                     ->where('price', '<=', $max);
    }

    /**
     * Get the bookings for the listing.
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
} 