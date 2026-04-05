<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

use Str;



use App\Models\TaxType;
use App\Models\Customer;


use App\Models\License;
use App\Models\LicenseeBranch;

use App\Models\ZakahNumber;
use App\Models\ZakahType;
use Carbon\Carbon;
use App\Models\TaxBalanceBulinding;
use App\Models\CustomerZakahBalanceLog;
use App\Models\ZakahDeclaration;
use App\Models\CustomerZakahBalance;
use Illuminate\Validation\ValidationException;
use App\Models\CustomerZakahBalanceEmployee;
use App\Models\CustomerZakahBuilding;
class ZakahApiController extends Controller
{
    
    private function calculateFees($item, $collection = null)
    {
        $fees = [];
        $total = 0;
    
        /* ✅ الحالة الأولى: مبلغ تحت الحساب */
        if ($item->zakah_declaration_id === null) {
    
            $amount = $item->value ?? 0;
    
            $fees = [
                ['key' => 'amrta7selt7tel7sab', 'label' => 'مبلغ تحت الحساب', 'value' => $amount],
                ['key' => 'elegmaly', 'label' => 'الاجمالي', 'value' => $amount],
            ];
    
            $total = $amount;
        }
    
        /* ✅ مباني */
        elseif ($item->zakah_type_id == 4 && $item->zakah_declaration_id == 7) {
    
            $khasm = $collection
                ? $collection->where('zakah_type_id', 4)->whereNull('zakah_declaration_id')->sum('value')
                : 0;
    
            $elzakah  = $item->buildings()->sum('zakat_value');
            $el8ramat = 0;
            $discount = $item->discount ?? 0;
    
            $total = $elzakah - ($khasm + $el8ramat + $discount);
    
            $fees = [
                ['key'=>'zakat','label'=>'الزكاة','value'=>$elzakah],
                ['key'=>'penalties','label'=>'الغرامات','value'=>$el8ramat],
                ['key'=>'discount','label'=>'الخصم','value'=>$discount],
                ['key'=>'advance_discount','label'=>'خصم تحت الحساب','value'=>$khasm],
                ['key'=>'total','label'=>'الاجمالي','value'=>$total],
            ];
        }
    
        /* ✅ موظفين */
        elseif ($item->zakah_type_id == 4 && $item->zakah_declaration_id == 10) {
    
            $khasm = $collection
                ? $collection->where('zakah_type_id', 4)->whereNull('zakah_declaration_id')->sum('value')
                : 0;
    
            $elzakah  = $item->employees()->sum('zakat_amount') * 0.025;
            $el8ramat = 0;
            $discount = $item->discount ?? 0;
    
            $total = $elzakah - ($khasm + $el8ramat + $discount);
    
            $fees = [
                ['key'=>'zakat','label'=>'الزكاة','value'=>$elzakah],
                ['key'=>'penalties','label'=>'الغرامات','value'=>$el8ramat],
                ['key'=>'discount','label'=>'الخصم','value'=>$discount],
                ['key'=>'advance_discount','label'=>'خصم تحت الحساب','value'=>$khasm],
                ['key'=>'total','label'=>'الاجمالي','value'=>$total],
            ];
        }
    
        /* ✅ أي إقرار زكوي */
        elseif ($item->zakah_declaration_id !== null) {

        // ✅ خصم تحت الحساب
        $khasm = $collection
            ? $collection->whereNull('zakah_declaration_id')->sum('value')
            : 0;
    
        // ✅ حساب الزكاة بأمان
        $revenue  = $item->total_revenue ?? 0;
        $expenses = $item->total_expenses ?? 0;
    
        $elzakah = $item->value ?? ($revenue - $expenses);
    
        // ✅ الغرامات والخصم
        $el8ramat = 0;
        $discount = $item->discount ?? 0;
    
        // ✅ الإجمالي (بدون سالب)
        $total = max(0, $elzakah - ($khasm + $el8ramat + $discount));
    
        $fees = [
            ['key'=>'zakat','label'=>'الزكاة','value'=>$elzakah],
            ['key'=>'penalties','label'=>'الغرامات','value'=>$el8ramat],
            ['key'=>'discount','label'=>'الخصم','value'=>$discount],
            ['key'=>'advance_discount','label'=>'خصم تحت الحساب','value'=>$khasm],
            ['key'=>'total','label'=>'الاجمالي','value'=>$total],
        ];
    }
    
        return [
            'fees'  => $fees,
            'total' => $total,
        ];
    }
    
   public function add_zakah_number(Request $request)
   {
       $item =  new ZakahNumber();
       $item->customer_id = $request->customer_id;
       $item->save();
       
        return response()->json([
                'message' => 'تم انشاء الطلب بنجاح',
                'status' => 200,
                'type' => 'add_zakah_number',
                
        ], 200);
   }
   
   public function zakah_types(Request $request)
   {
       $item =  ZakahType::get();
      
       
        return response()->json([
                'message' => 'تم ',
                'status' => 200,
                'data' => $item,
                
        ], 200);
   }
   
