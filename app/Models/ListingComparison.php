<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListingComparison extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'listings',
        'notes',
    ];

    protected $casts = [
        'listings' => 'array',
    ];

    /**
     * Get the user that owns the comparison.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the listings in this comparison.
     */
    public function getListingsCollection()
    {
        if (!$this->listings) {
            return collect([]);
        }
        
        return Listing::whereIn('id', $this->listings)
            ->active()
            ->with(['user', 'adType', 'propertyImages'])
            ->get();
    }
} 