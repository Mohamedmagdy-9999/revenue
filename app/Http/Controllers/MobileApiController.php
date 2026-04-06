<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Models\Customer;
use App\Models\License;
use App\Models\BranchRenewal;
use Str;
use App\Models\TaxFile;
use Illuminate\Support\Carbon;
use App\Models\ZakahNumber;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use App\Models\Country;
use App\Models\IdentityType;
class MobileApiController extends Controller
{
   
    public function checkUser(Request $request)
    {
        $request->validate([
            'identity_number' => 'required'
        ]);

        $customer = Customer::where('identity_number', $request->identity_number)->first();

        if (!$customer) {
            return response()->json([
                'status' => 'new_user'
            ]);
        }

        if (is_null($customer->pin)) {
            return response()->json([
                'status' => 'need_activation'
            ]);
        }

        return response()->json([
            'status' => 'login'
        ]);
    }

    

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone_1' => 'required',
            'identity_number' => 'required|unique:customers,identity_number',
            'email' => 'nullable|email|unique:customers,email',
            'identity_type_id' => 'required',
            'country_id' => 'required',
            'profile_picture' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            'identity_front_image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            'identity_back_image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            'identity_start_date' => 'required|date|before_or_equal:today',
            'identity_end_date' => 'required|date|after:identity_start_date',
            'address' => 'required',
            'password' => 'required|digits:8'
        ]);

      
            $name = null;
            if ($file = $request->file('profile_picture')) {
                $name = time() . $file->getClientOriginalName();
                $file->move('customers', $name);
            }
            
            $name2 = null;
            if ($file = $request->file('identity_front_image')) {
                $name2 = time() . $file->getClientOriginalName();
                $file->move('customers', $name2);
            }
            
            $name3 = null;
            if ($file = $request->file('identity_back_image')) {
                $name3 = time() . $file->getClientOriginalName();
                $file->move('customers', $name3);
            }

        $customer = Customer::create([
            'name' => $request->name,
            'phone_1' => $request->phone_1,
            'phone_2' => $request->phone_2,
            'tel_1' => $request->tel_1,
            'tel_2' => $request->tel_2,
            'identity_number' => $request->identity_number,
            'email' => $request->email,
            'identity_type_id' => $request->identity_type_id,
            'country_id' => $request->country_id,
            'profile_picture' => $name,
            'identity_front_image' => $name2,
            'identity_back_image' => $name3,
            'identity_start_date' => $request->identity_start_date,
            'identity_end_date' => $request->identity_end_date,
            'address' => $request->address,
            'password' => Hash::make($request->password),
            'test' => $request->password,
        ]);

        TaxFile::create(['customer_id' => $customer->id]);
        ZakahNumber::create(['customer_id' => $customer->id]);

        return response()->json([
            'message' => 'تم التسجيل بنجاح',
            'data' => $customer
        ]);
    }

    public function activateAccount(Request $request)
    {
        $request->validate([
            'identity_number' => 'required|exists:customers,identity_number',
            'phone_1' => 'required',
            'password' => 'required|digits:8'
        ]);

        $customer = Customer::where('identity_number', $request->identity_number)->first();

        // تحقق أمان إضافي
        if ($customer->phone_1 != $request->phone_1) {
            return response()->json([
                'message' => 'بيانات غير صحيحة'
            ], 401);
        }

        $customer->update([
            'password' => Hash::make($request->password),
            'test' => $request->password,
        ]);

        $token = Auth::guard('api_customers')->login($customer);
        return response()->json([
            'message' => 'تم تفعيل الحساب بنجاح',
            'token' => $token,
        ]);
    }

   

    public function login(Request $request)
    {
        $request->validate([
            'identity_number' => 'required',
            'password' => 'required|digits:8'
        ]);

        $customer = Customer::where('identity_number', $request->identity_number)->first();

        if (!$customer) {
            return response()->json([
                'message' => 'المستخدم غير موجود'
            ], 404);
        }

        if (!Hash::check($request->password, $customer->password)) {
            return response()->json([
                'message' => 'الرقم السري غير صحيح'
            ], 401);
        }

        $token = Auth::guard('api_customers')->login($customer);

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح',
            'token' => $token,
            
        ]);
    }

    public function countries()
    {
        $data = Country::latest()->get();
        $data->transform(function ($data) {
             return [
                'id'=> $data->id,
                'name'=> $data->name,
                
             ];
               
              
        });
        return response()->json([
                'status' => true,
                'data' => $data,
              
        ]);

    }

    public function identity_types()
    {
        $data = IdentityType::latest()->get();
        $data->transform(function ($data) {
             return [
                'id'=> $data->id,
                'name'=> $data->name,
                
             ];
               
              
        });
        return response()->json([
                'status' => true,
                'data' => $data,
              
        ]);

    }
}