   public function add_zakah_balance(Request $request)
    {
        try {
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
                'after' => 'حقل :attribute يجب أن يكون بعد تاريخ البداية.',
                'numeric' => 'حقل :attribute يجب أن يكون رقم.',
                'year' => 'required|integer|min:' . date('Y'),
                
            ];
            
            $attributes = [
                'customer_id' => 'المكلف',
                'zakah_type_id' => 'نوع الزكاة',
            
                'ownership_image' => 'صورة عقد الملكية',
            
                'electric_image' => 'صورة فاتورة الكهرباء',
                'manual_image' => 'صورة امر التحصيل اليدوي'
                ,
            
                'value' => 'المبلغ',
                'year.min' => 'لا يمكن إدخال سنة قديمة، يجب أن تكون السنة الحالية أو أكبر.',
                'year.integer' => 'يجب ان يكون رقما',
            ];
            
            $request->validate([
                'customer_id' => 'required',
                'zakah_type_id' => 'required',
                'year' => 'required',
            
                'ownership_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
                'electric_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
                
                'other_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
               
                 'beneficiary_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            
                'value' => 'required',
                
            ], $messages, $attributes);


            $ownership_image = null;
            if ($file = $request->file('ownership_image')) {
                $ownership_image = time() . $file->getClientOriginalName();
                $file->move('zakah', $ownership_image);
            }
            
            
            $electric_image = null;
            if ($file = $request->file('electric_image')) {
                $electric_image = time() . $file->getClientOriginalName();
                $file->move('zakah', $electric_image);
            }
            
            $manual_image = null;
            if ($file = $request->file('manual_image')) {
                $manual_image = time() . $file->getClientOriginalName();
                $file->move('zakah', $manual_image);
            }
            
            
            $other_image = null;
            if ($file = $request->file('other_image')) {
                $other_image = time() . $file->getClientOriginalName();
                $file->move('zakah', $other_image);
            }
            
            $beneficiary_image = null;
            if ($file = $request->file('beneficiary_image')) {
                $beneficiary_image = time() . $file->getClientOriginalName();
                $file->move('zakah', $beneficiary_image);
            }
            
  
            $file = ZakahNumber::where('customer_id', $request->customer_id)->first();
            
            $item = new CustomerZakahBalance();
            $item->zakah_type_id = $request->zakah_type_id;
            $item->customer_id = $request->customer_id;
            $item->year = $request->year;
            $item->value = $request->value;
            $item->ownership_image = $ownership_image;
            $item->electric_image = $electric_image;
            $item->manual_image = $manual_image;
            $item->other_image = $other_image;
            $item->beneficiary_image = $beneficiary_image;
            $item->zakah_status_id = 1;
            $item->zakah_number_id = $file->id;
            $item->user_id = auth('api')->user()->id;
             $item->notes = $request->notes;
            $item->save();
            
            $log = new CustomerZakahBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_zakah_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "اضافة رصيد تحت الحساب";
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
           
            return response()->json([
                'message' => 'تم انشاء الطلب بنجاح',
                'status' => 200,
                'type' => 'add_balance',
                'data' => [],
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
    
    public function approve_zakah_balance(Request $request, $id)
    {
        try {
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
                'numeric' => 'حقل :attribute يجب أن يكون رقم.',
            ];
    
            $attributes = [
               
                'zakah_type_id' => 'نوع الزكاة',
                'value' => 'المبلغ',
                'year' => 'العام',
                'ownership_image' => 'صورة عقد الملكية',
                'electric_image' => 'صورة فاتورة الكهرباء',
                'manual_image' => 'صورة امر التحصيل اليدوي',
             
            ];
    
            // ✔️ validation بدون required للصور لأن التحديث ممكن لا يرفع صورة جديدة
            $request->validate([
               
                'zakah_type_id' => 'required',
                'year' => 'required',
               // 'value' => 'required',

    
                'ownership_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'electric_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'manual_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'other_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    
            ], $messages, $attributes);
    
            // ✔️ جلب السجل
            $item = CustomerZakahBalance::find($id);
    
            if (!$item) {
                return response()->json([
                    'message' => 'السجل غير موجود.',
                    'status'  => 404,
                ], 404);
            }
    
            // =============================================
            //               تحديث الصور
            // =============================================
    
            if ($file = $request->file('ownership_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('zakah', $filename);
                $item->ownership_image = $filename;
            }
    
            if ($file = $request->file('electric_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('zakah', $filename);
                $item->electric_image = $filename;
            }
    
            if ($file = $request->file('manual_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('zakah', $filename);
                $item->manual_image = $filename;
            }
    
            if ($file = $request->file('other_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('zakah', $filename);
                $item->other_image = $filename;
            }
    
            // =============================================
            //               تحديث البيانات
            // =============================================
    
            
            $item->zakah_type_id = $request->zakah_type_id;
            $item->year        = $request->year;
            $item->value       = $request->value;
             $item->zakah_status_id       = 2;
             $item->notes = $request->notes;
          
            $item->save();
            
            $log = new CustomerZakahBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_zakah_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "اعتماد رصيد تحت الحساب";
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
    
            return response()->json([
                'message' => 'تم موافقة الطلب.',
                'status' => 200,
                'data' => $item,
            ], 200);
    
        } catch (ValidationException $e) {
            $errors = collect($e->errors())->map(fn($messages) => $messages[0]);
    
            return response()->json([
                'message' => $errors,
                'status' => 422,
            ], 422);
        }
    }
    
     public function refuse_zakah_balance(Request $request, $id)
    {
        try {
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
                'numeric' => 'حقل :attribute يجب أن يكون رقم.',
            ];
    
            $attributes = [
               
                'notes' => 'الملاحظات',
                
            ];
    
            // ✔️ validation بدون required للصور لأن التحديث ممكن لا يرفع صورة جديدة
            $request->validate([
               
               
                'notes' => 'required',
    
           
    
            ], $messages, $attributes);
    
            // ✔️ جلب السجل
            $item = CustomerZakahBalance::find($id);
    
            if (!$item) {
                return response()->json([
                    'message' => 'السجل غير موجود.',
                    'status'  => 404,
                ], 404);
            }
    
            // =============================================
            //               تحديث الصور
            // =============================================
    
           
    
          
             $item->zakah_status_id = 3;
            $item->notes = $request->notes;
            $item->save();
            
            $log = new CustomerZakahBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_zakah_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "رفض اعتماد رصيد تحت الحساب";
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
    
            return response()->json([
                'message' => 'تم الرفض.',
                'status' => 200,
                'data' => $item,
            ], 200);
    
        } catch (ValidationException $e) {
            $errors = collect($e->errors())->map(fn($messages) => $messages[0]);
    
            return response()->json([
                'message' => $errors,
                'status' => 422,
            ], 422);
        }
    }
    
    public function update_zakah_balance(Request $request, $id)
    {
        try {
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
                'numeric' => 'حقل :attribute يجب أن يكون رقم.',
            ];
    
            $attributes = [
               
                'zakah_type_id' => 'نوع الضريبة',
                'value' => 'المبلغ',
                'year' => 'العام',
                'ownership_image' => 'صورة عقد الملكية',
                'electric_image' => 'صورة فاتورة الكهرباء',
                'manual_image' => 'صورة امر التحصيل اليدوي',
            ];
    
            // ✔️ validation بدون required للصور لأن التحديث ممكن لا يرفع صورة جديدة
            $request->validate([
                
                'zakah_type_id' => 'required',
                'year' => 'required',
               // 'value' => 'required',
    
                'ownership_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'electric_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'manual_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'other_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'beneficiary_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    
            ], $messages, $attributes);
    
            // ✔️ جلب السجل
            $item = CustomerZakahBalance::find($id);
    
            if (!$item) {
                return response()->json([
                    'message' => 'السجل غير موجود.',
                    'status'  => 404,
                ], 404);
            }
    
            // =============================================
            //               تحديث الصور
            // =============================================
    
            if ($file = $request->file('ownership_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('zakah', $filename);
                $item->ownership_image = $filename;
            }
    
            if ($file = $request->file('electric_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('zakah', $filename);
                $item->electric_image = $filename;
            }
    
            if ($file = $request->file('manual_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('zakah', $filename);
                $item->manual_image = $filename;
            }
    
            if ($file = $request->file('other_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('zakah', $filename);
                $item->other_image = $filename;
            }
            
            if ($file = $request->file('beneficiary_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('zakah', $filename);
                $item->beneficiary_image = $filename;
            }
    
            // =============================================
            //               تحديث البيانات
            // =============================================
            $oldData = $item->toArray();
           
            $item->zakah_type_id = $request->zakah_type_id;
            $item->year        = $request->year;
            $item->value       = $request->value;
            $item->zakah_status_id = 1;
            $item->save();
            
            $newData = $item->toArray();
            
             $changes = [];
            foreach ($newData as $key => $value) {
                if (isset($oldData[$key]) && $oldData[$key] != $value) {
                    $changes[$key] = [
                        'old' => $oldData[$key],
                        'new' => $value
                    ];
                }
            }
    
           $log = new CustomerZakahBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_zakah_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = json_encode($changes, JSON_UNESCAPED_UNICODE);
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
    
            return response()->json([
                'message' => 'تم تعديل رصيد المكلف بنجاح.',
                'status' => 200,
                'data' => $item,
            ], 200);
    
        } catch (ValidationException $e) {
            $errors = collect($e->errors())->map(fn($messages) => $messages[0]);
    
            return response()->json([
                'message' => $errors,
                'status' => 422,
            ], 422);
        }
    }
    
    public function approve_mehdar(Request $request, $id)
    {
        try {
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
                'numeric' => 'حقل :attribute يجب أن يكون رقم.',
            ];
    
            $attributes = [
               
                'zakah_type_id' => 'نوع الزكاة',
                'value' => 'المبلغ',
                'year' => 'العام',
                'ownership_image' => 'صورة عقد الملكية',
                'electric_image' => 'صورة فاتورة الكهرباء',
                'manual_image' => 'صورة امر التحصيل اليدوي',
             
            ];
    
            // ✔️ validation بدون required للصور لأن التحديث ممكن لا يرفع صورة جديدة
            $request->validate([
               
                'zakah_type_id' => 'required',
                'year' => 'required',
               // 'value' => 'required',

    
                'ownership_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'electric_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'manual_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'other_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    
            ], $messages, $attributes);
    
            // ✔️ جلب السجل
            $item = CustomerZakahBalance::find($id);
    
            if (!$item) {
                return response()->json([
                    'message' => 'السجل غير موجود.',
                    'status'  => 404,
                ], 404);
            }
    
            // =============================================
            //               تحديث الصور
            // =============================================
    
            if ($file = $request->file('ownership_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('zakah', $filename);
                $item->ownership_image = $filename;
            }
    
            if ($file = $request->file('electric_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('zakah', $filename);
                $item->electric_image = $filename;
            }
    
            if ($file = $request->file('manual_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('zakah', $filename);
                $item->manual_image = $filename;
            }
    
            if ($file = $request->file('other_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('zakah', $filename);
                $item->other_image = $filename;
            }
    
            // =============================================
            //               تحديث البيانات
            // =============================================
    
            
            $item->zakah_type_id = $request->zakah_type_id;
            $item->year        = $request->year;
            $item->value       = $request->value;
             $item->zakah_status_id       = 4;
             $item->notes = $request->notes;
          
            $item->save();
            
            $log = new CustomerZakahBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_zakah_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "اعتماد رصيد تحت الحساب";
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
    
            return response()->json([
                'message' => 'تم موافقة الطلب.',
                'status' => 200,
                'data' => $item,
            ], 200);
    
        } catch (ValidationException $e) {
            $errors = collect($e->errors())->map(fn($messages) => $messages[0]);
    
            return response()->json([
                'message' => $errors,
                'status' => 422,
            ], 422);
        }
    }
    
    public function refuse_mehdar(Request $request, $id)
    {
        try {
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
                'numeric' => 'حقل :attribute يجب أن يكون رقم.',
            ];
    
            $attributes = [
               
                'notes' => 'الملاحظات',
                
            ];
    
            // ✔️ validation بدون required للصور لأن التحديث ممكن لا يرفع صورة جديدة
            $request->validate([
               
               
                'notes' => 'required',
    
           
    
            ], $messages, $attributes);
    
            // ✔️ جلب السجل
            $item = CustomerZakahBalance::find($id);
    
            if (!$item) {
                return response()->json([
                    'message' => 'السجل غير موجود.',
                    'status'  => 404,
                ], 404);
            }
    
            // =============================================
            //               تحديث الصور
            // =============================================
    
           
    
          
             $item->zakah_status_id = 5;
            $item->notes = $request->notes;
            $item->save();
            
            $log = new CustomerZakahBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_zakah_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "رفض اعتماد رصيد تحت الحساب";
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
    
            return response()->json([
                'message' => 'تم الرفض.',
                'status' => 200,
                'data' => $item,
            ], 200);
    
        } catch (ValidationException $e) {
            $errors = collect($e->errors())->map(fn($messages) => $messages[0]);
    
            return response()->json([
                'message' => $errors,
                'status' => 422,
            ], 422);
        }
    }
    
    public function finance_accept_zakaha_balance(Request $request, $id)
    {
        try {
                $messages = [
                    'required' => 'حقل :attribute مطلوب.',
                    'image' => 'حقل :attribute يجب أن يكون صورة.',
                    'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                    'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
                    'numeric' => 'حقل :attribute يجب أن يكون رقم.',
                ];
        
                $attributes = [
                   
                    'payment_receipt_number' => 'رقم ايصال الدفع',
                    'payment_receipt_image' => 'صورة ايصال الدفع',
                   
                 
                ];
        
                // ✔️ validation بدون required للصور لأن التحديث ممكن لا يرفع صورة جديدة
                $request->validate([
                   
                   // 'payment_receipt_number' => 'required',
                  
                 
                 'payment_type_id' => 'required',
            
                'payment_receipt_image' => 'required_if:payment_type_id,1|image|mimes:jpg,jpeg,png,webp|max:2048',
                    'bank_id'      => 'required_if:payment_type_id,2',
                'check_number' => 'required_if:payment_type_id,2',
                'check_value' => 'required_if:payment_type_id,2',
                'check_image'  => 'required_if:payment_type_id,2|image|mimes:jpg,jpeg,png,webp|max:2048',
                'supply_voucher_image'  => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
    
        
                    
                  
        
                ], $messages, $attributes);
                
            
            
            // ✔️ جلب السجل
            $item = CustomerZakahBalance::find($id);
            
            if ($file = $request->file('payment_receipt_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('zakah', $filename);
                $item->payment_receipt_image = $filename;
            }
            
            if ($file = $request->file('supply_voucher_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('zakah', $filename);
                $item->supply_voucher_image = $filename;
            }
            
            if ($file = $request->file('extra_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('zakah', $filename);
                $item->extra_image = $filename;
            }
             
             
            if ($file = $request->file('check_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('zakah', $filename);
                $item->check_image = $filename;
            }
    
            if (!$item) {
                return response()->json([
                    'message' => 'السجل غير موجود.',
                    'status'  => 404,
                ], 404);
            }
    
            $item->payment_receipt_number = $request->payment_receipt_number;
            
             $item->zakah_status_id       = 8;
             $item->payment_type_id = $request->payment_type_id;
            $item->bank_id = $request->bank_id;
            $item->check_number = $request->check_number;
            $item->check_value = $request->check_value;
         
              
            $item->payment_date = $request->payment_date;
           
            $item->payment_value = $request->payment_value;
            $item->save();
            
            $log = new CustomerZakahBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_zakah_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "تم تحصيل رصيد تحت الحساب";
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
    
            return response()->json([
                'message' => 'تم.',
                'status' => 200,
                'data' => $item,
            ], 200);
    
        } catch (ValidationException $e) {
            $errors = collect($e->errors())->map(fn($messages) => $messages[0]);
    
            return response()->json([
                'message' => $errors,
                'status' => 422,
            ], 422);
        }
    }
    
    public function filter_by_zakah_number_id($id)
    {
        $data = CustomerZakahBalance::where('zakah_number_id',$id)->paginate(8);
        
        return response()->json([
                'message' => 'الرقم الزكوي',
                'status' => 200,
                
                'data' => $data,
            ], 200);
        
    }
    
    public function zakah_reviewer_table(Request $request)
    {
        $query = CustomerZakahBalance::with('customer')
            ->where('zakah_status_id', 1);
    
        if (
            $request->filled('name') ||
            $request->filled('zakah_number_id') ||
            $request->filled('id') ||
            $request->filled('identity_number')
        ) {
    
            $query->where(function ($q) use ($request) {
    
                if ($request->filled('zakah_number_id')) {
                    $q->orWhere('zakah_number_id', $request->zakah_number_id);
                }
    
                if ($request->filled('id')) {
                    $q->orWhere('id', $request->id);
                }
    
                if ($request->filled('name') || $request->filled('identity_number')) {
    
                    $q->orWhereHas('customer', function ($q2) use ($request) {
    
                        if ($request->filled('name')) {
                            $q2->where('name', 'LIKE', "%{$request->name}%");
                        }
    
                        // ✅ مطابق تمامًا
                        if ($request->filled('identity_number')) {
                            $q2->where('identity_number', $request->identity_number);
                        }
                    });
                }
            });
        }
    
        $data = $query->latest()->paginate(8);
    
        return response()->json([
            'message' => 'تم',
            'status'  => 200,
            'data'    => $data,
        ]);
    }
    
    public function zakah_reviewer_mehdar_table(Request $request)
    {
        $query = CustomerZakahBalance::with('customer')
            ->where('zakah_status_id', 2);
    
        if (
            $request->filled('name') ||
            $request->filled('zakah_number_id') ||
            $request->filled('id') ||
            $request->filled('identity_number')
        ) {
    
            $query->where(function ($q) use ($request) {
    
                if ($request->filled('zakah_number_id')) {
                    $q->orWhere('zakah_number_id', $request->zakah_number_id);
                }
    
                if ($request->filled('id')) {
                    $q->orWhere('id', $request->id);
                }
    
                if ($request->filled('name') || $request->filled('identity_number')) {
    
                    $q->orWhereHas('customer', function ($q2) use ($request) {
    
                        if ($request->filled('name')) {
                            $q2->where('name', 'LIKE', "%{$request->name}%");
                        }
    
                        // ✅ مطابق تمامًا
                        if ($request->filled('identity_number')) {
                            $q2->where('identity_number', $request->identity_number);
                        }
                    });
                }
            });
        }
    
        $data = $query->latest()->paginate(8);
    
        return response()->json([
            'message' => 'تم',
            'status'  => 200,
            'data'    => $data,
        ]);
    }
    
    public function zakah_finance_table(Request $request)
    {
        
         $query = CustomerZakahBalance::whereIn('zakah_status_id', [6,9])->whereNotNull('zakah_declaration_id')
    
       ->join('customers', 'customers.id', '=', 'customer_zakah_balance.customer_id')
            ->select('customer_zakah_balance.*')
    
            ->when($request->id, function ($q, $v) {
                $q->where('customer_zakah_balance.id', $v);
            })
    
            ->when($request->customer_name, function ($q, $v) {
                $q->where('customers.name', 'like', "%{$v}%");
            })
    
            ->when($request->customer_identity_number, function ($q, $v) {
                $q->where('customers.identity_number', 'like', "%{$v}%");
            })
            
            ->when($request->zakah_type_id, function ($q, $v) {
                $q->where('customer_zakah_balance.zakah_type_id', $v);
            })
    
            ->when($request->from, function ($q, $v) {
                $q->whereDate('customer_zakah_balance.created_at', '>=', $v);
            })
    
            ->when($request->to, function ($q, $v) {
                $q->whereDate('customer_zakah_balance.created_at', '<=', $v);
            })
    
            ->latest('customer_zakah_balance.created_at');
    
        $data = $query->paginate(8);
        
        $data->getCollection()->transform(function ($item) {
            return [
                'id'               => $item->id,
                'directorate_name' => $item->directorate_name,  // accessor
                'payment_type_id' => $item->payment_type_id,
                'customer_name'    => $item->customer_name,     // accessor
                'status_name'      => $item->status_name,       // accessor
                'user_id'  => $item->user_id,
                'zakah_type_name' => $item->zakah_type_name,
                'declaration_name' => $item->declaration_name,
                'zakah_status_id' => $item->zakah_status_id,
                'zakah_type_name' => $item->zakah_type_name,
           
            ];
        });
    
        return response()->json([
            'message' => 'تم',
            'status'  => 200,
            'data'    => $data,
        ]);
   
    }
    
  
   
    public function zakah_table(Request $request)
    {
        $query = CustomerZakahBalance::where('zakah_status_id', '!=', 7);
    
        if ($request->filled('zakah_status_id')) {
            $query->where('zakah_status_id', $request->zakah_status_id);
        }
        
        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }
    
        $data = $query->paginate(8);
        $collection = $data->getCollection();
        $collection->transform(function ($item) use ($collection) {
    
            $item->fees = [];
    
            /* ✅ الحالة الأولى: مبلغ تحت الحساب */
            if ($item->zakah_declaration_id === null) {
    
                $amrta7selt7tel7sab = $item->value;
    
                $item->fees = [
                    [
                        'key'   => 'amrta7selt7tel7sab',
                        'label' => 'مبلغ تحت الحساب',
                        'value' => $amrta7selt7tel7sab,
                    ],
                    [
                        'key'   => 'elegmaly',
                        'label' => 'الاجمالي',
                        'value' => $amrta7selt7tel7sab,
                    ],
                ];
            }elseif($item->zakah_type_id ==4 AND $item->zakah_declaration_id ==7){
                $khasm = $collection
                ? $collection->where('zakah_type_id', 4)->whereNull('zakah_declaration_id')->sum('value')
                : 0;
    
                $elzakah = $item->buildings()->sum('zakat_value');
                $el8ramat = 0;
                $discount = $item->discount ?? 0;
                $item->fees = [
                    ['key'=>'eldreba','label'=>'الزكاة','value'=>$elzakah],
                    ['key'=>'eldreba','label'=>'الغرامات','value'  =>$el8ramat],
                    ['key'=>'el8ramat','label'=>'الخصم','value'=>$discount
                    ],
                    ['key'=>'khasmta7selt7tel7sab','label'=>'خصم تحت الحساب','value'=>$khasm],
                    [
                        'key'=>'elegmaly',
                        'label'=>'الاجمالي',
                        'value'=>$elzakah - ($khasm + $el8ramat + $discount)
                    ],
                ];
            }elseif($item->zakah_type_id ==4 AND $item->zakah_declaration_id ==10){
                $khasm = $collection
                ? $collection->where('zakah_type_id', 4)->whereNull('zakah_declaration_id')->sum('value')
                : 0;
    
                $elzakah = $item->employees()->sum('zakat_amount') * 0.025;

                $el8ramat = 0;
                $discount = $item->discount ?? 0;
                $item->fees = [
                    ['key'=>'eldreba','label'=>'الزكاة','value'=>$elzakah],
                    ['key'=>'eldreba','label'=>'الغرامات','value'  =>$el8ramat],
                    ['key'=>'el8ramat','label'=>'الخصم','value'=>$discount
                    ],
                    ['key'=>'khasmta7selt7tel7sab','label'=>'خصم تحت الحساب','value'=>$khasm],
                    [
                        'key'=>'elegmaly',
                        'label'=>'الاجمالي',
                        'value'=>$elzakah - ($khasm + $el8ramat + $discount)
                    ],
                ];
            }elseif ($item->zakah_declaration_id !== null) {

                $khasm = $collection
                    ->whereNull('zakah_declaration_id')
                    ->sum('value');
            
                $elzakah  = $item->value ?? 0;
                $el8ramat = 0;
                $discount = $item->discount ?? 0;
            
                $item->fees = [
                    ['key'=>'zakat','label'=>'الزكاة','value'=>$elzakah],
                    ['key'=>'penalties','label'=>'الغرامات','value'=>$el8ramat],
                    ['key'=>'discount','label'=>'الخصم','value'=>$discount],
                    ['key'=>'advance_discount','label'=>'خصم تحت الحساب','value'=>$khasm],
                    [
                        'key'=>'total',
                        'label'=>'الاجمالي',
                        'value'=>$elzakah - ($khasm + $el8ramat + $discount)
                    ],
                ];
            }

    
            
            return $item;
        });
       
    
        return response()->json([
            'message' => 'تم',
            'status'  => 200,
            'data'    => $data
        ], 200);
    }
    
    public function add_zakah(Request $request)
    {
        try {
    
            // =========================
            // Validation كامل
            // =========================
            $request->validate([
                'customer_id'   => 'required|exists:customers,id',
                'zakah_type_id' => 'required|exists:zakah_types,id',
                'year'          => 'required|integer',
                'zakah_declaration_id' => 'required|exists:zakah_declarations,id',
            ]);
    
            // =========================
            // Upload helper
            // =========================
            $upload = function ($key) use ($request) {
                if ($request->hasFile($key)) {
                    $file = $request->file($key);
                    $name = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $name = preg_replace('/[^A-Za-z0-9\-_\.]/', '_', $name);
                    $file->move(public_path('zakah'), $name);
                    return $name;
                }
                return null;
            };
    
            // =========================
            // Get zakah number
            // =========================
            $zakahNumber = ZakahNumber::where('customer_id', $request->customer_id)->first();
    
            if (!$zakahNumber) {
                return response()->json([
                    'status'  => 422,
                    'message' => 'لا يوجد رقم زكاة لهذا العميل',
                ], 422);
            }
    
            // =========================
            // Create zakah balance
            // =========================
            $item = CustomerZakahBalance::create([
                'zakah_number_id' => $zakahNumber->id,
                'zakah_type_id'   => $request->zakah_type_id,
                'customer_id'     => $request->customer_id,
                'year'            => $request->year,
    
                'declaration_number' => $request->declaration_number,
                'declaration_date'   => $request->declaration_date,
                'zakat_base'         => $request->zakat_base,
                'discount'           => $request->discount,
                'total_revenue'      => $request->total_revenue,
                'total_expenses'     => $request->total_expenses,
                'account_nature'     => $request->account_nature,
                'zakat_discount_percentage' => $request->zakat_discount_percentage,
    
                // Images
                'ownership_image'                        => $upload('ownership_image'),
                'electric_image'                         => $upload('electric_image'),
                'manual_image'                           => $upload('manual_image'),
                'other_image'                            => $upload('other_image'),
                'beneficiary_image'                      => $upload('beneficiary_image'),
                'payment_receipt_image'                  => $upload('payment_receipt_image'),
                'advance_payment_statement_image'        => $upload('advance_payment_statement_image'),
                'annual_zakat_declaration_image'         => $upload('annual_zakat_declaration_image'),
                'last_payment_receipt_image'             => $upload('last_payment_receipt_image'),
                'central_audit_authority_approval_image' => $upload('central_audit_authority_approval_image'),
                
                'detailed_income_statement_image' => $upload('detailed_income_statement_image'),
                'detailed_trial_balance_image' => $upload('detailed_trial_balance_image'),
                'detailed_final_accounts_report_image' => $upload('detailed_final_accounts_report_image'),
                
                'salaries_and_entitlements_report_image' => $upload('salaries_and_entitlements_report_image'),
                'cash_and_inventory_statement_image' => $upload('cash_and_inventory_statement_image'),
                'creditors_report_image' => $upload('creditors_report_image'),
                'debtors_report_image' => $upload('debtors_report_image'),
                
                
                'rental_owner_contract_image' => $upload('rental_owner_contract_image'),
                'detailed_revenue_report_image' => $upload('detailed_revenue_report_image'),
                'detailed_expenses_report_image' => $upload('detailed_expenses_report_image'),
                'clients_and_contracts_report_image' => $upload('clients_and_contracts_report_image'),
                'income_data_image' => $upload('income_data_image'),
                
                'unit_sale_price'                => $request->unit_sale_price,
                'total_sale_value'                => $request->total_sale_value,
                'original_property_value'                => $request->original_property_value,
                'sale_contract_image' => $upload('sale_contract_image'),
                'prev_rental_contract_image' => $upload('prev_rental_contract_image'),
                'rent_exclusion_image' => $upload('rent_exclusion_image'),
                'dependents_report_image' => $upload('dependents_report_image'),
    
                // Payment
                'zakah_percentage' => $request->zakah_percentage,
                'check_number'     => $request->check_number,
                'payment_value'    => $request->payment_value,
                'payment_type_id'  => $request->payment_type_id,
                'bank_id'          => $request->bank_id,
                'payment_date'     => $request->payment_date,
    
                'zakah_declaration_id' => $request->zakah_declaration_id,
                'notes'                => $request->notes,
    
                'zakah_status_id' => 1,
                'user_id'         => auth('api')->id(),
            ]);
            
            if ($request->employees && is_array($request->employees)) {

                foreach ($request->employees as $emp) {
                    $item->employees()->create([
                        'name'  => $emp['name'] ?? null,
                        'dependents'           => $emp['dependents'] ?? null,
                        'zakat_amount'    => $emp['zakat_amount'] ?? null,
                        
                    ]);
                }
            }
            
            if ($request->buildings && is_array($request->buildings)) {

                foreach ($request->buildings as $index => $build) {
                    $electricBillPath = null;
                    if ($request->hasFile("buildings.$index.electric_bill")) {
                        $electricBillPath = $request
                            ->file("buildings.$index.electric_bill")
                            ->store('buildings/electric_bills', 'public');
                    }
            
                    // رفع صورة سبب الاستبعاد
                    $excludeNotePath = null;
                    if ($request->hasFile("buildings.$index.exclude_note")) {
                        $excludeNotePath = $request
                            ->file("buildings.$index.exclude_note")
                            ->store('buildings/exclude_notes', 'public');
                    }
                    $item->buildings()->create([
                        'actual_months'    => $build['actual_months'] ?? null,
                        'address'    => $build['address'] ?? null,
                        'building_type'    => $build['building_type'] ?? null,
                        'currency'    => $build['currency'] ?? null,
                        'electric_bill'    => $electricBillPath,
                        'electric_meter'    => $build['electric_meter'] ?? null,
                        'end_date'    => $build['end_date'] ?? null,
                        'exclude_note'    =>$excludeNotePath,
                        'excluded_months'    => $build['excluded_months'] ?? null,
                        'lang'    => $build['lang'] ?? null,
                        'lat'    => $build['lat'] ?? null,
                        'monthly_rent'    => $build['monthly_rent'] ?? null,
                        'rent_contract'    => $build['rent_contract'] ?? null,
                        'start_date'    => $build['start_date'] ?? null,
                        'tenant_name'    => $build['tenant_name'] ?? null,
                        'unit_type'    => $build['unit_type'] ?? null,
                        'usage'    => $build['usage'] ?? null,
                        'zakat_value'    => $build['zakat_value'] ?? null,
                       
                    ]);
                }
            }
    
            // =========================
            // Log
            // =========================
            CustomerZakahBalanceLog::create([
                'user_id'                   => auth('api')->id(),
                'customer_zakah_balance_id' => $item->id,
                'department_id'             => auth('api')->user()->department_id,
                'details'                   => 'إضافة',
                'notes'                     => $item->notes,
            ]);
    
            // =========================
            // Clear notes after logging
            // =========================
            $item->update(['notes' => null]);
    
            // =========================
            // Response
            // =========================
            return response()->json([
                'status'  => 200,
                'message' => 'تم إنشاء الطلب بنجاح',
                'data'    => $item,
            ]);
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation Error',
                'errors' => $e->errors(),
            ], 422);
        }
    }




    
    public function update_zakah(Request $request, $id)
    {
        $item = CustomerZakahBalance::findOrFail($id);
    
        // =========================
        // Validation
        // =========================
        $request->validate([
            'zakah_type_id' => 'required|exists:zakah_types,id',
            'year'          => 'required|integer',
            'declaration_number' => 'nullable|string',
            'declaration_date'   => 'nullable|date',
            'zakat_base'         => 'nullable|numeric',
            'discount'           => 'nullable|numeric',
            'total_revenue'      => 'nullable|numeric',
            'total_expenses'     => 'nullable|numeric',
            'account_nature'     => 'nullable|string',
            'zakat_discount_percentage' => 'nullable|numeric',
            'ownership_image'    => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'electric_image'     => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'manual_image'       => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'other_image'        => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'beneficiary_image'  => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'payment_receipt_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'advance_payment_statement_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'annual_zakat_declaration_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'last_payment_receipt_image'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'central_audit_authority_approval_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'detailed_income_statement_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'detailed_trial_balance_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'detailed_final_accounts_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'salaries_and_entitlements_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'cash_and_inventory_statement_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'creditors_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'debtors_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'rental_owner_contract_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'detailed_revenue_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'detailed_expenses_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'clients_and_contracts_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'income_data_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'sale_contract_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'prev_rental_contract_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'rent_exclusion_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'dependents_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'notes'             => 'nullable|string',
        ]);
    
    
        // =========================
        // Upload helper
        // =========================
        $upload = function ($key) use ($request, $item) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                $name = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $name = preg_replace('/[^A-Za-z0-9\-_\.]/', '_', $name);
                $file->move(public_path('zakah'), $name);
                return $name;
            }
            return $item->$key; // لو مفيش صورة جديدة يبقى خلي القديمة
        };
    
        // =========================
        // Update data
        // =========================
        $item->update([
            'zakah_type_id' => $request->zakah_type_id,
            'year'          => $request->year,
            'declaration_number' => $request->declaration_number ?? $item->declaration_number,
            'declaration_date'   => $request->declaration_date ?? $item->declaration_date,
            'zakat_base'         => $request->zakat_base ?? $item->zakat_base,
            'discount'           => $request->discount ?? $item->discount,
            'total_revenue'      => $request->total_revenue ?? $item->total_revenue,
            'total_expenses'     => $request->total_expenses ?? $item->total_expenses,
            'account_nature'     => $request->account_nature ?? $item->account_nature,
            'zakat_discount_percentage' => $request->zakat_discount_percentage ?? $item->zakat_discount_percentage,
    
            // Images
            'ownership_image'                        => $upload('ownership_image'),
            'electric_image'                         => $upload('electric_image'),
            'manual_image'                           => $upload('manual_image'),
            'other_image'                            => $upload('other_image'),
            'beneficiary_image'                      => $upload('beneficiary_image'),
            'payment_receipt_image'                  => $upload('payment_receipt_image'),
            'advance_payment_statement_image'        => $upload('advance_payment_statement_image'),
            'annual_zakat_declaration_image'         => $upload('annual_zakat_declaration_image'),
            'last_payment_receipt_image'             => $upload('last_payment_receipt_image'),
            'central_audit_authority_approval_image' => $upload('central_audit_authority_approval_image'),
            'detailed_income_statement_image' => $upload('detailed_income_statement_image'),
            'detailed_trial_balance_image' => $upload('detailed_trial_balance_image'),
            'detailed_final_accounts_report_image' => $upload('detailed_final_accounts_report_image'),
            'salaries_and_entitlements_report_image' => $upload('salaries_and_entitlements_report_image'),
            'cash_and_inventory_statement_image' => $upload('cash_and_inventory_statement_image'),
            'creditors_report_image' => $upload('creditors_report_image'),
            'debtors_report_image' => $upload('debtors_report_image'),
            'rental_owner_contract_image' => $upload('rental_owner_contract_image'),
                'detailed_revenue_report_image' => $upload('detailed_revenue_report_image'),
                'detailed_expenses_report_image' => $upload('detailed_expenses_report_image'),
                'clients_and_contracts_report_image' => $upload('clients_and_contracts_report_image'),
                'income_data_image' => $upload('income_data_image'),
            'zakah_status_id' => 1,
            'notes' => $request->notes,
            'unit_sale_price'                => $request->unit_sale_price,
                'total_sale_value'                => $request->total_sale_value,
                'original_property_value'                => $request->original_property_value,
                'sale_contract_image' => $upload('sale_contract_image'),
                'prev_rental_contract_image' => $upload('prev_rental_contract_image'),
                'rent_exclusion_image' => $upload('rent_exclusion_image'),
                'dependents_report_image' => $upload('dependents_report_image'),
        ]);
    
         if ($request->employees && is_array($request->employees)) {
    
                // ❗ حذف الموظفين القديمين
                $item->employees()->delete();
    
                // إضافة الموظفين الجدد
                foreach ($request->employees as $emp) {
                    $item->employees()->create([
                       'name'  => $emp['name'] ?? null,
                        'dependents'           => $emp['dependents'] ?? null,
                        'zakat_amount'    => $emp['zakat_amount'] ?? null,
                       
                    ]);
                }
            }
            
            if ($request->buildings && is_array($request->buildings)) {
                $item->buildings()->delete();
                foreach ($request->buildings as $index => $build) {
                    $electricBillPath = null;
                    if ($request->hasFile("buildings.$index.electric_bill")) {
                        $electricBillPath = $request
                            ->file("buildings.$index.electric_bill")
                            ->store('buildings/electric_bills', 'public');
                    }
            
                    // رفع صورة سبب الاستبعاد
                    $excludeNotePath = null;
                    if ($request->hasFile("buildings.$index.exclude_note")) {
                        $excludeNotePath = $request
                            ->file("buildings.$index.exclude_note")
                            ->store('buildings/exclude_notes', 'public');
                    }
                    $item->buildings()->create([
                        'actual_months'    => $build['actual_months'] ?? null,
                        'address'    => $build['address'] ?? null,
                        'building_type'    => $build['building_type'] ?? null,
                        'currency'    => $build['currency'] ?? null,
                        'electric_bill'    => $electricBillPath,
                        'electric_meter'    => $build['electric_meter'] ?? null,
                        'end_date'    => $build['end_date'] ?? null,
                        'exclude_note'    =>$excludeNotePath,
                        'excluded_months'    => $build['excluded_months'] ?? null,
                        'lang'    => $build['lang'] ?? null,
                        'lat'    => $build['lat'] ?? null,
                        'monthly_rent'    => $build['monthly_rent'] ?? null,
                        'rent_contract'    => $build['rent_contract'] ?? null,
                        'start_date'    => $build['start_date'] ?? null,
                        'tenant_name'    => $build['tenant_name'] ?? null,
                        'unit_type'    => $build['unit_type'] ?? null,
                        'usage'    => $build['usage'] ?? null,
                        'zakat_value'    => $build['zakat_value'] ?? null,
                       
                    ]);
                }
            }
        // =========================
        // Log
        // =========================
        CustomerZakahBalanceLog::create([
            'user_id'                   => auth('api')->id(),
            'customer_zakah_balance_id' => $item->id,
            'department_id'             => auth('api')->user()->department_id,
            'details'                   => 'تعديل',
            'notes'                     => $request->notes,
        ]);
    
        // =========================
        // Clear notes
        // =========================
        $item->update(['notes' => null]);
    
        return response()->json([
            'status'  => 200,
            'message' => 'تم تعديل الطلب بنجاح',
            'data'    => $item,
        ]);
    }

    
    public function approve_zakah(Request $request,$id)
    {
         $item = CustomerZakahBalance::findOrFail($id);
        $request->validate([
            'zakah_type_id' => 'required|exists:zakah_types,id',
            'year'          => 'required|integer',
            'declaration_number' => 'nullable|string',
            'declaration_date'   => 'nullable|date',
            'zakat_base'         => 'nullable|numeric',
            'discount'           => 'nullable|numeric',
            'total_revenue'      => 'nullable|numeric',
            'total_expenses'     => 'nullable|numeric',
            'account_nature'     => 'nullable|string',
            'zakat_discount_percentage' => 'nullable|numeric',
            'ownership_image'    => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'electric_image'     => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'manual_image'       => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'other_image'        => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'beneficiary_image'  => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'payment_receipt_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'advance_payment_statement_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'annual_zakat_declaration_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'last_payment_receipt_image'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'central_audit_authority_approval_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'detailed_income_statement_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'detailed_trial_balance_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'detailed_final_accounts_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            
            'salaries_and_entitlements_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'cash_and_inventory_statement_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'creditors_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'debtors_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            
            'rental_owner_contract_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'detailed_revenue_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'detailed_expenses_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'clients_and_contracts_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'income_data_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            
            'sale_contract_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'prev_rental_contract_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'rent_exclusion_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'dependents_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'notes'             => 'nullable|string',
        ]);
        
        $upload = function ($key) use ($request, $item) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                $name = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $name = preg_replace('/[^A-Za-z0-9\-_\.]/', '_', $name);
                $file->move(public_path('zakah'), $name);
                return $name;
            }
            return $item->$key; // لو مفيش صورة جديدة يبقى خلي القديمة
        };
        
        $item->update([
            'zakah_type_id' => $request->zakah_type_id,
            'year'          => $request->year,
            'declaration_number' => $request->declaration_number ?? $item->declaration_number,
            'declaration_date'   => $request->declaration_date ?? $item->declaration_date,
            'zakat_base'         => $request->zakat_base ?? $item->zakat_base,
            'discount'           => $request->discount ?? $item->discount,
            'total_revenue'      => $request->total_revenue ?? $item->total_revenue,
            'total_expenses'     => $request->total_expenses ?? $item->total_expenses,
            'account_nature'     => $request->account_nature ?? $item->account_nature,
            'zakat_discount_percentage' => $request->zakat_discount_percentage ?? $item->zakat_discount_percentage,
    
            // Images
            'ownership_image'                        => $upload('ownership_image'),
            'electric_image'                         => $upload('electric_image'),
            'manual_image'                           => $upload('manual_image'),
            'other_image'                            => $upload('other_image'),
            'beneficiary_image'                      => $upload('beneficiary_image'),
            'payment_receipt_image'                  => $upload('payment_receipt_image'),
            'advance_payment_statement_image'        => $upload('advance_payment_statement_image'),
            'annual_zakat_declaration_image'         => $upload('annual_zakat_declaration_image'),
            'last_payment_receipt_image'             => $upload('last_payment_receipt_image'),
            'central_audit_authority_approval_image' => $upload('central_audit_authority_approval_image'),
            'detailed_income_statement_image' => $upload('detailed_income_statement_image'),
            'detailed_trial_balance_image' => $upload('detailed_trial_balance_image'),
            'detailed_final_accounts_report_image' => $upload('detailed_final_accounts_report_image'),
            'salaries_and_entitlements_report_image' => $upload('salaries_and_entitlements_report_image'),
            'cash_and_inventory_statement_image' => $upload('cash_and_inventory_statement_image'),
            'creditors_report_image' => $upload('creditors_report_image'),
            'debtors_report_image' => $upload('debtors_report_image'),
            'rental_owner_contract_image' => $upload('rental_owner_contract_image'),
                'detailed_revenue_report_image' => $upload('detailed_revenue_report_image'),
                'detailed_expenses_report_image' => $upload('detailed_expenses_report_image'),
                'clients_and_contracts_report_image' => $upload('clients_and_contracts_report_image'),
                'income_data_image' => $upload('income_data_image'),
            'zakah_status_id' => 2,
            'notes' => $request->notes,
            'unit_sale_price'                => $request->unit_sale_price,
                'total_sale_value'                => $request->total_sale_value,
                'original_property_value'                => $request->original_property_value,
                'sale_contract_image' => $upload('sale_contract_image'),
                'prev_rental_contract_image' => $upload('prev_rental_contract_image'),
                'rent_exclusion_image' => $upload('rent_exclusion_image'),
                'dependents_report_image' => $upload('dependents_report_image'),
        ]);
        
        
        if ($request->employees && is_array($request->employees)) {
    
                // ❗ حذف الموظفين القديمين
                $item->employees()->delete();
    
                // إضافة الموظفين الجدد
                foreach ($request->employees as $emp) {
                    $item->employees()->create([
                       'name'  => $emp['name'] ?? null,
                        'dependents'           => $emp['dependents'] ?? null,
                        'zakat_amount'    => $emp['zakat_amount'] ?? null,
                       
                    ]);
                }
        }
        
         if ($request->buildings && is_array($request->buildings)) {
                $item->buildings()->delete();
                foreach ($request->buildings as $index => $build) {
                    $electricBillPath = null;
                    if ($request->hasFile("buildings.$index.electric_bill")) {
                        $electricBillPath = $request
                            ->file("buildings.$index.electric_bill")
                            ->store('buildings/electric_bills', 'public');
                    }
            
                    // رفع صورة سبب الاستبعاد
                    $excludeNotePath = null;
                    if ($request->hasFile("buildings.$index.exclude_note")) {
                        $excludeNotePath = $request
                            ->file("buildings.$index.exclude_note")
                            ->store('buildings/exclude_notes', 'public');
                    }
                    $item->buildings()->create([
                        'actual_months'    => $build['actual_months'] ?? null,
                        'address'    => $build['address'] ?? null,
                        'building_type'    => $build['building_type'] ?? null,
                        'currency'    => $build['currency'] ?? null,
                        'electric_bill'    => $electricBillPath,
                        'electric_meter'    => $build['electric_meter'] ?? null,
                        'end_date'    => $build['end_date'] ?? null,
                        'exclude_note'    =>$excludeNotePath,
                        'excluded_months'    => $build['excluded_months'] ?? null,
                        'lang'    => $build['lang'] ?? null,
                        'lat'    => $build['lat'] ?? null,
                        'monthly_rent'    => $build['monthly_rent'] ?? null,
                        'rent_contract'    => $build['rent_contract'] ?? null,
                        'start_date'    => $build['start_date'] ?? null,
                        'tenant_name'    => $build['tenant_name'] ?? null,
                        'unit_type'    => $build['unit_type'] ?? null,
                        'usage'    => $build['usage'] ?? null,
                        'zakat_value'    => $build['zakat_value'] ?? null,
                       
                    ]);
                }
            }
            
        CustomerZakahBalanceLog::create([
            'user_id' => auth('api')->id(),
            'customer_zakah_balance_id' => $item->id,
            'department_id' => auth('api')->user()->department_id,
            'details' => 'اعتماد',
        ]);
        $item->update(['notes' => null]);
        return response()->json([
                'message' => 'تم',
                'status' => 200,
        ]);
    
    }
    
    public function refuse_zakah(Request $request, $id)
    {
        $request->validate(['notes' => 'required']);
    
        $item = CustomerZakahBalance::findOrFail($id);
    
        $item->update([
            'zakah_status_id' => 3,
            'notes' => $request->notes
        ]);
    
        CustomerZakahBalanceLog::create([
            'user_id' => auth('api')->id(),
            'customer_zakah_balance_id' => $item->id,
            'department_id' => auth('api')->user()->department_id,
            'details' => 'رفض',
            'notes' => $request->notes,
        ]);
    
         $item->update(['notes' => null]);
        return response()->json([
                'message' => 'تم',
                'status' => 200,
        ]);
    }
    
    
    public function approve_mehdar_zakah(Request $request,$id)
    {
         $item = CustomerZakahBalance::findOrFail($id);
        $request->validate([
            'zakah_type_id' => 'required|exists:zakah_types,id',
            'year'          => 'required|integer',
            'declaration_number' => 'nullable|string',
            'declaration_date'   => 'nullable|date',
            'zakat_base'         => 'nullable|numeric',
            'discount'           => 'nullable|numeric',
            'total_revenue'      => 'nullable|numeric',
            'total_expenses'     => 'nullable|numeric',
            'account_nature'     => 'nullable|string',
            'zakat_discount_percentage' => 'nullable|numeric',
            'ownership_image'    => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'electric_image'     => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'manual_image'       => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'other_image'        => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'beneficiary_image'  => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'payment_receipt_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'advance_payment_statement_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'annual_zakat_declaration_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'last_payment_receipt_image'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'central_audit_authority_approval_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'detailed_income_statement_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'detailed_trial_balance_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'detailed_final_accounts_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            
            'salaries_and_entitlements_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'cash_and_inventory_statement_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'creditors_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'debtors_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            
            'rental_owner_contract_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'detailed_revenue_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'detailed_expenses_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'clients_and_contracts_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'income_data_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            
            'sale_contract_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'prev_rental_contract_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'rent_exclusion_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'dependents_report_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'notes'             => 'nullable|string',
        ]);
        
        $upload = function ($key) use ($request, $item) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                $name = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $name = preg_replace('/[^A-Za-z0-9\-_\.]/', '_', $name);
                $file->move(public_path('zakah'), $name);
                return $name;
            }
            return $item->$key; // لو مفيش صورة جديدة يبقى خلي القديمة
        };
        
        $item->update([
            'zakah_type_id' => $request->zakah_type_id,
            'year'          => $request->year,
            'declaration_number' => $request->declaration_number ?? $item->declaration_number,
            'declaration_date'   => $request->declaration_date ?? $item->declaration_date,
            'zakat_base'         => $request->zakat_base ?? $item->zakat_base,
            'discount'           => $request->discount ?? $item->discount,
            'total_revenue'      => $request->total_revenue ?? $item->total_revenue,
            'total_expenses'     => $request->total_expenses ?? $item->total_expenses,
            'account_nature'     => $request->account_nature ?? $item->account_nature,
            'zakat_discount_percentage' => $request->zakat_discount_percentage ?? $item->zakat_discount_percentage,
    
            // Images
            'ownership_image'                        => $upload('ownership_image'),
            'electric_image'                         => $upload('electric_image'),
            'manual_image'                           => $upload('manual_image'),
            'other_image'                            => $upload('other_image'),
            'beneficiary_image'                      => $upload('beneficiary_image'),
            'payment_receipt_image'                  => $upload('payment_receipt_image'),
            'advance_payment_statement_image'        => $upload('advance_payment_statement_image'),
            'annual_zakat_declaration_image'         => $upload('annual_zakat_declaration_image'),
            'last_payment_receipt_image'             => $upload('last_payment_receipt_image'),
            'central_audit_authority_approval_image' => $upload('central_audit_authority_approval_image'),
            'detailed_income_statement_image' => $upload('detailed_income_statement_image'),
            'detailed_trial_balance_image' => $upload('detailed_trial_balance_image'),
            'detailed_final_accounts_report_image' => $upload('detailed_final_accounts_report_image'),
            'salaries_and_entitlements_report_image' => $upload('salaries_and_entitlements_report_image'),
            'cash_and_inventory_statement_image' => $upload('cash_and_inventory_statement_image'),
            'creditors_report_image' => $upload('creditors_report_image'),
            'debtors_report_image' => $upload('debtors_report_image'),
            'rental_owner_contract_image' => $upload('rental_owner_contract_image'),
                'detailed_revenue_report_image' => $upload('detailed_revenue_report_image'),
                'detailed_expenses_report_image' => $upload('detailed_expenses_report_image'),
                'clients_and_contracts_report_image' => $upload('clients_and_contracts_report_image'),
                'income_data_image' => $upload('income_data_image'),
            'zakah_status_id' => 4,
            'notes' => $request->notes,
            'unit_sale_price'                => $request->unit_sale_price,
                'total_sale_value'                => $request->total_sale_value,
                'original_property_value'                => $request->original_property_value,
                'sale_contract_image' => $upload('sale_contract_image'),
                'prev_rental_contract_image' => $upload('prev_rental_contract_image'),
                'rent_exclusion_image' => $upload('rent_exclusion_image'),
                'dependents_report_image' => $upload('dependents_report_image'),
        ]);
        
        
        if ($request->employees && is_array($request->employees)) {
    
                // ❗ حذف الموظفين القديمين
                $item->employees()->delete();
    
                // إضافة الموظفين الجدد
                foreach ($request->employees as $emp) {
                    $item->employees()->create([
                       'name'  => $emp['name'] ?? null,
                        'dependents'           => $emp['dependents'] ?? null,
                        'zakat_amount'    => $emp['zakat_amount'] ?? null,
                       
                    ]);
                }
        }
        
         if ($request->buildings && is_array($request->buildings)) {
                $item->buildings()->delete();
                foreach ($request->buildings as $index => $build) {
                    $electricBillPath = null;
                    if ($request->hasFile("buildings.$index.electric_bill")) {
                        $electricBillPath = $request
                            ->file("buildings.$index.electric_bill")
                            ->store('buildings/electric_bills', 'public');
                    }
            
                    // رفع صورة سبب الاستبعاد
                    $excludeNotePath = null;
                    if ($request->hasFile("buildings.$index.exclude_note")) {
                        $excludeNotePath = $request
                            ->file("buildings.$index.exclude_note")
                            ->store('buildings/exclude_notes', 'public');
                    }
                    $item->buildings()->create([
                        'actual_months'    => $build['actual_months'] ?? null,
                        'address'    => $build['address'] ?? null,
                        'building_type'    => $build['building_type'] ?? null,
                        'currency'    => $build['currency'] ?? null,
                        'electric_bill'    => $electricBillPath,
                        'electric_meter'    => $build['electric_meter'] ?? null,
                        'end_date'    => $build['end_date'] ?? null,
                        'exclude_note'    =>$excludeNotePath,
                        'excluded_months'    => $build['excluded_months'] ?? null,
                        'lang'    => $build['lang'] ?? null,
                        'lat'    => $build['lat'] ?? null,
                        'monthly_rent'    => $build['monthly_rent'] ?? null,
                        'rent_contract'    => $build['rent_contract'] ?? null,
                        'start_date'    => $build['start_date'] ?? null,
                        'tenant_name'    => $build['tenant_name'] ?? null,
                        'unit_type'    => $build['unit_type'] ?? null,
                        'usage'    => $build['usage'] ?? null,
                        'zakat_value'    => $build['zakat_value'] ?? null,
                       
                    ]);
                }
            }
            
        CustomerZakahBalanceLog::create([
            'user_id' => auth('api')->id(),
            'customer_zakah_balance_id' => $item->id,
            'department_id' => auth('api')->user()->department_id,
            'details' => 'اعتماد نهائي',
        ]);
        $item->update(['notes' => null]);
        return response()->json([
                'message' => 'تم',
                'status' => 200,
        ]);
    
    }
    
    public function refuse_mehdar_zakah(Request $request, $id)
    {
        $request->validate(['notes' => 'required']);
    
        $item = CustomerZakahBalance::findOrFail($id);
    
        $item->update([
            'zakah_status_id' => 5,
            'notes' => $request->notes
        ]);
    
        CustomerZakahBalanceLog::create([
            'user_id' => auth('api')->id(),
            'customer_zakah_balance_id' => $item->id,
            'department_id' => auth('api')->user()->department_id,
            'details' => 'رفض نهائي',
            'notes' => $request->notes,
        ]);
    
         $item->update(['notes' => null]);
        return response()->json([
                'message' => 'تم',
                'status' => 200,
        ]);
    }
    
    public function print_zakah(Request $request, $id)
    {
       
    
        $item = CustomerZakahBalance::findOrFail($id);
    
        $item->update([
            'zakah_status_id' => 6,
            
        ]);
    
        CustomerZakahBalanceLog::create([
            'user_id' => auth('api')->id(),
            'customer_zakah_balance_id' => $item->id,
            'department_id' => auth('api')->user()->department_id,
            'details' => 'طباعة حافظة',
            'notes' => $request->notes,
        ]);
    
         $item->update(['notes' => null]);
        return response()->json([
                'message' => 'تم',
                'status' => 200,
        ]);
    }
    
    public function finance_zakah(Request $request, $id)
    {
        $item = CustomerZakahBalance::findOrFail($id);
        
        $upload = function ($key) use ($request, $item) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                $name = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $name = preg_replace('/[^A-Za-z0-9\-_\.]/', '_', $name);
                $file->move(public_path('zakah'), $name);
                return $name;
            }
            return $item->$key; // لو مفيش صورة جديدة يبقى خلي القديمة
        };
    
        $item->update([
            'zakah_status_id' => 7,
            'payment_receipt_number' => $request->payment_receipt_number,
            'payment_value' => $request->payment_value,
            'payment_type_id' => $request->payment_type_id,
            'bank_id' => $request->bank_id,
            'payment_date' => $request->payment_date,
             'supply_voucher_image'=> $upload('supply_voucher_image'),
             'check_image'=> $upload('check_image'),
             'payment_receipt_image'=> $upload('payment_receipt_image'),
            
        ]);
    
        CustomerZakahBalanceLog::create([
            'user_id' => auth('api')->id(),
            'customer_zakah_balance_id' => $item->id,
            'department_id' => auth('api')->user()->department_id,
            'details' => 'اعتماد مالي',
        ]);
        return response()->json([
                'message' => 'تم',
                'status' => 200,
        ]);
    
    }

    public function get_zakah_balance($id)
    {
        
        $item = CustomerZakahBalance::with('employees','buildings')->findOrFail($id);
         $fees = $this->calculateFees($item);
        return response()->json([
                'message' => 'تم',
                'status' => 200,
                'data' => $item,
                'fees' => $fees,
        ]);

    }

     public function zakah_tabs(Request $request)
    {
        
        $pending = CustomerZakahBalance::where('zakah_status_id', 1)->count();
        $approved = CustomerZakahBalance::where('zakah_status_id', 2)->count();
        $refused = CustomerZakahBalance::where('zakah_status_id', 3)->count();
        $finalapproved = CustomerZakahBalance::where('zakah_status_id', 4)->count();
        $finalrefused = CustomerZakahBalance::where('zakah_status_id', 5)->count();
        $all = CustomerZakahBalance::where('zakah_status_id','!=',7)->count();
        

        
        
        return response()->json([
                'message' => 'تم',
                'status' => 200,
                'pending' => $pending,
                'approved' => $approved,
                'refused' => $refused,
                'finalapproved' => $finalapproved,
                'finalrefused' => $finalrefused,
                'all' => $all,
        ], 200);
    }
    
     public function get_logs_zakah(Request $request ,$id)
    {
        $logs = CustomerZakahBalanceLog::where('customer_zakah_balance_id', $id);
    
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
    
    public function get_customer_zakah_details($id)
    {
        $zakah = CustomerZakahBalance::findOrFail($id);
    
        $zakahData = [
            'id'                         => $zakah->id,
            'customer_name'              => $zakah->customer_name,
            'customer_identity_number'   => $zakah->customer->identity_number,
            'customer_identity_name'     => $zakah->customer->identity_name,
            'customer_country_name'      => $zakah->customer->country_name,
             'customer_address'      => $zakah->customer->address,
             'start_date'      => $zakah->customer->identity_start_date,
             'end_date'      => $zakah->customer->identity_end_date,
           
            'zakah_type_name'                => $zakah->zakah_type_name,
            'declaration_name'              => $zakah->declaration_name,
            'zakah_number_id'              => $zakah->zakah_number_id,
            'report_number'                => $zakah->report_number,
            'payment_receipt_number'    =>$zakah->payment_receipt_number,
            'payment_value'    =>$zakah->total,
            'check_image'    =>$zakah->check_image,
            'check_image_url'    =>$zakah->check_image_url,
            'extra_image'    =>$zakah->extra_image,
            'extra_image_url'    =>$zakah->extra_image_url,
            'bank_id'    =>$zakah->bank_id,
            'check_number'    =>$zakah->check_number,
            'check_value'    =>$zakah->check_value,
            'notes'    =>$zakah->notes,
            'payment_receipt_image'    =>$zakah->payment_receipt_image,
            'payment_receipt_image_url'    =>$zakah->payment_receipt_image_url,
            'supply_voucher_image'    =>$zakah->supply_voucher_image,
            'supply_voucher_image_url'    =>$zakah->supply_voucher_image_url,
            'zakah_balance_details' =>$zakah->customer->zakah_balance_details,
            
          
    
            
        ];
    
        return response()->json([
            'status' => 200,
            'data'   => $zakahData,
        ], 200);
    }
    
     public function get_customer_zakah_balance($id)
    {
        $customer = Customer::findOrFail($id);
    
        $customerData = [
            'id'                     => $customer->id,
            'name'                   => $customer->name,
            'country_name'           => $customer->country_name,
            'identity_name'          => $customer->identity_name,
            'identity_number'        => $customer->identity_number,
            'phone_1'                => $customer->phone_1,
            'profile_image_url'      => $customer->profile_image_url,
            'identity_start_date'    => $customer->identity_start_date,
            'address'    => $customer->address,
            'zakah_balance_details' =>$customer->zakah_balance_details,
            'zakah_number_id' => $customer->zakah_number_id
        ];
    
        return response()->json([
            'status' => 200,
            'data'   => $customerData,
        ], 200);
    }
    
    
    public function get_customer_declaration_details(Request $request, $customer_id, $zakah_type_id)
    {
        $query = CustomerZakahBalance::where('customer_id', $customer_id)
            ->where('zakah_type_id', $zakah_type_id);
    
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
    
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }
    
        $zakahList = $query->latest()->get();
    
        return response()->json([
            'status' => 200,
            'data'   => $zakahList,
        ], 200);
    }

    
    

  public function approve_pulck_zakah_finance(Request $request)
    {
        try {
    
            // ✅ Validation
            $request->validate([
                'ids'   => 'required|array|min:1',
                'ids.*' => 'required|integer|exists:customer_zakah_balance,id',
            ]);
    
            // ✅ اعتماد الكل مرة واحدة
            CustomerZakahBalance::whereIn('id', $request->ids)
                ->update([
                    'zakah_status_id' => 8,
                    'is_matched' => 1,
                ]);
    
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
    
    public function approve_zakah_finance(Request $request, $id)
    {
        $renewal = CustomerZakahBalance::findOrFail($id);
    
        $renewal->update([
            'zakah_status_id' => 8,
            'is_matched' => 1,
        ]);
    
        return response()->json([
            'message' => 'تم الاعتماد',
            'status'  => 200,
            'type'    => 'refused',
            'data'    => [],
        ], 200);
    }
    
    
    public function refuse_zakah_finance(Request $request, $id)
    {
        $renewal = CustomerZakahBalance::findOrFail($id);
    
        $renewal->update([
            'zakah_status_id' => 9,
        ]);
    
        return response()->json([
            'message' => 'تم الرفض بنجاح',
            'status'  => 200,
            'type'    => 'refused',
            'data'    => [],
        ], 200);
    }
    
    public function update_finance_zakah(Request $request, $id)
    {
        
        try {
    
             $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
            ];
    
            $attributes = [
                'payment_receipt_number' => 'رقم ايصال الدفع',
                'payment_receipt_image' => 'صورة ايصال الدفع',
                
            ];
    
            $request->validate([
               // 'payment_receipt_number' => 'required',
                
            //   'payment_receipt_number' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            
            
         // 'payment_receipt_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',

            ], $messages, $attributes);
    
            // جلب السجل
           $item = CustomerZakahBalance::findOrFail($id);
            
            if ($file = $request->file('payment_receipt_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('zakah', $filename);
                $item->payment_receipt_image = $filename;
            }
            
            if ($file = $request->file('supply_voucher_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('zakah', $filename);
                $item->supply_voucher_image = $filename;
            }
            
            if ($file = $request->file('extra_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('zakah', $filename);
                $item->extra_image = $filename;
            }
             
             
            if ($file = $request->file('check_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('zakah', $filename);
                $item->check_image = $filename;
            }
             
         
            
            $item->zakah_status_id = 7; 
            $item->payment_receipt_number = $request->payment_receipt_number;
            $item->payment_type_id = $request->payment_type_id;
            $item->bank_id = $request->bank_id;
            $item->check_number = $request->check_number;
            $item->check_value = $request->check_value;
            $item->payment_date = $request->payment_date;
            $item->payment_value = $request->payment_value;
            $item->save();
    
            $log = new CustomerZakahBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_zakah_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "تم التحديث";
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
        } catch (ValidationException $e) {
            $errors = collect($e->errors())->map(fn($m) => $m[0]);
    
            return response()->json([
                'message' => $errors,
                'status' => 422,
                'data' => [],
            ], 422);
        }
    }
    
    public function matching_zakah_table(Request $request)
    {
       $query = CustomerZakahBalance::whereIn('zakah_status_id',[6,7])->whereNotNull('zakah_declaration_id');
    
        if ($request->filled('search')) {
    
            $search = $request->search;
    
            $query->where(function ($q) use ($search) {
    
                // البحث في الملف الضريبي أو ID
                $q->where('zakah_number_id', $search)
                  ->orWhere('id', $search)
    
                  // البحث في بيانات العميل
                  ->orWhereHas('customer', function ($q2) use ($search) {
                      $q2->where('name', 'LIKE', "%{$search}%")
                         ->orWhere('identity_number', 'LIKE', "%{$search}%");
                  });
            });
        }
    
        $data = $query->latest()->paginate(8);
        
        $data->getCollection()->transform(function ($item) {
            return [
                'id'               => $item->id,
                'directorate_name' => $item->directorate_name,  // accessor
                'payment_type_id' => $item->payment_type_id,
                'customer_name'    => $item->customer_name,     // accessor
                'status_name'      => $item->status_name,       // accessor
                'user_id'  => $item->user_id,
                'created_at' => $item->created_at->format('Y.m/d'),
                'payment_receipt_number' => $item->payment_receipt_number,
                'user_name' => $item->user_name,
                'category_name' =>$item->declaration_name,
                 'amount' => $item->amount,
                'status_id' => $item->zakah_status_id,
           
            ];
        });
    
        return response()->json([
            'message' => 'تم',
            'status'  => 200,
            'data'    => $data,
        ]);
    }
    
    
private function calculateZakahTotal($item, $collection)
{
    // مبلغ تحت الحساب
    if ($item->zakah_declaration_id === null) {
        return $item->value;
    }

    // مباني
    if ($item->zakah_type_id == 4 && $item->zakah_declaration_id == 7) {

        $khasm = $collection
            ->where('zakah_type_id', 4)
            ->whereNull('zakah_declaration_id')
            ->sum('value');

        $elzakah  = $item->buildings()->sum('zakat_value');
        $discount = $item->discount ?? 0;

        return max(0, $elzakah - ($khasm + $discount));
    }

    // موظفين
    if ($item->zakah_type_id == 4 && $item->zakah_declaration_id == 10) {

        $khasm = $collection
            ->where('zakah_type_id', 4)
            ->whereNull('zakah_declaration_id')
            ->sum('value');

        $elzakah  = $item->employees()->sum('zakat_amount') * 0.025;
        $discount = $item->discount ?? 0;

        return max(0, $elzakah - ($khasm + $discount));
    }

    // باقي الحالات
    $khasm = $collection
        ->whereNull('zakah_declaration_id')
        ->sum('value');

    $elzakah  = $item->value ?? 0;
    $discount = $item->discount ?? 0;

    return max(0, $elzakah - ($khasm + $discount));
}

public function supply_print_zakah(Request $request)
{
    $renewals = CustomerZakahBalance::where('is_matched', 1)
        ->where('is_printed', 0)
        ->latest()
        ->get();

    $result = [];

    $categories = $renewals->pluck('zakah_type_id')->unique();

    // =========================
    // Config الخدمات
    // =========================
    $tabConfig = [
        1 => [
            'services' => [
                ['code' => '1-1-1-5', 'name' => 'زكاة الباطن على مؤسسات و شركات القطاع العام', 'type' => 'محلي'],
            ],
        ],
        2 => [
            'services' => [
                ['code' => '1-1-1-6', 'name' => 'زكاة الباطن على شركات القطاع الخاص', 'type' => 'محلي'],
            ],
        ],
        3 => [
            'services' => [
                ['code' => '1-1-1-7', 'name' => 'زكاة الباطن على الافراد', 'type' => 'محلي'],
            ],
        ],
        4 => [
            'services' => [
                ['code' => '1-1-1-8', 'name' => 'اخري', 'type' => 'محلي'],
            ],
        ],
    ];

    foreach ($categories as $catId) {

        $categoryRenewals = $renewals->where('zakah_type_id', $catId);

        if ($categoryRenewals->isEmpty()) {
            continue;
        }

        // =========================
        // الإجمالي
        // =========================
        $grandTotal = 0;
        foreach ($categoryRenewals as $item) {
            $grandTotal += $this->calculateZakahTotal($item, $categoryRenewals);
        }

        // =========================
        // بيانات عامة
        // =========================
        $directorateName = $categoryRenewals->pluck('directorate_name')->unique()->implode(' - ');
        $createdAtList   = $categoryRenewals->pluck('created_at')
            ->map(fn ($dt) => \Carbon\Carbon::parse($dt)->format('Y-m-d'))
            ->unique()
            ->implode(' - ');

        $idsList         = $categoryRenewals->pluck('id')->implode(' - ');
        $categoryName    = $categoryRenewals->pluck('category_name')->unique()->implode(' - ');
        $paymentNumbers  = $categoryRenewals->pluck('payment_receipt_number')->filter()->unique()->implode(' - ');

        // =========================
        // النسب
        // =========================
        $percent12 = $grandTotal * 0.12;
        $local44   = $grandTotal * 0.44;
        $shared44  = $grandTotal * 0.44;

        $serviceTabs = $tabConfig[$catId]['services'] ?? [];

        $serviceCodes = implode(' - ', array_column($serviceTabs, 'code'));
        $serviceTypes = implode(' - ', array_column($serviceTabs, 'type'));
        $serviceNames = implode(' - ', array_column($serviceTabs, 'name'));

        // =========================
        // التفاصيل
        // =========================
        $details = [
            $this->zakahDetailRow($directorateName, $percent12, 'نسبة 12%', $createdAtList, $idsList, $categoryName, $paymentNumbers, $serviceCodes, $serviceTypes, $serviceNames),
            $this->zakahDetailRow($directorateName, $local44, '44% محلي', $createdAtList, $idsList, $categoryName, $paymentNumbers, $serviceCodes, $serviceTypes, $serviceNames),
            $this->zakahDetailRow($directorateName, $shared44, '44% مشترك', $createdAtList, $idsList, $categoryName, $paymentNumbers, $serviceCodes, $serviceTypes, $serviceNames),
        ];

        // =========================
        // الصف النهائي
        // =========================
        $result[] = [
            'directorate_name'       => $directorateName,
            'amount'                 => $grandTotal,
            'currency_name'          => 'ريال يمني',
            'created_at'             => $createdAtList,
            'auto_number'            => $idsList,
            'office'                 => $categoryName,
            'payment_receipt_number' => $paymentNumbers,
            'money'                  => 'سند سداد نقدية المديرية',
            'approve_status'         => 'لا',
            'bank'                   => 'بنك',
            'details'                => $details,
        ];
    }

    // =========================
    // 🎯 Filters by params
    // =========================
    $result = collect($result)->filter(function ($item) use ($request) {

        if ($request->filled('directorate_name') &&
            stripos($item['directorate_name'], $request->directorate_name) === false) {
            return false;
        }

        if ($request->filled('office') &&
            stripos($item['office'], $request->office) === false) {
            return false;
        }

        if ($request->filled('payment_receipt_number') &&
            stripos((string)$item['payment_receipt_number'], $request->payment_receipt_number) === false) {
            return false;
        }

        if ($request->filled('currency_name') &&
            stripos($item['currency_name'], $request->currency_name) === false) {
            return false;
        }

        if ($request->filled('approve_status') &&
            $item['approve_status'] != $request->approve_status) {
            return false;
        }

        if ($request->filled('bank') &&
            stripos($item['bank'], $request->bank) === false) {
            return false;
        }

        if ($request->filled('money') &&
            stripos($item['money'], $request->money) === false) {
            return false;
        }

        if ($request->filled('auto_number') &&
            stripos((string)$item['auto_number'], $request->auto_number) === false) {
            return false;
        }

        if ($request->filled('created_at') &&
            stripos($item['created_at'], $request->created_at) === false) {
            return false;
        }

        if ($request->filled('amount_from') &&
            $item['amount'] < $request->amount_from) {
            return false;
        }

        if ($request->filled('amount_to') &&
            $item['amount'] > $request->amount_to) {
            return false;
        }

        // كود الخدمة (من details)
        if ($request->filled('service_code')) {
            $found = false;
            foreach ($item['details'] as $detail) {
                if (isset($detail['service_code']) &&
                    stripos($detail['service_code'], $request->service_code) !== false) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }

        return true;
    })->values();

    return response()->json([
        'status' => true,
        'data'   => $result,
    ]);
}

/**
 * Helper لتجميع صف التفاصيل
 */
private function zakahDetailRow($directorate, $amount, $moneyText, $createdAt, $ids, $office, $payments, $codes, $types, $names)
{
    return [
        'directorate_name'       => $directorate,
        'amount'                 => $amount,
        'currency_name'          => 'ريال يمني',
        'created_at'             => $createdAt,
        'auto_number'            => $ids,
        'office'                 => $office,
        'payment_receipt_number' => $payments,
        'money'                  => $moneyText,
        'approve_status'         => 'لا',
        'bank'                   => 'بنك',
        'code'                   => $codes,
        'type'                   => $types,
        'code_name'              => $names,
    ];
}



public function liquidation_of_custody_zakah()
{
    
    $renewals = CustomerZakahBalance::where('is_printed', 1)
        ->whereDate('printed_at', Carbon::today())
        ->latest()
        ->get();

    $result = [];
    $categories = $renewals->pluck('zakah_type_id')->unique();
    
    $tabConfig = [
        1 => [
            'services' => [
                ['code' => '1-1-1-5', 'name' => 'زكاة الباطن على مؤسسات و شركات القطاع العام', 'type' => 'محلي'],
            ],
        ],
        2 => [
            'services' => [
                ['code' => '1-1-1-6', 'name' => 'زكاة الباطن على شركات القطاع الخاص', 'type' => 'محلي'],
            ],
        ],
        3 => [
            'services' => [
                ['code' => '1-1-1-7', 'name' => 'زكاة الباطن على الافراد', 'type' => 'محلي'],
            ],
        ],
        4 => [
            'services' => [
                ['code' => '1-1-1-8', 'name' => 'اخري', 'type' => 'محلي'],
            ],
        ],
    ];


    foreach ($categories as $catId) {

        $category_renewals = $renewals->where('zakah_type_id', $catId);

        if ($category_renewals->isEmpty()) {
            continue;
        }

        // =========================
        // حساب الإجمالي (من zakah_table)
        // =========================
        $grand_total = 0;

        foreach ($category_renewals as $item) {
            $grand_total += $this->calculateZakahTotal($item, $category_renewals);
        }

        // =========================
        // بيانات عامة
        // =========================
        $directorate_name = $category_renewals
            ->pluck('directorate_name')
            ->unique()
            ->implode(' - ');

        $created_at_list = $category_renewals
            ->pluck('created_at')
            ->map(fn($dt) => \Carbon\Carbon::parse($dt)->format('Y-m-d'))
            ->unique()
            ->implode(' - ');

        $ids_list = $category_renewals
            ->pluck('id')
            ->implode(' - ');

        $category_name = $category_renewals
            ->pluck('category_name')
            ->unique()
            ->implode(' - ');

        $paymentNumbers = $category_renewals
            ->pluck('payment_receipt_number')
            ->filter()
            ->unique()
            ->implode(' - ');

        // =========================
        // توزيع النسب
        // =========================
        $percent12 = $grand_total * 0.12;
        $local44   = $grand_total * 0.44;
        $shared44  = $grand_total * 0.44;
        
        $serviceTabs = $tabConfig[$catId]['services'] ?? [];

        $codes = implode(' - ', array_column($serviceTabs, 'code'));
        $types = implode(' - ', array_column($serviceTabs, 'type'));
        $names = implode(' - ', array_column($serviceTabs, 'name'));

        // =========================
        // دمج الـ details مباشرة
        // =========================
        $result[] = [
            'directorate_name'       => $directorate_name,
            'amount'                 => $percent12,
            'currency_name'          => "ريال يمني",
            'created_at'             => $created_at_list,
            'auto_number'            => $ids_list,
            'office'                 => $category_name,
            'payment_receipt_number' => $paymentNumbers,
            'money'                  => "نسبة 12%",
            'approve_status'         => "لا",
            'bank'                   => "بنك",
            'code'                   => $codes,
            'type'              => $types,
            'code_name'              => $names,
        ];

        $result[] = [
            'directorate_name'       => $directorate_name,
            'amount'                 => $local44,
            'currency_name'          => "ريال يمني",
            'created_at'             => $created_at_list,
            'auto_number'            => $ids_list,
            'office'                 => $category_name,
            'payment_receipt_number' => $paymentNumbers,
            'money'                  => "44% محلي",
            'approve_status'         => "لا",
            'bank'                   => "بنك",
            'code'                   => $codes,
            'type'              => $types,
            'code_name'  => $names,
        ];

        $result[] = [
            'directorate_name'       => $directorate_name,
            'amount'                 => $shared44,
            'currency_name'          => "ريال يمني",
            'created_at'             => $created_at_list,
            'auto_number'            => $ids_list,
            'office'                 => $category_name,
            'payment_receipt_number' => $paymentNumbers,
            'money'                  => "44% مشترك",
            'approve_status'         => "لا",
            'bank'                   => "بنك",
            'code'                   => $codes,
            'type'              => $types,
            'code_name'              => $names,
        ];
    }

    return response()->json([
        'status' => true,
        'data'   => $result,
    ]);
}




public function supply_print_zakah_by_ids(Request $request)
{
    $request->validate([
        'ids' => 'required|array|min:1',
        'ids.*' => 'integer|exists:customer_zakah_balance,id'
    ]);

    CustomerZakahBalance::whereIn('id', $request->ids)
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


    public function customers_zakah_balances(Request $request,$id)
    {
        $query = CustomerZakahBalance::where('customer_id',$id)->with(['customer','zakah_type']);

        if ($request->filled('zakah_type_id')) {
            $query->where('zakah_type_id', $request->zakah_type_id);
        }

        if ($request->filled('from')) {
            $query->whereDate('payment_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('payment_date', '<=', $request->to);
        }

        $data = $query->latest()->paginate(10);

        $collection = $data->getCollection();

        $collection->transform(function ($item) use ($collection) {
        
            // نحسب الفيز
            $feesData = $item->calculateFees($collection);
        
            return [
                'customer_name' => $item->customer->name ?? '-',
                'zakah_type'    => $item->zakah_type->name ?? '-',
                'year'          => $item->year,
                'amount'        => $feesData['total'],
                'fees'          => $feesData['fees'],
            ];
        });

        return response()->json([
            'message' => 'سجل الرصيد ',
            'status'  => 200,
            'data'    => $data,
        ]);
    }



    
}
