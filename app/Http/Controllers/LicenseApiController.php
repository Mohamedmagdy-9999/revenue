<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Models\Customer;
use App\Models\LicenseeBranch;
use App\Models\BranchRenewal;
use App\Models\BranchRenewalServices;
use App\Models\License;
use App\Models\Service;
use Str;
use App\Models\BranchRenewalLog;
use Illuminate\Support\Carbon;
use App\Models\Application;
use App\Models\TaxFile;
use App\Models\Price;
use App\Models\ServiceType;
use App\Models\ServicePrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\LiquidationBank;
use App\Models\BranchRenewalFinance;
use App\Models\BranchRenewalNzafa;
use App\Models\BranchRenewalFinancePayment;
use App\Models\BranchRenewalNzafaPayment;
class LicenseApiController extends Controller
{
   
    public function getBranchAllServices($branchId)
    {
        $services = BranchRenewalServices::with('service')
            ->whereHas('branchRenewal', function ($q) use ($branchId) {
                $q->where('license_branch_id', $branchId);
            })
            ->where('status', 0) // الخدمات الفعالة
            ->get()
            ->unique('service_id') // إزالة التكرار
            ->values();
    
        return response()->json($services);
    }
    
    
    public function add_new_license(Request $request)
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
                'address' => 'required',
                'lat' => 'required',
                'lang' => 'required',
            
                'owner_id' => 'nullable',
                'rent_start_date' => 'nullable|date',
                'rent_end_date'   => 'nullable|date|after:rent_start_date',
                'rent_value' => 'required_with:rent_start_date,rent_end_date',
            
                'rent_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'electric_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'electric_number' => 'nullable',
                'shop_front_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'shop_back_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            
                'panel_area' => 'nullable',
                'panel_type_id' => 'nullable',
            
                'application_number' => 'required',
                'application_image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
                'penalty' => 'nullable',
            
                'service_id' => 'required|array',
                'service_id.*' => 'exists:services,id',
                
                'rental_type_id' => 'nullable',
                'business_name' => 'nullable|string|max:255',
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
  
  
  
