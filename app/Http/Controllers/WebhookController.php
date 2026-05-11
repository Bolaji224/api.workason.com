// app/Http/Controllers/WebhookController.php

namespace App\Http\Controllers;

use App\Models\SmartStartRequest;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function paystack(Request $request)
    {
        // Verify the request is genuinely from Paystack
        $hash = hash_hmac(
            'sha512',
            $request->getContent(),
            config('paystack.secretKey')
        );

        if ($hash !== $request->header('x-paystack-signature')) {
            return response('Unauthorized', 401);
        }

        $payload = $request->json()->all();
        $event   = $payload['event'] ?? null;

        if ($event === 'charge.success') {
            $reference  = $payload['data']['reference'];
            $smartStart = SmartStartRequest::where('payment_reference', $reference)->first();

            // Only update if not already marked paid (avoid duplicate processing)
            if ($smartStart && $smartStart->payment_status === 'unpaid') {
                $smartStart->update([
                    'payment_status' => 'paid',
                    'paid_at'        => now(),
                ]);

                // Notify admin to begin matching (uncomment when ready)
                // Notification::route('mail', config('workason.admin_email'))
                //     ->notify(new SmartStartPaidNotification($smartStart));
            }
        }

        return response('OK', 200);
    }
}