<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'listing_id',
        'reason',
        'details',
        'status',
        'admin_notes',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_REJECTED = 'rejected';

    /**
     * Get the user that created the report.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the listing that was reported.
     */
    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }
} 