    public function add_new_branch(Request $request)
    {
        try {
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
                'numeric' => 'حقل :attribute يجب أن يكون رقم.',
                'after' => 'حقل :attribute يجب أن يكون بعد تاريخ البداية.',
                'required_with' => 'حقل :attribute مطلوب عند إدخال تواريخ الإيجار.',
            ];
    
            $attributes = [
                'license_id' => 'الرخصة',
                'directorate_id' => 'المديرية',
                'address' => 'العنوان',
                'lat' => 'احداثيات الشمال',
                'lang' => 'احداثيات الغرب',
                'rent_start_date' => 'تاريخ بداية الإيجار',
                'rent_end_date' => 'تاريخ نهاية الإيجار',
                'rent_value' => 'قيمة الإيجار',
                'rent_image' => 'صورة عقد الإيجار',
                'number_of_doors' => 'عدد الأبواب',
                'obstacle_space' => 'مساحة العائق',
                'electric_image' => 'صورة عداد الكهرباء',
                'electric_number' => 'رقم عداد الكهرباء',
                'shop_front_image' => 'صورة واجهة المحل',
                'shop_back_image' => 'صورة خلفية المحل',
                'panel_area' => 'مساحة اللوحة',
                'panel_type_id' => 'نوع اللوحة',
                'application_number' => 'رقم الطلب',
                'application_image' => 'صورة الطلب',
                'penalty' => 'الغرامة',
                'employee_number' => 'عدد الموظفين',
                'rental_type_id' => 'نوع الإيجار',
            ];
    
            $request->validate([
                'license_id' => 'required',
                'directorate_id' => 'required',
                'address' => 'required',
                'lat' => 'required',
                'lang' => 'required',
                'rent_start_date' => 'nullable|date',
                'rent_end_date' => 'nullable|date|after:rent_start_date',
                'rent_value' => 'required_with:rent_start_date,rent_end_date',
                'rent_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'number_of_doors' => 'nullable|numeric|min:1',
                'obstacle_space' => 'nullable|numeric|min:1',
                'electric_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'shop_front_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'shop_back_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'panel_area' => 'nullable',
                'panel_type_id' => 'nullable',
                'application_number' => 'required',
                'application_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'employee_number' => 'nullable',
                'rental_type_id' => 'nullable',
            ], $messages, $attributes);
    
            // دالة لحفظ الصور
            // دالة لحفظ الصور في مسار 'renewal'
            $saveImage = fn($file) => $file ? tap(time() . $file->getClientOriginalName(), fn($name) => $file->move('renewal', $name)) : null;
            
            // حفظ الصور
            $rent_image = $saveImage($request->file('rent_image'));
            $electric_image = $saveImage($request->file('electric_image'));
            $shop_front_image = $saveImage($request->file('shop_front_image'));
            $shop_back_image = $saveImage($request->file('shop_back_image'));
            $application_image = $saveImage($request->file('application_image'));
            $facility_plan_image = $saveImage($request->file('facility_plan_image'));
            $license_main_facility_image = $saveImage($request->file('license_main_facility_image'));
            $facility_licenses_image = $saveImage($request->file('facility_licenses_image'));
            $first_review_report_image = $saveImage($request->file('first_review_report_image'));
            $second_review_report_image = $saveImage($request->file('second_review_report_image'));
            $employee_contract = $saveImage($request->file('employee_contract'));



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
    
            $li = License::find($request->license_id);
    
            $br = LicenseeBranch::where('license_id', $li->id)->latest()->first();
    
            $ren = null;
            $br_renewal_sers = [];
    
            if ($br) {
                // جلب آخر تجديدات
                $ren = BranchRenewal::where('license_branch_id', $br->id)
                        ->latest()
                        ->first();   // 👈 أهم تعديل
                
                $br_renewal_sers = collect();
                
                if ($ren) {
                    $br_renewal_sers = BranchRenewalServices::where('branch_renewal_id', $ren->id)->get();
                }
            }

    
            // تحقق من وجود رقم الاستمارة
            $applicationExists = Application::where('number', $request->application_number)
                ->where('category_id', $li->category_id)
                ->exists();
    
            if (!$applicationExists) {
                return response()->json([
                    'status' => 422,
                    'message' => 'رقم الاستمارة غير موجود في النظام.',
                    'data' => [],
                ], 422);
            }
    
            // التأكد أن رقم الاستمارة لم يُستخدم من قبل
            $usedBefore = BranchRenewal::where('application_number', $request->application_number)->exists();
            if ($usedBefore) {
                return response()->json([
                    'status' => 422,
                    'message' => 'رقم الاستمارة تم استخدامه من قبل.',
                    'data' => [],
                ], 422);
            }
    
            // إنشاء الفرع
            $branch = LicenseeBranch::create([
                'license_id' => $request->license_id,
                'branch_type_id' => 2,
                'directorate_id' => $request->directorate_id,
                'name' => $request->name,
                'address' => $request->address,
                'lat' => $request->lat,
                'lang' => $request->lang,
            ]);
    
            $currentYear = now()->year;
            $start_date = Carbon::create($currentYear, 1, 1)->startOfDay();
            $end_date = Carbon::create($currentYear, 12, 31)->endOfDay();
    
            // إنشاء التجديد
            $renewal = BranchRenewal::create([
                'type' => "فرع",
                'depart' => "المراجعة",
                'coming_from' => "الادخال",
                'user_id' => auth()->user()->id,
                'license_branch_id' => $branch->id,
                'owner_id' => $request->owner_id,
               
                'rental_type_id' => $request->rental_type_id,
                'rent_start_date' => $request->rent_start_date,
                'rent_end_date' => $request->rent_end_date,
                'rent_value' => $request->rent_value,
                'currency_id' => $request->currency_id,
                'rent_image' => $rent_image,
                'number_of_doors' => $request->number_of_doors,
                'obstacle_space' => $request->obstacle_space,
                'electric_image' => $electric_image,
                'electric_number' => $request->electric_number,
                'shop_front_image' => $shop_front_image,
                'shop_back_image' => $shop_back_image,
                'panel_area' => $request->panel_area,
                'panel_type_id' => $request->panel_type_id,
                'status_id' => 1,
                'application_number' => $request->application_number,
                'application_image' => $application_image,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'penalty' => $request->penalty,
                'notes' => $request->notes,
                'employee_number' => $request->employee_number,
                'beds_number' => $request->beds_number,
                'staff_number' => $request->staff_number,
                'medical_staff_number' => $request->medical_staff_number,
                'facility_plan_image' => $facility_plan_image,
                'license_main_facility_image' => $license_main_facility_image,
                'facility_licenses_image' => $facility_licenses_image,
                'first_review_report_image' => $first_review_report_image,
                'second_review_report_image' => $second_review_report_image,
                'facility_name' => $request->facility_name,
                'pharmacy_area' => $request->pharmacy_area,
                'employee_contract' => $employee_contract,
                'service_type_id' => 64,
            ]);
            $supervisorId = null;

            if ($request->has('owner_license') && $request->boolean('owner_license')) {
                $supervisorId = $request->customer_id;
            } elseif ($request->has('supervisor_id')) {
                $supervisorId = $request->supervisor_id;
            }
            
            $renewal->update([
                'supervisor_id' => $supervisorId
            ]);

    
            // نسخ الخدمات السابقة
            foreach($br_renewal_sers as $item) {
                BranchRenewalServices::create([
                    'service_id' => $item->service_id,
                    'branch_renewal_id' => $renewal->id,
                    'value' => $item->value,
                ]);
            }
    
            // ✨ تأكد أن $brserv دائمًا مصفوفة
            $brserv = BranchRenewalServices::where('branch_renewal_id', $renewal->id)
            ->pluck('service_id')
            ->toArray();

    
            // حساب الرسوم
            $rsom = 0;
            if ((int)$renewal->service_type_id === 62 && $renewal->type === 'ايقاف فرع') {
                $rsom = count($brserv) * 260;
            } elseif (!empty($brserv)) {
                $rsom = ServicePrice::whereIn('service_id', $brserv)
                    ->where('service_type_id', $renewal->service_type_id)
                    ->sum('price');
            }
    
            $d3ayawe3lan = ($renewal->panel_area ?? 0) * match($renewal->panel_type_id) {
                1 => 800,
                2 => 1000,
                default => 0,
            };
    
            $obstacleMeterPrice = Price::where('name', 'رسوم عوائق')->value('value') ?? 0;
            $rsom3waek = ($renewal->obstacle_space ?? 0) * $obstacleMeterPrice;
    
            $rsomfat7at = 0;
            if ((int)$renewal->number_of_doors > 2 && !empty($brserv)) {
                $servicesPrices = ServicePrice::whereIn('service_id', $brserv)
                    ->where('service_type_id', $renewal->service_type_id)
                    ->pluck('price');
                foreach ($servicesPrices as $price) {
                    $rsomfat7at += ($price * 0.20);
                }
            }
    
            $rsom8rama = 0;
            if (!empty($brserv)) {
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
            }
    
            $elegmalyelrsom = $rsom + $d3ayawe3lan + $rsom3waek + $rsomfat7at + $rsom8rama;
    
            BranchRenewalFinance::create([
                'branch_renewal_id' => $renewal->id,
                'rsom' => $rsom,
                'mota5rat' => 0,
                'rsom3waek' => $rsom3waek,
                'd3ayawe3lan' => $d3ayawe3lan,
                'rsomfat7at' => $rsomfat7at,
                'rsom8rama' => $rsom8rama,
                'total' => $elegmalyelrsom,
            ]);
    
            // رسوم تحسين ونظافة
            $rsomta7sen = $rsomnzafa = 0;
            if (!empty($brserv)) {
                if (in_array($renewal->service_type_id, [62,63])) {
                    $rsomta7sen = ServicePrice::whereIn('service_id',$brserv)->where('service_type_id',67)->sum('price');
                    $rsomnzafa = ServicePrice::whereIn('service_id',$brserv)->where('service_type_id',69)->sum('price');
                } else {
                    $rsomta7sen = ServicePrice::whereIn('service_id',$brserv)->where('service_type_id',68)->sum('price');
                    $rsomnzafa = ServicePrice::whereIn('service_id',$brserv)->where('service_type_id',70)->sum('price');
                }
            }
    
            $matbo3at = 1200;
            $elegmaly = $rsomta7sen + $rsomnzafa + $matbo3at;
    
            BranchRenewalNzafa::create([
                'branch_renewal_id' => $renewal->id,
                'rsomta7sen' => $rsomta7sen,
                'rsomnzafa' => $rsomnzafa,
                'matbo3at' => $matbo3at,
                'total' => $elegmaly,
            ]);
    
            // تسجيل العملية في اللوج
            BranchRenewalLog::create([
                'user_id' => auth('api')->user()->id,
                'branch_renewal_id' => $renewal->id,
                'department_id' => auth('api')->user()->department_id,
                'details' => "اضافة فرع جديد",
                'notes' => $renewal->notes,
            ]);
    
            $renewal->update(['notes' => null]);
    
            return response()->json([
                'message' => 'تم انشاء فرع جديد بنجاح',
                'status' => 200,
                'type' => 'branch_management',
                'data' => [],
            ], 200);
    
        } catch (ValidationException $e) {
            $errors = collect($e->errors())->map(fn($messages) => $messages[0]);
            return response()->json([
                'message' => $errors,
                'status' => 422,
                'data' => [],
            ], 422);
        }
    }


    public function renewal_branch(Request $request)
    {
        try {
    
            $messages = [
                'required'      => 'حقل :attribute مطلوب.',
                'required_with' => 'حقل :attribute مطلوب عند اختيار تاريخي البداية والنهاية.',
                'image'         => 'حقل :attribute يجب أن يكون صورة.',
                'mimes'         => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max.file' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
                'business_name.max' => 'حقل :attribute يجب ألا يتجاوز :max حرف.',
                'after'         => 'حقل :attribute يجب أن يكون بعد تاريخ البداية.',
            ];
            
            $attributes = [
                'license_branch_id' => 'فرع الرخصة',
                'owner_id' => 'المالك',
            
                'rent_start_date' => 'تاريخ بداية الإيجار',
                'rent_end_date' => 'تاريخ نهاية الإيجار',
                'rent_value' => 'قيمة الإيجار',
            
                'rent_image' => 'صورة عقد الإيجار',
                'electric_image' => 'صورة عداد الكهرباء',
                'shop_front_image' => 'صورة واجهة المحل',
                'shop_back_image' => 'صورة خلفية المحل',
                'application_image' => 'صورة الطلب',
                'previous_professional_license_image' => 'صورة الرخصة المهنية السابقة',
            
                'number_of_doors' => 'عدد الأبواب',
                'obstacle_space' => 'مساحة العوائق',
                'electric_number' => 'رقم عداد الكهرباء',
            
                'panel_area' => 'مساحة اللوحة',
                'panel_type_id' => 'نوع اللوحة',
            
                'application_number' => 'رقم الطلب',
                'penalty' => 'الغرامة',
            
                'service_id' => 'الخدمات',
                'service_id.*' => 'الخدمة',
            
                'employee_number' => 'عدد الموظفين',
                'rental_type_id' => 'نوع الإيجار',
                'business_name' => 'اسم الشهرة',
            ];
            
            $request->validate([
                'license_branch_id' => 'required',
                'owner_id' => 'nullable',
            
                'rent_start_date' => 'nullable|date',
                'rent_end_date'   => 'nullable|date|after:rent_start_date',
                'rent_value' => 'required_with:rent_start_date,rent_end_date',
            
                'rent_image' => 'nullable',
                'electric_image' => 'nullable',
                'shop_front_image' => 'nullable',
                'shop_back_image' => 'nullable',
                'application_image' => 'nullable',
            
                'previous_professional_license_image' => 'nullable|file|image|mimes:jpg,jpeg,png|max:2048',
            
                'number_of_doors' => 'nullable|numeric|min:1',
                'obstacle_space' => 'nullable|numeric|min:1',
                'electric_number' => 'nullable|numeric|min:1',
                'panel_area' => 'nullable|numeric|min:1',
                'panel_type_id' => 'nullable',
            
                'application_number' => 'nullable',
                'penalty' => 'nullable',
            
                'service_id' => 'required|array',
                'service_id.*' => 'exists:services,id',
            
                'employee_number' => 'nullable',
                'rental_type_id' => 'nullable',
                'business_name' => 'nullable|string|max:255',
                
            ], $messages, $attributes);
    
    
            $applicationExists = Application::where('number', $request->application_number)->exists();
            
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
    
            //-----------------------------------------------------
            // هات آخر renewal لنفس الفرع
            //-----------------------------------------------------
            $lastRenewal = BranchRenewal::where('license_branch_id', $request->license_branch_id)
                ->latest()
                ->first();
    
    
            //-----------------------------------------------------
            // Function للتعامل مع الصور (فايل أو قديم)
            //-----------------------------------------------------
           $handleImage = function ($field) use ($request, $lastRenewal) {

                // لو تم رفع فايل → احفظه
                if ($request->hasFile($field)) {
                    $file = $request->file($field);
                    $newName = time() . "_" . $file->getClientOriginalName();
                    $file->move('renewal', $newName);
                    return $newName;
                }
            
                // لو مفيش فايل → ارجع القديم مباشرة
                return $lastRenewal->$field ?? null;
            };

    
            //-----------------------------------------------------
            // التعامل مع كل الصور
            //-----------------------------------------------------
            $rent_image = $handleImage('rent_image');
            $electric_image = $handleImage('electric_image');
            $shop_front_image = $handleImage('shop_front_image');
            $shop_back_image = $handleImage('shop_back_image');
            $application_image = $handleImage('application_image');
            $previous_professional_license_image = $handleImage('previous_professional_license_image');
            
            
            $facility_plan_image = $handleImage('facility_plan_image');
            
            $license_main_facility_image = $handleImage('license_main_facility_image');
            
            $facility_licenses_image = $handleImage('facility_licenses_image');
            
            $first_review_report_image = $handleImage('first_review_report_image');
            
            $second_review_report_image = $handleImage('second_review_report_image');
            
            $employee_contract = $handleImage('employee_contract');
    
    
            //-----------------------------------------------------
            // تواريخ السنة الحالية
            //-----------------------------------------------------
            $currentYear = now()->year;
            $start_date = Carbon::create($currentYear, 1, 1)->startOfDay();
            $end_date   = Carbon::create($currentYear, 12, 31)->endOfDay();
    
    
            //-----------------------------------------------------
            // حفظ البيانات
            //-----------------------------------------------------
            $renewal = new BranchRenewal();
            $renewal->type =  "تجديد";
            $renewal->depart = "المراجعة";
            $renewal->coming_from = "الادخال";
            $renewal->user_id =  auth()->user()->id;
            $renewal->license_branch_id =  $request->license_branch_id;
            $renewal->owner_id = $request->owner_id;
            $renewal->supervisor_id = $request->supervisor_id;
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
    
            $renewal->previous_professional_license_image = $previous_professional_license_image;
            $renewal->employee_number = $request->employee_number;
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
            $renewal->notes = $request->notes;
            $renewal->service_type_id = 63;
             $renewal->serv_code = $request->serv_code;
            $renewal->save();
    
    
    
    
            //-----------------------------------------------------
            // حفظ الخدمات
            //-----------------------------------------------------
            foreach ($request->service_id as $service) {
    
                $ser = Service::find($service);
    
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
            $log->details = "اضافة تجديد جديد";
            $log->notes = $renewal->notes;
            $log->save();
            
            $renewal->update([
                'notes' => null, 
            ]);
    
            return response()->json([
                'message' => 'تم تجديد الفرع بنجاح',
                'status' => 200,
                'type' => 'renewal',
                'data' => [],
            ], 200);
    
    
        } catch (ValidationException $e) {
    
            $errors = collect($e->errors())->map(fn($m) => $m[0]);
    
            return response()->json([
                'message' => $errors,
                'status' => 422,
                'data' => [],
            ], 422);
        }
    }

    
    public function license_branches($id)
    {
        $license = License::findOrFail($id);
        
        $licenserData = [
            'id'           => $license->id,
            'sub_name'           => $license->sub_name,
            'category_name'           => $license->category_name,
            'first_status_name'           => $license->first_status_name,
            'created_at'           => $license->created_at,
            'first_start_date'           => $license->first_start_date,
            'first_end_date'           => $license->first_end_date,
            'category_id'           => $license->category_id,
            'sub_category_id'           => $license->sub_category_id,
        ];
    
        $branches = $license->branches()->paginate(8);
        
        $branches->getCollection()->transform(function ($branches) {
             return [
                'id'            => $branches->id,
                'category_id'=> $branches->category_id,
                'sub_category_id'=> $branches->sub_category_id,
                'type_name'=> $branches->type_name,
               'name'=> $branches->name,
               'directorate_name'=> $branches->directorate_name,
               'address'=> $branches->address,
            ];
        });
    
        return response()->json([
            'message' => 'فروع الرخصة',
            'status' => 200,
            'license' => $licenserData,
            'branches' => $branches,
        ], 200);
    }
    
    public function branches_renewals($id)
    {
        $license_branch = LicenseeBranch::findOrFail($id);
         $license_branchData = [
            'id'           => $license_branch->id,
            'type_name'           => $license_branch->type_name,
            'address'           => $license_branch->address,
            'directorate_name'           => $license_branch->directorate_name,
            'name'           => $license_branch->name,
            'lat'           => $license_branch->lat,
            'lang'           => $license_branch->lang,
            'customer_phone'           => $license_branch->customer_phone,
            'customer_address'           => $license_branch->customer_address,
            'category_id'           => $license_branch->category_id,
            'sub_category_id'           => $license_branch->sub_category_id,
            'license_id'           => $license_branch->license_id,
            'status'                =>$license_branch->status,
            'number_of_doors'                =>$license_branch->number_of_doors,
              'panel_area'                =>$license_branch->panel_area,
                'obstacle_space'                =>$license_branch->obstacle_space,
        ];
    
        $renewals = $license_branch->renewals()->paginate(8);
        
        $renewals->getCollection()->transform(function ($renewals) {
        return [
            'id'            => $renewals->id,
            'application_number'            => $renewals->application_number,
            'owner_name'            => $renewals->owner_name,
            'created_at'            => $renewals->created_at,
            'start_date'            => $renewals->start_date,
            'end_date'            => $renewals->end_date,
            'number_of_doors'            => $renewals->number_of_doors,
            'obstacle_space'            => $renewals->obstacle_space,
            'panel_area'            => $renewals->panel_area,
            'panel_type_name'            => $renewals->panel_type_name,
            'status_name'            => $renewals->status_name,
            'status_id'            => $renewals->status_id,
            'sub_category_id'            => $renewals->sub_category_id,
            'category_id'            => $renewals->category_id,
            'type'            => $renewals->type,
            // أضف أي حقول أخرى تحب ترجعها
        ];
    });
    
        return response()->json([
            'message' => 'تجديدات الرخصة',
            'status' => 200,
            'license_branch' => $license_branchData,
            'renewals' => $renewals,
        ], 200);
    }
    
    
    public function get_branch_details(Request $request, $id)
    {
        if (!$id) {
            return response()->json([
                'status'  => 422,
                'message' => 'branch_id مطلوب',
                'data'    => []
            ], 422);
        }
    
        // جلب آخر تجديد للفرع + العلاقات المالية
       // $data = BranchRenewal::with([
          //  'services.service',
          //  'finance',
          //  'nzafa'
       // ])
        //->where('license_branch_id', $id)
        //->latest()
        //->first();
        
        $data = BranchRenewal::with([
           'services.service',
            'finance',
            'nzafa'
        ])
        ->where('license_branch_id', $id)
        ->get();
        
        if (!$data) {
            return response()->json([
                'status'  => 404,
                'message' => 'لا يوجد بيانات لهذا الفرع',
                'data'    => []
            ], 404);
        }
    
        // جلب الفروع الأخرى للرخصة
        $licbranch = LicenseeBranch::find($id);
        $license   = License::find($licbranch->license_id);
    
        $branches = LicenseeBranch::where('license_id', $license->id)
            ->where('status', 0)
            ->where('id', '!=', $id)
            ->get();
    
        // العلاقات المالية
        $lastRenewal = $data->sortByDesc('created_at')->first();
        
        $finance = $lastRenewal?->finance;
        $nzafa   = $lastRenewal?->nzafa;
        
        //$finance = $data->finance;
        //$nzafa   = $data->nzafa;
    
        // تجهيز fees من الداتابيز
        $financial = [
    
            // ---------- الرسوم ----------
            [
                'key'   => 'rsom',
                'label' => 'رسوم ترخيص',
                'value' => $finance?->rsom ?? 0,
            ],
            [
                'key'   => 'rsom_3waek',
                'label' => 'رسوم عوائق',
                'value' => $finance?->rsom3waek ?? 0,
            ],
            [
                'key'   => 'rsom_fat7at',
                'label' => 'رسوم فتحات',
                'value' => $finance?->rsomfat7at ?? 0,
            ],
            [
                'key'   => 'rsom_8rama',
                'label' => 'رسوم غرامة',
                'value' => $finance?->rsom8rama ?? 0,
            ],
            [
                'key'   => 'd3ayawe3lan',
                'label' => 'دعاية واعلان',
                'value' => $finance?->d3ayawe3lan ?? 0,
            ],
            [
                'key'   => 'mota5rat',
                'label' => 'متأخرات الرسوم',
                'value' => $finance?->mota5rat ?? 0,
            ],
            [
                'key'   => 'total_rsom',
                'label' => 'إجمالي الرسوم',
                'value' => $finance?->total ?? 0,
            ],
    
            // ---------- النظافة ----------
            [
                'key'   => 'rsom_ta7sen',
                'label' => 'رسوم تحسين',
                'value' => $nzafa?->rsomta7sen ?? 0,
            ],
            [
                'key'   => 'rsom_nzafa',
                'label' => 'رسوم نظافة',
                'value' => $nzafa?->rsomnzafa ?? 0,
            ],
            [
                'key'   => 'matbo3at',
                'label' => 'مطبوعات',
                'value' => $nzafa?->matbo3at ?? 0,
            ],
            [
                'key'   => 'total_nzafa',
                'label' => 'إجمالي النظافة',
                'value' => $nzafa?->total ?? 0,
            ],
    
            // ---------- الإجمالي ----------
            [
                'key'   => 'grand_total',
                'label' => 'الإجمالي',
                'value' => ($finance?->total ?? 0) + ($nzafa?->total ?? 0),
            ],
        ];
    
        return response()->json([
            'status'   => 200,
            'message'  => 'success',
            'data'     => $lastRenewal,
            'fees'     => $financial,
            'branches' => $branches,
        ], 200);
    }

   
    
    public function get_renewal_details(Request $request, $id)
    {
        if (!$id) {
            return response()->json([
                'status'  => 422,
                'message' => 'renewal_id مطلوب',
                'data'    => []
            ], 422);
        }
    
        // جلب التجديد + العلاقات المالية
        $data = BranchRenewal::with([
            'services.service',
            'finance',
            'nzafa'
        ])
        ->where('id', $id)
        ->first();
    
        if (!$data) {
            return response()->json([
                'status'  => 404,
                'message' => 'لا يوجد بيانات لهذا التجديد',
                'data'    => []
            ], 404);
        }
    
        // العلاقات
        $finance = $data->finance;
        $nzafa   = $data->nzafa;
    
        // تجهيز الرسوم من الداتابيز
        $financial = [
    
            // ---------- الرسوم ----------
            [
                'key'   => 'rsom',
                'label' => 'رسوم ترخيص',
                'value' => $finance?->rsom ?? 0,
            ],
            [
                'key'   => 'rsom_3waek',
                'label' => 'رسوم عوائق',
                'value' => $finance?->rsom3waek ?? 0,
            ],
            [
                'key'   => 'rsom_fat7at',
                'label' => 'رسوم فتحات',
                'value' => $finance?->rsomfat7at ?? 0,
            ],
            [
                'key'   => 'rsom_8rama',
                'label' => 'رسوم غرامة',
                'value' => $finance?->rsom8rama ?? 0,
            ],
            [
                'key'   => 'd3ayawe3lan',
                'label' => 'دعاية واعلان',
                'value' => $finance?->d3ayawe3lan ?? 0,
            ],
            [
                'key'   => 'mota5rat',
                'label' => 'متأخرات الرسوم',
                'value' => $finance?->mota5rat ?? 0,
            ],
            [
                'key'   => 'total_rsom',
                'label' => 'إجمالي الرسوم',
                'value' => $finance?->total ?? 0,
            ],
    
            // ---------- النظافة ----------
            [
                'key'   => 'rsom_ta7sen',
                'label' => 'رسوم تحسين',
                'value' => $nzafa?->rsomta7sen ?? 0,
            ],
            [
                'key'   => 'rsom_nzafa',
                'label' => 'رسوم نظافة',
                'value' => $nzafa?->rsomnzafa ?? 0,
            ],
            [
                'key'   => 'matbo3at',
                'label' => 'مطبوعات',
                'value' => $nzafa?->matbo3at ?? 0,
            ],
            [
                'key'   => 'total_nzafa',
                'label' => 'إجمالي النظافة',
                'value' => $nzafa?->total ?? 0,
            ],
    
            // ---------- الإجمالي ----------
            [
                'key'   => 'grand_total',
                'label' => 'الإجمالي',
                'value' => ($finance?->total ?? 0) + ($nzafa?->total ?? 0),
            ],
        ];
    
        return response()->json([
            'status'  => 200,
            'message' => 'success',
            'data'    => $data,
            'fees'    => $financial,
        ], 200);
    }


    
    public function approve_branch_renewal(Request $request, $id)
    {
        try {
            
            $renewal = BranchRenewal::findOrFail($id);
            $renewal->status_id = 2;
            $renewal->depart = "الادخال";
            $renewal->coming_from = "المراجعة";
           
            $renewal->save();
    
            $log = new BranchRenewalLog();
            $log->user_id = auth('api')->user()->id;
            $log->branch_renewal_id = $renewal->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "اعتمدت من المراجعة";
            $log->notes = $renewal->notes;
            $log->save();
            
            $renewal->update([
                'notes' => null, 
            ]);
    
            return response()->json([
                'message' => 'تم الاعتماد بنجاح',
                'status' => 200,
                'type' => 'approval',
                'data' => [],
            ], 200);
    
        } catch (ValidationException $e) {
            $errors = collect($e->errors())->map(fn($messages) => $messages[0]);
            return response()->json([
                'message' => $errors,
                'status' => 422,
                'data' => [],
            ], 422);
        }
    }
    
    public function refuse_branch_renewal(Request $request, $id)
    {
        try {
    
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
                'notes' => 'الملاحظات',
            ];
            
    
    
            $request->validate([
                'notes' => 'required',
                
            ], $messages);
    
    
    
            // جلب التجديد الموجود
            $renewal = BranchRenewal::findOrFail($id);
            $renewal->notes = $request->notes;
             $renewal->status_id = 3;
             $renewal->depart = "الادخال";
            $renewal->coming_from = "المراجعة";
            $renewal->save();
            
        // if($renewal->type == "ايقاف خدمة")
        // {
        //      BranchRenewalServices::where('branch_renewal_id', $renewal->id)
        //     ->update(['status' => 0]);
            
        //     // حذف البيانات المالية المرتبطة بالتجديد
        //     BranchRenewalFinance::where('branch_renewal_id', $renewal->id)->delete();
            
        //     // حذف بيانات النظافة المرتبطة بالتجديد
        //     BranchRenewalNzafa::where('branch_renewal_id', $renewal->id)->delete();
        // }
            
         
    
            // حفظ اللوج
            $log = new BranchRenewalLog();
            $log->user_id = auth('api')->user()->id;
            $log->branch_renewal_id = $renewal->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "رفض اعتماد"; 
            $log->notes = $renewal->notes; // الملاحظات قبل التفريغ
            $log->save();
    
            // ⭐️ تفريغ الـ notes من الـ renewal
            $renewal->update([
                'notes' => null,
            ]);
    
            return response()->json([
                'message' => 'تم الرفض',
                'status' => 200,
                'type' => 'refuse',
                'data' => [],
            ], 200);
    
        } catch (ValidationException $e) {
            $errors = collect($e->errors())->map(fn($messages) => $messages[0]);
            return response()->json([
                'message' => $errors,
                'status' => 422,
                'data' => [],
            ], 422);
        }
    }


    public function update_branch_renewal(Request $request, $id)
    {
        DB::beginTransaction();
    
        try {
    
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max.file' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
                'business_name.max' => 'حقل :attribute يجب ألا يتجاوز :max حرف.',
            ];
    
            $request->validate([
                'owner_id' => 'nullable',
                'rent_start_date' => 'nullable|date',
                'rent_end_date' => 'nullable|date|after:rent_start_date',
                'rent_value' => 'required_with:rent_start_date,rent_end_date',
                'number_of_doors' => 'nullable|numeric|min:1',
                'obstacle_space' => 'nullable|numeric|min:1',
                'panel_area' => 'nullable|numeric|min:1',
                'panel_type_id' => 'nullable',
                'application_number' => 'nullable',
                'penalty' => 'nullable',
                'employee_number' => 'nullable',
                'rental_type_id' => 'nullable',
                 'business_name' => 'nullable|string|max:255',
            ], $messages);
    
            // =====================
            // Helper function للصور
            // =====================
            $saveImage = function ($file, $folder = 'renewal') {
                $name = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path($folder), $name);
                return $name;
            };
    
            // =====================
            // تحديث العميل
            // =====================
            $customer = Customer::findOrFail($request->customer_id);
            
            // نجهز بيانات العميل
            $customerData = [
                'qualification_id' => $request->qualification_id,
                'specialization_id' => $request->specialization_id,
                'profession' => $request->profession,
                'graduation_date' => $request->graduation_date,
                'graduation_country' => $request->graduation_country,
            ];
            
            // تحديث الصور فقط لو موجودة
            $imageFields = [
                'request_application_image',
                'preview_location_application_image',
                'health_checkup_image',
                'qualification_image',
            ];
            
            foreach ($imageFields as $field) {
                if ($request->hasFile($field)) {
                    $customerData[$field] = $saveImage($request->file($field), 'customers');
                }
            }
            
            // تحديث العميل
            $customer->update($customerData);

    
            // =====================
            // تحديث المشرف
            // =====================
            if ($request->supervisor_id) {
               $super = Customer::findOrFail($request->supervisor_id);

                $superData = [
                    'qualification_id' => $request->supervisor_qualification_id,
                    'specialization_id' => $request->supervisor_specialization_id,
                    'profession' => $request->supervisor_profession,
                    'graduation_date' => $request->supervisor_graduation_date,
                    'graduation_country' => $request->supervisor_graduation_country,
                ];
                
                // تحديث الصور فقط لو موجودة
                if ($request->hasFile('supervisor_qualification_image')) {
                    // حفظ الصور في فولدر customers
                    $superData['supervisor_qualification_image'] = $saveImage($request->file('supervisor_qualification_image'), 'customers');
                }
                
                $super->update($superData);
            }
    
            // =====================
            // تحديث BranchRenewal
            // =====================
            $renewal = BranchRenewal::findOrFail($id);
    
            // حفظ بيانات الصور
            $imageFields = [
                'rent_image',
                'application_image',
                'electric_image',
                'shop_front_image',
                'shop_back_image',
                'previous_professional_license_image',
                'suspension_form_image',
                'facility_plan_image',
                'license_main_facility_image',
                'facility_licenses_image',
                'first_review_report_image',
                'second_review_report_image',
                'payment_receipt_image',
            ];
    
            foreach ($imageFields as $field) {
                if ($request->hasFile($field)) {
                    $renewal->$field = $saveImage($request->file($field));
                }
            }
    
            // حفظ بيانات الباقي
            $renewal->update([
                'owner_id' => $request->owner_id,
                'rental_type_id' => $request->rental_type_id,
                'rent_start_date' => $request->rent_start_date,
                'rent_end_date' => $request->rent_end_date,
                'rent_value' => $request->rent_value,
                'currency_id' => $request->currency_id,
                'number_of_doors' => $request->number_of_doors,
                'obstacle_space' => $request->obstacle_space,
                'electric_number' => $request->electric_number,
                'panel_area' => $request->panel_area,
                'panel_type_id' => $request->panel_type_id,
                'status_id' => 1,
                'application_number' => $request->application_number,
                'penalty' => $request->penalty,
                'notes' => $request->notes,
                'employee_number' => $request->employee_number,
                'temporary' => $request->temporary,
                'facility_name' =>$request->facility_name,
                'beds_number' =>$request->beds_number,
                'medical_staff_number' =>$request->medical_staff_number,
                'staff_number' =>$request->staff_number,
               
                
            ]);
            
            $supervisorId = null;

            if ($request->has('owner_license') && $request->boolean('owner_license')) {
                $supervisorId = $request->customer_id;
            } elseif ($request->has('supervisor_id')) {
                $supervisorId = $request->supervisor_id;
            }
            
            $renewal->update([
                'supervisor_id' => $supervisorId
            ]);
            
            // =====================
            // تحديث الخدمات
            // =====================
            if ($request->has('service_id')) {
                $oldServices = BranchRenewalServices::where('branch_renewal_id', $renewal->id)->pluck('service_id')->toArray();
                $newServices = $request->service_id;
    
                $servicesToDelete = array_diff($oldServices, $newServices);
                $servicesToAdd = array_diff($newServices, $oldServices);
    
                if (!empty($servicesToDelete)) {
                    BranchRenewalServices::where('branch_renewal_id', $renewal->id)->whereIn('service_id', $servicesToDelete)->delete();
                }
    
                foreach ($servicesToAdd as $serviceId) {
                    BranchRenewalServices::create([
                        'branch_renewal_id' => $renewal->id,
                        'service_id' => $serviceId,
                    ]);
                }
            }
            
            // =====================
            // إعادة حساب الرسوم
            // =====================
            
            $brserv = BranchRenewalServices::where('branch_renewal_id', $renewal->id)
                        ->pluck('service_id')
                        ->toArray();
            
            /*
            |--------------------------------------------------------------------------
            | 1️⃣ الرسوم الأساسية
            |--------------------------------------------------------------------------
            */
            $rsom = 0;
            
            if ((int)$renewal->service_type_id === 62 && $renewal->type === 'ايقاف فرع') {
            
                $rsom = count($brserv) * 260;
            
            } else {
            
                $rsom = ServicePrice::whereIn('service_id', $brserv)
                    ->where('service_type_id', $renewal->service_type_id)
                    ->sum('price');
            }
            
            /*
            |--------------------------------------------------------------------------
            | 2️⃣ الدعاية والإعلان
            |--------------------------------------------------------------------------
            */
            $d3ayawe3lan = 0;
            
            if (!is_null($renewal->panel_area)) {
            
                if ($renewal->panel_type_id == 1) {
                    $pricePerMeter = 800;
                } elseif ($renewal->panel_type_id == 2) {
                    $pricePerMeter = 1000;
                } else {
                    $pricePerMeter = 0;
                }
            
                $d3ayawe3lan = $renewal->panel_area * $pricePerMeter;
            }
            
            /*
            |--------------------------------------------------------------------------
            | 3️⃣ العوائق
            |--------------------------------------------------------------------------
            */
            $obstacleMeterPrice = Price::where('name', 'رسوم عوائق')->value('value') ?? 0;
            $rsom3waek = ($renewal->obstacle_space ?? 0) * $obstacleMeterPrice;
            
            /*
            |--------------------------------------------------------------------------
            | 4️⃣ الفتحات
            |--------------------------------------------------------------------------
            */
            $rsomfat7at = 0;
            
            if ((int)$renewal->number_of_doors > 2) {
            
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
            | الإجمالي
            |--------------------------------------------------------------------------
            */
            $totalFinance =
                $rsom +
                $d3ayawe3lan +
                $rsom3waek +
                $rsomfat7at +
                $rsom8rama;
            
            
            /*
            |--------------------------------------------------------------------------
            | تحديث أو إنشاء Finance
            |--------------------------------------------------------------------------
            */
            BranchRenewalFinance::updateOrCreate(
                ['branch_renewal_id' => $renewal->id],
                [
                    'rsom'        => $rsom,
                    'mota5rat'    => 0,
                    'rsom3waek'   => $rsom3waek,
                    'd3ayawe3lan' => $d3ayawe3lan,
                    'rsomfat7at'  => $rsomfat7at,
                    'rsom8rama'   => $rsom8rama,
                    'total'       => $totalFinance,
                ]
            );
            
            if ($renewal->service_type_id == 62 || $renewal->service_type_id == 63) 
            {

                $rsomta7sen = ServicePrice::whereIn('service_id',$brserv)
                                ->where('service_type_id',67)
                                ->sum('price');
            
                $rsomnzafa = ServicePrice::whereIn('service_id',$brserv)
                                ->where('service_type_id',69)
                                ->sum('price');
            
            } else {
            
                $rsomta7sen = ServicePrice::whereIn('service_id',$brserv)
                                ->where('service_type_id',68)
                                ->sum('price');
            
                $rsomnzafa = ServicePrice::whereIn('service_id',$brserv)
                                ->where('service_type_id',70)
                                ->sum('price');
            }
            
            $matbo3at = 1200;
            
            $totalNzafa = $rsomta7sen + $rsomnzafa + $matbo3at;
            
            BranchRenewalNzafa::updateOrCreate(
                ['branch_renewal_id' => $renewal->id],
                [
                    'rsomta7sen' => $rsomta7sen,
                    'rsomnzafa'  => $rsomnzafa,
                    'matbo3at'   => $matbo3at,
                    'total'      => $totalNzafa,
                ]
            );


    
            // =====================
            // تحديث Branch
            // =====================
            $branch = LicenseeBranch::where('id', $renewal->license_branch_id)->first();
            if ($branch) {
                $branch->update([
                    'branch_type_id' => 1,
                    'directorate_id' => $request->directorate_id,
                    'name' => $request->name,
                    'address' => $request->address,
                    'lat' => $request->lat,
                    'lang' => $request->lang,
                ]);
            }
    
            // =====================
            // تحديث License
            // =====================
            $license = License::findOrFail($branch->license_id);
            $license->update([
                'customer_id' => $request->customer_id,
                'category_id' => $request->category_id,
                //'sub_category_id' => $request->sub_category_id,
                'email' => $request->email,
                'business_name' => $request->business_name,
                'commercial_registration_image' => $request->hasFile('commercial_registration_image') 
                ? $saveImage($request->file('commercial_registration_image'), 'license') 
                : $license->commercial_registration_image,

            ]);
    
            // =====================
            // Logging
            // =====================
            $log = new BranchRenewalLog();
            $log->user_id = auth('api')->user()->id;
            $log->branch_renewal_id = $renewal->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = json_encode($renewal->getChanges(), JSON_UNESCAPED_UNICODE);
            $log->notes = $renewal->notes;
            $log->save();
    
            DB::commit();
    
            return response()->json([
                'message' => 'تم تحديث البيانات بنجاح وقيد المراجعة',
                'status' => 200,
                'data' => $renewal
            ]);
    
        } catch (ValidationException $e) {
            DB::rollBack();
            $errors = collect($e->errors())->map(fn($messages) => $messages[0]);
            return response()->json([
                'message' => $errors,
                'status' => 422,
                'data' => [],
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 500,
                'data' => [],
            ], 500);
        }
    }

    public function logs(Request $request, $id)
    {
        $logs = BranchRenewalLog::where('branch_renewal_id', $id);
    
        // فلتر من تاريخ إلى تاريخ
        if ($request->from_date && $request->to_date) {
            $logs->whereBetween('created_at', [
                $request->from_date . " 00:00:00",
                $request->to_date . " 23:59:59"
            ]);
        }
    
        $logs = $logs->latest()->paginate(8);
    
        return response()->json([
            'message' => 'success',
            'status' => 200,
            'data' => $logs,
        ], 200);
    }
    

    public function add_service_to_license(Request $request)
    {
        $request->validate([
            'license_id' => 'required|exists:licenses,id',
            'application_number' => 'required',
            'service_id' => 'required|array',
            'service_id.*' => 'exists:services,id',
            'application_image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);
    
        DB::beginTransaction();
    
        try {
    
            $user = auth('api')->user();
    
            // 1️⃣ الفروع
            $branches = LicenseeBranch::where('license_id', $request->license_id)->get();
    
            if ($branches->isEmpty()) {
                return response()->json([
                    'status' => 404,
                    'message' => 'لا يوجد فروع لهذه الرخصة',
                ], 404);
            }
    
            // 2️⃣ الفرع الرئيسي
            $mainBranch = $branches->where('branch_type_id', 1)->first();
    
            if (!$mainBranch) {
                throw new \Exception('لا يوجد فرع رئيسي');
            }
    
            $lastMainRenewal = BranchRenewal::where('license_branch_id', $mainBranch->id)
                ->latest()
                ->first();
    
            if (!$lastMainRenewal) {
                throw new \Exception('لا يوجد تجديد سابق للفرع الرئيسي');
            }
    
            // 3️⃣ تحديد الخدمات الجديدة قبل أي إضافة
            $oldServiceIds = BranchRenewalServices::where('branch_renewal_id', $lastMainRenewal->id)
                ->pluck('service_id')
                ->toArray();
    
            $newServiceIds = array_values(array_diff($request->service_id, $oldServiceIds));
    
            if (empty($newServiceIds)) {
                throw new \Exception('كل الخدمات مضافة بالفعل');
            }
    
            // 4️⃣ رفع الصورة
            $file = $request->file('application_image');
            $application_image = time() . '_' . $file->getClientOriginalName();
            $file->move('renewal', $application_image);
    
            // 5️⃣ إنشاء التجديد الجديد
            $newRenewal = $lastMainRenewal->replicate();
    
            $newRenewal->type = "اضافة خدمة";
            $newRenewal->depart = "المراجعة";
            $newRenewal->coming_from = "الادخال";
            $newRenewal->user_id = $user->id;
            $newRenewal->status_id = 1;
            $newRenewal->start_date = now()->startOfYear();
            $newRenewal->end_date = now()->endOfYear();
            $newRenewal->application_number = $request->application_number;
            $newRenewal->application_image = $application_image;
            $newRenewal->service_type_id = 62;
            $newRenewal->serv_code = $request->serv_code;
            $newRenewal->save();
    
            // 6️⃣ إضافة الخدمات الجديدة للتجديد الجديد
            $services = Service::whereIn('id', $newServiceIds)->get();
    
            foreach ($services as $service) {
                BranchRenewalServices::create([
                    'branch_renewal_id' => $newRenewal->id,
                    'service_id' => $service->id,
                    'value' => $service->value,
                   // 'status' => 1,
                ]);
            }
    
            /*
            |--------------------------------------------------------------------------
            | الحسابات المالية
            |--------------------------------------------------------------------------
            */
    
            $rsom = ServicePrice::whereIn('service_id', $newServiceIds)
                ->where('service_type_id', 62)
                ->sum('price');
    
            $obstacleMeterPrice = Price::where('name', 'رسوم عوائق')->value('value') ?? 0;
            $rsom3waek = ($newRenewal->obstacle_space ?? 0) * $obstacleMeterPrice;
    
            $rsomfat7at = 0;
            if ((int)$newRenewal->number_of_doors > 2) {
                $rsomfat7at = $rsom * 0.20;
            }
    
            $rsom8rama = 0;
            $penaltySum = ServicePrice::whereIn('service_id', $newServiceIds)
                ->where('service_type_id', 66)
                ->sum('price');
    
            if ($penaltySum) {
                $expiryDate = Carbon::parse($newRenewal->end_date);
                $penaltyStart = Carbon::create($expiryDate->year, 3, 1);
    
                if (now()->gt($penaltyStart)) {
                    $yearsLate = max($penaltyStart->diffInYears(now()), 1);
                    $rsom8rama = $yearsLate * $penaltySum;
                }
            }
    
            $totalFinance = $rsom + $rsom3waek + $rsomfat7at + $rsom8rama;
    
            BranchRenewalFinance::create([
                'branch_renewal_id' => $newRenewal->id,
                'rsom' => $rsom,
                'mota5rat' => 0,
                'rsom3waek' => $rsom3waek,
                'd3ayawe3lan' => 0,
                'rsomfat7at' => $rsomfat7at,
                'rsom8rama' => $rsom8rama,
                'total' => $totalFinance,
            ]);
    
            // تحسين + نظافة
            $rsomta7sen = ServicePrice::whereIn('service_id', $newServiceIds)
                ->where('service_type_id', 67)
                ->sum('price');
    
            $rsomnzafa = ServicePrice::whereIn('service_id', $newServiceIds)
                ->where('service_type_id', 69)
                ->sum('price');
    
            $matbo3at = 1200;
            $totalNzafa = $rsomta7sen + $rsomnzafa + $matbo3at;
    
            BranchRenewalNzafa::create([
                'branch_renewal_id' => $newRenewal->id,
                'rsomta7sen' => $rsomta7sen,
                'rsomnzafa' => $rsomnzafa,
                'matbo3at' => $matbo3at,
                'total' => $totalNzafa,
            ]);
    
            // 7️⃣ توزيع الخدمات على باقي التجديدات
            foreach ($branches as $branch) {
    
                $renewals = BranchRenewal::where('license_branch_id', $branch->id)->get();
    
                foreach ($renewals as $renewal) {
    
                    foreach ($services as $service) {
    
                        BranchRenewalServices::firstOrCreate([
                            'branch_renewal_id' => $renewal->id,
                            'service_id' => $service->id,
                        ], [
                            'value' => $service->value
                        ]);
                    }
                }
            }
    
            // لوج
            BranchRenewalLog::create([
                'user_id' => $user->id,
                'branch_renewal_id' => $newRenewal->id,
                'department_id' => $user->department_id,
                'details' => "إضافة تجديد جديد تلقائي للفرع الرئيسي بعد إضافة خدمات",
            ]);
    
            DB::commit();
    
            return response()->json([
                'status' => 200,
                'message' => 'تم إضافة الخدمة وإنشاء التجديد والحسابات بنجاح',
                'type' => 'addService',
            ]);
    
        } catch (\Exception $e) {
    
            DB::rollBack();
    
            return response()->json([
                'status' => 500,
                'message' => 'حدث خطأ غير متوقع',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function remove_service_from_license(Request $request)
    {
        
        try {
        
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
               
            ];
            
            $attributes = [
               
            
                'service_id' => 'الخدمات',
                'service_id.*' => 'الخدمة',
            
                'application_number' => 'رقم الاستمارة',
                'application_image' => 'صورة استمارة الطلب',
              //  'previous_professional_license_image' => 'صورة رخصة مزاولة المهنة السابقة',
            ];
            
            $request->validate([
                'license_id' => 'required|exists:licenses,id',
                'application_number' => 'required',
                'application_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
               // 'previous_professional_license_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
                'service_id' => 'required|array|min:1',
                'service_id.*' => 'exists:services,id',
            ], $messages, $attributes);
       
    
        
            
            
    
            // 1. جلب الفروع الخاصة بالرخصة
            $branches = LicenseeBranch::where('license_id', $request->license_id)->get();
    
            if ($branches->isEmpty()) {
                return response()->json([
                    'status' => 404,
                    'message' => 'لا يوجد فروع لهذه الرخصة',
                ], 404);
            }
    
          
          
    
    
            // -------------------------------------------------------
            // 3. إنشاء تجديد جديد للفرع الرئيسي مثل الإضافة بالضبط
            // -------------------------------------------------------
    
            $mainBranch = LicenseeBranch::where('license_id', $request->license_id)
                ->where('branch_type_id', 1)
                ->first();
    
            if ($mainBranch) {
                
                    $application_image = null;
                    if ($file = $request->file('application_image')) {
                        $application_image = time() . $file->getClientOriginalName();
                        $file->move('renewal', $application_image);
                    }
        
        
                  //  $previous_professional_license_image = null;
                  //  if ($file = $request->file//('previous_professional_license_image')) {
                      //  $previous_professional_license_image = time() . $file->getClientOriginalName();
                      //  $file->move('renewal', $previous_professional_license_image);
                  //  }
                $lastMainRenewal = BranchRenewal::where('license_branch_id', $mainBranch->id)
                    ->latest()
                    ->first();
    
                if ($lastMainRenewal) {
    
                    // إنشاء نسخة كاملة من آخر تجديد
                    $newRenewal = $lastMainRenewal->replicate();
                    $newRenewal->type =  "ايقاف خدمة";
                    $newRenewal->depart = "المراجعة";
                    $newRenewal->coming_from = "الادخال";
                    $newRenewal->user_id = auth('api')->user()->id;
                    $newRenewal->status_id = 1;
                    $newRenewal->start_date = now()->startOfYear();
                    $newRenewal->end_date = now()->endOfYear();
                    $newRenewal->application_number = $request->application_number;
                    $newRenewal->application_image = $application_image;
                   // $newRenewal->previous_professional_license_image = $previous_professional_license_image;
                    $newRenewal->service_type_id = 62;
                    $newRenewal->serv_code = $request->serv_code;
                    $newRenewal->created_at = now();
                    $newRenewal->updated_at = now();
                    $newRenewal->save();
    
                    // جلب كل الخدمات القديمة لهذا التجديد
                    $oldServices = BranchRenewalServices::where('branch_renewal_id', $lastMainRenewal->id)->get();
    
                    // نسخ الخدمات القديمة مع تعديل حالة الخدمات المحذوفة
                    foreach ($request->service_id as $service_id) 
                    {

                        $branchesId = LicenseeBranch::where('license_id', $request->license_id)
                        ->pluck('id');

                        $renewalsId = BranchRenewal::whereIn('license_branch_id',$branchesId)->pluck('id');

                        BranchRenewalServices::whereIn('branch_renewal_id', $renewalsId)
                        ->where('service_id', $service_id)
                        ->update([
                            'status' => 1
                        ]);

    
                        BranchRenewalServices::create([
                            'branch_renewal_id' => $newRenewal->id,
                            'service_id' => $service_id,
                            
                            'status' => 1,
                        ]);
                    }
                    
                    $brserv = $request->service_id;

                    /*
                    |--------------------------------------------------------------------------
                    | 1️⃣ الرسوم الأساسية (rsom)
                    |--------------------------------------------------------------------------
                    */
                    $rsom = 0;
                    
                    if ((int)$newRenewal->service_type_id === 62 && $newRenewal->type === 'ايقاف فرع') {
                    
                        $count = count($brserv);
                        $rsom  = $count * 260;
                    
                    } else {
                    
                        $rsom = ServicePrice::whereIn('service_id', $brserv)
                            ->where('service_type_id', $newRenewal->service_type_id)
                            ->sum('price');
                    }
                    
                    /*
                    |--------------------------------------------------------------------------
                    | 2️⃣ رسوم الدعاية والإعلان
                    |--------------------------------------------------------------------------
                    */
                    $d3ayawe3lan = 0;
                    
                    //if (!is_null($newRenewal->panel_area)) {
                    
                       // if ($newRenewal->panel_type_id == 1) {
                        //    $pricePerMeter = 800;
                       // } elseif ($newRenewal->panel_type_id == 2) {
                          //  $pricePerMeter = 1000;
                       // } else {
                       //     $pricePerMeter = 0; // أو أي قيمة افتراضية تحبها
                       // }
                       // $d3ayawe3lan   = $newRenewal->panel_area * //$pricePerMeter;
                   // }
                    
                    /*
                    |--------------------------------------------------------------------------
                    | 3️⃣ رسوم العوائق
                    |--------------------------------------------------------------------------
                    */
                    $obstacleMeterPrice = Price::where('name', 'رسوم عوائق')->value('value') ?? 0;
                   //$rsom3waek = ($newRenewal->obstacle_space ?? 0) * $obstacleMeterPrice;
                    $rsom3waek = 0;
                    /*
                    |--------------------------------------------------------------------------
                    | 4️⃣ رسوم الفتحات
                    |--------------------------------------------------------------------------
                    */
                    $rsomfat7at = 0;
                    $fat7atCount = (int) $newRenewal->number_of_doors;
                    
                    if ($fat7atCount > 2) {
                    
                        $servicesPrices = ServicePrice::whereIn('service_id', $brserv)
                            ->where('service_type_id', $newRenewal->service_type_id)
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
                    
                    if ($penaltyPrice && $newRenewal->end_date) {
                    
                        $expiryDate = Carbon::parse($newRenewal->end_date);
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
                        'branch_renewal_id' => $newRenewal->id,
                    
                        'rsom'         => $rsom,
                        'mota5rat'     => 0,
                        'rsom3waek'    => $rsom3waek,
                        'd3ayawe3lan'  => $d3ayawe3lan,
                        'rsomfat7at'   => $rsomfat7at,
                        'rsom8rama'    => $rsom8rama,
                        'total'        => $elegmalyelrsom,
                    ]);
                    
                    if($newRenewal->service_type_id == 62 OR $newRenewal->service_type_id == 63)
                    {
                        $rsomta7sen = ServicePrice::whereIn('service_id',$brserv)->where('service_type_id',67)->sum('price');
                    }else{
                        $rsomta7sen = ServicePrice::whereIn('service_id',$brserv)->where('service_type_id',68)->sum('price');
                    }
                    
                    if($newRenewal->service_type_id == 62 OR $newRenewal->service_type_id == 63)
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
                        'branch_renewal_id' => $newRenewal->id,
                        'rsomta7sen' => $rsomta7sen,
                        'rsomnzafa' => $rsomnzafa,
                        'matbo3at' => $matbo3at,
                        'total' => $elegmaly,
                    
                        
                    ]);
                    
                    
    
                    // تسجيل لوج
                    BranchRenewalLog::create([
                        'user_id' => auth('api')->user()->id,
                        'branch_renewal_id' => $newRenewal->id,
                        'department_id' => auth('api')->user()->department_id,
                        'details' => "إنشاء تجديد جديد للفرع الرئيسي بعد حذف خدمات",
                    ]);
                }
            }
    
    
            return response()->json([
                'status' => 200,
                'message' => 'تم حذف الخدمات وتطبيق الحذف على جميع التجديدات، وإنشاء تجديد جديد للفرع الرئيسي',
                'type' => 'removeService',
            ], 200);
    
        } catch (\Exception $e) {
    
            return response()->json([
                'status' => 500,
                'message' => 'حدث خطأ غير متوقع',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function deactivate_branch(Request $request)
    {
        $request->validate([
            'license_branch_id' => 'required|exists:license_branches,id',
            'new_main_branch_id' => 'nullable|exists:license_branches,id',
            'application_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            'previous_professional_license_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);
    
        try {
    
            // 1️⃣ جلب الفرع
            $branch = LicenseeBranch::find($request->license_branch_id);
    
            if (!$branch) {
                return response()->json([
                    'status' => 404,
                    'message' => 'الفرع غير موجود'
                ], 404);
            }
    
            // 2️⃣ رفع الصور
            $application_image = null;
            if ($file = $request->file('application_image')) {
                $application_image = time().'_'.$file->getClientOriginalName();
                $file->move('renewal', $application_image);
            }
    
            $previous_professional_license_image = null;
            if ($file = $request->file('previous_professional_license_image')) {
                $previous_professional_license_image = time().'_'.$file->getClientOriginalName();
                $file->move('renewal', $previous_professional_license_image);
            }
    
            // 3️⃣ آخر تجديد للفرع
            $lastRenewal = BranchRenewal::where('license_branch_id', $branch->id)
                ->latest()
                ->first();
    
            if (!$lastRenewal) {
                return response()->json([
                    'status' => 422,
                    'message' => 'لا يوجد تجديد سابق للفرع'
                ], 422);
            }
    
            // 4️⃣ إنشاء طلب تجديد جديد (ايقاف فرع)
            $newRenewal = $lastRenewal->replicate();
            $newRenewal->status_id = 1; // في انتظار الاعتماد
            $newRenewal->type = 'ايقاف فرع';
            $newRenewal->depart = "المراجعة";
            $newRenewal->coming_from = "الادخال";
            $newRenewal->user_id =  auth()->user()->id;
            $newRenewal->application_image = $application_image;
            $newRenewal->previous_professional_license_image = $previous_professional_license_image;
            $newRenewal->application_number = $request->application_number;
            $newRenewal->new_main_branch_id = $request->new_main_branch_id;
            $newRenewal->serv_code = $lastRenewal->serv_code;
            $newRenewal->start_date = now()->startOfYear();
            $newRenewal->end_date = now()->endOfYear();
            $newRenewal->created_at = now();
            $newRenewal->updated_at = now();
            $newRenewal->save();
    
            // 5️⃣ نسخ الخدمات من آخر تجديد
            $oldServices = BranchRenewalServices::where('branch_renewal_id', $lastRenewal->id)->get();
    
            foreach ($oldServices as $service) {
                BranchRenewalServices::create([
                    'branch_renewal_id' => $newRenewal->id,
                    'service_id' => $service->service_id,
                    'value' => $service->value,
                    'status' => 0, // حالة الخدمة جاهزة للاعتماد
                ]);
            }
            
            $brserv = $oldServices->pluck('service_id')->toArray();

            /*
            |--------------------------------------------------------------------------
            | 1️⃣ الرسوم الأساسية (rsom)
            |--------------------------------------------------------------------------
            */
            
            
           
            
            $count = count($brserv);
            $rsom  = $count * 260;
            
           
            
            /*
            |--------------------------------------------------------------------------
            | 2️⃣ رسوم الدعاية والإعلان
            |--------------------------------------------------------------------------
            */
            $d3ayawe3lan = 0;
            
            //if (!is_null($newRenewal->panel_area)) {
            
             //   if ($newRenewal->panel_type_id == 1) {
               //           $pricePerMeter = 800;
              //  } elseif ($newRenewal->panel_type_id == 2) {
                 //         $pricePerMeter = 1000;
              //  } else {
               //     $pricePerMeter = 0; // أو أي قيمة افتراضية تحبها
               // }
              //  $d3ayawe3lan   = $newRenewal->panel_area * $pricePerMeter;
           // }
            
            /*
            |--------------------------------------------------------------------------
            | 3️⃣ رسوم العوائق
            |--------------------------------------------------------------------------
            */
           // $obstacleMeterPrice = Price::where('name', 'رسوم عوائق')->value('value') ?? 0;
           // $rsom3waek = ($newRenewal->obstacle_space ?? 0) * $obstacleMeterPrice;
            
            $rsom3waek = 0;
            
            /*
            |--------------------------------------------------------------------------
            | 4️⃣ رسوم الفتحات
            |--------------------------------------------------------------------------
            */
            $rsomfat7at = 0;
           // $fat7atCount = (int) $newRenewal->number_of_doors;
            
            //if ($fat7atCount > 2) {
            
              //  $servicesPrices = ServicePrice::whereIn('service_id', $brserv)
                 //   ->where('service_type_id', $newRenewal->service_type_id)
                 //   ->pluck('price');
            
                //foreach ($servicesPrices as $price) {
                //    $rsomfat7at += ($price * 0.20);
               // }
           // }
            
            /*
            |--------------------------------------------------------------------------
            | 5️⃣ الغرامات
            |--------------------------------------------------------------------------
            */
            $rsom8rama = 0;
            
            $penaltyPrice = ServicePrice::whereIn('service_id', $brserv)
                ->where('service_type_id', 66)
                ->value('price') ?? 0;
            
            if ($penaltyPrice && $newRenewal->end_date) {
            
                $expiryDate = Carbon::parse($newRenewal->end_date);
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
                    'branch_renewal_id' => $newRenewal->id,
                
                    'rsom'         => $rsom,
                    'mota5rat'     => 0,
                    'rsom3waek'    => $rsom3waek,
                    'd3ayawe3lan'  => $d3ayawe3lan,
                    'rsomfat7at'   => $rsomfat7at,
                    'rsom8rama'    => $rsom8rama,
                    'total'        => $elegmalyelrsom,
                ]);
                
                if($newRenewal->service_type_id == 62 OR $newRenewal->service_type_id == 63)
                {
                    $rsomta7sen = ServicePrice::whereIn('service_id',$brserv)->where('service_type_id',67)->sum('price');
                }else{
                    $rsomta7sen = ServicePrice::whereIn('service_id',$brserv)->where('service_type_id',68)->sum('price');
                }
                
                if($newRenewal->service_type_id == 62 OR $newRenewal->service_type_id == 63)
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
                    'branch_renewal_id' => $newRenewal->id,
                    'rsomta7sen' => $rsomta7sen,
                    'rsomnzafa' => $rsomnzafa,
                    'matbo3at' => $matbo3at,
                    'total' => $elegmaly,
                
                    
                ]);

    
            // 6️⃣ تسجيل Log
            BranchRenewalLog::create([
                'user_id' => auth('api')->id(),
                'branch_renewal_id' => $newRenewal->id,
                'department_id' => auth('api')->user()->department_id,
                'details' => 'طلب إيقاف فرع (بانتظار الاعتماد)',
            ]);
    
            return response()->json([
                'status' => 200,
                'message' => 'تم تسجيل طلب إيقاف الفرع وبانتظار إصدار الرخصة',
                'type' => 'suspension_request',
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'حدث خطأ غير متوقع',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function print_portfolio(Request $request, $id)
    {
        try {
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
            ];
    
           
    
            // جلب التجديد الموجود
        
            $renewal = BranchRenewal::findOrFail($id);
            

            $renewal->status_id = 4;
              $renewal->depart = "المالية";
            $renewal->coming_from = "الادخال";
            $renewal->save();
            
    
            $log = new BranchRenewalLog();
            $log->user_id = auth('api')->user()->id;
            $log->branch_renewal_id = $renewal->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "ـم اصدار حافظة";
            $log->save();
    
            return response()->json([
                'message' => 'تم طباعة الحافظة بنجاح',
                'status' => 200,
                'type' => 'approval',
                'data' => [],
            ], 200);
    
        } catch (ValidationException $e) {
            $errors = collect($e->errors())->map(fn($messages) => $messages[0]);
            return response()->json([
                'message' => $errors,
                'status' => 422,
                'data' => [],
            ], 422);
        }
    }
    
    private function checkAndUpdateRenewalStatus($renewalId)
    {
        $financePaid = BranchRenewalFinancePayment::where('branch_renewal_id', $renewalId)->exists();
        $nzafaPaid   = BranchRenewalNzafaPayment::where('branch_renewal_id', $renewalId)->exists();
    
        if ($financePaid && $nzafaPaid) {
            BranchRenewal::where('id', $renewalId)->update([
                'status_id' => 5
            ]);
        }
    }


    public function finance_approve(Request $request, $id)
    {
        try {
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
            ];
    
            $request->validate([
                
            
                'payment_type_id' => 'required',
            
                'payment_receipt_image' => 'required_if:payment_type_id,1|image|mimes:jpg,jpeg,png,webp|max:2048',
            
                // 👇 مطلوبة لو payment_type_id = 2
                'bank_id'      => 'required_if:payment_type_id,2',
                'check_number' => 'required_if:payment_type_id,2',
                'value' => 'required',
                'check_image'  => 'required_if:payment_type_id,2|image|mimes:jpg,jpeg,png,webp|max:2048',
                'supply_voucher_image'  => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            
                
            
            ], $messages);

    
            // جلب التجديد الموجود
            
            $payment_receipt_image = null;
            if ($file = $request->file('payment_receipt_image')) {
                $payment_receipt_image = time() . $file->getClientOriginalName();
                $file->move('finance', $payment_receipt_image);
            }
            
            $check_image = null;
            if ($file = $request->file('check_image')) {
                $check_image = time() . $file->getClientOriginalName();
                $file->move('finance', $check_image);
            }
            
             $supply_voucher_image = null;
            if ($file = $request->file('supply_voucher_image')) {
                $supply_voucher_image = time() . $file->getClientOriginalName();
                $file->move('finance', $supply_voucher_image);
            }
            
            $extra_image = null;
            if ($file = $request->file('extra_image')) {
                $extra_image = time() . $file->getClientOriginalName();
                $file->move('finance', $extra_image);
            }
            
            $renewal = BranchRenewal::findOrFail($id);
            
            $finance = new BranchRenewalFinancePayment();
            $finance->branch_renewal_id = $renewal->id;
            $finance->user_id = auth('api')->user()->id;
            $finance->extra_image = $extra_image;
            $finance->check_image = $check_image;
            $finance->payment_receipt_image = $payment_receipt_image;
            $finance->payment_receipt_number = $request->payment_receipt_number;
            $finance->supply_voucher_image = $supply_voucher_image;
            $finance->payment_type_id =   $request->payment_type_id;
            $finance->bank_id =   $request->bank_id;
            $finance->check_number =   $request->check_number;
            $finance->value =   $request->value;
            $finance->status =   0;
            $finance->save();
            
            $this->checkAndUpdateRenewalStatus($renewal->id);
         
            
    
            $log = new BranchRenewalLog();
            $log->user_id = auth('api')->user()->id;
            $log->branch_renewal_id = $renewal->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "تم التحصيل من المالية";
            $log->notes = $request->notes;
            $log->save();
    
            return response()->json([
                'message' => 'تم الاعتماد بنجاح',
                'status' => 200,
                'type' => 'approval',
                'data' => [],
            ], 200);
    
        } catch (ValidationException $e) {
            $errors = collect($e->errors())->map(fn($messages) => $messages[0]);
            return response()->json([
                'message' => $errors,
                'status' => 422,
                'data' => [],
            ], 422);
        }
    }
    
    public function nzafa_approve(Request $request, $id)
    {
        try {
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
            ];
    
            $request->validate([
                
               
                'payment_receipt_image' => 'required_if:payment_type_id,1|image|mimes:jpg,jpeg,png|max:2048',
                
                
                'payment_type_id' => 'required',
                'bank_id'      => 'required_if:payment_type_id,2',
                'check_number' => 'required_if:payment_type_id,2',
                'check_image'  => 'required_if:payment_type_id,2|image|mimes:jpg,jpeg,png,webp|max:2048',
                'supply_voucher_image'  => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
             
               
                
            ], $messages);
    
            // جلب التجديد الموجود
            
            $payment_receipt_image = null;
            if ($file = $request->file('payment_receipt_image')) {
                $payment_receipt_image = time() . $file->getClientOriginalName();
                $file->move('nzafa', $payment_receipt_image);
            }
            
            $check_image = null;
            if ($file = $request->file('check_image')) {
                $check_image = time() . $file->getClientOriginalName();
                $file->move('nzafa', $check_image);
            }
            
             $supply_voucher_image = null;
            if ($file = $request->file('supply_voucher_image')) {
                $supply_voucher_image = time() . $file->getClientOriginalName();
                $file->move('nzafa', $supply_voucher_image);
            }
            
            
            $extra_image = null;
            if ($file = $request->file('extra_image')) {
                $extra_image = time() . $file->getClientOriginalName();
                $file->move('nzafa', $extra_image);
            }
            
            
            $renewal = BranchRenewal::findOrFail($id);
            
            $nzafa = new BranchRenewalNzafaPayment();
            $nzafa->branch_renewal_id = $renewal->id;
            $nzafa->user_id = auth('api')->user()->id;
            $nzafa->extra_image = $extra_image;
            $nzafa->check_image = $check_image;
            $nzafa->payment_receipt_image = $payment_receipt_image;
            $nzafa->payment_receipt_number = $request->payment_receipt_number;
            $nzafa->supply_voucher_image = $supply_voucher_image;
            $nzafa->payment_type_id =   $request->payment_type_id;
            $nzafa->bank_id =   $request->bank_id;
            $nzafa->check_number =   $request->check_number;
            $nzafa->value =   $request->value;
            $nzafa->status =   0;
            $nzafa->save();
            
            $this->checkAndUpdateRenewalStatus($renewal->id);
            
    
            $log = new BranchRenewalLog();
            $log->user_id = auth('api')->user()->id;
            $log->branch_renewal_id = $renewal->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "تم التحصيل من النظافة";
            $log->notes = $request->notes;
            $log->save();
    
            return response()->json([
                'message' => 'تم الاعتماد بنجاح',
                'status' => 200,
                'type' => 'approval',
                'data' => [],
            ], 200);
    
        } catch (ValidationException $e) {
            $errors = collect($e->errors())->map(fn($messages) => $messages[0]);
            return response()->json([
                'message' => $errors,
                'status' => 422,
                'data' => [],
            ], 422);
        }
    }


    public function print_license(Request $request, $id)
    {
        try {
    
            $renewal = BranchRenewal::findOrFail($id);
    
            // اعتماد / طباعة الرخصة
            $renewal->status_id = 6;
            $renewal->save();
    
            // 🛑 تنفيذ الإيقاف فعليًا لو نوعه "ايقاف فرع"
            if ($renewal->type === 'ايقاف فرع') {
    
                $branch = LicenseeBranch::find($renewal->license_branch_id);
                $branchesCount = LicenseeBranch::where('license_id', $branch->license_id)
                ->where('status', 0) // نشط
                ->count();

                // لو الفرع رئيسي يجب تحديد فرع بديل
                // لو الفرع رئيسي
                if ($branch->branch_type_id == 1) {
                
                    // لو عنده فروع تانية
                    if ($branchesCount > 1) {
                
                        if (!$renewal->new_main_branch_id) {
                            return response()->json([
                                'status' => 422,
                                'message' => 'يجب تحديد فرع بديل ليصبح رئيسي'
                            ], 422);
                        }
                
                        $newMain = LicenseeBranch::find($renewal->new_main_branch_id);
                
                        if (!$newMain || $newMain->license_id != $branch->license_id) {
                            return response()->json([
                                'status' => 422,
                                'message' => 'الفرع البديل غير صالح'
                            ], 422);
                        }
                
                        $newMain->update([
                            'branch_type_id' => 1,
                        ]);
                
                    } else {
                        // 🚫 مفيش فروع تانية → قفل النشاط كله
                        $branch->license->update([
                            'status' => 1 // موقوف
                        ]);
                    }
                }

    
                // تحديث الفرع الحالي → تم إيقافه
                $branch->update([
                    'status' => 1,        // إيقاف الفرع
                    'branch_type_id' => 2, // ليس رئيسي
                ]);
    
                // ✂️ تعديل حالة الخدمات بعد اعتماد الرخصة
                BranchRenewalServices::where('branch_renewal_id', $renewal->id)
                    ->update(['status' => 1]); // مثال: إيقاف الخدمات القديمة
            }
    
            // تسجيل Log
            BranchRenewalLog::create([
                'user_id' => auth('api')->user()->id,
                'branch_renewal_id' => $renewal->id,
                'department_id' => auth('api')->user()->department_id,
                'details' => 'إصدار رخصة / تنفيذ الإجراء',
            ]);
    
            return response()->json([
                'status' => 200,
                'message' => 'تم إصدار الرخصة وتنفيذ الإجراء بنجاح',
                'type' => 'approval',
                'data' => [],
            ], 200);
    
        } catch (\Exception $e) {
    
            return response()->json([
                'status' => 500,
                'message' => 'حدث خطأ غير متوقع',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function subtractYear($id)
    {
        $branchRenewal = BranchRenewal::find($id);
    
        if (!$branchRenewal) {
            return response()->json([
                'status' => 404,
                'message' => 'الرقم غير موجود بالنظام'
            ], 404);
        }
    
        if ($branchRenewal->status_id != 6) {
            return response()->json([
                'status' => 422,
                'message' => 'لا يمكن تعديل هذه الرخصة'
            ], 422);
        }
    
        if (!$branchRenewal->start_date || !$branchRenewal->end_date) {
            return response()->json([
                'status' => 422,
                'message' => 'لا يمكن تعديل التاريخ لعدم وجود start_date أو end_date'
            ], 422);
        }
    
        // قبل التعديل
        $before = [
            'start_date' => $branchRenewal->start_date,
            'end_date'   => $branchRenewal->end_date,
        ];
    
        // التعديل
        $branchRenewal->update([
            'start_date' => Carbon::parse($branchRenewal->start_date)->subYear()->format('Y-m-d'),
            'end_date'   => Carbon::parse($branchRenewal->end_date)->subYear()->format('Y-m-d'),
        ]);
    
        // بعد التعديل
        $after = [
            'start_date' => $branchRenewal->start_date,
            'end_date'   => $branchRenewal->end_date,
        ];
    
        return response()->json([
            'status'  => 200,
            'message' => 'تم تقليل سنة من مدة الترخيص',
            'data' => [
                'branch_renewal_id' => $branchRenewal->id,
                'customer_name'     => $branchRenewal->customer_name ?? null,
                'customer_identity' => $branchRenewal->customer_identity_number ?? null,
            ],
            'before' => $before,
            'after'  => $after,
        ]);
    }
    
    public function approve_pulck_finance(Request $request)
    {
        try {
    
            $request->validate([
                'ids'   => 'required|array|min:1',
                'ids.*' => 'required|integer|exists:branch_renewals,id',
            ]);
    
            DB::transaction(function () use ($request) {
    
                // تحديث التجديدات
                BranchRenewal::whereIn('id', $request->ids)
                    ->update([
                        'is_matched' => 1,
                    ]);
    
                // تحديث المدفوعات المرتبطة
                BranchRenewalFinancePayment::whereIn('branch_renewal_id', $request->ids)
                    ->update([
                        'status' => 1,
                    ]);
            });
    
            return response()->json([
                'message' => 'تم اعتماد العناصر المحددة بنجاح',
                'status'  => 200,
                'type'    => 'approval',
                'data'    => [],
            ], 200);
    
        } catch (ValidationException $e) {
    
            $errors = collect($e->errors())->map(fn($messages) => $messages[0]);
    
            return response()->json([
                'message' => $errors,
                'status'  => 422,
                'data'    => [],
            ], 422);
        }
    }

    
    public function refuse_pulck_finance(Request $request)
    {
         try {
    
            $request->validate([
                'ids'   => 'required|array|min:1',
                'ids.*' => 'required|integer|exists:branch_renewals,id',
            ]);
    
            DB::transaction(function () use ($request) {
    
                // تحديث التجديدات
              
    
                // تحديث المدفوعات المرتبطة
                BranchRenewalFinancePayment::whereIn('branch_renewal_id', $request->ids)
                    ->update([
                        'status' => 2,
                    ]);
            });
    
            return response()->json([
                'message' => 'تم اعتماد العناصر المحددة بنجاح',
                'status'  => 200,
                'type'    => 'approval',
                'data'    => [],
            ], 200);
    
        } catch (ValidationException $e) {
    
            $errors = collect($e->errors())->map(fn($messages) => $messages[0]);
    
            return response()->json([
                'message' => $errors,
                'status'  => 422,
                'data'    => [],
            ], 422);
        }
    }
    
    
    public function approve_finance(Request $request, $id)
    {
        DB::transaction(function () use ($id) {
    
            $renewal = BranchRenewal::findOrFail($id);
    
            $renewal->update([
                'is_matched' => 1,
            ]);
    
            $finance = BranchRenewalFinancePayment::where('branch_renewal_id', $renewal->id)
                ->where('status', 0) // اختياري لو عايز تعتمد بس pending
                ->first();
    
            if ($finance) {
                $finance->update([
                    'status' => 1,
                   
                ]);
            }
        });
    
        return response()->json([
            'message' => 'تم الاعتماد بنجاح',
            'status'  => 200,
            'type'    => 'approval',
            'data'    => [],
        ], 200);
    }

    
    
    public function refuse_finance(Request $request, $id)
    {
        DB::transaction(function () use ($id, $request) {
    
            $renewal = BranchRenewal::findOrFail($id);
    
            $finance = BranchRenewalFinancePayment::where('branch_renewal_id', $renewal->id)
                ->where('status', 0) // بس اللي لسه pending
                ->first();
    
            if ($finance) {
                $finance->update([
                    'status' => 2, 
                 
                ]);
            }
    
        
           
        });
    
        return response()->json([
            'message' => 'تم الرفض بنجاح',
            'status'  => 200,
            'type'    => 'refused',
            'data'    => [],
        ], 200);
    }


    public function finance_update(Request $request, $id)
    {
        try {
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
            ];
    
            $request->validate([
                'payment_type_id' => 'required',
    
                'payment_receipt_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'check_image'           => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'supply_voucher_image'  => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'extra_image'           => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
    
                'bank_id'      => 'nullable',
                'check_number' => 'nullable',
                'check_value'  => 'nullable',
                'payment_receipt_number' => 'nullable',
                'value'       => 'nullable',
            ], $messages);
    
            $renewal = BranchRenewal::findOrFail($id);
    
            // جلب سجل الدفع المالي الحالي
            $finance = BranchRenewalFinancePayment::where('branch_renewal_id', $renewal->id)
                         ->where('user_id', auth('api')->user()->id)
                         ->first();
    
            // لو مش موجود، نقدر نعمل جديد
            if (!$finance) {
                $finance = new BranchRenewalFinancePayment();
                $finance->branch_renewal_id = $renewal->id;
                $finance->user_id = auth('api')->user()->id;
            }
    
            // رفع الصور
            foreach (['payment_receipt_image', 'check_image', 'supply_voucher_image', 'extra_image'] as $field) {
                if ($file = $request->file($field)) {
                    $filename = time() . $file->getClientOriginalName();
                    $file->move('finance', $filename);
                    $finance->{$field} = $filename;
                }
            }
    
            // تحديث باقي الحقول
            $finance->payment_receipt_number = $request->payment_receipt_number;
            $finance->payment_type_id = $request->payment_type_id;
            $finance->bank_id = $request->bank_id;
            $finance->check_number = $request->check_number;
            $finance->check_value = $request->check_value;
            $finance->value = $request->value;
            $finance->status = 0;
            $finance->save();
    
            // تسجيل اللوج
            $log = new BranchRenewalLog();
            $log->user_id = auth('api')->user()->id;
            $log->branch_renewal_id = $renewal->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "تم تحديث التحصيل من المالية";
            $log->save();
    
            return response()->json([
                'message' => 'تم التحديث بنجاح',
                'status' => 200,
                'type' => 'update',
                'data' => [],
            ], 200);
    
        } catch (ValidationException $e) {
            $errors = collect($e->errors())->map(fn($messages) => $messages[0]);
            return response()->json([
                'message' => $errors,
                'status' => 422,
                'data' => [],
            ], 422);
        }
    }

    

    public function supply_print_finance()
    {
        $renewals = BranchRenewal::with('finance','financePayments')
            ->where('is_matched', 1)
            ->where('is_printed', 0)
            ->whereHas('finance')
            ->latest()
            ->get();
    
        $result = [];
    
        $categories = $renewals
            ->pluck('category_id')
            ->map(fn($v) => (int)$v)
            ->unique();
    
        // ✅ نفس التبويبات بتاعتك بدون حذف أي حاجة
        $tabConfig = [
            1 => [
                'services' => [
                    ['code' =>'1-5-4-4', 'name' => 'رسوم رخص الاعمال الفنية', 'type' => 'محلي'],
                ],
                'penalties' => [
                    ['code' => '3-3-2-1', 'name' => 'غرامات و مصادر محلية', 'type' => 'محلي'],
                ],
                'ads' => [
                    ['code' => '1-5-4-10', 'name' => 'رسوم الدعايا والاعلان', 'type' =>'محلي'],
                ],
            ],
            2 => [
                'services' => [
                    ['code' =>'1-5-4-11', 'name' => 'رسوم رخص الاعمال الفنية', 'type' => 'محلي'],
                ],
                'penalties' => [
                    ['code' => '3-3-2-1', 'name' => 'غرامات و مصادر محلية', 'type' => 'محلي'],
                ],
                'ads' => [
                    ['code' => '1-5-4-10', 'name' => 'رسوم الدعايا والاعلان', 'type' =>'محلي'],
                ],
            ],
            3 => [
                'services' => [
                    ['code' =>'1-5-4-4', 'name' => 'رسوم تراخيص مزاولة المهن المختلفة', 'type' => 'محلي'],
                ],
                'penalties' => [
                    ['code' => '3-3-2-1', 'name' => 'الغرامات والمصادرات المشتركة الاخرى', 'type' => 'محلي'],
                ],
                'ads' => [
                    ['code' => '1-5-4-10', 'name' => 'رسوم الدعايا والاعلان', 'type' =>'محلي'],
                ],
            ],
            4 => [
                'services' => [
                    ['code' =>'1-5-4-4', 'name' => 'رسوم تراخيص مزاولة المهن المختلفة', 'type' => 'مشترك'],
                ],
                'penalties' => [
                    ['code' => '3-3-2-4', 'name' => 'الغرامات والمصادرات المشتركة الاخرى', 'type' => 'محلي'],
                ],
                'ads' => [
                    ['code' => '1-5-4-10', 'name' => 'رسوم الدعايا والاعلان', 'type' =>'محلي'],
                ],
            ],
        ];
    
        foreach ($categories as $catId) {
    
            $categoryRenewals = $renewals->where('category_id', $catId);
    
            // ✅ جمع كل نوع لوحده
            $total_services = $categoryRenewals->sum(fn($r) => $r->finance->rsom ?? 0) + $categoryRenewals->sum(fn($r) => $r->finance->rsomfat7at ?? 0) + $categoryRenewals->sum(fn($r) => $r->finance->rsom3waek ?? 0);
            $total_penalty  = $categoryRenewals->sum(fn($r) => $r->finance->rsom8rama ?? 0);
            $total_ads      = $categoryRenewals->sum(fn($r) => $r->finance->d3ayawe3lan ?? 0);
    
            $grand_total = $total_services + $total_penalty + $total_ads;
    
            if ($grand_total <= 0) continue;
    
            // ✅ 5% و 95%
            $fivePercent  = round($grand_total * 0.05, 2);
            $ninetyFive   = round($grand_total * 0.95, 2);
    
            $five_10 = round($fivePercent * 0.10, 2);
            $five_15 = round($fivePercent * 0.15, 2);
            $five_75 = round($fivePercent * 0.75, 2);
    
            // ✅ بيانات عامة
            $paymentNumbers = $categoryRenewals
            ->flatMap(function ($renewal) {
                return $renewal->financePayments
                    ->pluck('payment_receipt_number');
            })
            ->filter()
            ->unique()
            ->implode(' - ');
    
            $directorate_name = $categoryRenewals
                ->pluck('directorate_name')
                ->unique()
                ->implode(' - ');
    
            $created_at_list = $categoryRenewals
                ->pluck('created_at')
                ->map(fn($dt) => $dt->format('Y-m-d'))
                ->unique()
                ->implode(' - ');
    
            $ids_list = $categoryRenewals->pluck('id')->implode(' - ');
            $category_name = $categoryRenewals->pluck('category_name')->unique()->implode(' - ');
    
            $details = [];
    
            // ✅ تفاصيل 5%
            $details[] = $this->detailRow($directorate_name,$created_at_list,$ids_list,$category_name,$paymentNumbers,$five_10,"");
            $details[] = $this->detailRow($directorate_name,$created_at_list,$ids_list,$category_name,$paymentNumbers,$five_15,"");
            $details[] = $this->detailRow($directorate_name,$created_at_list,$ids_list,$category_name,$paymentNumbers,$five_75,"");
    
            // ✅ توزيع 95% بنسبة كل بند
            $ratio_services = $total_services / $grand_total;
            $ratio_penalty  = $total_penalty  / $grand_total;
            $ratio_ads      = $total_ads      / $grand_total;
    
            $services_after = round($ninetyFive * $ratio_services, 2);
            $penalty_after  = round($ninetyFive * $ratio_penalty, 2);
            $ads_after      = round($ninetyFive * $ratio_ads, 2);
    
            foreach ($tabConfig[$catId]['services'] ?? [] as $tab) {
                if ($services_after > 0) {
                    $details[] = $this->detailRow($directorate_name,$created_at_list,$ids_list,$category_name,$paymentNumbers,$services_after,$tab['code'],$tab['type'],$tab['name']);
                }
            }
    
            foreach ($tabConfig[$catId]['penalties'] ?? [] as $tab) {
                if ($penalty_after > 0) {
                    $details[] = $this->detailRow($directorate_name,$created_at_list,$ids_list,$category_name,$paymentNumbers,$penalty_after,$tab['code'],$tab['type'],$tab['name']);
                }
            }
    
            foreach ($tabConfig[$catId]['ads'] ?? [] as $tab) {
                if ($ads_after > 0) {
                    $details[] = $this->detailRow($directorate_name,$created_at_list,$ids_list,$category_name,$paymentNumbers,$ads_after,$tab['code'],$tab['type'],$tab['name']);
                }
            }
    
            $result[] = [
                'directorate_name'       => $directorate_name,
                'amount'                 => round($grand_total,2),
                'currency_name'          => "ريال يمني",
                'created_at'             => $created_at_list,
                'auto_number'            => $ids_list,
                'office'                 => $category_name,
                'payment_receipt_number' => $paymentNumbers,
                'money'                  => "سند سداد نقدية المديرية",
                'approve_status'         => "لا",
                'bank'                   => "بنك",
                'details'                => $details,
            ];
        }
    
        return response()->json([
            'status'=>true,
            'data'=>$result
        ]);
    }

    
    private function detailRow( $directorate,$date,$ids,$office,$receipt,$amount,
        $code="",$type="",$code_name="" )
   
    {
        return [
            'directorate_name'=>$directorate,
            'amount'=>round($amount,2),
            'currency_name'=>"ريال يمني",
            'created_at'=>$date,
            'auto_number'=>$ids,
            'office'=>$office,
            'payment_receipt_number'=>$receipt,
            'money'=>"سند سداد نقدية المديرية",
            'approve_status'=>"لا",
            'bank'=>"بنك",
            'code'=>$code,
            'type'=>$type,
            'code_name'=>$code_name,
            
        ];
    }



    public function supply_print_finance_by_ids(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:branch_renewals,id'
        ]);
    
        BranchRenewal::whereIn('id', $request->ids)
            ->where('is_printed', 0)
            ->update([
                'is_printed' => 1,
                'printed_at' => now(),
            ]);
    
        return response()->json([
            'status' => 200,
            'message' => 'تمت الطباعة بنجاح',
        ]);
    }


    public function liquidation_of_custody()
    {
        
        $renewals = BranchRenewal::with('finance','financePayments')
            ->where('is_printed', 1)
            ->whereDate('printed_at', Carbon::today())
            ->whereHas('finance')

            ->latest()
            ->get();
                
        $categories = $renewals
            ->pluck('category_id')
            ->map(fn($v)=>(int)$v)
            ->unique();
    
        $tabConfig = [
            1 => [
                'services' => [
                    ['code' =>'1-5-4-4', 'name' => 'رسوم رخص الاعمال الفنية', 'type' => 'محلي'],
                ],
                'penalties' => [
                    ['code' => '3-3-2-1', 'name' => 'غرامات و مصادر محلية', 'type' => 'محلي'],
                ],
                'ads' => [
                    ['code' => '1-5-4-10', 'name' => 'رسوم الدعايا والاعلان', 'type' =>'محلي'],
                ],
            ],
            2 => [
                'services' => [
                    ['code' =>'1-5-4-11', 'name' => 'رسوم رخص الاعمال الفنية', 'type' => 'محلي'],
                ],
                'penalties' => [
                    ['code' => '3-3-2-1', 'name' => 'غرامات و مصادر محلية', 'type' => 'محلي'],
                ],
                'ads' => [
                    ['code' => '1-5-4-10', 'name' => 'رسوم الدعايا والاعلان', 'type' =>'محلي'],
                ],
            ],
            3 => [
                'services' => [
                    ['code' =>'1-5-4-4', 'name' => 'رسوم تراخيص مزاولة المهن المختلفة', 'type' => 'محلي'],
                ],
                'penalties' => [
                    ['code' => '3-3-2-1', 'name' => 'الغرامات والمصادرات المشتركة الاخرى', 'type' => 'محلي'],
                ],
                'ads' => [
                    ['code' => '1-5-4-10', 'name' => 'رسوم الدعايا والاعلان', 'type' =>'محلي'],
                ],
            ],
            4 => [
                'services' => [
                    ['code' =>'1-5-4-4', 'name' => 'رسوم تراخيص مزاولة المهن المختلفة', 'type' => 'مشترك'],
                ],
                'penalties' => [
                    ['code' => '3-3-2-4', 'name' => 'الغرامات والمصادرات المشتركة الاخرى', 'type' => 'محلي'],
                ],
                'ads' => [
                    ['code' => '1-5-4-10', 'name' => 'رسوم الدعايا والاعلان', 'type' =>'محلي'],
                ],
            ],
        ];
    
        $result = [];
    
        foreach ($categories as $catId) {
    
            $categoryRenewals = $renewals->where('category_id', $catId);
    
            $total_services =
                  $categoryRenewals->sum(fn($r) => $r->finance->rsom ?? 0)
                + $categoryRenewals->sum(fn($r) => $r->finance->rsomfat7at ?? 0)
                + $categoryRenewals->sum(fn($r) => $r->finance->rsom3waek ?? 0);
    
            $total_penalty = $categoryRenewals->sum(fn($r) => $r->finance->rsom8rama ?? 0);
            $total_ads     = $categoryRenewals->sum(fn($r) => $r->finance->d3ayawe3lan ?? 0);
    
            $grand_total = $total_services + $total_penalty + $total_ads;
    
            if ($grand_total <= 0) continue;
    
            $paymentNumbers = $categoryRenewals
                ->flatMap(fn($r)=>$r->financePayments->pluck('payment_receipt_number'))
                ->filter()
                ->unique()
                ->implode(' - ');
    
            $directorate_name = $categoryRenewals->pluck('directorate_name')->unique()->implode(' - ');
            $created_at_list  = $categoryRenewals->pluck('created_at')->map(fn($d)=>$d->format('Y-m-d'))->unique()->implode(' - ');
            $ids_list         = $categoryRenewals->pluck('id')->implode(' - ');
            $category_name    = $categoryRenewals->pluck('category_name')->unique()->implode(' - ');
    
            $details = [];
    
            /*
            |--------------------------------------------------------------------------
            | 5%
            |--------------------------------------------------------------------------
            */
    
            $fivePercent  = round($grand_total * 0.05, 2);
    
            $details[] = $this->detailRow($directorate_name,$created_at_list,$ids_list,$category_name,$paymentNumbers,round($fivePercent*0.10,2));
            $details[] = $this->detailRow($directorate_name,$created_at_list,$ids_list,$category_name,$paymentNumbers,round($fivePercent*0.15,2));
            $details[] = $this->detailRow($directorate_name,$created_at_list,$ids_list,$category_name,$paymentNumbers,round($fivePercent*0.75,2));
    
            /*
            |--------------------------------------------------------------------------
            | 95%
            |--------------------------------------------------------------------------
            */
    
            $ninetyFive = round($grand_total * 0.95, 2);
    
            $ratio_services = $total_services / $grand_total;
            $ratio_penalty  = $total_penalty  / $grand_total;
            $ratio_ads      = $total_ads      / $grand_total;
    
            $services_after = round($ninetyFive * $ratio_services, 2);
            $penalty_after  = round($ninetyFive * $ratio_penalty, 2);
            $ads_after      = round($ninetyFive * $ratio_ads, 2);
    
            foreach ($tabConfig[$catId]['services'] ?? [] as $tab) {
                if ($services_after > 0) {
                    $details[] = $this->detailRow(
                        $directorate_name,
                        $created_at_list,
                        $ids_list,
                        $category_name,
                        $paymentNumbers,
                        $services_after,
                        $tab['code'],
                        $tab['type'],
                        $tab['name']
                        
                    );
                }
            }
    
            foreach ($tabConfig[$catId]['penalties'] ?? [] as $tab) {
                if ($penalty_after > 0) {
                    $details[] = $this->detailRow(
                        $directorate_name,
                        $created_at_list,
                        $ids_list,
                        $category_name,
                        $paymentNumbers,
                        $penalty_after,
                        $tab['code'],
                        $tab['type'],
                        $tab['name']
                    );
                }
            }
    
            foreach ($tabConfig[$catId]['ads'] ?? [] as $tab) {
                if ($ads_after > 0) {
                    $details[] = $this->detailRow(
                        $directorate_name,
                        $created_at_list,
                        $ids_list,
                        $category_name,
                        $paymentNumbers,
                        $ads_after,
                        $tab['code'],
                        $tab['type'],
                        $tab['name']
                    );
                }
            }
    
            $result = array_merge($result, $details);
        }
    
        return response()->json([
            'status'=>true,
            'data'=>$result
        ]);
    }


    public function add_liquidation_bank(Request $request)
    {
            $request->validate([
                'auto_number' => 'required|string',
                'recipient_name' => 'required|string',
                'notification_date' => 'required|date',
                'bank_receipt_image' => 'nullable|file|mimes:jpg,png,pdf|max:2048',
            ]);
    
            $imagePath = null;
            if ($request->hasFile('bank_receipt_image')) {
                $imagePath = $request->file('bank_receipt_image')->store('bank_receipts', 'public');
            }
    
            $record = LiquidationBank::create([
                'auto_number' => $request->auto_number,
                'recipient_name' => $request->recipient_name,
                'notification_date' => $request->notification_date,
                'bank_receipt_image' => $imagePath,
                'type' => $request->type,
            ]);
    
            return response()->json([
                'status' => true,
                'message' => 'تم حفظ بيانات تصفية سند البنك بنجاح ✅',
                'data' => $record,
            ]);
        }



    public function approve_pulck_nzafa(Request $request)
    {
        try {
    
            // ✅ Validation
            $request->validate([
                'ids'   => 'required|array|min:1',
                'ids.*' => 'required|integer|exists:branch_renewals,id',
            ]);
                DB::transaction(function () use ($request) {
        
                    // تحديث التجديدات
                    BranchRenewal::whereIn('id', $request->ids)
                        ->update([
                            'is_matched_nzafa' => 1,
                        ]);
        
                    // تحديث المدفوعات المرتبطة
                    BranchRenewalNzafaPayment::whereIn('branch_renewal_id', $request->ids)
                        ->update([
                            'status' => 1,
                        ]);
                });
    
    
            return response()->json([
                'message' => 'تم اعتماد العناصر المحددة بنجاح',
                'status'  => 200,
                'type'    => 'approval',
                'data'    => [],
            ], 200);
    
        } catch (ValidationException $e) {
    
            $errors = collect($e->errors())->map(fn($messages) => $messages[0]);
    
            return response()->json([
                'message' => $errors,
                'status'  => 422,
                'data'    => [],
            ], 422);
        }
    }
    
    public function refuse_pulck_nzafa(Request $request)
    {
        try {
    
            // ✅ Validation
            $request->validate([
                'ids'   => 'required|array|min:1',
                'ids.*' => 'required|integer|exists:branch_renewals,id',
            ]);
    
            DB::transaction(function () use ($request) {
    
                // تحديث التجديدات
              
    
                // تحديث المدفوعات المرتبطة
                BranchRenewalNzafaPayment::whereIn('branch_renewal_id', $request->ids)
                    ->update([
                        'status' => 2,
                    ]);
            });

            return response()->json([
                'message' => 'تم رفض العناصر المحددة بنجاح',
                'status'  => 200,
                'type'    => 'refused',
                'data'    => [],
            ], 200);
    
        } catch (ValidationException $e) {
    
            $errors = collect($e->errors())->map(fn($messages) => $messages[0]);
    
            return response()->json([
                'message' => $errors,
                'status'  => 422,
                'data'    => [],
            ], 422);
        }
    }
    
    
    public function approve_nzafa(Request $request, $id)
    {
        $DB::transaction(function () use ($id) {
    
            $renewal = BranchRenewal::findOrFail($id);
    
            $renewal->update([
                'is_matched_nzafa' => 1,
            ]);
    
            $nzafa = BranchRenewalNzafaPayment::where('branch_renewal_id', $renewal->id)
                ->where('status', 0) // اختياري لو عايز تعتمد بس pending
                ->first();
    
            if ($nzafa) {
                $nzafa->update([
                    'status' => 1,
                   
                ]);
            }
        });
    
        return response()->json([
            'message' => 'تم الاعتماد',
            'status'  => 200,
            'type'    => 'refused',
            'data'    => [],
        ], 200);
    }
    
    public function refuse_nzafa(Request $request, $id)
    {
        DB::transaction(function () use ($id, $request) {
    
            $renewal = BranchRenewal::findOrFail($id);
    
            $finance = BranchRenewalNzafaPayment::where('branch_renewal_id', $renewal->id)
                ->where('status', 0) // بس اللي لسه pending
                ->first();
    
            if ($finance) {
                $finance->update([
                    'status' => 2, 
                 
                ]);
            }
    
        
           
        });
    
        return response()->json([
            'message' => 'تم الرفض بنجاح',
            'status'  => 200,
            'type'    => 'refused',
            'data'    => [],
        ], 200);
    }
    
    public function update_nzafa(Request $request, $id)
    {
        try {
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
            ];
    
            $request->validate([
                'payment_type_id' => 'required',
    
                'payment_receipt_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'check_image'           => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'supply_voucher_image'  => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'extra_image'           => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
    
                'bank_id'      => 'nullable',
                'check_number' => 'nullable',
                
                'payment_receipt_number' => 'nullable',
                'value'       => 'nullable',
            ], $messages);
    
            $renewal = BranchRenewal::findOrFail($id);
    
            // جلب سجل الدفع المالي الحالي
            $nzafa = BranchRenewalNzafaPayment::where('branch_renewal_id', $renewal->id)
                         ->where('user_id', auth('api')->user()->id)
                         ->first();
    
            // لو مش موجود، نقدر نعمل جديد
            if (!$nzafa) {
                $nzafa = new BranchRenewalNzafaPayment();
                $nzafa->branch_renewal_id = $renewal->id;
                $nzafa->user_id = auth('api')->user()->id;
            }
    
            // رفع الصور
            foreach (['payment_receipt_image', 'check_image', 'supply_voucher_image', 'extra_image'] as $field) {
                if ($file = $request->file($field)) {
                    $filename = time() . $file->getClientOriginalName();
                    $file->move('nzafa', $filename);
                    $finance->{$field} = $filename;
                }
            }
    
            // تحديث باقي الحقول
            $nzafa->payment_receipt_number = $request->payment_receipt_number;
            $nzafa->payment_type_id = $request->payment_type_id;
            $nzafa->bank_id = $request->bank_id;
            $nzafa->check_number = $request->check_number;
            $nzafa->check_value = $request->check_value;
            $nzafa->value = $request->value;
            $nzafa->status = 0;
            $nzafa->save();
    
            // تسجيل اللوج
            $log = new BranchRenewalLog();
            $log->user_id = auth('api')->user()->id;
            $log->branch_renewal_id = $renewal->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "تم تحديث التحصيل من النظافة";
            $log->save();
    
            return response()->json([
                'message' => 'تم التحديث بنجاح',
                'status' => 200,
                'type' => 'update',
                'data' => [],
            ], 200);
    
        } catch (ValidationException $e) {
            $errors = collect($e->errors())->map(fn($messages) => $messages[0]);
            return response()->json([
                'message' => $errors,
                'status' => 422,
                'data' => [],
            ], 422);
        }
    }

    public function supply_print_nzafa()
    {
        $renewals = BranchRenewal::where('is_matched_nzafa', 1)
            ->where('is_printed_nzafa', 0)
            ->latest()
            ->get();
    
        $result = [];
        $categories = $renewals->pluck('category_id')->unique();
    
        foreach ($categories as $catId) {
    
            $category_renewals = $renewals->where('category_id', $catId);
    
            // =========================
            // إجمالي النظافة فقط
            // =========================
            $total_nzafa = 0;
    
            // أرقام السداد
            $paymentNumbers = $category_renewals
                ->pluck('payment_receipt_number_nzafa')
                ->filter()
                ->unique()
                ->implode(' - ');
    
            foreach ($category_renewals as $data) {
    
                $brserv = BranchRenewalServices::where('branch_renewal_id', $data->id)
                    ->pluck('service_id')
                    ->toArray();
    
                // -------- حساب النظافة فقط --------
                if (in_array($data->service_type_id, [62, 63])) {
                    $total_nzafa += ServicePrice::whereIn('service_id', $brserv)
                        ->where('service_type_id', 69)
                        ->sum('price');
                } else {
                    $total_nzafa += ServicePrice::whereIn('service_id', $brserv)
                        ->where('service_type_id', 70)
                        ->sum('price');
                }
            }
    
            // =========================
            // بيانات مشتركة
            // =========================
            $directorate_name = $category_renewals
                ->pluck('directorate_name')
                ->unique()
                ->implode(' - ');
    
            $created_at_list = $category_renewals
                ->pluck('created_at')
                ->map(fn ($dt) => Carbon::parse($dt)->format('Y-m-d'))
                ->unique()
                ->implode(' - ');
    
            $ids_list = $category_renewals
                ->pluck('id')
                ->implode(' - ');
    
            $category_name = $category_renewals
                ->pluck('category_name')
                ->unique()
                ->implode(' - ');
    
            // =========================
            // التفاصيل (نظافة فقط)
            // =========================
            $details = [];
    
            if ($total_nzafa > 0) {
                $details[] = [
                    'directorate_name'       => $directorate_name,
                    'amount'                 => $total_nzafa,
                    'currency_name'          => "ريال يمني",
                    'created_at'             => $created_at_list,
                    'auto_number'            => $ids_list,
                    'office'                 => $category_name,
                    'payment_receipt_number' => $paymentNumbers,
                    'money'                  => "سند سداد نقدية المديرية",
                    'approve_status'         => "لا",
                    'bank'                   => "بنك",
                    'code'                   => '6-1-1-1',
                    'type'                   => 'نظافة',
                    'code_name'              => 'صندوق النظافة',
                ];
            }
    
            // =========================
            // السطر النهائي لكل فئة
            // =========================
            $result[] = [
                'directorate_name'       => $directorate_name,
                'amount'                 => $total_nzafa,
                'currency_name'          => "ريال يمني",
                'created_at'             => $created_at_list,
                'auto_number'            => $ids_list,
                'office'                 => $category_name,
                'payment_receipt_number' => $paymentNumbers,
                'money'                  => "سند سداد نقدية المديرية",
                'approve_status'         => "لا",
                'bank'                   => "بنك",
                'code'                   => '6-1-1-1',
                'type'                   => 'نظافة',
                'details'                => $details,
            ];
        }
    
        return response()->json([
            'status' => true,
            'data'   => $result,
        ]);
    }


    public function supply_print_nzafa_by_ids(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:branch_renewals,id'
        ]);
    
        BranchRenewal::whereIn('id', $request->ids)
            ->where('is_printed_nzafa', 0)
            ->update([
                'is_printed_nzafa' => 1,
                'printed_at_nzafa' => now(),
            ]);
    
        return response()->json([
            'status' => 200,
            'message' => 'تمت الطباعة بنجاح',
        ]);
    }


    public function liquidation_of_custody_nzafa()
    {
        $renewals = BranchRenewal::where('is_printed_nzafa', 1)
            ->whereDate('printed_at_nzafa', Carbon::today())
            ->latest()
            ->get();
    
        $result = [];
        $categories = $renewals->pluck('category_id')->unique();
    
        foreach ($categories as $catId) {
    
            $category_renewals = $renewals->where('category_id', $catId);
    
            // =========================
            // إجمالي النظافة فقط
            // =========================
            $total_nzafa = 0;
    
            // أرقام السداد
            $paymentNumbers = $category_renewals
                ->pluck('payment_receipt_number_nzafa')
                ->filter()
                ->unique()
                ->implode(' - ');
    
            foreach ($category_renewals as $data) {
    
                $brserv = BranchRenewalServices::where('branch_renewal_id', $data->id)
                    ->pluck('service_id')
                    ->toArray();
    
                // -------- حساب النظافة فقط --------
                if (in_array($data->service_type_id, [62, 63])) {
                    $total_nzafa += ServicePrice::whereIn('service_id', $brserv)
                        ->where('service_type_id', 69)
                        ->sum('price');
                } else {
                    $total_nzafa += ServicePrice::whereIn('service_id', $brserv)
                        ->where('service_type_id', 70)
                        ->sum('price');
                }
            }
    
            // =========================
            // بيانات مشتركة
            // =========================
            $directorate_name = $category_renewals
                ->pluck('directorate_name')
                ->unique()
                ->implode(' - ');
    
            $created_at_list = $category_renewals
                ->pluck('created_at')
                ->map(fn ($dt) => Carbon::parse($dt)->format('Y-m-d'))
                ->unique()
                ->implode(' - ');
    
            $ids_list = $category_renewals
                ->pluck('id')
                ->implode(' - ');
    
            $category_name = $category_renewals
                ->pluck('category_name')
                ->unique()
                ->implode(' - ');
    
            // =========================
            // التفاصيل (نظافة فقط)
            // =========================
            $details = [];
    
            if ($total_nzafa > 0) {
                $details[] = [
                    'directorate_name'       => $directorate_name,
                    'amount'                 => $total_nzafa,
                    'currency_name'          => "ريال يمني",
                    'created_at'             => $created_at_list,
                    'auto_number'            => $ids_list,
                    'office'                 => $category_name,
                    'payment_receipt_number' => $paymentNumbers,
                    'money'                  => "سند سداد نقدية المديرية",
                    'approve_status'         => "لا",
                    'bank'                   => "بنك",
                    'code'                   => '6-1-1-1',
                    'type'                   => 'نظافة',
                    'code_name'              => 'صندوق النظافة',
                ];
            }
    
            // =========================
            // السطر النهائي لكل فئة
            // =========================
            $result[] = [
                'directorate_name'       => $directorate_name,
                'amount'                 => $total_nzafa,
                'currency_name'          => "ريال يمني",
                'created_at'             => $created_at_list,
                'auto_number'            => $ids_list,
                'office'                 => $category_name,
                'payment_receipt_number' => $paymentNumbers,
                'money'                  => "سند سداد نقدية المديرية",
                'approve_status'         => "لا",
                'bank'                   => "بنك",
                'code'                   => '6-1-1-1',
                'type'                   => 'نظافة',
                'code_name'                   => 'صندوق النظافة',
                'details'                => $details,
                 
            ];
        }
    
        return response()->json([
            'status' => true,
            'data'   => $result,
        ]);
    }




}

