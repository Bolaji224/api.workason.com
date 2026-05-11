<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'name'    => 'required|string',
            'email'   => 'required|email',
            'subject' => 'required|string',
            'message' => 'required|string',
        ]);

        // ✅ Send email to Workason team
        try {
            Mail::raw(
                "New contact message from {$request->name} ({$request->email}):\n\nSubject: {$request->subject}\n\nMessage:\n{$request->message}",
                function ($mail) use ($request) {
                    $mail->to('contact@workason.com')
                         ->subject("Contact Form: {$request->subject}")
                         ->replyTo($request->email, $request->name);
                }
            );
        } catch (\Exception $e) {
            \Log::error('Contact email failed: ' . $e->getMessage());
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Message sent successfully!',
        ]);
    }
}