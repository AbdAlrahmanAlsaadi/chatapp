<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Http\Requests\VerifyCodeRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\CheckResetPasswordCodeRequest;
use App\Http\Requests\ProfileImageRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Pusher\Pusher;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated());
        return response()->json(['message' => 'Verification code sent to your email.'], 201);
    }

    public function verifyCodeOnly(VerifyCodeRequest $request): JsonResponse
{
    $response = $this->authService->verifyCodeOnly($request->validated());

    if (!$response) {
        return response()->json(['message' => 'Invalid verification code.'], 400);
    }

    return response()->json([
        'message' => 'Email verified successfully.',
        'user' => $response['user'],
        'token' => $response['token']
    ], 200);
}

    public function login(LoginRequest $request): JsonResponse
    {


            $loginData = $this->authService->login($request->validated());

        if (!$loginData) {
            return response()->json(['message' => 'Invalid credentials or email not verified.'], 401);
        }

        return response()->json([ 'message'=>' login succesfully','data'=> $loginData],
        200);
    }



    public function logout()
    {

        $userId = Auth::id();

        if ($userId) {
            $user = User::find($userId);
        if ($user) {
            $user->update([
                'is_online' => false,
                'last_seen_at' => now(),  // يتم تحديد وقت آخر ظهور
            ]);
        }

        // قم بتدمير التوكن أو الجلسة هنا
        Auth::logout();

        return response()->json(['message' => 'Successfully logged out.']);
    }}


    public function userForgetPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->forgotPassword($request->validated());
        return response()->json(['message' => 'Password reset code sent.'], 200);
    }

    public function userCheckPasswordCode(CheckResetPasswordCodeRequest $request): JsonResponse
    {
        $isValid = $this->authService->checkResetPasswordCode($request->validated());

        return $isValid
            ? response()->json(['message' => 'Code is valid.'], 200)
            : response()->json(['message' => 'Code is expired or invalid.'], 400);
    }

    public function userResetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $isReset = $this->authService->resetPassword($request->validated());

        return $isReset
            ? response()->json(['message' => 'Password reset successfully.'], 200)
            : response()->json(['message' => 'Invalid or expired code.'], 400);
    }

    public function pusherAuth(Request $request): JsonResponse{
        $request->validate(['channel_name' => 'required|string', 'socket_id' => 'required']);
        $pusher = new Pusher(
            env('PUSHER_APP_KEY'),
            env('PUSHER_APP_SECRET'),
            env('PUSHER_APP_ID'),
            [
                'cluster' => 'eu',
                'useTLS'  => true,
            ]
        );
        $auth = $pusher->socket_auth($request->channel_name, $request->socket_id);
        return response()->json(['message' => 'authorized succsessfully', 'auth' => $auth]);
    }
}
