<?php
namespace App\Http\Controllers;

use App\Mail\NewsletterWelcomeMail;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->email;

        // ✅ Check if already subscribed
        $existing = NewsletterSubscriber::where('email', $email)->first();
        if ($existing) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This email is already subscribed.',
            ], 409);
        }

        // ✅ Generate unique token
        $token = strtoupper(Str::random(8));

        // ✅ Save subscriber first
        NewsletterSubscriber::create([
            'email' => $email,
            'token' => $token,
        ]);

        // ✅ Send welcome email
        try {
            \Mail::to($email)->send(new NewsletterWelcomeMail($email, $token));
            \Log::info("Newsletter email sent to: " . $email);
        } catch (\Exception $e) {
            \Log::error('Newsletter email failed: ' . $e->getMessage());
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Subscribed successfully! Check your email for your token.',
        ]);
    }
}