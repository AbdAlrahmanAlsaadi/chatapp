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
use Illuminate\Support\Facades\Log;
class AuthService
{
    public function register(array $data)
    {
        $userName = $data['user_name'] ?? $this->generateRandomUsername($data['name']);

        $verificationCode = random_int(100000, 999999);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'verification_code' => $verificationCode,
            'user_name' => $userName,
        ]);

        Mail::to($user->email)->send(new SendVerificationCode($user, $verificationCode));

        return $user;
    }

    protected function generateRandomUsername($name)
    {
        $baseName = preg_replace('/\s+/', '', $name);
        $randomNumber = rand(100, 999);
        $username = strtolower($baseName) . $randomNumber;

        while (User::where('user_name', $username)->exists()) {
            $randomNumber = rand(100, 999);
            $username = strtolower($baseName) . $randomNumber;
        }

        return $username;
    }

    public function verifyCodeOnly(array $data)
    {
        $user = User::where('verification_code', $data['code'])->first();

        if (!$user) {
            return false; // فشل التحقق إذا لم يتم العثور على المستخدم أو الكود خاطئ
        }

        // تحديث حالة التحقق وتعيين الرمز إلى null بعد التحقق
        $user->email_verified_at = now();
        $user->verification_code = null;
        $user->save();

        // إنشاء التوكن وإرجاعه مع معلومات المستخدم
        $token = $user->createToken('API Token')->plainTextToken;
        Log::channel('telegram')->info('New user has logged in', [
            'user' => $user
        ]);
        return [
            'token' => $token,
            'user' => $user

        ];
    }


    public function login(array $data)
    {
        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password) || !$user->email_verified_at) {
            return null;
        }


        $token = $user->createToken('API Token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
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
    public function updateUserMedia(User $user,  $media, $type = 'image')
    {
        $destinationPath = 'story/media/';

        // تحديد المسار بناءً على نوع الوسائط
        switch ($type) {
            case 'image':
                $destinationPath .= 'images';
                break;
            case 'video':
                $destinationPath .= 'videos';
                break;
            default:
                $destinationPath .= 'others';
        }

        // إنشاء اسم الملف مع timestamp لمنع التكرار
        $fileName = time() . '_' . $media->getClientOriginalName();

        // نقل الملف إلى المسار المحدد داخل مجلد public
        $media->move(public_path($destinationPath), $fileName);

        // تحديث مسار الوسائط في نموذج المستخدم (أو أي نموذج آخر)
        $user->update([
            'media_path' => $destinationPath . '/' . $fileName,
        ]);

        return $destinationPath . '/' . $fileName;
    }



}




