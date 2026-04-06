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
use App\Models\Category;
use App\Models\Service;
use App\Models\TaxType;
use App\Models\ZakahType;
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

    public function customer_change_password(Request $request)
    {
        $user = Auth::guard('api_customers')->user();


        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'المستخدم غير موجود أو التوكن غير صالح'
            ], 401);
        }

        $messages = [
            'current_password.required' => 'يجب إدخال كلمة المرور الحالية',
            'new_password.required' => 'يجب إدخال كلمة المرور الجديدة',
            'new_password.min' => 'كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل',
            'new_password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
        ];

        $data = $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ], $messages);

        // التحقق من كلمة المرور الحالية
        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'كلمة المرور الحالية غير صحيحة'
            ], 422);
        }

        // تحديث كلمة المرور
        $user->update([
            'password' => Hash::make($data['new_password']),
            'test' => $data['new_password'],
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم تغيير كلمة المرور بنجاح'
        ]);
    }

    public function delete_customer(Request $request)
    {
        $user = Auth::guard('api_customers')->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'المستخدم غير موجود أو التوكن غير صالح'
            ], 401);
        }

        // Soft delete مباشرة
        $user->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم الحذف بنجاح',
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

    public function licenses_category()
    {
        $categories = Category::select('id', 'name')->get();

        $services = Service::whereRaw('LENGTH(code) = 3')
            ->where('active', 1)
            ->orderBy('code', 'asc')
            ->select('id', 'name', 'category_id', 'code')
            ->get()
            ->groupBy('category_id');


        $data = $categories->map(function ($category) use ($services) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'services' => $services[$category->id] ?? [],
            ];
        });

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $data,
        ]);
    }

    public function tax_types()
    {
        $data = TaxType::with(['declarations' => function($q) {
            $q->select('id', 'name', 'tax_type_id'); // مهم جداً include tax_type_id
        }])->get();

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $data
        ]);
    }

    public function zakah_types()
    {
        $data = ZakahType::with(['declarations' => function($q) {
            $q->select('id', 'name', 'zakah_type_id'); // مهم جداً include tax_type_id
        }])->get();

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $data
        ]);
    }
}
