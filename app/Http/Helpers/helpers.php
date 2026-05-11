<?php
use Illuminate\Http\Request;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!function_exists("randomToken")) {
    function randomToken($length = 6, $type = 'number')
    {
        $random = "123456789";
        if($type == "text"){
            $random = "ABCDEFHIJKLMNOPQRSTUVYZYX1234567890";
        }
        return substr(str_shuffle($random), 0, $length);
    }
}

if (!function_exists("errorResponse")) {
    function errorResponse($msg = "", $errors = "", $code = 422)
    {
        return response()->json([
            'code'    => $code,
            'status'  => 'error',
            'errors'  => $errors,
            'message' => $msg
        ], $code);
    }
}

if (!function_exists("simplePagination")) {
    function simplePagination($data, $count, $page, $limit)
    {
        return [
            'data'         => $data,
            'limit'        => $limit,
            'currentPage'  => $page,
            'totalRecords' => $count,
            'totalPages'   => ceil($count/$limit),
        ];
    }
}

if (!function_exists("okResponse")) {
    function okResponse($msg = "", $response = [])
    {
        return response()->json([
            'code'    => 200,
            'status'  => 'success',
            'data'    => $response,
            'message' => $msg
        ], 200);
    }
}

if (!function_exists("uploadFile")) {
    function uploadFile(Request $request, $key = "")
    {
        try {
            $filePath = $request->file($key != "" ? $key : "file")->getRealPath();

            // upload as public so URL is accessible without authentication
            $upload = Cloudinary::uploadFile($filePath, [
                'resource_type' => 'auto',
                'type'          => 'upload',
                'access_mode'   => 'public',
            ]);

            return [
                "url"         => $upload->getSecurePath(),
                "size"        => $upload->getSize(),
                "size_in_kb"  => $upload->getReadableSize(),
                "file_type"   => $upload->getFileType(),
                "file_name"   => $upload->getFileName(),
                "file_id"     => $upload->getPublicId(),
                "ext"         => $upload->getExtension(),
                "width"       => $upload->getWidth(),
                "height"      => $upload->getHeight(),
                "uploaded_at" => $upload->getTimeUploaded(),
            ];
        } catch (\Throwable $th) {
            \Log::error('Cloudinary upload failed: ' . $th->getMessage());
            return false;
        }
    }
}

if (!function_exists("uploadFiles")) {
    function uploadFiles(Request $request, $key = 'files')
    {
        $response = [];
        foreach ($request->file($key) as $k => $file) {
            $upload = Cloudinary::upload($file->getRealPath(), [
                'resource_type' => 'auto',
                'type'          => 'upload',
                'access_mode'   => 'public',
            ]);
            $response[] = [
                "url"         => $upload->getSecurePath(),
                "size"        => $upload->getSize(),
                "size_in_kb"  => $upload->getReadableSize(),
                "file_type"   => $upload->getFileType(),
                "file_name"   => $upload->getFileName(),
                "file_id"     => $upload->getPublicId(),
                "ext"         => $upload->getExtension(),
                "width"       => $upload->getWidth(),
                "height"      => $upload->getHeight(),
                "uploaded_at" => $upload->getTimeUploaded(),
            ];
        }
        return $response;
    }
}

if (!function_exists("uploadLocalFile")) {
    function uploadLocalFile($url)
    {
        try {
            $upload = Cloudinary::uploadFile($url, [
                'resource_type' => 'auto',
                'type'          => 'upload',
                'access_mode'   => 'public',
            ]);
            return [
                "url"     => $upload->getSecurePath(),
                "file_id" => $upload->getPublicId(),
            ];
        } catch (\Throwable $th) {
            \Log::error('Cloudinary local upload failed: ' . $th->getMessage());
            return false;
        }
    }
}

if (!function_exists("deleteCloudFile")) {
    function deleteCloudFile($publicId)
    {
        try {
            return Cloudinary::destroy($publicId);
        } catch (\Throwable $th) {
            \Log::error('Cloudinary delete failed: ' . $th->getMessage());
            return false;
        }
    }
}

if (!function_exists("sendMail2")) {
    function sendMail2($email, $subject, $body)
    {
        require base_path("vendor/autoload.php");
        $mail = new PHPMailer(true);

        try {
            $mail->SMTPDebug  = 0;
            $mail->isSMTP();
            $mail->Host       = env('MAIL_HOST');
            $mail->SMTPAuth   = true;
            $mail->Username   = env('MAIL_USERNAME');
            $mail->Password   = env('MAIL_PASSWORD');
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom(env("MAIL_FROM_ADDRESS"), env("MAIL_FROM_NAME"));
            $mail->addAddress($email);
            $mail->isHTML(true);

            $mail->Subject = $subject;
            $mail->Body    = $body;

            return $mail->send() ? true : false;

        } catch (Exception $e) {
            return false;
        }
    }
}