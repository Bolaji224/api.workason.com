<?php
namespace App\Http\Controllers;
use App\Models\Dispute;
use Illuminate\Http\Request;
class DisputeController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'user_type'        => 'required|in:candidate,employer',
            'full_name'        => 'required|string|max:255',
            'email'            => 'required|email',
            'phone'            => 'required|string|max:20',
            'employer_name'    => 'nullable|string|max:255',
            'candidate_name'   => 'nullable|string|max:255', // ✅ added validation
            'dispute_category' => 'required|string',
            'subject'          => 'required|string|max:255',
            'description'      => 'required|string|min:50',
            'priority'         => 'required|in:low,medium,high,urgent',
            'attachments.*'    => 'file|mimes:pdf,doc,docx,jpg,png|max:5120',
        ]);

        $attachmentPaths = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachmentPaths[] = $file->store('dispute-attachments', 'public');
            }
        }

        $dispute = Dispute::create([
            'user_id'          => auth()->check() ? auth()->id() : null,
            'user_type'        => $request->user_type,        // ✅ was missing
            'full_name'        => $request->full_name,
            'email'            => $request->email,
            'phone'            => $request->phone,
            'employer_name'    => $request->employer_name,
            'candidate_name'   => $request->candidate_name,  // ✅ was missing
            'dispute_category' => $request->dispute_category,
            'subject'          => $request->subject,
            'description'      => $request->description,
            'priority'         => $request->priority,
            'status'           => 'Pending',
            'attachments'      => $attachmentPaths,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Dispute submitted successfully',
            'data'    => $dispute,
        ], 201);
    }

    public function index()
    {
        return response()->json([
            'status' => true,
            'data'   => Dispute::latest()->get()
        ]);
    }
}