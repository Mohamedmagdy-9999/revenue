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

        if (is_null($customer->password)) {
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
            'password' => 'required'
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


    public function request_license(Request $request)
    {
        
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
               
                'after' => 'حقل :attribute يجب أن يكون بعد تاريخ البداية.',
                'numeric' => 'حقل :attribute يجب أن يكون رقم.',
                // 🔥 رسائل الـ between المخصصة
                'max.file' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
                'business_name.max' => 'حقل :attribute يجب ألا يتجاوز :max حرف.',
            ];
            
            $attributes = [
                'customer_id' => 'المكلف',
                'category_id' => 'الفئة',
                'sub_category_id' => 'الفئة الفرعية',
                'directorate_id' => 'المديرية',
                'address' => 'العنوان',
                'lat' => 'احداثيات الشمال',
                'lang' => 'احداثيات الغرب',
            
                'owner_id' => 'المالك',
                'rent_start_date' => 'تاريخ بداية الإيجار',
                'rent_end_date' => 'تاريخ نهاية الإيجار',
                'rent_value' => 'قيمة الإيجار',
            
                'rent_image' => 'صورة عقد الإيجار',
            
                'electric_image' => 'صورة عداد الكهرباء',
                'electric_number' => 'رقم عداد الكهرباء',
            
                'shop_front_image' => 'صورة واجهة المحل',
                'shop_back_image' => 'صورة خلفية المحل',
            
                'panel_area' => 'مساحة اللوحة',
                'panel_type_id' => 'نوع اللوحة',
            
                'application_number' => 'رقم الطلب',
                'application_image' => 'صورة الطلب',
                'penalty' => 'الغرامة',
            
                'service_id' => 'الخدمات',
                'service_id.*' => 'الخدمة',
            
                'rental_type_id' => 'نوع الإيجار',
                'business_name' => 'اسم الشهرة',
            ];
            
            $request->validate([
                'customer_id' => 'required',
                'category_id' => 'required',
                'sub_category_id' => 'required',
                'directorate_id' => 'required',
                // 'address' => 'required',
                // 'lat' => 'required',
                // 'lang' => 'required',
            
                // 'owner_id' => 'nullable',
                // 'rent_start_date' => 'nullable|date',
                // 'rent_end_date'   => 'nullable|date|after:rent_start_date',
                // 'rent_value' => 'required_with:rent_start_date,rent_end_date',
            
                // 'rent_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                // 'electric_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                // 'electric_number' => 'nullable',
                // 'shop_front_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                // 'shop_back_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            
                // 'panel_area' => 'nullable',
                // 'panel_type_id' => 'nullable',
            
                // 'application_number' => 'required',
                // 'application_image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
                // 'penalty' => 'nullable',
            
                // 'service_id' => 'required|array',
                // 'service_id.*' => 'exists:services,id',
                
                // 'rental_type_id' => 'nullable',
                // 'business_name' => 'nullable|string|max:255',
            ], $messages, $attributes);


            $name = null;
            if ($file = $request->file('commercial_registration_image')) {
                $name = time() . $file->getClientOriginalName();
                $file->move('license', $name);
            }
            
            
            $rent_image = null;
            if ($file = $request->file('rent_image')) {
                $rent_image = time() . $file->getClientOriginalName();
                $file->move('renewal', $rent_image);
            }
            
            $electric_image = null;
            if ($file = $request->file('electric_image')) {
                $electric_image = time() . $file->getClientOriginalName();
                $file->move('renewal', $electric_image);
            }
            
            $shop_front_image = null;
            if ($file = $request->file('shop_front_image')) {
                $shop_front_image = time() . $file->getClientOriginalName();
                $file->move('renewal', $shop_front_image);
            }
            
            $shop_back_image = null;
            if ($file = $request->file('shop_back_image')) {
                $shop_back_image = time() . $file->getClientOriginalName();
                $file->move('renewal', $shop_back_image);
            }
            
            $application_image = null;
            if ($file = $request->file('application_image')) {
                $application_image = time() . $file->getClientOriginalName();
                $file->move('renewal', $application_image);
            }
            
            $suspension_form_image = null;
            if ($file = $request->file('suspension_form_image')) {
                $suspension_form_image = time() . $file->getClientOriginalName();
                $file->move('renewal', $suspension_form_image);
            }
            
            
            $facility_plan_image = null;
            if ($file = $request->file('facility_plan_image')) {
                $facility_plan_image = time() . $file->getClientOriginalName();
                $file->move('renewal', $facility_plan_image);
            }
            
            
            $license_main_facility_image = null;
            if ($file = $request->file('license_main_facility_image')) {
                $license_main_facility_image = time() . $file->getClientOriginalName();
                $file->move('renewal', $license_main_facility_image);
            }
            
            $facility_licenses_image = null;
            if ($file = $request->file('facility_licenses_image')) {
                $facility_licenses_image = time() . $file->getClientOriginalName();
                $file->move('renewal', $facility_licenses_image);
            }
            
            $first_review_report_image = null;
            if ($file = $request->file('first_review_report_image')) {
                $first_review_report_image = time() . $file->getClientOriginalName();
                $file->move('renewal', $first_review_report_image);
            }
            
            $second_review_report_image = null;
            if ($file = $request->file('second_review_report_image')) {
                $second_review_report_image = time() . $file->getClientOriginalName();
                $file->move('renewal', $second_review_report_image);
            }
            
            $employee_contract = null;
            if ($file = $request->file('employee_contract')) {
                $employee_contract = time() . $file->getClientOriginalName();
                $file->move('renewal', $employee_contract);
            }
            
            
            
            
            $request_application_image = null;
            if ($file = $request->file('request_application_image')) {
                $request_application_image = time() . $file->getClientOriginalName();
                $file->move('customers', $request_application_image);
            }
            
            
            $preview_location_application_image = null;
            if ($file = $request->file('preview_location_application_image')) {
                $preview_location_application_image = time() . $file->getClientOriginalName();
                $file->move('customers', $preview_location_application_image);
            }
            
            $health_checkup_image = null;
            if ($file = $request->file('health_checkup_image')) {
                $health_checkup_image = time() . $file->getClientOriginalName();
                $file->move('customers', $health_checkup_image);
            }
            
            $qualification_image = null;
            if ($file = $request->file('qualification_image')) {
                $qualification_image = time() . $file->getClientOriginalName();
                $file->move('customers', $qualification_image);
            }
            
            $supervisor_qualification_image = null;
            if ($file = $request->file('supervisor_qualification_image')) {
                $supervisor_qualification_image = time() . $file->getClientOriginalName();
                $file->move('customers', $supervisor_qualification_image);
            }
            
            
           
            $applicationExists = Application::where('number', $request->application_number)->where('category_id',$request->category_id)->exists();
            
            if (!$applicationExists) {
                return response()->json([
                    'status' => 422,
                    'message' => 'رقم الاستمارة غير موجود في النظام.',
                    'data' => [],
                ], 422);
            }
            
            // 2) التأكد أن رقم الاستمارة لم يُستخدم من قبل
            $usedBefore = BranchRenewal::where('application_number', $request->application_number)->exists();
            
            if ($usedBefore) {
                return response()->json([
                    'status' => 422,
                    'message' => 'رقم الاستمارة تم استخدامه من قبل.',
                    'data' => [],
                ], 422);
            }

            
            
            $customer = Customer::where('id',$request->customer_id)->first();
            $customer->update([
               'qualification_id' => $request->qualification_id,
               'specialization_id' => $request->specialization_id,
               'profession' => $request->profession,
               'graduation_date' => $request->graduation_date,
               'request_application_image' => $request_application_image,
               'preview_location_application_image' => $preview_location_application_image,
               'health_checkup_image' => $health_checkup_image,
               'qualification_image' => $qualification_image,
               'graduation_country' => $request->graduation_country,

               
            ]);
            
            if($request->supervisor_id)
            {
                $super = Customer::where('id',$request->supervisor_id)->first();
                $super->update([
                   'qualification_id' => $request->supervisor_qualification_id,
                   'specialization_id' => $request->supervisor_specialization_id,
                   'profession' => $request->supervisor_profession,
                   'graduation_date' => $request->supervisor_graduation_date,
                   'supervisor_qualification_image' => $supervisor_qualification_image,
                   'graduation_country' => $request->supervisor_graduation_country,
                   
                ]);
            }
            
            
            

            $license = License::create([
                'customer_id' => $request->customer_id,
                'category_id' => $request->category_id,
                'sub_category_id' => $request->sub_category_id,
                'email' => $request->email,
                'business_name' => $request->business_name,
                
                'commercial_registration_image' => $name,
            ]);
            
            
            $branch = new LicenseeBranch();
            $branch->license_id = $license->id;
            $branch->branch_type_id = 1;
            $branch->directorate_id = $request->directorate_id;
            $branch->name = $request->name;
            $branch->address = $request->address;
            $branch->lat = $request->lat;
            $branch->lang = $request->lang;
            $branch->save();
            
            $currentYear = now()->year;
            $start_date = Carbon::create($currentYear, 1, 1)->startOfDay();
            $end_date   = Carbon::create($currentYear, 12, 31)->endOfDay();
            
            $renewal = new BranchRenewal();
            $renewal->type =  "جديد";
            $renewal->depart = "المراجعة";
            $renewal->coming_from = "الادخال";
            $renewal->user_id =  auth()->user()->id;
            $renewal->license_branch_id =  $branch->id;
            $renewal->owner_id = $request->owner_id;
           if ($request->has('owner_license') && $request->boolean('owner_license')) {
                $renewal->supervisor_id = $request->customer_id;
            } elseif ($request->has('supervisor_id')) {
                $renewal->supervisor_id = $request->supervisor_id;
            }

            
            
            
            $renewal->rental_type_id = $request->rental_type_id;
            $renewal->rent_start_date = $request->rent_start_date;
            $renewal->rent_end_date = $request->rent_end_date;
            $renewal->rent_value = $request->rent_value;
            $renewal->currency_id = $request->currency_id;
            $renewal->rent_image = $rent_image;
            $renewal->number_of_doors = $request->number_of_doors;
            $renewal->obstacle_space = $request->obstacle_space;
            $renewal->electric_image = $electric_image;
            $renewal->electric_number = $request->electric_number;
            $renewal->shop_front_image = $shop_front_image;
            $renewal->shop_back_image = $shop_back_image;
            $renewal->panel_area = $request->panel_area;
            $renewal->panel_type_id = $request->panel_type_id;
            $renewal->status_id = 1;
            $renewal->application_number = $request->application_number;
            $renewal->application_image = $application_image;
            $renewal->start_date = $start_date;
            $renewal->end_date = $end_date;
            $renewal->penalty = $request->penalty;
            $renewal->notes = $request->notes;
            $renewal->employee_number = $request->employee_number;
            $renewal->suspension_form_image = $suspension_form_image;
            $renewal->beds_number = $request->beds_number;
            $renewal->staff_number = $request->staff_number;
            $renewal->medical_staff_number = $request->medical_staff_number;
            $renewal->facility_plan_image = $facility_plan_image;
            $renewal->license_main_facility_image = $license_main_facility_image;
           $renewal->license_main_facility_image = $license_main_facility_image;
           $renewal->facility_licenses_image = $facility_licenses_image;
           $renewal->first_review_report_image = $first_review_report_image;
           $renewal->second_review_report_image = $second_review_report_image;
           $renewal->facility_name = $request->facility_name;
           $renewal->pharmacy_area = $request->pharmacy_area;
           $renewal->employee_contract = $employee_contract;
           $renewal->temporary = $request->temporary;
           $renewal->notes = $request->notes;
           $renewal->service_type_id = $request->service_type_id;
           $renewal->serv_code = $request->serv_code;
           
            $renewal->save();
            
            foreach($request->service_id as  $service)
            {
                $ser = Service::where('id',$service)->first();
                $renewal_service = new BranchRenewalServices();
                $renewal_service->service_id = $service;
                $renewal_service->branch_renewal_id = $renewal->id;
                $renewal_service->value = $ser->value;
                $renewal_service->save();
                
              
                
            }
            
            $brserv = $request->service_id;

            /*
            |--------------------------------------------------------------------------
            | 1️⃣ الرسوم الأساسية (rsom)
            |--------------------------------------------------------------------------
            */
            $rsom = 0;
            
            if ((int)$renewal->service_type_id === 62 && $renewal->type === 'ايقاف فرع') {
            
                $count = count($brserv);
                $rsom  = $count * 260;
            
            } else {
            
                $rsom = ServicePrice::whereIn('service_id', $brserv)
                    ->where('service_type_id', $renewal->service_type_id)
                    ->sum('price');
            }
            
            /*
            |--------------------------------------------------------------------------
            | 2️⃣ رسوم الدعاية والإعلان
            |--------------------------------------------------------------------------
            */
            $d3ayawe3lan = 0;
            
            if (!is_null($renewal->panel_area)) {
            
                if ($renewal->panel_type_id == 1) {
                          $pricePerMeter = 800;
                } elseif ($renewal->panel_type_id == 2) {
                          $pricePerMeter = 1000;
                } else {
                    $pricePerMeter = 0; // أو أي قيمة افتراضية تحبها
                }
                $d3ayawe3lan   = $renewal->panel_area * $pricePerMeter;
            }
            
            /*
            |--------------------------------------------------------------------------
            | 3️⃣ رسوم العوائق
            |--------------------------------------------------------------------------
            */
            $obstacleMeterPrice = Price::where('name', 'رسوم عوائق')->value('value') ?? 0;
            $rsom3waek = ($renewal->obstacle_space ?? 0) * $obstacleMeterPrice;
            
            /*
            |--------------------------------------------------------------------------
            | 4️⃣ رسوم الفتحات
            |--------------------------------------------------------------------------
            */
            $rsomfat7at = 0;
            $fat7atCount = (int) $renewal->number_of_doors;
            
            if ($fat7atCount > 2) {
            
                $servicesPrices = ServicePrice::whereIn('service_id', $brserv)
                    ->where('service_type_id', $renewal->service_type_id)
                    ->pluck('price');
            
                foreach ($servicesPrices as $price) {
                    $rsomfat7at += ($price * 0.20);
                }
            }
            
            /*
            |--------------------------------------------------------------------------
            | 5️⃣ الغرامات
            |--------------------------------------------------------------------------
            */
            $rsom8rama = 0;
            
            $penaltyPrice = ServicePrice::whereIn('service_id', $brserv)
                ->where('service_type_id', 66)
                ->value('price') ?? 0;
            
            if ($penaltyPrice && $renewal->end_date) {
            
                $expiryDate = Carbon::parse($renewal->end_date);
                $penaltyStartDate = Carbon::create($expiryDate->year, 3, 1);
            
                if (Carbon::today()->gt($penaltyStartDate)) {
            
                    $yearsLate = $penaltyStartDate->diffInYears(now()) + 1;
                    $rsom8rama = $yearsLate * $penaltyPrice * count($brserv);
                }
            }
            
            /*
            |--------------------------------------------------------------------------
            | 6️⃣ الإجمالي
            |--------------------------------------------------------------------------
            */
            $elegmalyelrsom =
                $rsom +
                $d3ayawe3lan +
                $rsom3waek +
                $rsomfat7at +
                $rsom8rama;

                BranchRenewalFinance::create([
                    'branch_renewal_id' => $renewal->id,
                
                    'rsom'         => $rsom,
                    'mota5rat'     => 0,
                    'rsom3waek'    => $rsom3waek,
                    'd3ayawe3lan'  => $d3ayawe3lan,
                    'rsomfat7at'   => $rsomfat7at,
                    'rsom8rama'    => $rsom8rama,
                    'total'        => $elegmalyelrsom,
                ]);
                
                if($renewal->service_type_id == 62 OR $renewal->service_type_id == 63)
                {
                    $rsomta7sen = ServicePrice::whereIn('service_id',$brserv)->where('service_type_id',67)->sum('price');
                }else{
                    $rsomta7sen = ServicePrice::whereIn('service_id',$brserv)->where('service_type_id',68)->sum('price');
                }
                
                if($renewal->service_type_id == 62 OR $renewal->service_type_id == 63)
                {
                    $rsomnzafa = ServicePrice::whereIn('service_id',$brserv)->where('service_type_id',69)->sum('price');
                }else{
                    $rsomnzafa = ServicePrice::whereIn('service_id',$brserv)->where('service_type_id',70)->sum('price');
                }
                
                $matbo3at = 1200;
                
                $elegmaly =
                $rsomta7sen +
                $rsomnzafa +
                $matbo3at;
                
                
                BranchRenewalNzafa::create([
                    'branch_renewal_id' => $renewal->id,
                    'rsomta7sen' => $rsomta7sen,
                    'rsomnzafa' => $rsomnzafa,
                    'matbo3at' => $matbo3at,
                    'total' => $elegmaly,
                
                    
                ]);

            
            
            $log = new BranchRenewalLog();
            $log->user_id = auth('api')->user()->id;
            $log->branch_renewal_id = $renewal->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "اضافة رخصة جديدة";
            $log->notes = $renewal->notes;
            $log->save();
            
            $renewal->update([
                'notes' => null,
            ]);
            
            
            

            return response()->json([
                'message' => 'تم انشاء رخصة جديدة بنجاح',
                'status' => 200,
                'type' => 'create_license',
                'data' => [],
            ], 200);

       
    }
}
