<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Dispute;

class ReportController extends Controller
{
    public function index()
    {
        $disputes = Dispute::latest()->get();
        $data = $disputes->map(function ($dispute) {
            return [
                'id'               => $dispute->id,
                'user_type'        => $dispute->user_type,
                'full_name'        => $dispute->full_name,
                'email'            => $dispute->email,
                'phone'            => $dispute->phone,
                'employer_name'    => $dispute->employer_name,
                'candidate_name'   => $dispute->candidate_name,
                'dispute_category' => $dispute->dispute_category,
                'subject'          => $dispute->subject,
                'priority'         => ucfirst($dispute->priority ?? 'low'),
                'status'           => $dispute->status ?? 'Pending',
                'description'      => $dispute->description,
                'attachments'      => $dispute->attachments,
                'created_at'       => $dispute->created_at,
            ];
        });

        return response()->json([
            'status' => true,
            'data'   => $data,
        ]);
    }

    public function resolve($id)
    {
        $dispute = Dispute::findOrFail($id);
        $dispute->status = 'Resolved';
        $dispute->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Dispute resolved successfully.',
        ]);
    }
}