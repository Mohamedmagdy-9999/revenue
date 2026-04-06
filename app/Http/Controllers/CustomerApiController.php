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
class CustomerApiController extends Controller
{
   

    
    
    public function add_new_customer(Request $request)
    {
        
            $messages = [
                'name.required' => 'من فضلك أدخل الاسم',
                'name.string'   => 'الاسم يجب أن يكون نصًا',
                'name.max'      => 'الاسم يجب ألا يزيد عن 255 حرف',
            
                'phone_1.required' => 'رقم الهاتف مطلوب',
            
                'identity_number.required' => 'رقم الهوية مطلوب',
                'identity_number.unique'   => 'رقم الهوية مستخدم بالفعل',
            
                'email.email'  => 'البريد الإلكتروني غير صالح',
                'email.unique' => 'البريد الإلكتروني مستخدم بالفعل',
            
                'identity_type_id.required' => 'اختر نوع الهوية',
                'country_id.required'       => 'اختر الدولة',
            
                'profile_picture.required' => 'الصورة الشخصية مطلوبة',
                'profile_picture.image'    => 'الصورة الشخصية يجب أن تكون صورة',
                'profile_picture.mimes'    => 'الصورة الشخصية يجب أن تكون من نوع jpg أو jpeg أو png أو webp',
                'profile_picture.max'      => 'حجم الصورة الشخصية يجب ألا يزيد عن 2 ميجابايت',
            
                'identity_front_image.required' => 'صورة الهوية الأمامية مطلوبة',
                'identity_front_image.image'    => 'صورة الهوية الأمامية يجب أن تكون صورة',
                'identity_front_image.mimes'    => 'صورة الهوية الأمامية يجب أن تكون من نوع jpg أو jpeg أو png أو webp',
                'identity_front_image.max'      => 'حجم صورة الهوية الأمامية يجب ألا يزيد عن 2 ميجابايت',
            
                'identity_back_image.required' => 'صورة الهوية الخلفية مطلوبة',
                'identity_back_image.image'    => 'صورة الهوية الخلفية يجب أن تكون صورة',
                'identity_back_image.mimes'    => 'صورة الهوية الخلفية يجب أن تكون من نوع jpg أو jpeg أو png أو webp',
                'identity_back_image.max'      => 'حجم صورة الهوية الخلفية يجب ألا يزيد عن 2 ميجابايت',
            
                'identity_start_date.required' => 'تاريخ بداية الهوية مطلوب',
                'identity_start_date.date'     => 'تاريخ بداية الهوية غير صالح',
            
                'identity_end_date.required' => 'تاريخ انتهاء الهوية مطلوب',
                'identity_end_date.date'     => 'تاريخ انتهاء الهوية غير صالح',
                'identity_end_date.after'    => 'تاريخ انتهاء الهوية يجب أن يكون بعد تاريخ البداية',
            
                'address.required' => 'العنوان مطلوب',
            ];
            
            $attributes = [
                'name' => 'الاسم',
                'phone_1' => 'رقم الهاتف',
                'identity_number' => 'رقم الهوية',
                'email' => 'البريد الإلكتروني',
                'identity_type_id' => 'نوع الهوية',
                'country_id' => 'الدولة',
                'profile_picture' => 'الصورة الشخصية',
                'identity_front_image' => 'صورة الهوية الأمامية',
                'identity_back_image' => 'صورة الهوية الخلفية',
                'identity_start_date' => 'تاريخ بداية الهوية',
                'identity_end_date' => 'تاريخ انتهاء الهوية',
                'address' => 'العنوان',
            ];
            
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
            ], $messages, $attributes);




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
                'identity_number' => $request->identity_number,
                'phone_1' => $request->phone_1,
                'phone_2' => $request->phone_2,
                'tel_1' => $request->tel_1,
                'tel_2' => $request->tel_2,
                'identity_type_id' => $request->identity_type_id,
                'country_id' => $request->country_id,
                'profile_picture' => $name,
                'identity_front_image' => $name2,
                'identity_back_image' => $name3,
                'user_id' => auth('api')->user()->id,
                'email' => $request->email,
                'identity_start_date' => $request->identity_start_date,
                'identity_end_date' => $request->identity_end_date,
                'address' => $request->address,
            ]);
            
            $file = new TaxFile();
            $file->customer_id = $customer->id;
            $file->save();
            
            $number = new ZakahNumber();
            $number->customer_id = $customer->id;
            $number->save();

            return response()->json([
                'message' => 'تم انشاء المكلف بنجاح',
                'status' => 200,
                'data' => $customer,
            ], 200);

        
    }
  
    public function search_taxpayer(Request $request)
    {
        $request->validate([
            'type' => 'required|in:name,identity_number,tax_file,zakah_number',
        ]);
    
        $customers = Customer::query();
    
        // جلب العلاقات المطلوبة
       
    
        switch ($request->type) {
    
            case 'name':
                $request->validate(['name' => 'required|string']);
                $name = str_replace(' ', '', mb_strtolower($request->name));
                $customers->whereRaw("LOWER(REPLACE(name, ' ', '')) LIKE ?", ["%{$name}%"]);
                break;
    
            case 'identity_number':
                $request->validate([
                    'identity_number' => 'required',
                    'identity_type_id' => 'required',
                    'country_id' => 'required',
                ]);
                $customers->where('identity_number', $request->identity_number)
                          ->where('identity_type_id', $request->identity_type_id)
                          ->where('country_id', $request->country_id);
                break;
    
            case 'tax_file':
               
                $customers->whereHas('tax_file', fn($q) => $q->where('id', $request->tax_file));
                break;
    
            case 'zakah_number':
               
                $customers->whereHas('zakah_number', fn($q) => $q->where('id', $request->zakah_number));
                break;
        }
    
        $customers = $customers->paginate(8);
    
        if ($customers->isEmpty()) {
            return response()->json([
                'message' => 'لا يوجد بيانات مطابقة لبحثك',
                'status'  => 404,
                'customer'=> [],
            ], 404);
        }
    
        // تحويل البيانات لتنسيق يشبه $customerData
        $customersData = $customers->map(function($customer) {
            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'country_name' => $customer->country_name,
                'identity_name' => $customer->identity_name,
                'identity_number' => $customer->identity_number,
                'phone_1' => $customer->phone_1,
                'identity_start_date' => $customer->identity_start_date,
                'identity_end_date' => $customer->identity_end_date,
                'tax_file_id' => $customer->tax_file_id,
                'zakah_number_id' => $customer->zakah_number_id,
                
            ];
        });
    
        return response()->json([
            'message' => 'تم العثور على بيانات',
            'status' => 200,
            'customer' => $customersData,
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ]
        ], 200);
    }


   public function search_customer(Request $request)
    {
        $request->validate([
            'identity_number' => 'required|string',
        ]);
    
        $customer = Customer::where('identity_number', $request->identity_number)->first();
    
        if (!$customer) {
            return response()->json([
                'status'  => 404,
               
                'data'    => null
            ], 200);
        }
    
        return response()->json([
            'status' => 200,
            'data' => [
                'id'               => $customer->id,
                'name'             => $customer->name,
                'country_name'     => $customer->country_name,
                'identity_name'    => $customer->identity_name,
                'identity_number'  => $customer->identity_number,
                'phone_1'          => $customer->phone_1,
                'identity_start_date'          => $customer->identity_start_date,
                'identity_end_date'          => $customer->identity_end_date,
                'address'          => $customer->address,
            ],
        ], 200);
    }

    
    public function get_customer_by_id($id)
    {
        
        $customer = Customer::find($id);
          $customerData = [
                'id'           => $customer->id,
                'name'         => $customer->name,
                'email'         => $customer->email,
                'country_name' => $customer->country_name,
                'identity_name'=> $customer->identity_name,
                'identity_number'=> $customer->identity_number,
                'phone_1'      => $customer->phone_1,
                'phone_2'      => $customer->phone_2,
                'identity_start_date' => $customer->identity_start_date,
                'identity_end_date' => $customer->identity_end_date,
                'tel_1' => $customer->tel_1,
                'tel_2' => $customer->tel_2,
                'profile_image_url' => $customer->profile_image_url,
                'address' => $customer->address,
                'front_image_url' => $customer->front_image_url,
                'back_image_url' => $customer->back_image_url,
                'tax_file_id' => $customer->tax_file_id,
                'zakah_number_id' => $customer->zakah_number_id,
                'identity_type_id' => $customer->identity_type_id,
                'country_id' => $customer->country_id,
            ];
    
        if (!$customer) {
            return response()->json([
                'message' => 'العميل غير موجود',
                'status' => 404,
                'data' => null,
            ], 404);
        }
           
    
        return response()->json([
          
            'status' => 200,
            'data' => $customerData,
        ], 200);
    }
    
    public function all_customers()
    {
        $customer = Customer::latest()->paginate(15);
        $customer->getCollection()->transform(function ($item) {
            return [
                'id'               => $item->id,
                'name'    => $item->name,     // accessor
                'country_name' => $item->country_name,  // accessor
                'identity_name'         => $item->identity_name,          
                'identity_number'    => $item->identity_number,
                'phone_1'    => $item->phone_1,
               'address'    => $item->address,
            ];
        });
        
    
        return response()->json([
            'message' => 'كل العمكلفين',
            'status' => 200,
            'data' => $customer,
        ], 200);
    }
    
    public function update_customer(Request $request,$id)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'phone_1' => 'required',
                 'email' => 'nullable|email|unique:customers,email,' . $id,
                'identity_number' => 'required|unique:customers,' .$id,
            
                'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'identity_front_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'identity_back_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'identity_start_date' => 'required|date|before_or_equal:today',
                'identity_end_date'   => 'required|date|after:identity_start_date',
                'address' => 'required',
            ], [
                'name.required' => 'من فضلك أدخل الاسم',
                'name.string' => 'الاسم يجب أن يكون نصًا',
                'name.max' => 'الاسم يجب ألا يزيد عن 255 حرف',
            
                'identity_number.required' => 'رقم الهوية مطلوب',
                'identity_number.unique' => 'رقم الهوية مستخدم بالفعل',
                
                'phone_1.required' => 'رقم الهاتف مطلوب',
                'phone_1.unique' => 'رقم الهاتف مستخدم بالفعل',
                'email.unique' => 'البريد ال,الكترونى مستخدم بالفعل'
                ,
                'profile_picture.image' => 'الصورة الشخصية يجب أن تكون صورة',
                'profile_picture.mimes' => 'الصورة الشخصية يجب أن تكون من نوع jpg أو jpeg أو png',
                'profile_picture.max' => 'حجم الصورة الشخصية يجب ألا يزيد عن 2 ميجابايت',
            
                'identity_front_image.image' => 'صورة الهوية الأمامية يجب أن تكون صورة',
                'identity_front_image.mimes' => 'صورة الهوية الأمامية يجب أن تكون من نوع jpg أو jpeg أو png',
                'identity_front_image.max' => 'حجم صورة الهوية الأمامية يجب ألا يزيد عن 2 ميجابايت',
            
                'identity_back_image.image' => 'صورة الهوية الخلفية يجب أن تكون صورة',
                'identity_back_image.mimes' => 'صورة الهوية الخلفية يجب أن تكون من نوع jpg أو jpeg أو png',
                'identity_back_image.max' => 'حجم صورة الهوية الخلفية يجب ألا يزيد عن 2 ميجابايت',
                'identity_start_date.required' => 'تاريخ بداية الهوية مطلوب',
                'identity_start_date.date' => 'تاريخ بداية الهوية غير صالح',
            
                'identity_end_date.required' => 'تاريخ انتهاء الهوية مطلوب',
                'identity_end_date.date' => 'تاريخ انتهاء الهوية غير صالح',
                'identity_end_date.after' => 'تاريخ انتهاء الهوية يجب أن يكون بعد تاريخ البداية',
                'address' => 'العنوان',
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

            $customer = Customer::findOrFail($id);
            $customer->name = $request->name;
            $customer->identity_number = $request->identity_number;
            $customer->email = $request->email;
            $customer->phone_1 = $request->phone_1;
            $customer->phone_2 = $request->phone_2;
            $customer->tel_1 = $request->tel_1;
            $customer->tel_2 = $request->tel_2;
            $customer->identity_start_date = $request->identity_start_date;
            $customer->identity_end_date = $request->identity_end_date;
            $customer->address = $request->address;
            if(!empty($name))
            {
                $customer->profile_picture = $name;
            }
            
            if(!empty($name2))
            {
                $customer->identity_front_image = $name2;
            }
            
            if(!empty($name3))
            {
                $customer->identity_back_image = $name3;
            }
            $customer->save();
            

            return response()->json([
                'message' => 'تم تعديل بيانات المكلف بنجاح',
                'status' => 200,
                'data' => $customer,
            ], 200);

        } catch (ValidationException $e) {
            $errors = collect($e->errors())->map(function($messages){
                return $messages[0]; // أول رسالة فقط
            });
            return response()->json([
                'message' => $errors,
                'status' => 422,
                'data' => [],
            ], 422);
        }
    }
    
    public function get_customer_licenses(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
    
        // بيانات العميل
        $customerData = [
            'id'              => $customer->id,
            'name'            => $customer->name,
            'country_name'    => $customer->country_name,
            'identity_name'   => $customer->identity_name,
            'identity_number' => $customer->identity_number,
            'phone_1'         => $customer->phone_1,
            'profile_image_url' => $customer->profile_image_url,
            'created_at'      => $customer->created_at,
            'phone_2'         => $customer->phone_2,
            'tel_1'           => $customer->tel_1,
            'tel_2'           => $customer->tel_2,
            'address'           => $customer->address,
          
        ];
    
        // query الرخص
        //$licensesQuery = $customer->licenses();
        $licensesQuery = $customer->licenses()->with('firstRenewal');
    
        // لو status_id موجود في request فلتر عليه
        if ($request->filled('renewal_status_id')) {
    
            // لو المستخدم بعت status معين
            $licensesQuery->whereHas('firstRenewal', function ($q) use ($request) {
                $q->where('status_id', $request->renewal_status_id);
            });
        
        } else {
        
            // لو المستخدم مبعتش → استبعد اللي أول تجديد حالته = 6
            $licensesQuery->whereDoesntHave('firstRenewal', function ($q) {
                $q->where('status_id', 6);
            });
        
        }
    
        // pagination
        $licenses = $licensesQuery->paginate(8);
        
    
        // تنسيق البيانات
        $licenses->getCollection()->transform(function ($license) {
            return [
                'id'                 => $license->id,
                'category_name'      => $license->category_name,
                'sub_name'           => $license->sub_name,
                'created_at' => $license->created_at->format('Y-m-d'),
                'first_start_date'   => $license->first_start_date,
                'first_end_date'     => $license->first_end_date,
                'first_status_name'  => $license->first_status_name,
                'category_id'        => $license->category_id,
                 'first_status_name'           => $license->dashboard_status,
                'first_start_date'           => $license->first_start_date,
                'first_end_date'           => $license->first_end_date,
                'department_name' =>$license->depart,
                'directorate_name' =>$license->directorate_name,
                'business_name' =>$license->business_name,
                'status_color' =>$license->dashboard_color,
                'renewal_id' =>$license->renewal_id,
                'coming_from' =>$license->coming_from,
                'customer_name' => $license->customer_name,
                'status_id' => $license->status_id,
                'branch_id' => $license->branch_id,
                'type' => $license->type,
               
                
            ];
        });

    
        return response()->json([
            'message'  => 'رخص المكلف',
            'status'   => 200,
            'customer' => $customerData,
            'licenses' => $licenses,
        ], 200);
    }
    
    
    public function get_customer_licenses_profile(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
    
        // بيانات العميل
        $customerData = [
            'id'              => $customer->id,
            'name'            => $customer->name,
            'country_name'    => $customer->country_name,
            'identity_name'   => $customer->identity_name,
            'identity_number' => $customer->identity_number,
            'phone_1'         => $customer->phone_1,
            'profile_image_url' => $customer->profile_image_url,
            'created_at'      => $customer->created_at,
            'phone_2'         => $customer->phone_2,
            'tel_1'           => $customer->tel_1,
            'tel_2'           => $customer->tel_2,
            
        ];
    
        // query الرخص
        $licensesQuery = $customer->licenses();
    
   
        // pagination
        $licenses = $licensesQuery->paginate(8);
    
        // تنسيق البيانات
        $licenses->getCollection()->transform(function ($license) {
            return [
                'id'                 => $license->id,
                'category_name'      => $license->category_name,
                'sub_name'           => $license->sub_name,
                'created_at'         => $license->created_at,
                'first_start_date'   => $license->first_start_date,
                'first_end_date'     => $license->first_end_date,
                'first_status_name'  => $license->first_status_name,
                'category_id'        => $license->category_id,
            ];
        });
    
        return response()->json([
            'message'  => 'رخص المكلف',
            'status'   => 200,
            'customer' => $customerData,
            'licenses' => $licenses,
        ], 200);
    }


    
    
    
   public function pending_licenses(Request $request)
    {
        $query = BranchRenewal::where('status_id', 1)->latest();
    
        // =========================
        // Filters
        // =========================
        if (
            $request->filled('identity_number') ||
            $request->filled('identity_type_id') ||
            $request->filled('country_id') ||
            $request->filled('name')
        ) {
    
            $customers = Customer::where(function ($q) use ($request) {
    
                    if ($request->filled('identity_number')) {
                        $q->where('identity_number', $request->identity_number);
                    }
    
                    if ($request->filled('identity_type_id')) {
                        $q->where('identity_type_id', $request->identity_type_id);
                    }
    
                    if ($request->filled('country_id')) {
                        $q->where('country_id', $request->country_id);
                    }
    
                    if ($request->filled('name')) {
                        $name = str_replace(' ', '', mb_strtolower($request->name));
    
                        $q->whereRaw(
                            "LOWER(REPLACE(name, ' ', '')) LIKE ?",
                            ["%{$name}%"]
                        );
                    }
                })
                ->pluck('id'); // 👈 جلب كل الـ IDs المطابقة
    
            // لو مفيش عملاء مطابقين
            if ($customers->isEmpty()) {
                return response()->json([
                    'message' => 'No active renewals found for given filters',
                    'status' => 201,
                    'renewals' => [],
                ], 201);
            }
    
            // فلترة التراخيص بناءً على العملاء
            $query->whereHas('license_branch.license', function ($q) use ($customers) {
                $q->whereIn('customer_id', $customers);
            });
        }
    
        // =========================
        // Pagination
        // =========================
        $renewals = $query->paginate(8);
    
        // =========================
        // Transform data
        // =========================
        $renewals->getCollection()->transform(function ($item) {
            return [
                'id'                 => $item->id,
                'category_name'      => $item->category_name,
                'directorate_name'   => $item->directorate_name,
                'sub_name'           => $item->sub_name,
                'customer_name'      => $item->customer_name,
                'status_name'        => $item->status_name,
                'service_type_id'    => $item->service_type_id,
                'license_branch_id'  => $item->license_branch_id,
                'status_id'          => $item->status_id,
                'sub_category_id'    => $item->sub_category_id,
                'coming_from' => $item->coming_from,
                'department' => $item->depart,
                'type' => $item->type,
                'category_id'        => $item->category_id,
                'created_at'         => $item->created_at,
            ];
        });
    
        // لو مفيش renewals
        if ($renewals->isEmpty()) {
            return response()->json([
                'message' => 'No active renewals found',
                'status' => 201,
                'renewals' => [],
            ], 201);
        }
    
        return response()->json([
            'message' => 'قيد الموافقة',
            'status' => 200,
            'renewals' => $renewals,
        ], 200);
    }



    public function not_pending_licenses(Request $request)
    {
        // بنقرأ القيمة من الـ URL 
        // مثال: /api/not_pending_licenses?status_id=2
        $statusId = $request->query('status_id');
    
        $renewals = BranchRenewal::query()
            ->when($statusId, function ($query) use ($statusId) {
                // لو المستخدم بعت status (2 أو 3)
                return $query->where('status_id', $statusId);
            }, function ($query) {
                // لو مبعتش -> رجّع كل الحالات ماعدا pending (1)
                return $query->where('status_id', '!=', 1);
            })
            ->latest()
            ->paginate(8);
            
           $renewals->getCollection()->transform(function ($item) {
            return [
                'id'               => $item->id,
                'category_name'    => $item->category_name,     // accessor
                'directorate_name' => $item->directorate_name,  // accessor
                'sub_name'         => $item->sub_name,          // accessor
                'customer_name'    => $item->customer_name,     // accessor
                'status_name'      => $item->status_name,       // accessor
                'service_type_id'  => $item->service_type_id,
                'license_branch_id' => $item->license_branch_id,
                'status_id' => $item->status_id,
                'category_id' => $item->category_id,
                'coming_from' => $item->coming_from,
                'department' => $item->depart,
                'type' => $item->type,
                'sub_category_id' => $item->sub_category_id,
                'created_at'       => $item->created_at,
            ];
        });
    
    
        return response()->json([
            'message' => 'تم',
            'status'   => 200,
            'renewals' => $renewals,
        ], 200);
    }

    public function tabs(Request $request)
    {
        
        
        $pending = BranchRenewal::where('status_id', 1)->count();
        $approved = BranchRenewal::where('status_id', 2)->count();
        $refused = BranchRenewal::where('status_id', 3)->count();
        $tmelt7sel = BranchRenewal::where('status_id', 5)->count();
       
        $all = BranchRenewal::where('status_id', '!=',6)->count();
        

        
        
        return response()->json([
                'message' => 'تم',
                'status' => 200,
                'pending' => $pending,
                'approved' => $approved,
                'refused' => $refused,
                'tmelta7sel' => $tmelt7sel,
               
                'all' => $all,
        ], 200);
    }

    public function search_by_customer_name(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
        ]);
    
        $name = str_replace(' ', '', mb_strtolower($request->name));

        $customer = Customer::whereRaw("LOWER(REPLACE(name, ' ', '')) LIKE ?", ["%{$name}%"])
                    ->latest()->paginate(8);
            $customer->getCollection()->transform(function ($item) {
            return [
                'id'               => $item->id,
                'name'    => $item->name,     // accessor
                'country_name' => $item->country_name,  // accessor
                'identity_name'         => $item->identity_name,          
                'identity_number'    => $item->identity_number,
                'phone_1'    => $item->phone_1,
                'address'    => $item->address,
               
            ];
        });
        return response()->json([
            'message' => 'success',
            'status' => 200,
            'customer' => $customer,
        ], 200);
                            
    }
    
    
    public function filter_customer(Request $request)
    {
        $request->validate([
            'identity_number' => 'required',
            'identity_type_id' => 'required',
            'country_id' => 'required',
        ]);
        
        
    
        $customer = Customer::where('identity_number', $request->identity_number)->where('identity_type_id',$request->identity_type_id)->where('country_id',$request->country_id)->latest()->paginate(8);
        if ($customer->isEmpty()) {
            return response()->json([
                'message' => 'لا يوجد بيانات',
                'status' => 404,
                'customer' => [],
            ]);
        }
        return response()->json([
            'message' => 'success',
            'status' => 200,
            'customer' => $customer,
        ], 200);
                            
    }
    
    
    public function pending_search_by_customer_name(Request $request)
    {
        $request->validate([
        'name' => 'required|string',
        ]);
    
        // ابحث عن العميل بالاسم
        $name = str_replace(' ', '', mb_strtolower($request->name));

        $customer = Customer::whereRaw("LOWER(REPLACE(name, ' ', '')) LIKE ?", ["%{$name}%"])
                            ->first();
    
        if (!$customer) {
            return response()->json([
                'message' => 'Customer not found',
                'status' => 201,
            ], 201);
        }
    
        // تحقق هل لديه branch renewals بحالة status_id = 1
        $hasRenewal = BranchRenewal::where('status_id', 1)
            ->whereHas('license_branch.license', function ($q) use ($customer) {
                $q->where('customer_id', $customer->id);
            })
            ->exists();
    
        if (!$hasRenewal) {
            return response()->json([
                'message' => 'No active renewals found',
                'status' => 201,
            ], 201);
        }
    
        return response()->json([
            'message' => 'success',
            'status' => 200,
            'customer' => $customer,
        ], 200);
                            
    }
    
    
    public function pending_filter_customer(Request $request)
    {
        $request->validate([
            'identity_number' => 'required',
            'identity_type_id' => 'required',
            'country_id' => 'required',
        ]);
    
        // ابحث عن العميل
        $customer = Customer::where('identity_number', $request->identity_number)
            ->where('identity_type_id', $request->identity_type_id)
            ->where('country_id', $request->country_id)
            ->first();
    
        if (!$customer) {
            return response()->json([
                'message' => 'Customer not found',
                'status' => 201,
            ], 201);
        }
    
        // تحقق هل لديه branch renewals بحالة status_id = 1
        $hasRenewal = BranchRenewal::where('status_id', 1)
            ->whereHas('license_branch.license', function ($q) use ($customer) {
                $q->where('customer_id', $customer->id);
            })
            ->exists();
    
        if (!$hasRenewal) {
            return response()->json([
                'message' => 'No active renewals found',
                'status' => 201,
            ], 201);
        }
    
        return response()->json([
            'message' => 'success',
            'status' => 200,
            'customer' => $customer,
        ], 200);
    }
    
    
    public function finance_licenses(Request $request)
    {
        $query = BranchRenewal::where('branch_renewals.status_id', 4)
            ->whereDoesntHave('financePayments')
            ->join('license_branches', 'license_branches.id', '=', 'branch_renewals.license_branch_id')
            ->join('licenses', 'licenses.id', '=', 'license_branches.license_id')
            ->leftJoin('customers', 'customers.id', '=', 'licenses.customer_id')
            ->leftJoin('directorates', 'directorates.id', '=', 'license_branches.directorate_id')
            ->select('branch_renewals.*')
    
            ->when($request->license_id, function ($q, $v) {
                $q->where('branch_renewals.id', $v);
            })
    
            ->when($request->customer_name, function ($q, $v) {
                $q->where('customers.name', 'like', "%{$v}%");
            })
    
            ->when($request->business_name, function ($q, $v) {
                $q->where('licenses.business_name', 'like', "%{$v}%");
            })
    
            ->when($request->customer_identity_number, function ($q, $v) {
                $q->where('customers.identity_number', 'like', "%{$v}%");
            })
    
            ->when($request->category_id, function ($q, $v) {
                $q->where('licenses.category_id', $v);
            })
    
            ->when($request->from, function ($q, $v) {
                $q->whereDate('branch_renewals.created_at', '>=', $v);
            })
    
            ->when($request->to, function ($q, $v) {
                $q->whereDate('branch_renewals.created_at', '<=', $v);
            })
    
            ->latest('branch_renewals.created_at');
    
        $renewals = $query->paginate(8);
    
        $renewals->getCollection()->transform(function ($item) {
            return [
                'id'                  => $item->id,
                'category_name'       => $item->category_name,
                'directorate_name'    => $item->directorate_name,
                'sub_name'            => $item->sub_name,
                'customer_name'       => $item->customer_name,
                'status_name'         => $item->status_name,
                'license_branch_id'   => $item->license_branch_id,
                'status_id'           => $item->status_id,
                'payment_type_id'     => $item->payment_type_id,
                'payment_type_name'   => $item->payment_type_name,
                'category_id'         => $item->category_id,
                'created_at'          => $item->created_at,
                'status_finance'      => $item->status_id == 4 ? 'قيد التحصيل' : 'مرفوض',
            ];
        });
    
        if ($renewals->isEmpty()) {
            return response()->json([
                'message' => 'No active renewals found',
                'status'  => 201,
            ], 201);
        }
    
        return response()->json([
            'message'  => 'طباعة حافظة',
            'status'   => 200,
            'renewals' => $renewals,
        ], 200);
    }
    
    public function matching_table(Request $request)
    {
        $query = BranchRenewal::whereIn('status_id', [6, 5])
         ->where('is_matched', 0)
        ->latest();
        
        // لو في أي filter متمرر
        if ($request->filled('identity_number') || $request->filled('identity_type_id') || $request->filled('country_id') || $request->filled('name')) {
            $customer = Customer::where(function($q) use ($request) {
                    if ($request->filled('identity_number')) {
                        $q->where('identity_number', $request->identity_number);
                    }
                    if ($request->filled('identity_type_id')) {
                        $q->where('identity_type_id', $request->identity_type_id);
                    }
                    if ($request->filled('country_id')) {
                        $q->where('country_id', $request->country_id);
                    }
                })
                ->orWhere(function($q) use ($request) {
                    if ($request->filled('name')) {
                        $name = str_replace(' ', '', mb_strtolower($request->name));
                    
                        $q->whereRaw("LOWER(REPLACE(name, ' ', '')) LIKE ?", ["%{$name}%"]);
                    }
                })
                ->first();
    
            if ($customer) {
                $query->whereHas('license_branch.license', function ($q) use ($customer) {
                    $q->where('customer_id', $customer->id);
                });
            } else {
                // لو المستخدم مرر parameter ومفيش تطابق، نرجع مجموعة فاضية
                return response()->json([
                    'message' => 'No active renewals found for given filters',
                    'status' => 201,
                    'renewals' => [],
                ], 201);
            }
        }
    
        $renewals = $query->paginate(8);
         $renewals->getCollection()->transform(function ($item) {
            return [
                'id'               => $item->id,
                'category_name'    => $item->category_name,     // accessor
                'directorate_name' => $item->directorate_name,  // accessor
                'sub_name'         => $item->sub_name,          // accessor
                'customer_name'    => $item->customer_name,     // accessor
                'status_name'      => $item->status_name,       // accessor
                'license_branch_id' => $item->license_branch_id,
                'status_id' => $item->status_id,
                'payment_type_name' => $item->payment_type_name,
                 'payment_type_id' => $item->payment_type_id,
                'payment_receipt_number' => $item->finance_payment_receipt_number,
                'finance_user_name' => $item->finance_payment_user_name,
                'category_id' => $item->category_id,
                'created_at' => $item->created_at->format('Y-m-d'),
                'amount' => $item->finance_value,
                'resource_type' => $item->resource_type,
                'cutomer_name' => $item->cutomer_name,
                'payment_receipt_image_url' => $item->payment_receipt_image_url,
                'supply_voucher_image_url' => $item->supply_voucher_image_url,
                        
            ];
        });
    
        if ($renewals->isEmpty()) {
            return response()->json([
                'message' => 'No active renewals found',
                'status' => 201,
            ], 201);
        }
    
        return response()->json([
            'message' => 'طباعة حافظة',
            'status' => 200,
            'renewals' => $renewals,
        ], 200);
    }
    
    public function nzafa_licenses(Request $request)
    {
      
        $query = BranchRenewal::where('branch_renewals.status_id', 4)
            ->whereDoesntHave('nzafaPayments')
            ->join('license_branches', 'license_branches.id', '=', 'branch_renewals.license_branch_id')
            ->join('licenses', 'licenses.id', '=', 'license_branches.license_id')
            ->leftJoin('customers', 'customers.id', '=', 'licenses.customer_id')
            ->leftJoin('directorates', 'directorates.id', '=', 'license_branches.directorate_id')
            ->select('branch_renewals.*')
    
            ->when($request->license_id, function ($q, $v) {
                $q->where('branch_renewals.id', $v);
            })
    
            ->when($request->customer_name, function ($q, $v) {
                $q->where('customers.name', 'like', "%{$v}%");
            })
    
            ->when($request->business_name, function ($q, $v) {
                $q->where('licenses.business_name', 'like', "%{$v}%");
            })
    
            ->when($request->customer_identity_number, function ($q, $v) {
                $q->where('customers.identity_number', 'like', "%{$v}%");
            })
    
            ->when($request->category_id, function ($q, $v) {
                $q->where('licenses.category_id', $v);
            })
    
            ->when($request->from, function ($q, $v) {
                $q->whereDate('branch_renewals.created_at', '>=', $v);
            })
    
            ->when($request->to, function ($q, $v) {
                $q->whereDate('branch_renewals.created_at', '<=', $v);
            })
    
            ->latest('branch_renewals.created_at');
    
        $renewals = $query->paginate(8);
       
    
 
         $renewals->getCollection()->transform(function ($item) {
            return [
                 'id'               => $item->id,
                'category_name'    => $item->category_name,     // accessor
                'directorate_name' => $item->directorate_name,  // accessor
                'sub_name'         => $item->sub_name,          // accessor
                'customer_name'    => $item->customer_name,     // accessor
                'status_name'      => $item->status_name,       // accessor
                'license_branch_id' => $item->license_branch_id,
                'status_id' => $item->status_id,
                'payment_type_id_nzafa' => $item->payment_type_id_nzafa,
                'payment_type_name_nzafa' => $item->payment_type_name_nzafa,
                'category_id' => $item->category_id,
                'created_at'       => $item->created_at,
                 'status_nzafa' => in_array($item->status_id, [4, 5,7]) && $item->is_nzafa == 0
                ? 'قيد التحصيل'
                : 'مرفوض',


            ];
        });
    
        if ($renewals->isEmpty()) {
            return response()->json([
                'message' => 'No active renewals found',
                'status' => 201,
            ], 201);
        }
    
        return response()->json([
            'message' => 'طباعة حافظة',
            'status' => 200,
            'renewals' => $renewals,
        ], 200);
    }
    
    
    public function matching_nzafa_table(Request $request)
    {
        $query = BranchRenewal::where('is_nzafa',1)->where('is_matched_nzafa', 0)
        ->latest();
        
        // لو في أي filter متمرر
        if ($request->filled('identity_number') || $request->filled('identity_type_id') || $request->filled('country_id') || $request->filled('name')) {
            $customer = Customer::where(function($q) use ($request) {
                    if ($request->filled('identity_number')) {
                        $q->where('identity_number', $request->identity_number);
                    }
                    if ($request->filled('identity_type_id')) {
                        $q->where('identity_type_id', $request->identity_type_id);
                    }
                    if ($request->filled('country_id')) {
                        $q->where('country_id', $request->country_id);
                    }
                })
                ->orWhere(function($q) use ($request) {
                    if ($request->filled('name')) {
                        $name = str_replace(' ', '', mb_strtolower($request->name));
                    
                        $q->whereRaw("LOWER(REPLACE(name, ' ', '')) LIKE ?", ["%{$name}%"]);
                    }
                })
                ->first();
    
            if ($customer) {
                $query->whereHas('license_branch.license', function ($q) use ($customer) {
                    $q->where('customer_id', $customer->id);
                });
            } else {
                // لو المستخدم مرر parameter ومفيش تطابق، نرجع مجموعة فاضية
                return response()->json([
                    'message' => 'No active renewals found for given filters',
                    'status' => 201,
                    'renewals' => [],
                ], 201);
            }
        }
    
        $renewals = $query->paginate(8);
         $renewals->getCollection()->transform(function ($item) {
            return [
                'id'               => $item->id,
                'category_name'    => $item->category_name,     // accessor
                'directorate_name' => $item->directorate_name,  // accessor
                'sub_name'         => $item->sub_name,          // accessor
                'customer_name'    => $item->customer_name,     // accessor
                'status_name'      => $item->status_name,       // accessor
                'license_branch_id' => $item->license_branch_id,
                'status_id' => $item->status_id,
                'payment_type_id_nzafa' => $item->payment_type_id_nzafa,
                'payment_type_name_nzafa' => $item->payment_type_name_nzafa,
                'payment_receipt_number_nzafa' => $item->payment_receipt_number_nzafa,
                'nzafa_user_name' => $item->nzafa_user_name,
                'category_id' => $item->category_id,
                'created_at' => $item->created_at->format('Y-m-d'),
                'amount_nzafa' => $item->amount_nzafa,
                'resource_type' => $item->resource_type,
                'cutomer_name' => $item->cutomer_name,
                 'payment_receipt_image_nzafa_url' => $item->payment_receipt_image_nzafa_url,
                'supply_voucher_image_nzafa_url' => $item->supply_voucher_image_nzafa_url,
                        'resource_type' => "نظافة",
                        'code' => 6111,
            ];
        });
    
        if ($renewals->isEmpty()) {
            return response()->json([
                'message' => 'No active renewals found',
                'status' => 201,
            ], 201);
        }
    
        return response()->json([
            'message' => 'طباعة حافظة',
            'status' => 200,
            'renewals' => $renewals,
        ], 200);
    }
    

    
    public function all_licenses(Request $request)
    {
        $query = BranchRenewal::query();
    
        // -----------------------------
        // Filters
        // -----------------------------
        if (
            $request->filled('identity_number') ||
            $request->filled('identity_type_id') ||
            $request->filled('country_id') ||
            $request->filled('name')
        ) {
            $query->whereHas('license_branch.license.customer', function ($q) use ($request) {
    
                if ($request->filled('identity_number')) {
                    $q->where('identity_number', $request->identity_number);
                }
    
                if ($request->filled('identity_type_id')) {
                    $q->where('identity_type_id', $request->identity_type_id);
                }
    
                if ($request->filled('country_id')) {
                    $q->where('country_id', $request->country_id);
                }
    
                if ($request->filled('name')) {
                    $q->where('name', 'LIKE', '%' . $request->name . '%');
                }
            });
        }
    
        $query->orderByDesc('created_at');
    
        $renewals = $query->where('status_id','!=',6)->paginate(8);
    
        // -----------------------------
        // Return only needed fields
        // -----------------------------
        $renewals->getCollection()->transform(function ($item) {
            return [
                'id'               => $item->id,
                'category_name'    => $item->category_name,     // accessor
                'directorate_name' => $item->directorate_name,  // accessor
                'sub_name'         => $item->sub_name,          // accessor
                'customer_name'    => $item->customer_name,     // accessor
                'status_name'      => $item->status_name,       // accessor
                'service_type_id'  => $item->service_type_id,
                'license_branch_id' => $item->license_branch_id,
                'status_id' => $item->status_id,
                'sub_category_id' => $item->sub_category_id,
                'category_id' => $item->category_id,
                'created_at'       => $item->created_at,
                'depart'       => $item->depart,
                'coming_from'       => $item->coming_from,
                'finance' => $item->finance,
                'nzafa' => $item->nzafa,
                'type' => $item->type,
                
               
            ];
        });
    
        return response()->json([
            'message'  => 'الكل',
            'status'   => 200,
            'renewals' => $renewals,
        ], 200);
    }



    
    public function checkTaxFile($customer_id)
    {
        // 1. تأكد إن المكلف موجود
        $customer = Customer::find($customer_id);

        if (!$customer) {
            return response()->json([
                'status' => 404,
                'message' => 'المكلف غير موجود',
                'data' => [],
            ], 404);
        }

        // 2. فحص هل له ملف ضريبي
        $file = TaxFile::where('customer_id', $customer_id)->first();

        if ($file) {
            return response()->json([
                'status' => 200,
                'message' => 'المكلف لديه ملف ضريبي',
                'has_tax_file' => true,
                'data' => $file,
            ], 200);
        }

        return response()->json([
            'status' => 200,
            'message' => 'المكلف ليس لديه ملف ضريبي',
            'has_tax_file' => false,
            'data' => [],
        ], 200);
    }
    
    
    public function get_details($id)
    {
        $renewal = BranchRenewal::with('services')->findOrFail($id);
    
        $renewalData = [
            'id'                         => $renewal->id,
            'application_number'         => $renewal->application_number,
            'sub_name'                   => $renewal->sub_name,
            'customer_name'              => $renewal->customer_name,
            'customer_identity_number'   => $renewal->customer_identity_number,
            'customer_identity_name'     => $renewal->customer_identity_name,
            'customer_country_name'      => $renewal->customer_country_name,
            'customer_address'      => $renewal->customer_address,
            'start_date'                 => $renewal->customer_identity_start_date,
            'end_date'                   => $renewal->customer_identity_end_date,
            'category_id'                => $renewal->category_id,
            'category_name'              => $renewal->category_name,
            'payment_receipt_number'    =>$renewal->payment_receipt_number,
            'payment_value'    =>$renewal->payment_value,
            'check_image'    =>$renewal->check_image,
            'check_image_url'    =>$renewal->check_image_url,
            'extra_image'    =>$renewal->extra_image,
            'extra_image_url'    =>$renewal->extra_image_url,
            'bank_id'    =>$renewal->finance->bank_id,
            'check_number'    =>$renewal->finance->check_number,
            'check_value'    =>$renewal->check_value,
            'notes'    =>$renewal->notes,
            'payment_receipt_image'    =>$renewal->payment_receipt_image,
            'payment_receipt_image_url'    =>$renewal->payment_receipt_image_url,
            'supply_voucher_image'    =>$renewal->supply_voucher_image,
            'supply_voucher_image_url'    =>$renewal->supply_voucher_image_url,
            
            
            
            'payment_receipt_number_nzafa'    =>$renewal->payment_receipt_number_nzafa,
            'payment_value_nzafa'    =>$renewal->payment_value_nzafa,
            'check_image_nzafa'    =>$renewal->check_image_nzafa,
            'check_image_nzafa_url'    =>$renewal->check_image_nzafa_url,
            'extra_image_nzafa'    =>$renewal->extra_image_nzafa,
            'extra_image_nzafa_url'    =>$renewal->extra_image_nzafa_url,
            'bank_id_nzafa'    =>$renewal->bank_id_nzafa,
            'check_number_nzafa'    =>$renewal->check_number_nzafa,
            'check_value_nzafa'    =>$renewal->check_value_nzafa,
            'payment_receipt_image_nzafa'    =>$renewal->payment_receipt_image_nzafa,
            'payment_receipt_image_nzafa_url'    =>$renewal->payment_receipt_image_nzafa_url,
            'supply_voucher_image_nzafa'    =>$renewal->supply_voucher_image_nzafa,
            'supply_voucher_image_nzafa_url'    =>$renewal->supply_voucher_image_nzafa_url,
    
            // ✅ الخدمات
            'services' => $renewal->services->map(function ($service) {
                return [
                    'id'    => $service->id,
                    'name'  => $service->service->name,
                    
                ];
            }),
            'finance_total'    =>$renewal->finance->total ?? 0,
            'nzafa_total'    =>$renewal->nzafa->total ?? 0,
           
        ];
    
        return response()->json([
            'status' => 200,
            'data'   => $renewalData,
        ], 200);
    }




}
