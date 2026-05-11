<?php

namespace App\Http\Controllers;

use App\Events\ForgotPasswordEvent;
use App\Http\Resources\UserResource;
use App\Mail\PasswordResetOtpMail;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use App\Models\PasswordReset;
use App\Mail\WelcomeMail;
use App\Models\Role;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use App\Models\Wallet;
use Tymon\JWTAuth\Facades\JWTAuth; 

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'login', 
            'uploadfile', 
            'register', 
            'resendVerificationEmail', 
            'forgotPassword', 
            'resetPassword', 
            'resendOtp',
            'verifyToken',
            'googleLogin'
        ]]);
    }

    public function register (Request $request)
    {
        $validated = $request->all();
        $role = strtolower($request->input('role', 'user'));

        // Define validation rules
        $rules = [
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:8',
        ];

        if (in_array($role, ['company', 'employer'])) {
            $rules['name'] = 'required|string|min:3';
        } else {
            $rules['first_name'] = 'required|string|min:3';
            $rules['last_name'] = 'required|string|min:3';
        }

        // Validate input
        $validator = Validator::make($validated, $rules);

        if ($validator->fails()) {
            $errors = json_decode($validator->errors(), true);
            $msg = array_values($errors)[0];
            return response()->json([
                'status' => 'error',
                'message' => $msg[0],
                'errors' => $errors,
            ], 422);
        }

        // Determine role ID
        $roleId = in_array($role, ['company', 'employer'])
            ? (Role::where("title", "Company")->value('id') ?? 2)
            : (Role::where("title", "User")->value('id') ?? 1);

        // Create user
        $user = User::create([
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'first_name' => in_array($role, ['company', 'employer']) ? null : $validated['first_name'] ?? null,
            'last_name' => in_array($role, ['company', 'employer']) ? null : $validated['last_name'] ?? null,
            'name' => in_array($role, ['company', 'employer']) ? $validated['name'] ?? null : null,
            'role' => $roleId,
        ]);

        // Create wallet for the new user
        Wallet::create([
            'user_id' => $user->id,
            'balance' => 0,
            'currency' => 'NGN',
        ]);

        // Automatically send email verification
        event(new Registered($user));

        return response()->json([
            'status' => 'success',
            'message' => 'Account created successfully. Please verify your email.',
            'user' => $user,
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/login",
     *     operationId="login",
     *     summary="Logs in user",
     *     tags={"Users"},
     *     description="Logs in user and gives access to authenticated resources",
     *     @OA\Parameter(
     *          name="email",
     *          description="Email Field",
     *          required=true,
     *          in="query",
     *          @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *          name="password",
     *          description="Password",
     *          required=true,
     *          in="query",
     *          @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response="200", description="Display a credential User."),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found"),
     * )
     */
    public function login(Request $request)
    {
        $validated = $request->all();
        $validator = Validator::make($validated, [
            'email' => 'required|string|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            $erro = json_decode($validator->errors(), true);
            $msg = array_values($erro)[0];
            return errorResponse($msg[0], $erro);
        }

        if (!$token = auth()->attempt(['email' => $validated['email'], 'password' => $validated['password']])) {
            return errorResponse("Invalid login combination", 400);
        }

        try {
            $user = User::where('email', $validated['email'])->first();
        } catch (\Exception $e) {
            return errorResponse($e->getMessage());
        }

        if ($user->status === "deleted") {
            return errorResponse("Account not found!");
        }

        if ($user->email_verified_at === null) {
            return errorResponse("Email not verified");
        }

        return $this->createNewToken($token);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/google",
     *     operationId="googleLogin",
     *     summary="Login with Google",
     *     tags={"Users"},
     *     description="Authenticate user via Google OAuth",
     *     @OA\Parameter(
     *          name="email",
     *          description="Google Email",
     *          required=true,
     *          in="query",
     *          @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *          name="name",
     *          description="Google Name",
     *          required=true,
     *          in="query",
     *          @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *          name="google_id",
     *          description="Google ID",
     *          required=true,
     *          in="query",
     *          @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *          name="picture",
     *          description="Google Profile Picture",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response="200", description="User authenticated successfully"),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=422, description="Validation Error"),
     * )
     */
    public function googleLogin(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'name' => 'required|string',
            'google_id' => 'required|string',
            'picture' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            $errors = json_decode($validator->errors(), true);
            $msg = array_values($errors)[0];
            return response()->json([
                'status' => 'error',
                'message' => $msg[0],
                'errors' => $errors,
            ], 422);
        }

        try {
            // Find user by email or google_id
            $user = User::where('email', $request->email)
                ->orWhere('google_id', $request->google_id)
                ->first();

            if ($user) {
                // User exists - update google_id if not set
                if (!$user->google_id) {
                    $user->update([
                        'google_id' => $request->google_id,
                        'picture' => $request->picture,
                        'email_verified_at' => now(),
                    ]);
                }
            } else {
                // Determine default role (User)
                $roleId = Role::where("title", "User")->value('id') ?? 1;

                // Split name into first and last name
                $nameParts = explode(' ', $request->name, 2);
                $firstName = $nameParts[0] ?? $request->name;
                $lastName = $nameParts[1] ?? '';

                // Create new user
                $user = User::create([
                    'name' => $request->name,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $request->email,
                    'google_id' => $request->google_id,
                    'picture' => $request->picture,
                    'role' => $roleId,
                    'password' => Hash::make(uniqid()),
                    'email_verified_at' => now(),
                ]);

                // Create wallet for the new user
                Wallet::create([
                    'user_id' => $user->id,
                    'balance' => 0,
                    'currency' => 'NGN',
                ]);
            }

            // Generate JWT token
            $token = JWTAuth::fromUser($user);

            // Return response in same format as regular login
            return response()->json([
                'code' => 200,
                'status' => 'success',
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth()->factory()->getTTL() * 60,
                'user' => new UserResource($user)
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Google login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function createNewToken($token)
    {
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => new UserResource(auth()->user())
        ]);
    }

    public function profile()
    {
        return response()->json(auth()->user());
    }



    /**
     * @OA\Post(
     *     path="/api/v1/logout",
     *     operationId="logout",
     *     summary="Logs out user",
     *     description="Logs out user",
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent()
     *       ),
     *      security={{ "apiAuth": {} }},
     * )
     */
    public function logout()
    {
        auth()->logout();
        return response()->json(['message' => 'Logged out']);
    }



    /**
     * @OA\Post(
     *      path="/api/v1/resend-verification",
     *      operationId="resendVerificationEmail",
     *      summary="Resend email verification email",
     *      description="Resend email verification email",
     *     @OA\Parameter(
     *          name="email",
     *          description="Email",
     *          required=true,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *     ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent()
     *       ),
     *     )
     *
     */
    public function resendVerificationEmail(Request $request)
    {
        $validated = $request->validate(['email' => 'required|email|string']);
        try {
            $user = User::where('email', $validated['email'])->first();
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()]);
        }

        if (!isset($user)) {
            return response()->json(['message' => 'Invalid email']);
        }

        if ($user->email_verified_at !== null) {
            return response()->json(['message' => 'Email already verified']);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Mail not sent']);
        }

        return response()->json(['message' => 'Email verification mail sent']);
    }



    /**
     * @OA\Post(
     *      path="/api/v1/change-password",
     *      operationId="changePassword",
     *      tags={"Users"},
     *      summary="Change user password",
     *      description="Change user password",
     *      security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *          name="password",
     *          description="Password",
     *          required=true,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *     ),
     *      @OA\Parameter(
     *          name="password_confirmation",
     *          description="Password",
     *          required=true,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *     ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent()
     *       ),
     *     )
     *
     */
    public function changePassword(Request $request)
    {
        $validated = $request->validate(['password' => 'required|string|confirmed|min:8']);
        try {
            User::where('email', auth()->user()->email)
                ->update(['password' => bcrypt($validated['password'])]);
            return response()->json(['message' => 'Password updated']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Password update failed']);
        }
    }



    /**
     * @OA\Post(
     *      path="/api/v1/forgot-password",
     *      operationId="forgotPassword",
     *      tags={"Users"},
     *      summary="Initiate password reset process",
     *      description="Send password reset link to email",
     *     @OA\Parameter(
     *          name="email",
     *          description="email",
     *          required=true,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *     ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent()
     *       ),
     *     )
     *
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);
    
        $user = User::where('email', $request->email)->first();
    
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }
    
        // 6-digit numeric OTP
        $otp = random_int(100000, 999999);
    
        PasswordReset::updateOrCreate(
            ['email' => $request->email],
            [
                'token' => $otp,
                'created_at' => now()
            ]
        );
    
        Mail::to($request->email)->send(new PasswordResetOtpMail($otp));
    
        return response()->json([
            'status' => 'success',
            'message' => 'OTP sent to email'
        ]);
    }

    public function verifyToken(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'token' => 'required|digits:6'
    ]);

    $reset = PasswordReset::where('email', $request->email)
        ->where('token', $request->token)
        ->first();

    if (!$reset) {
        return response()->json([
            'status' => 'error',
            'message' => 'Invalid OTP'
        ], 400);
    }

    // Expire after 10 minutes
    if ($reset->created_at < now()->subMinutes(10)) {
        $reset->delete();

        return response()->json([
            'status' => 'error',
            'message' => 'OTP expired'
        ], 400);
    }

    return response()->json([
        'status' => 'success',
        'message' => 'OTP verified'
    ]);
}




    /**
     * @OA\Put(
     *      path="/api/v1/reset-password",
     *      operationId="resetPassword",
     *      tags={"Users"},
     *      summary="Reset password",
     *      description="Reset user password",
     *     @OA\Parameter(
     *          name="email",
     *          description="email",
     *          required=true,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *     ),
     *      @OA\Parameter(
     *          name="token",
     *          description="token",
     *          required=true,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *     ),
     *      @OA\Parameter(
     *          name="password",
     *          description="password",
     *          required=true,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *     ),
     *      @OA\Parameter(
     *          name="password_confirmation",
     *          description="password",
     *          required=true,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *     ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent()
     *       ),
     *     )
     *
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|digits:6',
            'password' => 'required|string|confirmed|min:8'
        ]);
    
        $reset = PasswordReset::where('email', $request->email)
            ->where('token', $request->token)
            ->first();
    
        if (!$reset) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid OTP'
            ], 400);
        }
    
        if ($reset->created_at < now()->subMinutes(10)) {
            $reset->delete();
    
            return response()->json([
                'status' => 'error',
                'message' => 'OTP expired'
            ], 400);
        }
    
        User::where('email', $request->email)->update([
            'password' => Hash::make($request->password)
        ]);
    
        $reset->delete();
    
        return response()->json([
            'status' => 'success',
            'message' => 'Password reset successful'
        ]);
    }

    public function resendOtp(Request $request)
{
    $request->validate([
        'email' => 'required|email'
    ]);

    $otp = random_int(100000, 999999);

    PasswordReset::updateOrCreate(
        ['email' => $request->email],
        [
            'token' => $otp,
            'created_at' => now()
        ]
    );

    Mail::to($request->email)->send(new PasswordResetOtpMail($otp));

    return response()->json([
        'status' => 'success',
        'message' => 'OTP resent'
    ]);
}

}
