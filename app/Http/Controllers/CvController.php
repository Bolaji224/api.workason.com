<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class CvController extends Controller
{
    public function generate(Request $request)
    {
        // DomPDF needs more memory than the default limit
        ini_set('memory_limit', '512M');
        set_time_limit(120);

        try {
            $data = $request->all();

            $user = auth()->user();
            $user->load('skillstamps');

            // Handle photo upload → convert to base64 for PDF
            if ($request->hasFile('photo')) {
                $photoFile = $request->file('photo');
                $imageData = base64_encode(file_get_contents($photoFile->getRealPath()));
                $mimeType  = $photoFile->getMimeType();
                $data['photo'] = 'data:' . $mimeType . ';base64,' . $imageData;
            }

            // Pass only safe scalar data + user to the view (avoid passing huge objects)
            $data['user'] = $user;

            // Embed skillstamp badge if it exists
            $badgePath = public_path('images/skillstamp-badge.png');
            if (file_exists($badgePath)) {
                $data['skillstampBadge'] = 'data:image/png;base64,' . base64_encode(file_get_contents($badgePath));
            } else {
                $data['skillstampBadge'] = null;
            }

            // Ensure arrays for skills/tools (guard against empty or non-array)
            $data['skills'] = is_array($data['skills'] ?? null) ? $data['skills'] : [];
            $data['tools']  = is_array($data['tools'] ?? null)  ? $data['tools']  : [];

            $pdf = Pdf::loadView('cv.template', $data)
                ->setOptions([
                    'isHTML5ParserEnabled' => true,
                    'isRemoteEnabled'      => false,
                    'defaultFont'          => 'sans-serif',
                ])
                ->setPaper('A4', 'portrait');

            $userId      = auth()->id();
            $fileName    = 'smartcv_' . $userId . '_' . time() . '.pdf';
            $storagePath = 'public/smartcv/' . $fileName;

            if (!\Storage::exists('public/smartcv')) {
                \Storage::makeDirectory('public/smartcv');
            }

            \Storage::put($storagePath, $pdf->output());

            $user->smartcv = 'file/smartcv/' . $fileName;
            $user->save();

            return response()->json([
                'status' => 'success',
                'cv_url' => url('/file/smartcv/' . $fileName),
            ]);

        } catch (\Throwable $e) {
            \Log::error('CV Generation Error: ' . $e->getMessage());
            \Log::error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());

            return response()->json([
                'status'  => 'error',
                'message' => 'CV generation failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}