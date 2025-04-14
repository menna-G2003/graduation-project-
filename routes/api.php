<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\AdTypeController;
use App\Http\Controllers\API\BookingController;
use App\Http\Controllers\API\CommentController;
use App\Http\Controllers\API\FavoriteController;
use App\Http\Controllers\API\ListingController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\AdminController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Test route
Route::get('/test', function () {
    return response()->json([
        'message' => 'API is working',
        'timestamp' => now()->toDateTimeString(),
    ]);
});

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
Route::post('/resend-verification-email', [AuthController::class, 'resendVerificationEmail']);

// Social login
Route::get('/login/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/login/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::get('/login/facebook', [AuthController::class, 'redirectToFacebook']);
Route::get('/login/facebook/callback', [AuthController::class, 'handleFacebookCallback']);

// Public listing routes
Route::get('/listings/featured', [ListingController::class, 'featured']);
Route::get('/listings/search', [ListingController::class, 'search']);
Route::get('/listings', [ListingController::class, 'index']);
Route::get('/listings/{listing}', [ListingController::class, 'show']);
Route::get('/ad-types', [AdTypeController::class, 'index']);
Route::get('/apartments', [UserController::class, 'getFilteredApartments']);

// City and governorate listing stats
Route::get('/locations/stats', [ListingController::class, 'locationStats']);
Route::get('/property-types/stats', [ListingController::class, 'propertyTypeStats']);

// Public comments routes
Route::get('/listings/{listing}/comments', [CommentController::class, 'index']);
Route::get('/listings/{listing}/comments/{comment}', [CommentController::class, 'show']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // User profile
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [UserController::class, 'getProfile']);
    Route::put('/user', [UserController::class, 'updateProfile']);
    Route::put('/user/change-password', [UserController::class, 'changePassword']);
    
    // Favorites
    Route::get('/favorites', [UserController::class, 'getFavorites']);
    Route::post('/favorites/{listing}', [FavoriteController::class, 'toggle']);
    Route::delete('/favorites/{listing}', [FavoriteController::class, 'remove']);
    
    // Comments
    Route::post('/listings/{listing}/comments', [CommentController::class, 'store']);
    Route::put('/listings/{listing}/comments/{comment}', [CommentController::class, 'update']);
    Route::delete('/listings/{listing}/comments/{comment}', [CommentController::class, 'destroy']);
    Route::get('/my-listings-comments', [CommentController::class, 'myListingsComments']);
    
    // Bookings
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::post('/listings/{listing}/bookings', [BookingController::class, 'store']);
    Route::put('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    
    // Owner only routes
    Route::middleware('role:owner,admin')->group(function () {
        // Listings management
        Route::post('/listings', [ListingController::class, 'store']);
        Route::put('/listings/{listing}', [ListingController::class, 'update']);
        Route::delete('/listings/{listing}', [ListingController::class, 'destroy']);
        Route::post('/listings/{listing}/images', [ListingController::class, 'uploadImages']);
        Route::delete('/listings/{listing}/images/{image}', [ListingController::class, 'deleteImage']);
        Route::put('/listings/{listing}/images/{image}/primary', [ListingController::class, 'setPrimaryImage']);
        Route::get('/my-listings', [ListingController::class, 'getMyListings']);
        
        // Booking management (for property owners)
        Route::put('/bookings/{booking}/status', [BookingController::class, 'updateStatus']);
        Route::get('/my-property-bookings', [BookingController::class, 'getPropertyBookings']);
        
        // Payments
        Route::get('/payment-methods', [PaymentController::class, 'getPaymentMethods']);
        Route::post('/payments/create-intent', [PaymentController::class, 'createPaymentIntent']);
        Route::post('/payments/confirm', [PaymentController::class, 'confirmPayment']);
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    });

    // Admin only routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // Users management
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/{user}', [AdminController::class, 'showUser']);
        Route::put('/users/{user}/toggle-activation', [AdminController::class, 'toggleUserActivation']);
        Route::delete('/users/{user}', [AdminController::class, 'deleteUser']);
        
        // Listings management
        Route::get('/listings', [AdminController::class, 'listings']);
        Route::put('/listings/{listing}/approve', [AdminController::class, 'approveListing']);
        Route::put('/listings/{listing}/reject', [AdminController::class, 'rejectListing']);
        Route::delete('/listings/{listing}', [AdminController::class, 'deleteListing']);
        
        // Payments management
        Route::get('/payments', [AdminController::class, 'payments']);
        Route::get('/statistics', [AdminController::class, 'statistics']);
        
        // Ad type management
        Route::post('/ad-types', [AdTypeController::class, 'store']);
        Route::put('/ad-types/{adType}', [AdTypeController::class, 'update']);
        Route::delete('/ad-types/{adType}', [AdTypeController::class, 'destroy']);
    });
}); 