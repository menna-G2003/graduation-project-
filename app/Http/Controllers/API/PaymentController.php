<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * Display a listing of the user's payments.
     */
    public function index(Request $request)
    {
        $payments = Payment::with(['listing'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json([
            'data' => $payments,
            'meta' => [
                'total' => $payments->total(),
                'per_page' => $payments->perPage(),
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
            ],
        ]);
    }

    /**
     * Show a specific payment.
     */
    public function show(Request $request, Payment $payment)
    {
        // Ensure the user owns this payment
        if ($payment->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $payment->load('listing'),
        ]);
    }

    /**
     * Create a payment intent.
     */
    public function createPaymentIntent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'listing_id' => 'required|exists:listings,id',
            'payment_method' => 'required|string|in:stripe,vodafone_cash,bank_transfer,paypal',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $listing = Listing::findOrFail($request->listing_id);

        // Ensure the user owns this listing
        if ($listing->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if listing is already paid
        if ($listing->is_paid) {
            return response()->json(['message' => 'Listing is already paid'], 400);
        }

        // Get the ad type price
        $price = $listing->adType->price;

        if ($price <= 0) {
            return response()->json(['message' => 'This ad type does not require payment'], 400);
        }

        // Handle different payment methods
        $paymentInfo = [];
        
        switch ($request->payment_method) {
            case 'stripe':
                // In a real application, you would create a Stripe payment intent here
                $paymentInfo = [
                    'id' => 'pi_' . uniqid(),
                    'client_secret' => 'cs_' . uniqid(),
                    'amount' => $price * 100,  // Amount in cents
                    'currency' => 'usd',
                    'instructions' => 'Complete payment on the checkout page',
                ];
                break;
                
            case 'vodafone_cash':
                $paymentInfo = [
                    'id' => 'vc_' . uniqid(),
                    'amount' => $price,
                    'currency' => 'egp',
                    'instructions' => 'Send payment to Vodafone Cash wallet: 01XXXXXXXXX with reference: ' . $listing->id,
                    'note' => 'After sending payment, please upload the receipt/screenshot as proof',
                ];
                break;
                
            case 'bank_transfer':
                $paymentInfo = [
                    'id' => 'bt_' . uniqid(),
                    'amount' => $price,
                    'currency' => 'egp',
                    'instructions' => 'Transfer payment to Bank Account: XXXXXXXX with reference: ' . $listing->id,
                    'note' => 'After sending payment, please upload the receipt/screenshot as proof',
                ];
                break;
                
            case 'paypal':
                $paymentInfo = [
                    'id' => 'pp_' . uniqid(),
                    'amount' => $price,
                    'currency' => 'usd',
                    'instructions' => 'Complete payment on the PayPal checkout page',
                ];
                break;
        }

        return response()->json([
            'payment_intent' => $paymentInfo,
            'listing' => $listing->load('adType'),
        ]);
    }

    /**
     * Confirm payment and update listing status.
     */
    public function confirmPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|string',
            'listing_id' => 'required|exists:listings,id',
            'payment_method' => 'required|string|in:stripe,vodafone_cash,bank_transfer,paypal',
            'receipt_image' => 'required_if:payment_method,vodafone_cash,bank_transfer|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $listing = Listing::findOrFail($request->listing_id);

        // Ensure the user owns this listing
        if ($listing->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if listing is already paid
        if ($listing->is_paid) {
            return response()->json(['message' => 'Listing is already paid'], 400);
        }

        // Process receipt image if provided
        $receiptPath = null;
        if ($request->hasFile('receipt_image')) {
            $receiptPath = $request->file('receipt_image')->store('receipts', 'public');
        }

        // Set payment status based on payment method
        $status = 'pending';
        $notes = '';
        
        // Automatic approval for credit card payments
        if ($request->payment_method === 'stripe' || $request->payment_method === 'paypal') {
            $status = 'completed';
            $notes = 'Payment processed automatically';
        } else {
            $status = 'pending';
            $notes = 'Awaiting manual verification';
        }

        // Create payment record
        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'listing_id' => $listing->id,
            'payment_id' => $request->payment_id,
            'payment_method' => $request->payment_method,
            'amount' => $listing->adType->price,
            'currency' => $request->payment_method === 'stripe' || $request->payment_method === 'paypal' ? 'usd' : 'egp',
            'status' => $status,
            'notes' => $notes,
        ]);

        // Update listing status for auto-approved payments
        if ($status === 'completed') {
            $listing->update([
                'is_paid' => true,
                // Reset expiry date from payment date
                'expiry_date' => Carbon::now()->addDays($listing->adType->duration_days),
            ]);
        }

        return response()->json([
            'message' => $status === 'completed' ? 
                'Payment confirmed successfully' : 
                'Payment received and awaiting verification',
            'payment' => $payment,
            'listing' => $listing->fresh(),
        ]);
    }

    /**
     * Get available payment methods.
     */
    public function getPaymentMethods()
    {
        $methods = [
            [
                'id' => 'stripe',
                'name' => 'Credit/Debit Card',
                'description' => 'Pay securely with your credit or debit card',
                'auto_approved' => true,
            ],
            [
                'id' => 'vodafone_cash',
                'name' => 'Vodafone Cash',
                'description' => 'Pay using Vodafone Cash mobile wallet',
                'auto_approved' => false,
            ],
            [
                'id' => 'bank_transfer',
                'name' => 'Bank Transfer',
                'description' => 'Pay via bank transfer',
                'auto_approved' => false,
            ],
            [
                'id' => 'paypal',
                'name' => 'PayPal',
                'description' => 'Pay securely with PayPal',
                'auto_approved' => true,
            ],
        ];

        return response()->json(['data' => $methods]);
    }
} 