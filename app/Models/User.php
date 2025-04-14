<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Booking;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'phone',
        'profile_image',
        'id_card_image',
        'wallet_number',
        'is_active',
        'google_id',
        'facebook_id',
        'joined_date',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
        'facebook_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'joined_date' => 'datetime',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        static::creating(function ($user) {
            $user->joined_date = now();
        });
    }

    /**
     * Get the role associated with the user.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the listings that belong to the user.
     */
    public function listings()
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * Get the favorites that belong to the user.
     */
    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * Get the payments that belong to the user.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get comments created by the user.
     */
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get comments received on the user's listings.
     */
    public function receivedComments()
    {
        return $this->hasManyThrough(
            Comment::class,
            Listing::class,
            'user_id', // Foreign key on listings table
            'listing_id', // Foreign key on comments table
            'id', // Local key on users table
            'id' // Local key on listings table
        );
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $roleName): bool
    {
        return $this->role->name === $roleName;
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Check if the user is an owner.
     */
    public function isOwner(): bool
    {
        return $this->hasRole('owner');
    }

    /**
     * Get favorited listings.
     */
    public function favoritedListings()
    {
        return $this->belongsToMany(Listing::class, 'favorites')
            ->withTimestamps();
    }

    /**
     * Get count of sales (completed payments received).
     */
    public function getSalesCountAttribute()
    {
        return Payment::whereHas('listing', function($query) {
            $query->where('user_id', $this->id);
        })->where('status', 'completed')->count();
    }

    /**
     * Get count of listings created by this user.
     */
    public function getListingsCountAttribute()
    {
        return $this->listings()->count();
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get the saved searches for the user.
     */
    public function savedSearches()
    {
        return $this->hasMany(SavedSearch::class);
    }
}
