<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UploadsController extends Controller
{
    public function uploadFile(Request $request)
    {
        try {
            $file = $request->file('file');

            if (!$file) {
                return errorResponse('No file provided');
            }

            // ✅ Grab metadata BEFORE moving (temp file still exists)
            $fileName  = time() . '_' . $file->getClientOriginalName();
            $mimeType  = $file->getClientMimeType();
            $size      = $file->getSize();
            $ext       = $file->getClientOriginalExtension();

            $destination = '/home1/owkvvkte/public_html/website_e166ca66/uploads';

            if (!is_dir($destination)) {
                mkdir($destination, 0755, true);
            }

            // Move file — after this the temp file is gone
            $file->move($destination, $fileName);

            $url = 'https://workason.com/uploads/' . $fileName;

            return okResponse('Upload success', [
                'url'       => $url,
                'file_name' => $fileName,
                'file_type' => $mimeType,
                'size'      => $size,
                'ext'       => $ext,
            ]);

        } catch (\Throwable $th) {
            \Log::error('File upload failed: ' . $th->getMessage());
            return errorResponse('Upload failed: ' . $th->getMessage());
        }
    }
}