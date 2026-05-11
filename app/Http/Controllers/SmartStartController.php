// app/Http/Controllers/SmartStartController.php

namespace App\Http\Controllers;

use App\Models\SmartStartRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SmartStartController extends Controller
{
    // ── Step 1: Save form & redirect to Paystack ──────────────
    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_type' => 'required|string',
            'title'        => 'required|string|max:255',
            'description'  => 'required|string',
            'budget_min'   => 'nullable|numeric',
            'budget_max'   => 'nullable|numeric',
            'deadline'     => 'nullable|date',
            'urgency'      => 'required|in:flexible,normal,urgent',
            'ref_links'    => 'nullable|string',
            'extra_notes'  => 'nullable|string',
            'files.*'      => 'nullable|file|mimes:pdf,jpg,jpeg,png,zip|max:20480',
        ]);

        // Upload files
        $fileUrls = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('smartstart/attachments', 'public');
                $fileUrls[] = asset('storage/' . $path);
            }
        }

        // Generate unique reference
        $reference = 'SS-' . auth()->id() . '-' . time();

        // Save to DB
        $smartStart = SmartStartRequest::create([
            ...$validated,
            'employer_id'       => auth()->id(),
            'file_urls'         => $fileUrls,
            'status'            => 'pending',
            'payment_status'    => 'unpaid',
            'payment_reference' => $reference,
        ]);

        // Initialize Paystack transaction
        $response = Http::withToken(config('paystack.secretKey'))
            ->post('https://api.paystack.co/transaction/initialize', [
                'email'        => auth()->user()->email,
                'amount'       => 1000000, // ₦10,000 in kobo
                'reference'    => $reference,
                'currency'     => 'NGN',
                'callback_url' => config('app.frontend_url') . '/smartstart/callback?reference=' . $reference,
                'metadata'     => [
                    'smartstart_id' => $smartStart->id,
                    'employer_name' => auth()->user()->name,
                    'project_type'  => $smartStart->project_type,
                ],
            ])->json();

        if (!$response['status']) {
            return response()->json([
                'message' => 'Could not initiate payment. Please try again.',
            ], 400);
        }

        return response()->json([
            'message'           => 'Payment initiated',
            'payment_url'       => $response['data']['authorization_url'],
            'reference'         => $reference,
            'smartstart_id'     => $smartStart->id,
        ]);
    }

    // ── Step 2: Frontend calls this after Paystack redirects back ──
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'reference' => 'required|string',
        ]);

        $reference  = $request->reference;
        $smartStart = SmartStartRequest::where('payment_reference', $reference)
                        ->where('employer_id', auth()->id())
                        ->firstOrFail();

        // Already verified — don't call Paystack again
        if ($smartStart->payment_status === 'paid') {
            return response()->json([
                'message'    => 'Payment already verified.',
                'smartstart' => $smartStart,
            ]);
        }

        // Verify with Paystack
        $response = Http::withToken(config('paystack.secretKey'))
            ->get("https://api.paystack.co/transaction/verify/{$reference}")
            ->json();

        if (
            isset($response['data']['status']) &&
            $response['data']['status'] === 'success' &&
            $response['data']['amount'] === 1000000 // confirm correct amount
        ) {
            $smartStart->update([
                'payment_status' => 'paid',
                'paid_at'        => now(),
            ]);

            return response()->json([
                'message'    => 'Payment verified. Your SmartStart request is now active.',
                'smartstart' => $smartStart,
            ]);
        }

        return response()->json([
            'message' => 'Payment verification failed. Please contact support.',
        ], 400);
    }

    // ── Get employer's SmartStart requests ────────────────────
    public function index()
    {
        $requests = SmartStartRequest::where('employer_id', auth()->id())
                        ->latest()
                        ->get();

        return response()->json(['data' => $requests]);
    }

    // ── Get single SmartStart request ─────────────────────────
    public function show($id)
    {
        $smartStart = SmartStartRequest::where('id', $id)
                        ->where('employer_id', auth()->id())
                        ->firstOrFail();

        return response()->json(['data' => $smartStart]);
    }
}