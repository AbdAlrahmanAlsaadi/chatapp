<?php

namespace App\Services;

use App\Mail\SendCodeResetPassword;
use App\Mail\SendVerificationCode;
use App\Models\reset_code_password;
use App\Models\ResetCodePassword;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class AuthService
{
    public function register(array $data)
    {
        $verificationCode = random_int(100000, 999999);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'verification_code' => $verificationCode,
        ]);

        Mail::to($user->email)->send(new SendVerificationCode($user, $verificationCode));

        return $user;
    }

    public function verifyCodeOnly(array $data)
    {
        $user = User::where('verification_code', $data['code'])->first();

        if (!$user) {
            return false;
        }

        $user->email_verified_at = now();
        $user->verification_code = null;
        $user->save();

        return $user->createToken('API Token')->plainTextToken;
    }

    public function login(array $data)
    {
        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password) || !$user->email_verified_at) {
            return null;
        }

        return $user->createToken('API Token')->accessToken;
    }

    public function forgotPassword(array $data)
    {
        reset_code_password::where('email', $data['email'])->delete();

        $data['code'] = mt_rand(100000, 999999);
        $codeData = reset_code_password::create($data);

        Mail::to($data['email'])->send(new SendCodeResetPassword($codeData['code']));

        return $codeData;
    }

    public function checkResetPasswordCode(array $data)
    {
        $passwordReset = reset_code_password::where('code', $data['code'])->first();

        if (!$passwordReset || $passwordReset->created_at < now()->subHour()) {
            return false;
        }

        return true;
    }

    public function resetPassword(array $data)
    {
        $passwordReset = reset_code_password::where('code', $data['code'])->first();

        if (!$passwordReset || $passwordReset->created_at < now()->subHour()) {
            return false;
        }

        $user = User::where('email', $passwordReset->email)->first();
        $user->password = bcrypt($data['password']);
        $user->save();

        $passwordReset->delete();

        return true;
    }

    
}




