<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Models\User;

use Str;
use DB;

use Illuminate\Support\Carbon;

use Illuminate\Validation\ValidationException;

use Tymon\JWTAuth\Facades\JWTAuth;

class AuthApiController extends Controller
{
   

    
    
    
    public function login(Request $request)
    {
        $credentials = [
            'name' => $request->username,
            'password' => $request->password
        ];
    
        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة',
                'status' => 401,
                'data' => []
            ], 401);
        }
    
        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح',
            'status' => 200,
            'data' => $token,
           
        ]);
    }


    public function logout(Request $request)
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
    
            return response()->json([
                'status' => 200,
                'message' => 'تم تسجيل الخروج بنجاح'
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'فشل تسجيل الخروج'
            ], 500);
        }
    }
    
    public function user_profile()
    {
        $user = auth('api')->user();
         return response()->json([
                'status' => 200,
                'message' => 'profile',
                'data' => $user,
        ]);
        
        
    }
    
    public function update_profile(Request $request)
    {
        try {
    
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
            ];
    
            $request->validate([
                'name' => 'required',
                'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'phone' => 'required',
                'email' => 'nullable|email',
                'password' => 'nullable|min:6'
            ], $messages);
    
    
            $user = auth('api')->user();
            
    
            // معالجة الصورة لو موجودة
            if ($request->hasFile('image')) {
    
                // حذف الصورة القديمة لو كانت موجودة
                if ($user->image && file_exists(public_path('user/' . $user->image))) {
                    unlink(public_path('user/' . $user->image));
                }
    
                $imageName = time() . '_' . $request->file('image')->getClientOriginalName();
                $request->file('image')->move(public_path('user'), $imageName);
    
                $user->image = $imageName;
            }
    
            // تحديث البيانات
            $user->name = $request->name;
            if ($request->email) {
                $user->email = $request->email;
            }
    
            if ($request->password) {
                $user->password = Hash::make($request->password);
            }
    
            $user->phone = $request->phone;
            $user->save();
    
            return response()->json([
                'message' => 'تم تعديل البيانات الشخصية بنجاح',
                'status' => 200,
                'data' => $user,
            ], 200);
    
        } catch (ValidationException $e) {
            $errors = collect($e->errors())->map(function ($messages) {
                return $messages[0];
            });
    
            return response()->json([
                'message' => $errors,
                'status' => 422,
                'data' => [],
            ], 422);
        }
    }




}
