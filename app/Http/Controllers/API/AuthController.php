<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;
use App\Models\Listing;
use App\Http\Resources\UserResource;

// Add class alias for IDE and linter
if (!class_exists('Socialite')) {
    class_alias('Laravel\Socialite\Facades\Socialite', 'Socialite');
}

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'phone' => 'nullable|string|max:20',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'id_card_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'wallet_number' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle profile image upload
        $profileImagePath = null;
        if ($request->hasFile('profile_image')) {
            $profileImagePath = $request->file('profile_image')->store('profile_images', 'public');
        }

        // Handle ID card image upload
        $idCardImagePath = null;
        if ($request->hasFile('id_card_image')) {
            $idCardImagePath = $request->file('id_card_image')->store('id_cards', 'public');
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
            'phone' => $request->phone,
            'profile_image' => $profileImagePath,
            'id_card_image' => $idCardImagePath,
            'wallet_number' => $request->wallet_number,
        ]);

        event(new Registered($user));

        // Send email verification notification
        $user->sendEmailVerificationNotification();

        // Create token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user->load('role'),
            'token' => $token,
        ], 201);
    }

    /**
     * Login user and create token.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
            'remember_me' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if user exists and password is correct
        if (!Auth::attempt(['email' => $request->email, 'password' => $request->password], $request->remember_me)) {
            return response()->json(['message' => 'Invalid login credentials'], 401);
        }

        $user = $request->user();

        // Check if user is active
        if (!$user->is_active) {
            Auth::logout();
            return response()->json(['message' => 'Your account has been deactivated'], 403);
        }

        // Create token with longer expiration if remember_me is true
        $tokenExpiration = $request->remember_me ? 60 * 24 * 30 : 60 * 24; // 30 days or 24 hours
        $token = $user->createToken('auth_token', ['*'], now()->addMinutes($tokenExpiration))->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user->load('role'),
            'token' => $token,
        ]);
    }

    /**
     * Logout user and invalidate token.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Send a password reset link to the user.
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Reset link sent to your email'])
            : response()->json(['message' => 'Unable to send reset link'], 400);
    }

    /**
     * Reset user's password.
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password has been reset'])
            : response()->json(['message' => 'Unable to reset password'], 400);
    }

    /**
     * Verify email address.
     */
    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::find($id);

        if (!$user || !hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid verification link'], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified']);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json(['message' => 'Email has been verified']);
    }

    /**
     * Resend verification email.
     */
    public function resendVerificationEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link sent']);
    }

    /**
     * Redirect to Google for authentication.
     */
    public function redirectToGoogle()
    {
        return app('socialite')->driver('google')->stateless()->redirect();
    }

    /**
     * Handle Google callback.
     */
    public function handleGoogleCallback()
    {
        try {
            $googleUser = app('socialite')->driver('google')->stateless()->user();
            
            $user = User::where('email', $googleUser->email)->first();
            
            if (!$user) {
                $user = User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'google_id' => $googleUser->id,
                    'password' => Hash::make(rand(1, 10000)),
                    'role_id' => 3, // Default to regular user
                    'email_verified_at' => now(),
                ]);
            } else {
                $user->update([
                    'google_id' => $googleUser->id,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
            }
            
            Auth::login($user);
            
            $token = $user->createToken('google-auth')->plainTextToken;
            
            return response()->json([
                'message' => 'Google login successful',
                'user' => $user,
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            Log::error('Social login failure: ' . $e->getMessage());
            return response()->json(['message' => 'Authentication failed'], 400);
        }
    }

    /**
     * Redirect to Facebook for authentication.
     */
    public function redirectToFacebook()
    {
        return app('socialite')->driver('facebook')->redirect();
    }

    /**
     * Handle Facebook callback.
     */
    public function handleFacebookCallback()
    {
        try {
            $facebookUser = app('socialite')->driver('facebook')->user();
            
            $user = User::where('email', $facebookUser->email)->first();
            
            if (!$user) {
                $user = User::create([
                    'name' => $facebookUser->name,
                    'email' => $facebookUser->email,
                    'facebook_id' => $facebookUser->id,
                    'password' => Hash::make(rand(1, 10000)),
                    'role_id' => 3, // Default to regular user
                    'email_verified_at' => now(),
                ]);
            } else {
                $user->update([
                    'facebook_id' => $facebookUser->id,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
            }
            
            Auth::login($user);
            
            $token = $user->createToken('facebook-auth')->plainTextToken;
            
            return response()->json([
                'message' => 'Facebook login successful',
                'user' => $user,
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            Log::error('Social login failure: ' . $e->getMessage());
            return response()->json(['message' => 'Authentication failed'], 400);
        }
    }

    // Add rate limiting to sensitive routes
    public function __construct()
    {
        $this->middleware('throttle:10,1')->only(['approveListing', 'rejectListing']);
    }

    // Use more efficient queries with select()
    public function index()
    {
        $query = Listing::with(['user:id,name', 'adType:id,name'])->select(['id', 'title', 'price', 'user_id', 'ad_type_id']);
        // Rest of the method code...
    }
}

// Create ApiResponse helper
class ApiResponse {
    public static function success($data, $message = '', $code = 200) {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    public static function error($message, $errors = [], $code = 422) {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $code);
    }
}

    return response()->json(['data' => $user]);