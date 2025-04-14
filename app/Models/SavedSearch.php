<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedSearch extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'search_params',
        'is_notifiable',
        'notification_frequency',
        'last_notified_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'search_params' => 'array',
        'is_notifiable' => 'boolean',
        'last_notified_at' => 'datetime',
    ];

    /**
     * Get the user that owns the saved search.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include notifiable searches.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotifiable($query)
    {
        return $query->where('is_notifiable', true);
    }

    /**
     * Scope a query to only include searches due for notification.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDueForNotification($query)
    {
        return $query->where(function ($query) {
            $query->whereNull('last_notified_at')
                ->orWhere(function ($q) {
                    $q->where('notification_frequency', 'daily')
                        ->where('last_notified_at', '<=', now()->subDay());
                })
                ->orWhere(function ($q) {
                    $q->where('notification_frequency', 'weekly')
                        ->where('last_notified_at', '<=', now()->subWeek());
                })
                ->orWhere(function ($q) {
                    $q->where('notification_frequency', 'monthly')
                        ->where('last_notified_at', '<=', now()->subMonth());
                });
        });
    }

    /**
     * Get the search URL.
     *
     * @return string
     */
    public function getSearchUrlAttribute()
    {
        $baseUrl = config('app.url') . '/properties';
        $queryString = http_build_query($this->search_params);
        
        return $baseUrl . '?' . $queryString;
    }
} 