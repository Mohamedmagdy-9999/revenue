<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

use Str;

use Illuminate\Validation\ValidationException;

use App\Models\TaxType;
use App\Models\Customer;
use App\Models\CustomerTaxBalance;
use App\Models\TaxFile;
use App\Models\Declaration;
use App\Models\TaxBalanceEmployee;
use App\Models\License;
use App\Models\LicenseeBranch;
use App\Models\BranchRenewal;
use App\Models\TaxBalanceActivity;
use Carbon\Carbon;
use App\Models\TaxBalanceBulinding;
use App\Models\CustomerTaxBalanceLog;
class TaxApiController extends Controller
{
    private function calculateFees($item, $collection = null)
    {
        $item->fees = [];
    
        /* ✅ الحالة الأولى: مبلغ تحت الحساب */
        if ($item->declaration_id === null) {
    
            $value = $item->value;
    
            $item->fees = [
                [
                    'key'   => 'amrta7selt7tel7sab',
                    'label' => 'مبلغ تحت الحساب',
                    'value' => $value,
                ],
                [
                    'key'   => 'elegmaly',
                    'label' => 'الاجمالي',
                    'value' => $value,
                ],
            ];
        }
    
        /* ✅ إقرار مرتبات */
        elseif ($item->declaration_id == 5 && $item->tax_type_id == 3) {
    
            $khasm = $collection
                ? $collection->where('tax_type_id', 3)->whereNull('declaration_id')->sum('value')
                : 0;
    
            $eldreba = $item->employees()->sum('total');
    
            $item->fees = [
                ['key'=>'eldreba','label'=>'الضريبة','value'=>$eldreba],
                ['key'=>'el8ramat','label'=>'الغرامات','value'=>0],
                ['key'=>'tadrebmahny','label'=>'تدريب مهني','value'=>0],
                ['key'=>'khasmta7selt7tel7sab','label'=>'خصم تحت الحساب','value'=>$khasm],
                [
                    'key'=>'elegmaly',
                    'label'=>'الاجمالي',
                    'value'=>$eldreba - $khasm
                ],
            ];
        }
    
        /* ✅ أنشطة تجارية */
        elseif ($item->declaration_id == 2 && $item->tax_type_id == 2) {
    
            $khasm = $collection
                ? $collection->where('tax_type_id', 2)->whereNull('declaration_id')->sum('value')
                : 0;
    
            $eldreba = $item->total;
    
            $item->fees = [
                ['key'=>'eldreba','label'=>'الضريبة','value'=>$eldreba],
                ['key'=>'khasmeleqrar','label'=>'خصم الاقرار','value'=>0],
                ['key'=>'el8ramat','label'=>'الغرامات','value'=>0],
                ['key'=>'khasmel8ramat','label'=>'خصم الغرامات','value'=>0],
                ['key'=>'khasmta7selt7tel7sab','label'=>'خصم تحت الحساب','value'=>$khasm],
                [
                    'key'=>'elegmaly',
                    'label'=>'الاجمالي',
                    'value'=>$eldreba - $khasm
                ],
            ];
        }
    
        /* ✅ ضريبة عقارية */
        elseif ($item->declaration_id == 1 && $item->tax_type_id == 1) {
    
            $khasm = $collection
                ? $collection->where('tax_type_id', 1)->whereNull('declaration_id')->sum('value')
                : 0;
    
            $eldreba = $item->buildings()->sum('tax_value');
            $el8ramat = 0;
    
            $dueDate = Carbon::create($item->year, 6, 30)->addYear();
            $paymentDate = $item->created_at ? Carbon::parse($item->created_at) : now();
    
            if ($paymentDate->greaterThan($dueDate)) {
                $months = $dueDate->diffInMonths($paymentDate);
                $el8ramat = $eldreba * 0.02 * $months;
            }
    
            $item->fees = [
                ['key'=>'eldreba','label'=>'الضريبة','value'=>round($eldreba,2)],
                ['key'=>'el8ramat','label'=>'الغرامات','value'=>round($el8ramat,2)],
                ['key'=>'khasmeleqrar','label'=>'خصم الاقرار','value'=>0],
                ['key'=>'khasmta7selt7tel7sab','label'=>'خصم تحت الحساب','value'=>round($khasm,2)],
                [
                    'key'=>'elegmaly',
                    'label'=>'الاجمالي',
                   'value'=>round( ($eldreba + $el8ramat) - $khasm,2)
                ],
            ];
        }
    
        return $item;
    }
   
    public function add_balance(Request $request)
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
                'tax_type_id' => 'نوع الضريبة',
            
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
                'tax_type_id' => 'required',
                'year' => 'required',
            
                'ownership_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'electric_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                
                'other_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
               
                 'beneficiary_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            
                'value' => 'required',
                
            ], $messages, $attributes);


            $ownership_image = null;
            if ($file = $request->file('ownership_image')) {
                $ownership_image = time() . $file->getClientOriginalName();
                $file->move('balance', $ownership_image);
            }
            
            
            $electric_image = null;
            if ($file = $request->file('electric_image')) {
                $electric_image = time() . $file->getClientOriginalName();
                $file->move('balance', $electric_image);
            }
            
            $manual_image = null;
            if ($file = $request->file('manual_image')) {
                $manual_image = time() . $file->getClientOriginalName();
                $file->move('balance', $manual_image);
            }
            
            
            $other_image = null;
            if ($file = $request->file('other_image')) {
                $other_image = time() . $file->getClientOriginalName();
                $file->move('balance', $other_image);
            }
            
            $beneficiary_image = null;
            if ($file = $request->file('beneficiary_image')) {
                $beneficiary_image = time() . $file->getClientOriginalName();
                $file->move('balance', $beneficiary_image);
            }
            
  
            $file = TaxFile::where('customer_id', $request->customer_id)->first();
            
            $item = new CustomerTaxBalance();
            $item->tax_type_id = $request->tax_type_id;
            $item->customer_id = $request->customer_id;
            $item->year = $request->year;
            $item->value = $request->value;
            $item->ownership_image = $ownership_image;
            $item->electric_image = $electric_image;
            $item->manual_image = $manual_image;
            $item->other_image = $other_image;
            $item->beneficiary_image = $beneficiary_image;
            $item->tax_status_id = 1;
            $item->tax_file_id = $file->id;
            $item->user_id = auth('api')->user()->id;
             $item->notes = $request->notes;
            $item->save();
            
            $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
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
    
    
    
    public function approve_balance(Request $request, $id)
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
               
                'tax_type_id' => 'نوع الضريبة',
                'value' => 'المبلغ',
                'year' => 'العام',
                'ownership_image' => 'صورة عقد الملكية',
                'electric_image' => 'صورة فاتورة الكهرباء',
                'manual_image' => 'صورة امر التحصيل اليدوي',
             
            ];
    
            // ✔️ validation بدون required للصور لأن التحديث ممكن لا يرفع صورة جديدة
            $request->validate([
               
                'tax_type_id' => 'required',
                'year' => 'required',
                'value' => 'required',

    
                'ownership_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'electric_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'manual_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'other_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    
            ], $messages, $attributes);
    
            // ✔️ جلب السجل
            $item = CustomerTaxBalance::find($id);
    
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
                $file->move('balance', $filename);
                $item->ownership_image = $filename;
            }
    
            if ($file = $request->file('electric_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('balance', $filename);
                $item->electric_image = $filename;
            }
    
            if ($file = $request->file('manual_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('balance', $filename);
                $item->manual_image = $filename;
            }
    
            if ($file = $request->file('other_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('balance', $filename);
                $item->other_image = $filename;
            }
    
            // =============================================
            //               تحديث البيانات
            // =============================================
    
            
            $item->tax_type_id = $request->tax_type_id;
            $item->year        = $request->year;
            $item->value       = $request->value;
             $item->tax_status_id       = 2;
             $item->notes = $request->notes;
          
            $item->save();
            
            $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
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
    
    public function refuse_balance(Request $request, $id)
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
            $item = CustomerTaxBalance::find($id);
    
            if (!$item) {
                return response()->json([
                    'message' => 'السجل غير موجود.',
                    'status'  => 404,
                ], 404);
            }
    
            // =============================================
            //               تحديث الصور
            // =============================================
    
           
    
          
             $item->tax_status_id       = 3;
            $item->notes = $request->notes;
            $item->save();
            
            $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
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
    
    public function update_balance(Request $request, $id)
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
               
                'tax_type_id' => 'نوع الضريبة',
                'value' => 'المبلغ',
                'year' => 'العام',
                'ownership_image' => 'صورة عقد الملكية',
                'electric_image' => 'صورة فاتورة الكهرباء',
                'manual_image' => 'صورة امر التحصيل اليدوي',
            ];
    
            // ✔️ validation بدون required للصور لأن التحديث ممكن لا يرفع صورة جديدة
            $request->validate([
                
                'tax_type_id' => 'required',
                'year' => 'required',
                'value' => 'required',
    
                'ownership_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'electric_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'manual_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'other_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'beneficiary_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    
            ], $messages, $attributes);
    
            // ✔️ جلب السجل
            $item = CustomerTaxBalance::find($id);
    
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
                $file->move('balance', $filename);
                $item->ownership_image = $filename;
            }
    
            if ($file = $request->file('electric_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('balance', $filename);
                $item->electric_image = $filename;
            }
    
            if ($file = $request->file('manual_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('balance', $filename);
                $item->manual_image = $filename;
            }
    
            if ($file = $request->file('other_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('balance', $filename);
                $item->other_image = $filename;
            }
            
            if ($file = $request->file('beneficiary_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('balance', $filename);
                $item->beneficiary_image = $filename;
            }
    
            // =============================================
            //               تحديث البيانات
            // =============================================
            $oldData = $item->toArray();
           
            $item->tax_type_id = $request->tax_type_id;
            $item->year        = $request->year;
            $item->value       = $request->value;
            $item->tax_status_id = 1;
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
    
           $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
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
    
    public function finance_accept_balance(Request $request, $id)
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
            
              //  'payment_receipt_image' => 'required_if:payment_type_id,1|image|mimes:jpg,jpeg,png|max:2048',
                    'bank_id'      => 'required_if:payment_type_id,2',
                'check_number' => 'required_if:payment_type_id,2',
                'check_value' => 'required_if:payment_type_id,2',
                'check_image'  => 'required_if:payment_type_id,2|image|mimes:jpg,jpeg,png|max:2048',
                'supply_voucher_image'  => 'required|image|mimes:jpg,jpeg,png|max:2048',
    
        
                    
                  
        
                ], $messages, $attributes);
                
            $payment_receipt_image = null;
            if ($file = $request->file('payment_receipt_image')) {
                $payment_receipt_image = time() . $file->getClientOriginalName();
                $file->move('balance', $payment_receipt_image);
            }
            
             $supply_voucher_image = null;
            if ($file = $request->file('supply_voucher_image')) {
                $supply_voucher_image = time() . $file->getClientOriginalName();
                $file->move('balance', $supply_voucher_image);
            }
            
            $extra_image = null;
            if ($file = $request->file('extra_image')) {
                $extra_image = time() . $file->getClientOriginalName();
                $file->move('balance', $extra_image);
            }
            
            $check_image = null;
            if ($file = $request->file('check_image')) {
                $check_image = time() . $file->getClientOriginalName();
                $file->move('balance', $check_image);
            }
            // ✔️ جلب السجل
            $item = CustomerTaxBalance::find($id);
    
            if (!$item) {
                return response()->json([
                    'message' => 'السجل غير موجود.',
                    'status'  => 404,
                ], 404);
            }
    
            $item->payment_receipt_number = $request->payment_receipt_number;
            $item->payment_receipt_image = $payment_receipt_image;
             $item->tax_status_id       = 5;
             $item->payment_type_id = $request->payment_type_id;
            $item->bank_id = $request->bank_id;
            $item->check_number = $request->check_number;
            $item->check_value = $request->check_value;
            $item->check_image = $check_image;
            $item->supply_voucher_image = $supply_voucher_image;  
            $item->payment_date = $request->payment_date;
            $item->extra_image = $extra_image;
            $item->payment_value = $request->payment_value;
            $item->save();
            
            $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
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

    
    
    public function add_tax_file(Request $request)
    {
        try {
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'exists'   => 'القيمة المختارة في :attribute غير صحيحة أو غير موجودة.',
            ];
            
            $attributes = [
                'customer_id' => 'المكلف',
            ];
            
            $request->validate([
                'customer_id' => 'required|exists:customers,id',
            ], $messages, $attributes);
    
            // 🔥 تحقق إذا كان المكلف لديه ملف ضريبي بالفعل
            if (TaxFile::where('customer_id', $request->customer_id)->exists()) {
                return response()->json([
                    'message' => 'هذا المكلف لديه ملف ضريبي سابق بالفعل.',
                    'status' => 422,
                    
                ], 422);
            }
    
            // ✔️ إضافة الملف الضريبي
            $item = new TaxFile();
            $item->customer_id = $request->customer_id;
            $item->save();
            
            return response()->json([
                'message' => 'تم انشاء الملف الضريبي بنجاح',
                'status' => 200,
                
                'data' => $item->id,
            ], 200);
    
        } catch (ValidationException $e) {
            $errors = collect($e->errors())->map(function($messages){
                return $messages[0];
            });
            return response()->json([
                'message' => $errors,
                'status' => 422,
                
            ], 422);
        }
    }
    
    
    public function get_balance_details($id)
    {
        $item = CustomerTaxBalance::with('employees','activities','buildings')->findOrFail($id);
        
       $customer = Customer::find($item->customer_id);
        $licenseids = License::where('customer_id',$customer->id)->pluck('id');
      
        
        $branches = LicenseeBranch::whereIn('license_id',$licenseids)->get();
        
        //$renewals = BranchRenewal::whereIn('license_branch_id', $branchesid)->get();
        $branches->transform(function($branch){
            return [
                'business_name' => $branch->business_name,
                'directorate_name' => $branch->directorate_name,
                'address' => $branch->address,
            ];
        });
        
    
        // نحسب الفلوس
        $item = $this->calculateFees($item);
    
        return response()->json([
            'message' => 'تفاصيل الرصيد',
            'status'  => 200,
            'data'    => $item,
            'activities' => $branches,
        ], 200);
    }

    
    public function filter_by_tax_file_id($id)
    {
        // التحقق من وجود TaxFile
        $data = TaxFile::find($id);
        if (!$data) {
            return response()->json([
                'message' => 'Tax file not found',
                'status' => 200,
                'customer' => [],
            ], 200);
        }
    
        // جلب الـ customer المرتبط
        $customer = Customer::where('id', $data->customer_id)
                    ->latest()
                    ->paginate(8);
    
        // لو مفيش بيانات
        if ($customer->isEmpty()) {
            return response()->json([
                'message' => 'No customer found',
                'status' => 200,
                'customer' => [],
            ], 200);
        }
    
        // لو فيه بيانات
        $customer->getCollection()->transform(function ($item) {
            return [
                'id'               => $item->id,
                'name'             => $item->name,
                'country_name'     => $item->country_name,
                'identity_name'    => $item->identity_name,
                'identity_number'  => $item->identity_number,
                'phone_1'          => $item->phone_1,
                'address'          => $item->address,
            ];
        });
    
        return response()->json([
            'message' => 'success',
            'status' => 200,
            'customer' => $customer,
        ], 200);
    }




    public function tax_reviewer_table(Request $request)
    {
        $query = CustomerTaxBalance::where('tax_status_id', 1);
    
        if ($request->filled('search')) {
    
            $search = $request->search;
    
            $query->where(function ($q) use ($search) {
    
                // البحث في الملف الضريبي أو ID
                $q->where('tax_file_id', $search)
                  ->orWhere('id', $search)
    
                  // البحث في بيانات العميل
                  ->orWhereHas('customer', function ($q2) use ($search) {
                      $q2->where('name', 'LIKE', "%{$search}%")
                         ->orWhere('identity_number', 'LIKE', "%{$search}%");
                  });
            });
        }
    
        $data = $query->latest()->paginate(8);
    
        return response()->json([
            'message' => 'تم',
            'status'  => 200,
            'data'    => $data,
        ]);
    }



    
    
    public function tax_finance_table(Request $request)
    {
        $query = CustomerTaxBalance::whereIn('customer_tax_balance.tax_status_id', [4, 7])
            ->join('customers', 'customers.id', '=', 'customer_tax_balance.customer_id')
            ->select('customer_tax_balance.*')
    
            ->when($request->id, function ($q, $v) {
                $q->where('customer_tax_balance.id', $v);
            })
    
            ->when($request->customer_name, function ($q, $v) {
                $q->where('customers.name', 'like', "%{$v}%");
            })
    
            ->when($request->customer_identity_number, function ($q, $v) {
                $q->where('customers.identity_number', 'like', "%{$v}%");
            })
            
            ->when($request->tax_type_id, function ($q, $v) {
                $q->where('customer_tax_balance.tax_type_id', $v);
            })
    
            ->when($request->from, function ($q, $v) {
                $q->whereDate('customer_tax_balance.created_at', '>=', $v);
            })
    
            ->when($request->to, function ($q, $v) {
                $q->whereDate('customer_tax_balance.created_at', '<=', $v);
            })
    
            ->latest('customer_tax_balance.created_at');
    
        $data = $query->paginate(8);
    
        $data->getCollection()->transform(function ($item) {
            return [
                'id'                 => $item->id,
                'directorate_name'   => $item->directorate_name,
                'payment_type_id'    => $item->payment_type_id,
                'payment_type_name'  => $item->payment_type_name,
                'customer_name'      => $item->customer_name,
                'status_name'        => $item->status_name,
                'user_id'            => $item->user_id,
                'tax_status_id'      => $item->tax_status_id,
                'tax_type_name' => $item->tax_type_name,
            ];
        });
    
        return response()->json([
            'message' => 'تم',
            'status'  => 200,
            'data'    => $data,
        ]);
    }
        
  
   
    public function tax_table(Request $request)
    {
        $query = CustomerTaxBalance::where('tax_status_id', '!=', 5);
        
    
        if ($request->filled('tax_status_id')) {
            $query->where('tax_status_id', $request->tax_status_id);
        }
        
        
        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }
    
        $data = $query->paginate(8);
    
        $collection = $data->getCollection();
    
        $collection->transform(function ($item) use ($collection) {
    
            $item->fees = [];
    
            /* ✅ الحالة الأولى: مبلغ تحت الحساب */
            if ($item->declaration_id === null) {
    
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
            }
    
            /* ✅ الحالة الثانية: إقرار + نوع ضريبة */
            elseif ($item->declaration_id == 5 && $item->tax_type_id == 3) {
    
                $khasmta7selt7tel7sab = CustomerTaxBalance::where('customer_id', $item->customer_id)
                ->where('tax_type_id', 3)
                ->whereNull('declaration_id')          // تحت الحساب
                ->whereNull('applied_declaration_id')  // لسه ما اتخصمش
                ->where('year', $item->year)
                ->sum('value');
    
                $eldreba = $item->employees()->sum('total');
                $el8ramat = 0;
                $tadrebmahny = 0;
    
                $item->fees = [
                    [
                        'key'   => 'eldreba',
                        'label' => 'الضريبة',
                        'value' => $eldreba,
                    ],
                    [
                        'key'   => 'el8ramat',
                        'label' => 'الغرامات',
                        'value' => $el8ramat,
                    ],
                    [
                        'key'   => 'tadrebmahny',
                        'label' => 'تدريب مهني',
                        'value' => $tadrebmahny,
                    ],
                    [
                        'key'   => 'khasmta7selt7tel7sab',
                        'label' => 'خصم تحت الحساب',
                        'value' => $khasmta7selt7tel7sab,
                    ],
                    [
                        'key'   => 'elegmaly',
                        'label' => 'الاجمالي',
                        'value' => ( $eldreba + $el8ramat ) - ($tadrebmahny + $khasmta7selt7tel7sab),
                    ],
                ];
            }elseif ($item->declaration_id == 2 && $item->tax_type_id == 2) {
    
                $khasmta7selt7tel7sab = CustomerTaxBalance::where('customer_id', $item->customer_id)
                ->where('tax_type_id', 2)
                ->whereNull('declaration_id')          // تحت الحساب
                ->whereNull('applied_declaration_id')  // لسه ما اتخصمش
                ->where('year', $item->year)
                ->sum('value');
    
                $eldreba = $item->total;
                $el8ramat = 0;
                $taxYear = $item->year; 
                $paymentDate = $item->created_at
                    ? Carbon::parse($item->created_at)
                    : now();
            
                // ميعاد الإقرار
                $dueDate = Carbon::create($taxYear, 6, 30);
            
                // بعد سنة سماح
                $penaltyStartDate = $dueDate->copy()->addYear();
            
                if ($paymentDate->greaterThan($penaltyStartDate)) {
            
                    // عدد شهور التأخير بعد السنة
                    $lateMonths = $penaltyStartDate->diffInMonths($paymentDate);
            
                    $el8ramat = $eldreba * 0.02 * $lateMonths;
                }
                $khasmel8ramat = 0;
                $khasmeleqrar = 0;
    
                $item->fees = [
                    [
                        'key'   => 'eldreba',
                        'label' => 'الضريبة',
                        'value' => $eldreba,
                    ],
                    [
                        'key'   => 'khasmeleqrar',
                        'label' => 'خصم الاقرار',
                        'value' => $khasmeleqrar,
                    ],
                    [
                        'key'   => 'el8ramat',
                        'label' => 'الغرامات',
                        'value' => $el8ramat,
                    ],
                    [
                        'key'   => 'khasmel8ramat',
                        'label' => 'خصم الغرامات',
                        'value' => $khasmel8ramat,
                    ],
                   
                    [
                        'key'   => 'khasmta7selt7tel7sab',
                        'label' => 'خصم تحت الحساب',
                        'value' => $khasmta7selt7tel7sab,
                    ],
                    [
                        'key'   => 'elegmaly',
                        'label' => 'الاجمالي',
                        'value' => ( $eldreba + $el8ramat ) - ($khasmeleqrar + $khasmel8ramat + $khasmta7selt7tel7sab),
                    ],
                ];
            }elseif ($item->declaration_id == 1 && $item->tax_type_id == 1) {

                $khasmta7selt7tel7sab = CustomerTaxBalance::where('customer_id', $item->customer_id)
                ->where('tax_type_id', 1)
                ->whereNull('declaration_id')          // تحت الحساب
                ->whereNull('applied_declaration_id')  // لسه ما اتخصمش
                ->where('year', $item->year)
                ->sum('value');
            
                $eldreba = $item->buildings()->sum('tax_value');
            
                $khasmeleqrar = 0;
                $el8ramat = 0;
            
                /* ✅ حساب الغرامات بعد سنة */
                $taxYear = $item->year; 
                $paymentDate = $item->created_at
                    ? Carbon::parse($item->created_at)
                    : now();
            
                // ميعاد الإقرار
                $dueDate = Carbon::create($taxYear, 6, 30);
            
                // بعد سنة سماح
                $penaltyStartDate = $dueDate->copy()->addYear();
            
                if ($paymentDate->greaterThan($penaltyStartDate)) {
            
                    // عدد شهور التأخير بعد السنة
                    $lateMonths = $penaltyStartDate->diffInMonths($paymentDate);
            
                    $el8ramat = $eldreba * 0.02 * $lateMonths;
                }
            
                $item->fees = [
                    [
                        'key'   => 'eldreba',
                        'label' => 'الضريبة',
                        'value' => round($eldreba, 2),
                    ],
                    [
                        'key'   => 'el8ramat',
                        'label' => 'الغرامات',
                        'value' => round($el8ramat, 2),
                    ],
                    [
                        'key'   => 'khasmeleqrar',
                        'label' => 'خصم الاقرار',
                        'value' => $khasmeleqrar,
                    ],
                    [
                        'key'   => 'khasmta7selt7tel7sab',
                        'label' => 'خصم تحت الحساب',
                        'value' => round($khasmta7selt7tel7sab, 2),
                    ],
                    [
                        'key'   => 'elegmaly',
                        'label' => 'الاجمالي',
                        'value' => round(
                            ( $eldreba + $el8ramat ) - ($khasmeleqrar  + $khasmta7selt7tel7sab),
                            2
                        ),
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




    
    
    public function free_professions(Request $request)
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
                'tax_type_id' => 'نوع الضريبة',
            
                'discount_percentage_image' => 'صورة اعتماد خصم الغرامة',
            
                'payment_statement_image' => 'صورة كشف سداد تحت الحساب',
                'calculation_image' => 'صورة بيانات الاحتساب'
                ,
                'tax_image' => 'صورة اقرار ضريبة المهن الحرة',
                'value' => 'المبلغ',
                'year.min' => 'لا يمكن إدخال سنة قديمة، يجب أن تكون السنة الحالية أو أكبر.',
                'year.integer' => 'يجب ان يكون رقما',
                'report_number' => 'رقم الاقرار',
            ];
            
            $request->validate([
                'customer_id' => 'required',
                // 'tax_type_id' => 'required',
                'year' => 'required',
                'report_number' => 'required',
                'type_report' => 'required',
            
                'discount_percentage_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'payment_statement_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                
                'calculation_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'tax_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'report_number' => 'required',
             
            
                // 'value' => 'required',
                
            ], $messages, $attributes);


            $discount_percentage_image = null;
            if ($file = $request->file('discount_percentage_image')) {
                $discount_percentage_image = time() . $file->getClientOriginalName();
                $file->move('balance', $discount_percentage_image);
            }
            
            
            $payment_statement_image = null;
            if ($file = $request->file('payment_statement_image')) {
                $payment_statement_image = time() . $file->getClientOriginalName();
                $file->move('balance', $payment_statement_image);
            }
            
            $calculation_image = null;
            if ($file = $request->file('calculation_image')) {
                $calculation_image = time() . $file->getClientOriginalName();
                $file->move('balance', $calculation_image);
            }
            
            
            $tax_image = null;
            if ($file = $request->file('tax_image')) {
                $tax_image = time() . $file->getClientOriginalName();
                $file->move('balance', $tax_image);
            }
            
            $file = TaxFile::where('customer_id', $request->customer_id)->first();
            
            $item = new CustomerTaxBalance();
            $item->tax_type_id = 3;
            $item->type_report = $request->type_report;
            $item->customer_id = $request->customer_id;
            $item->year = $request->year;
            $item->value = $request->value;
            $item->discount_percentage_image = $discount_percentage_image;
            $item->payment_statement_image = $payment_statement_image;
            $item->calculation_image = $calculation_image;
            $item->tax_image = $tax_image;
            $item->tax_status_id = 1;
            $item->user_id = auth('api')->user()->id;
            $item->declaration_id = 5;
            $item->tax_percentage = $request->tax_percentage;
            $item->tax_file_id = $file->id;
            $item->report_number = $request->report_number;
            $item->notes = $request->notes;
            $item->save();
            
            if ($request->employees && is_array($request->employees)) {

                foreach ($request->employees as $emp) {
                    $item->employees()->create([
                        'employee_name'  => $emp['employee_name'] ?? null,
                        'year'           => $emp['year'] ?? null,
                        'from_amount'    => $emp['from_amount'] ?? null,
                        'to_amount'      => $emp['to_amount'] ?? null,
                        'monthly'        => $emp['monthly'] ?? null,
                       // 'bonus'          => $emp['bonus'] ?? null,
                       // 'total_income'   => $emp['total_income'] ?? null,
                        
                        'total'=> $emp['total'] ?? null,
                        'identity_number'=> $emp['identity_number'] ?? null,
                    ]);
                }
            }
            
            $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "اضافة اقرار ضريبة المرتبات و الاجور";
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
    
    
    public function update_free_professions(Request $request, $id)
    {
        try {
    
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
            ];
    
            $attributes = [
                'customer_id' => 'المكلف',
                
                'discount_percentage_image' => 'صورة اعتماد خصم الغرامة',
                'payment_statement_image' => 'صورة كشف سداد تحت الحساب',
                'calculation_image' => 'صورة بيانات الاحتساب',
                'tax_image' => 'صورة اقرار ضريبة المهن الحرة',
                'value' => 'المبلغ',
            ];
    
            $request->validate([
                'customer_id' => 'required',
                
                'year' => 'required',
                // 'value' => 'required', value is not requird
    
                'discount_percentage_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'payment_statement_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'calculation_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'tax_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    
            ], $messages, $attributes);
    
            // جلب السجل
            $item = CustomerTaxBalance::findOrFail($id);
    
            // حفظ الصور
            if ($file = $request->file('discount_percentage_image')) {
                $item->discount_percentage_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->discount_percentage_image);
            }
    
            if ($file = $request->file('payment_statement_image')) {
                $item->payment_statement_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->payment_statement_image);
            }
    
            if ($file = $request->file('calculation_image')) {
                $item->calculation_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->calculation_image);
            }
    
            if ($file = $request->file('tax_image')) {
                $item->tax_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->tax_image);
            }
    
            // تحديث البيانات الأساسية
           
            
            $item->year = $request->year;
            //$item->value = $request->value;
            $item->tax_status_id = 1;
            $item->report_number = $request->report_number;
            $item->tax_percentage = $request->tax_percentage;
            $item->notes = $request->notes;
            $item->save();
    
            // تحديث الموظفين
            if ($request->employees && is_array($request->employees)) {
    
                // ❗ حذف الموظفين القديمين
                $item->employees()->delete();
    
                // إضافة الموظفين الجدد
                foreach ($request->employees as $emp) {
                    $item->employees()->create([
                       'employee_name'  => $emp['employee_name'] ?? null,
                        'year'           => $emp['year'] ?? null,
                        'from_amount'    => $emp['from_amount'] ?? null,
                        'to_amount'      => $emp['to_amount'] ?? null,
                        'monthly'        => $emp['monthly'] ?? null,
                       // 'bonus'          => $emp['bonus'] ?? null,
                       // 'total_income'   => $emp['total_income'] ?? null,
                        
                        'total'=> $emp['total'] ?? null,
                       // 'identity_number'=> $emp['identity_number'] ?? null,
                    ]);
                }
            }
            
            $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "تعديل اقرار ضريبة المرتبات و الاجور";
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
    
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    public function approve_free_professions(Request $request, $id)
    {
        try {
    
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
            ];
    
            $attributes = [
                'customer_id' => 'المكلف',
                'tax_type_id' => 'نوع الضريبة',
                'discount_percentage_image' => 'صورة اعتماد خصم الغرامة',
                'payment_statement_image' => 'صورة كشف سداد تحت الحساب',
                'calculation_image' => 'صورة بيانات الاحتساب',
                'tax_image' => 'صورة اقرار ضريبة المهن الحرة',
                'value' => 'المبلغ',
               
            ];
    
            $request->validate([
                'customer_id' => 'nullable',
                'tax_type_id' => 'nullable',
                'year' => 'required',
                'value' => 'nullable',
                
    
                'discount_percentage_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'payment_statement_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'calculation_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'tax_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    
            ], $messages, $attributes);
    
            // جلب السجل
            $item = CustomerTaxBalance::findOrFail($id);
    
            // حفظ الصور
            if ($file = $request->file('discount_percentage_image')) {
                $item->discount_percentage_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->discount_percentage_image);
            }
    
            if ($file = $request->file('payment_statement_image')) {
                $item->payment_statement_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->payment_statement_image);
            }
    
            if ($file = $request->file('calculation_image')) {
                $item->calculation_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->calculation_image);
            }
    
            if ($file = $request->file('tax_image')) {
                $item->tax_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->tax_image);
            }
    
            // تحديث البيانات الأساسية
            
          
            $item->notes = $request->notes;
            $item->value = $request->value;
    
            
            $item->tax_status_id = 2; 
        
    
            $item->save();
            
            $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "اعتماد";
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
    
            // تحديث الموظفين
           // if ($request->employees && is_array($request->employees)) {
    
             //   $item->employees()->delete(); // حذف القديم
    
              //  foreach ($request->employees as $emp) {
               //     $item->employees()->create([
                //        'employee_name'   => $emp['employee_name'] ?? null,
                 //       'year'            => $emp['year'] ?? $item->year,
                 //       'from_amount'     => $emp['from_amount'] ?? null,
                 //       'to_amount'       => $emp['to_amount'] ?? null,
                 //       'monthly'         => $emp['monthly'] ?? null,
                 //       'bonus'           => $emp['bonus'] ?? null,
                 //       'total_income'    => $emp['total_income'] ?? null,
                 //       'income_tax'      => $emp['income_tax'] ?? null,
                 //       'excluded_amount' => $emp['excluded_amount'] ?? null,
                   // ]);
               // }
            //}
    
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    
    public function refuse_free_professions(Request $request, $id)
    {
        try {
    
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
            ];
    
            $attributes = [
                'customer_id' => 'المكلف',
                'tax_type_id' => 'نوع الضريبة',
                'discount_percentage_image' => 'صورة اعتماد خصم الغرامة',
                'payment_statement_image' => 'صورة كشف سداد تحت الحساب',
                'calculation_image' => 'صورة بيانات الاحتساب',
                'tax_image' => 'صورة اقرار ضريبة المهن الحرة',
                'value' => 'المبلغ',
                'notes'=> 'الملاحظات',
               
            ];
    
            $request->validate([
                'customer_id' => 'nullable',
                'tax_type_id' => 'nullable',
               
                'value' => 'nullable',
                'notes' => 'required',
    
                'discount_percentage_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'payment_statement_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'calculation_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'tax_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    
            ], $messages, $attributes);
    
            // جلب السجل
            $item = CustomerTaxBalance::findOrFail($id);
    
            // حفظ الصور
            if ($file = $request->file('discount_percentage_image')) {
                $item->discount_percentage_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->discount_percentage_image);
            }
    
            if ($file = $request->file('payment_statement_image')) {
                $item->payment_statement_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->payment_statement_image);
            }
    
            if ($file = $request->file('calculation_image')) {
                $item->calculation_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->calculation_image);
            }
    
            if ($file = $request->file('tax_image')) {
                $item->tax_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->tax_image);
            }
    
            // تحديث البيانات الأساسية
           
            $item->notes = $request->notes;
            
            $item->tax_status_id = 3; 
        
    
            $item->save();
            $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "رفض اعتماد";
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
    
            // تحديث الموظفين
           // if ($request->employees && is_array($request->employees)) {
    
             //   $item->employees()->delete(); // حذف القديم
    
              //  foreach ($request->employees as $emp) {
               //     $item->employees()->create([
                //        'employee_name'   => $emp['employee_name'] ?? null,
                 //       'year'            => $emp['year'] ?? $item->year,
                 //       'from_amount'     => $emp['from_amount'] ?? null,
                 //       'to_amount'       => $emp['to_amount'] ?? null,
                 //       'monthly'         => $emp['monthly'] ?? null,
                 //       'bonus'           => $emp['bonus'] ?? null,
                 //       'total_income'    => $emp['total_income'] ?? null,
                 //       'income_tax'      => $emp['income_tax'] ?? null,
                 //       'excluded_amount' => $emp['excluded_amount'] ?? null,
                   // ]);
               // }
            //}
    
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    
    public function print_free_professions(Request $request, $id)
    {
        try {
    
            
    
            // جلب السجل
            $item = CustomerTaxBalance::findOrFail($id);
    
            $item->tax_status_id = 4; 
        
    
            $item->save();
    
            $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "طباعة حافظة";
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
    
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    
    public function finance_free_professions(Request $request, $id)
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
           $item = CustomerTaxBalance::findOrFail($id);
            if ($file = $request->file('payment_receipt_image')) {
                $item->payment_receipt_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->payment_receipt_image);
            }
             $supply_voucher_image = null;
            if ($file = $request->file('supply_voucher_image')) {
                $supply_voucher_image = time() . $file->getClientOriginalName();
                $file->move('balance', $supply_voucher_image);
            }
            
            $extra_image = null;
            if ($file = $request->file('extra_image')) {
                $extra_image = time() . $file->getClientOriginalName();
                $file->move('balance', $extra_image);
            }
            
            $check_image = null;
            if ($file = $request->file('check_image')) {
                $check_image = time() . $file->getClientOriginalName();
                $file->move('balance', $check_image);
            }
            
            $item->tax_status_id = 5; 
            $item->payment_receipt_number = $request->payment_receipt_number;
            $item->payment_type_id = $request->payment_type_id;
            $item->bank_id = $request->bank_id;
            $item->check_number = $request->check_number;
            $item->check_value = $request->check_value;
            $item->check_image = $check_image;
            $item->supply_voucher_image = $supply_voucher_image;  
            $item->payment_date = $request->payment_date;
            $item->extra_image = $extra_image;
            $item->payment_value = $request->payment_value;
            $item->save();
    
            $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "تم التحصيل";
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
    
    
    public function search_balance(Request $request)
    {
        
        $data =    CustomerTaxBalance::where('report_number',$request->id)->paginate(8);
        return response()->json([
                'message' => 'تم',
                'status' => 200,
                'data' => $data,
        ], 200);
    }


    public function search_balance_by_status(Request $request)
    {
        if ($request->has('tax_status_id') && $request->tax_status_id !== null) {
            $data = CustomerTaxBalance::where('tax_status_id', $request->tax_status_id)->paginate(8);
        } else {
            $data = CustomerTaxBalance::paginate(8);
        }
        
        return response()->json([
                'message' => 'تم',
                'status' => 200,
                'data' => $data,
        ], 200);
    }
    
    
    
    public function tabs(Request $request)
    {
        
        $pending = CustomerTaxBalance::where('tax_status_id', 1)->count();
        $approved = CustomerTaxBalance::where('tax_status_id', 2)->count();
        $refused = CustomerTaxBalance::where('tax_status_id', 3)->count();
        $all = CustomerTaxBalance::where('tax_status_id','!=',5)->count();
        

        
        
        return response()->json([
                'message' => 'تم',
                'status' => 200,
                'pending' => $pending,
                'approved' => $approved,
                'refused' => $refused,
                'all' => $all,
        ], 200);
    }



    public function customer_activities($id)
    {
        
        $customer = Customer::find($id);
        $licenseids = License::where('customer_id',$customer->id)->pluck('id');
       // $branchesid = LicenseeBranch::whereIn('license_id',$licenseids)->pluck('id');
        
        $branches = LicenseeBranch::whereIn('license_id',$licenseids)->get();
        
        //$renewals = BranchRenewal::whereIn('license_branch_id', $branchesid)->get();
        $branches->transform(function($branch){
            return [
                'business_name' => $branch->business_name,
                'directorate_name' => $branch->directorate_name,
                'address' => $branch->address,
            ];
        });
        
        return response()->json([
                'message' => 'تم',
                'status' => 200,
                'activities' => $branches,
                
        ], 200);

    }
    
    
    public function large_taxpayers(Request $request)
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
                'tax_type_id' => 'نوع الضريبة',
            
                'discount_percentage_image' => 'صورة اعتماد خصم الغرامة',
            
                'payment_statement_image' => 'صورة كشف سداد تحت الحساب',
                'calculation_image' => 'صورة بيانات الاحتساب'
                ,
                'tax_image' => 'صورة اقرار ضريبة المهن الحرة',
                'value' => 'المبلغ',
                'year.min' => 'لا يمكن إدخال سنة قديمة، يجب أن تكون السنة الحالية أو أكبر.',
                'year.integer' => 'يجب ان يكون رقما',
                'report_number' => 'رقم الاقرار',
            ];
            
            $request->validate([
                'customer_id' => 'required',
                // 'tax_type_id' => 'required',
                'year' => 'required',
                'report_number' => 'required',
                'type_report' => 'required',
            
                'discount_percentage_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
                'payment_statement_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
                
                'calculation_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
                'tax_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
                'report_number' => 'required',
             
                'activities' => 'required|array',  
                'activities.*.business_name' => 'required|string',
                'activities.*.address' => 'required|string',
                'activities.*.total' => 'required|numeric',
                'activities.*.directorate_id' => 'required|integer',
                            // 'value' => 'required',
                
            ], $messages, $attributes);


            $discount_percentage_image = null;
            if ($file = $request->file('discount_percentage_image')) {
                $discount_percentage_image = time() . $file->getClientOriginalName();
                $file->move('balance', $discount_percentage_image);
            }
            
            
            $payment_statement_image = null;
            if ($file = $request->file('payment_statement_image')) {
                $payment_statement_image = time() . $file->getClientOriginalName();
                $file->move('balance', $payment_statement_image);
            }
            
            $calculation_image = null;
            if ($file = $request->file('calculation_image')) {
                $calculation_image = time() . $file->getClientOriginalName();
                $file->move('balance', $calculation_image);
            }
            
            
            $tax_image = null;
            if ($file = $request->file('tax_image')) {
                $tax_image = time() . $file->getClientOriginalName();
                $file->move('balance', $tax_image);
            }
            
            $file = TaxFile::where('customer_id', $request->customer_id)->first();
            
            $item = new CustomerTaxBalance();
            $item->tax_type_id = 2;
            $item->type_report = $request->type_report;
            $item->customer_id = $request->customer_id;
            $item->year = $request->year;
            $item->value = $request->value;
            $item->discount_percentage_image = $discount_percentage_image;
            $item->payment_statement_image = $payment_statement_image;
            $item->calculation_image = $calculation_image;
            $item->tax_image = $tax_image;
            $item->tax_status_id = 1;
            $item->user_id = auth('api')->user()->id;
            $item->declaration_id = 4;
            $item->tax_percentage = $request->tax_percentage;
            $item->tax_file_id = $file->id;
            $item->report_number = $request->report_number;
            $item->save();
            
            if ($request->activities && is_array($request->activities)) {

                foreach ($request->activities as $act) {
                    $item->activities()->create([
                        'business_name'  => $act['business_name'] ?? null,
                        'address'           => $act['address'] ?? null,
                        'total'    => $act['total'] ?? null,
                        'directorate_id'    => $act['directorate_id'] ?? null,
                       
                    ]);
                }
            }
           
            return response()->json([
                'message' => 'تم انشاء الطلب بنجاح',
                'status' => 200,
                'type' => 'add_large_taxpayers',
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
    
     public function update_large_taxpayers(Request $request, $id)
    {
        try {
    
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
            ];
    
            $attributes = [
                'customer_id' => 'المكلف',
                
                'discount_percentage_image' => 'صورة اعتماد خصم الغرامة',
                'payment_statement_image' => 'صورة كشف سداد تحت الحساب',
                'calculation_image' => 'صورة بيانات الاحتساب',
                'tax_image' => 'صورة اقرار ضريبة المهن الحرة',
                'value' => 'المبلغ',
            ];
    
            $request->validate([
                'customer_id' => 'required',
                
                'year' => 'required',
                // 'value' => 'required', value is not requird
    
                'discount_percentage_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'payment_statement_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'calculation_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'tax_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    
            ], $messages, $attributes);
    
            // جلب السجل
            $item = CustomerTaxBalance::findOrFail($id);
    
            // حفظ الصور
            if ($file = $request->file('discount_percentage_image')) {
                $item->discount_percentage_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->discount_percentage_image);
            }
    
            if ($file = $request->file('payment_statement_image')) {
                $item->payment_statement_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->payment_statement_image);
            }
    
            if ($file = $request->file('calculation_image')) {
                $item->calculation_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->calculation_image);
            }
    
            if ($file = $request->file('tax_image')) {
                $item->tax_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->tax_image);
            }
    
            // تحديث البيانات الأساسية
           
            
            $item->year = $request->year;
            //$item->value = $request->value;
            $item->tax_status_id = 1;
            $item->report_number = $request->report_number;
            $item->tax_percentage = $request->tax_percentage;
            $item->save();
    
    
            
             if ($request->activities && is_array($request->activities)) 
             {
                $item->activities()->delete();
                foreach ($request->activities as $act) {
                    $item->activities()->create([
                        'business_name'  => $act['business_name'] ?? null,
                        'address'           => $act['address'] ?? null,
                        'total'    => $act['total'] ?? null,
                        'directorate_id'    => $act['directorate_id'] ?? null,
                       
                    ]);
                }
            }
    
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    public function approve_large_taxpayers(Request $request, $id)
    {
        try {
    
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
            ];
    
            $attributes = [
                'customer_id' => 'المكلف',
                'tax_type_id' => 'نوع الضريبة',
                'discount_percentage_image' => 'صورة اعتماد خصم الغرامة',
                'payment_statement_image' => 'صورة كشف سداد تحت الحساب',
                'calculation_image' => 'صورة بيانات الاحتساب',
                'tax_image' => 'صورة اقرار ضريبة المهن الحرة',
                'value' => 'المبلغ',
               
            ];
    
            $request->validate([
                'customer_id' => 'nullable',
                'tax_type_id' => 'nullable',
                'year' => 'required',
                'value' => 'nullable',
                
    
                'discount_percentage_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'payment_statement_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'calculation_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'tax_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    
            ], $messages, $attributes);
    
            // جلب السجل
            $item = CustomerTaxBalance::findOrFail($id);
    
            // حفظ الصور
            if ($file = $request->file('discount_percentage_image')) {
                $item->discount_percentage_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->discount_percentage_image);
            }
    
            if ($file = $request->file('payment_statement_image')) {
                $item->payment_statement_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->payment_statement_image);
            }
    
            if ($file = $request->file('calculation_image')) {
                $item->calculation_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->calculation_image);
            }
    
            if ($file = $request->file('tax_image')) {
                $item->tax_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->tax_image);
            }
    
            // تحديث البيانات الأساسية
            
          
            $item->year = $request->year;
            $item->value = $request->value;
    
            
            $item->tax_status_id = 2; 
        
    
            $item->save();
    
         
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    public function refuse_large_taxpayers(Request $request, $id)
    {
        try {
    
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
            ];
    
            $attributes = [
                
                'tax_type_id' => 'نوع الضريبة',
                'discount_percentage_image' => 'صورة اعتماد خصم الغرامة',
                'payment_statement_image' => 'صورة كشف سداد تحت الحساب',
                'calculation_image' => 'صورة بيانات الاحتساب',
                'tax_image' => 'صورة اقرار ضريبة المهن الحرة',
                'value' => 'المبلغ',
                'notes'=> 'الملاحظات',
               
            ];
    
            $request->validate([
                'customer_id' => 'nullable',
                'tax_type_id' => 'nullable',
               
                'value' => 'nullable',
                'notes' => 'required',
    
                'discount_percentage_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'payment_statement_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'calculation_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'tax_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    
            ], $messages, $attributes);
    
            // جلب السجل
            $item = CustomerTaxBalance::findOrFail($id);
    
            // حفظ الصور
            if ($file = $request->file('discount_percentage_image')) {
                $item->discount_percentage_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->discount_percentage_image);
            }
    
            if ($file = $request->file('payment_statement_image')) {
                $item->payment_statement_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->payment_statement_image);
            }
    
            if ($file = $request->file('calculation_image')) {
                $item->calculation_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->calculation_image);
            }
    
            if ($file = $request->file('tax_image')) {
                $item->tax_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->tax_image);
            }
    
            // تحديث البيانات الأساسية
        
            $item->notes = $request->notes;
            
            $item->tax_status_id = 3; 
        
    
            $item->save();
    
           
    
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    
    public function print_large_taxpayers(Request $request, $id)
    {
        try {
    
            
    
            // جلب السجل
            $item = CustomerTaxBalance::findOrFail($id);
    
            $item->tax_status_id = 4; 
        
    
            $item->save();
    
    
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    
    public function finance_large_taxpayers(Request $request, $id)
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
              //  'payment_receipt_number' => 'required',
                
            //   'payment_receipt_number' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            
            
         // 'payment_receipt_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',

            ], $messages, $attributes);
    
            // جلب السجل
            $item = CustomerTaxBalance::findOrFail($id);
            if ($file = $request->file('payment_receipt_image')) {
                $item->payment_receipt_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->payment_receipt_image);
            }
             $supply_voucher_image = null;
            if ($file = $request->file('supply_voucher_image')) {
                $supply_voucher_image = time() . $file->getClientOriginalName();
                $file->move('balance', $supply_voucher_image);
            }
            
            $extra_image = null;
            if ($file = $request->file('extra_image')) {
                $extra_image = time() . $file->getClientOriginalName();
                $file->move('balance', $extra_image);
            }
            
            $check_image = null;
            if ($file = $request->file('check_image')) {
                $check_image = time() . $file->getClientOriginalName();
                $file->move('balance', $check_image);
            }
            
            $item->tax_status_id = 5; 
            $item->payment_receipt_number = $request->payment_receipt_number;
            $item->payment_type_id = $request->payment_type_id;
            $item->bank_id = $request->bank_id;
            $item->check_number = $request->check_number;
            $item->check_value = $request->check_value;
            $item->check_image = $check_image;
            $item->supply_voucher_image = $supply_voucher_image;  
            $item->payment_date = $request->payment_date;
            $item->extra_image = $extra_image;
            $item->payment_value = $request->payment_value;
            $item->save();
    
            $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "تم التحصيل";
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
    
    
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    
    
    
    public function meduim_taxpayers(Request $request)
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
                'tax_type_id' => 'نوع الضريبة',
            
                'discount_percentage_image' => 'صورة اعتماد خصم الغرامة',
            
                'payment_statement_image' => 'صورة كشف سداد تحت الحساب',
                'calculation_image' => 'صورة بيانات الاحتساب'
                ,
                'tax_image' => 'صورة اقرار ضريبة المهن الحرة',
                'value' => 'المبلغ',
                'year.min' => 'لا يمكن إدخال سنة قديمة، يجب أن تكون السنة الحالية أو أكبر.',
                'year.integer' => 'يجب ان يكون رقما',
                'report_number' => 'رقم الاقرار',
            ];
            
            $request->validate([
                'customer_id' => 'required',
                // 'tax_type_id' => 'required',
                'year' => 'required',
                'report_number' => 'required',
                'type_report' => 'required',
            
                'discount_percentage_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
                'payment_statement_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
                
                'calculation_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
                'tax_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
                'report_number' => 'required',
             
                'activities' => 'required|array',  
                'activities.*.business_name' => 'required|string',
                'activities.*.address' => 'required|string',
                'activities.*.total' => 'required|numeric',
                'activities.*.directorate_id' => 'required|integer',
                            // 'value' => 'required',
                
            ], $messages, $attributes);


            $discount_percentage_image = null;
            if ($file = $request->file('discount_percentage_image')) {
                $discount_percentage_image = time() . $file->getClientOriginalName();
                $file->move('balance', $discount_percentage_image);
            }
            
            
            $payment_statement_image = null;
            if ($file = $request->file('payment_statement_image')) {
                $payment_statement_image = time() . $file->getClientOriginalName();
                $file->move('balance', $payment_statement_image);
            }
            
            $calculation_image = null;
            if ($file = $request->file('calculation_image')) {
                $calculation_image = time() . $file->getClientOriginalName();
                $file->move('balance', $calculation_image);
            }
            
            
            $tax_image = null;
            if ($file = $request->file('tax_image')) {
                $tax_image = time() . $file->getClientOriginalName();
                $file->move('balance', $tax_image);
            }
            
            $file = TaxFile::where('customer_id', $request->customer_id)->first();
            
            $item = new CustomerTaxBalance();
            $item->tax_type_id = 2;
            $item->type_report = $request->type_report;
            $item->customer_id = $request->customer_id;
            $item->year = $request->year;
            $item->value = $request->value;
            $item->discount_percentage_image = $discount_percentage_image;
            $item->payment_statement_image = $payment_statement_image;
            $item->calculation_image = $calculation_image;
            $item->tax_image = $tax_image;
            $item->tax_status_id = 1;
            $item->user_id = auth('api')->user()->id;
            $item->declaration_id = 3;
            $item->tax_percentage = $request->tax_percentage;
            $item->tax_file_id = $file->id;
            $item->report_number = $request->report_number;
            $item->save();
            
            if ($request->activities && is_array($request->activities)) {

                foreach ($request->activities as $act) {
                    $item->activities()->create([
                        'business_name'  => $act['business_name'] ?? null,
                        'address'           => $act['address'] ?? null,
                        'total'    => $act['total'] ?? null,
                        'directorate_id'    => $act['directorate_id'] ?? null,
                       
                    ]);
                }
            }
           
            return response()->json([
                'message' => 'تم انشاء الطلب بنجاح',
                'status' => 200,
                'type' => 'add_large_taxpayers',
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
    
     public function update_meduim_taxpayers(Request $request, $id)
    {
        try {
    
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
            ];
    
            $attributes = [
                'customer_id' => 'المكلف',
                
                'discount_percentage_image' => 'صورة اعتماد خصم الغرامة',
                'payment_statement_image' => 'صورة كشف سداد تحت الحساب',
                'calculation_image' => 'صورة بيانات الاحتساب',
                'tax_image' => 'صورة اقرار ضريبة المهن الحرة',
                'value' => 'المبلغ',
            ];
    
            $request->validate([
                'customer_id' => 'required',
                
                'year' => 'required',
                // 'value' => 'required', value is not requird
    
                'discount_percentage_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'payment_statement_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'calculation_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'tax_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    
            ], $messages, $attributes);
    
            // جلب السجل
            $item = CustomerTaxBalance::findOrFail($id);
    
            // حفظ الصور
            if ($file = $request->file('discount_percentage_image')) {
                $item->discount_percentage_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->discount_percentage_image);
            }
    
            if ($file = $request->file('payment_statement_image')) {
                $item->payment_statement_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->payment_statement_image);
            }
    
            if ($file = $request->file('calculation_image')) {
                $item->calculation_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->calculation_image);
            }
    
            if ($file = $request->file('tax_image')) {
                $item->tax_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->tax_image);
            }
    
            // تحديث البيانات الأساسية
           
            
            $item->year = $request->year;
            //$item->value = $request->value;
            $item->tax_status_id = 1;
            $item->report_number = $request->report_number;
            $item->save();
    
    
            
             if ($request->activities && is_array($request->activities)) 
             {
                $item->activities()->delete();
                foreach ($request->activities as $act) {
                    $item->activities()->create([
                        'business_name'  => $act['business_name'] ?? null,
                        'address'           => $act['address'] ?? null,
                        'total'    => $act['total'] ?? null,
                        'directorate_id'    => $act['directorate_id'] ?? null,
                       
                    ]);
                }
            }
    
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    public function approve_meduim_taxpayers(Request $request, $id)
    {
        try {
    
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
            ];
    
            $attributes = [
                'customer_id' => 'المكلف',
                'tax_type_id' => 'نوع الضريبة',
                'discount_percentage_image' => 'صورة اعتماد خصم الغرامة',
                'payment_statement_image' => 'صورة كشف سداد تحت الحساب',
                'calculation_image' => 'صورة بيانات الاحتساب',
                'tax_image' => 'صورة اقرار ضريبة المهن الحرة',
                'value' => 'المبلغ',
               
            ];
    
            $request->validate([
                'customer_id' => 'nullable',
                'tax_type_id' => 'nullable',
                'year' => 'required',
                'value' => 'nullable',
                
    
                'discount_percentage_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'payment_statement_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'calculation_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'tax_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    
            ], $messages, $attributes);
    
            // جلب السجل
            $item = CustomerTaxBalance::findOrFail($id);
    
            // حفظ الصور
            if ($file = $request->file('discount_percentage_image')) {
                $item->discount_percentage_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->discount_percentage_image);
            }
    
            if ($file = $request->file('payment_statement_image')) {
                $item->payment_statement_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->payment_statement_image);
            }
    
            if ($file = $request->file('calculation_image')) {
                $item->calculation_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->calculation_image);
            }
    
            if ($file = $request->file('tax_image')) {
                $item->tax_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->tax_image);
            }
    
            // تحديث البيانات الأساسية
            
          
            $item->year = $request->year;
            $item->value = $request->value;
    
            
            $item->tax_status_id = 2; 
        
    
            $item->save();
    
         
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    public function refuse_meduim_taxpayers(Request $request, $id)
    {
        try {
    
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
            ];
    
            $attributes = [
                
                'tax_type_id' => 'نوع الضريبة',
                'discount_percentage_image' => 'صورة اعتماد خصم الغرامة',
                'payment_statement_image' => 'صورة كشف سداد تحت الحساب',
                'calculation_image' => 'صورة بيانات الاحتساب',
                'tax_image' => 'صورة اقرار ضريبة المهن الحرة',
                'value' => 'المبلغ',
                'notes'=> 'الملاحظات',
               
            ];
    
            $request->validate([
                'customer_id' => 'nullable',
                'tax_type_id' => 'nullable',
               
                'value' => 'nullable',
                'notes' => 'required',
    
                'discount_percentage_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'payment_statement_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'calculation_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'tax_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    
            ], $messages, $attributes);
    
            // جلب السجل
            $item = CustomerTaxBalance::findOrFail($id);
    
            // حفظ الصور
            if ($file = $request->file('discount_percentage_image')) {
                $item->discount_percentage_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->discount_percentage_image);
            }
    
            if ($file = $request->file('payment_statement_image')) {
                $item->payment_statement_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->payment_statement_image);
            }
    
            if ($file = $request->file('calculation_image')) {
                $item->calculation_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->calculation_image);
            }
    
            if ($file = $request->file('tax_image')) {
                $item->tax_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->tax_image);
            }
    
            // تحديث البيانات الأساسية
        
            $item->notes = $request->notes;
            
            $item->tax_status_id = 3; 
        
    
            $item->save();
    
           
    
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    
    public function print_meduim_taxpayers(Request $request, $id)
    {
        try {
    
            
    
            // جلب السجل
            $item = CustomerTaxBalance::findOrFail($id);
    
            $item->tax_status_id = 4; 
        
    
            $item->save();
    
    
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    
    public function finance_meduim_taxpayers(Request $request, $id)
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
            $item = CustomerTaxBalance::findOrFail($id);
            if ($file = $request->file('payment_receipt_image')) {
                $item->payment_receipt_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->payment_receipt_image);
            }
            $item->tax_status_id = 5; 
            $item->payment_receipt_number = $request->payment_receipt_number;
    
            $item->save();
    
    
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    
    
    
    public function small_taxpayers(Request $request)
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
                'tax_type_id' => 'نوع الضريبة',
            
                'discount_percentage_image' => 'صورة اعتماد خصم الغرامة',
            
                'payment_statement_image' => 'صورة كشف سداد تحت الحساب',
                'calculation_image' => 'صورة بيانات الاحتساب'
                ,
                'tax_image' => 'صورة اقرار ضريبة المهن الحرة',
                'value' => 'المبلغ',
                'year.min' => 'لا يمكن إدخال سنة قديمة، يجب أن تكون السنة الحالية أو أكبر.',
                'year.integer' => 'يجب ان يكون رقما',
                'report_number' => 'رقم الاقرار',
            ];
            
            $request->validate([
                'customer_id' => 'required',
                // 'tax_type_id' => 'required',
                'year' => 'required',
                'report_number' => 'required',
                'type_report' => 'required',
                'value' => 'required',
            
                'discount_percentage_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048|required_with:tax_percentage',

                'payment_statement_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                
                'calculation_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'tax_image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
                'report_number' => 'required',
                 'tax_percentage' => 'nullable|numeric',

                //'activities' => 'required|array',  
                //'activities.*.business_name' => 'nullable|string',
                //'activities.*.address' => 'required|string',
                //'activities.*.total' => 'required|numeric',
               // 'activities.*.directorate_id' => 'required|integer',
                            // 'value' => 'required',
                
            ], $messages, $attributes);


            $discount_percentage_image = null;
            if ($file = $request->file('discount_percentage_image')) {
                $discount_percentage_image = time() . $file->getClientOriginalName();
                $file->move('balance', $discount_percentage_image);
            }
            
            
            $payment_statement_image = null;
            if ($file = $request->file('payment_statement_image')) {
                $payment_statement_image = time() . $file->getClientOriginalName();
                $file->move('balance', $payment_statement_image);
            }
            
            $calculation_image = null;
            if ($file = $request->file('calculation_image')) {
                $calculation_image = time() . $file->getClientOriginalName();
                $file->move('balance', $calculation_image);
            }
            
            
            $tax_image = null;
            if ($file = $request->file('tax_image')) {
                $tax_image = time() . $file->getClientOriginalName();
                $file->move('balance', $tax_image);
            }
            
            $file = TaxFile::where('customer_id', $request->customer_id)->first();
            
            $item = new CustomerTaxBalance();
            $item->tax_type_id = 2;
            $item->type_report = $request->type_report;
            $item->customer_id = $request->customer_id;
            $item->year = $request->year;
           $item->value = $request->value;
            $item->discount_percentage_image = $discount_percentage_image;
            $item->payment_statement_image = $payment_statement_image;
            $item->calculation_image = $calculation_image;
            $item->tax_image = $tax_image;
            $item->tax_status_id = 1;
            $item->user_id = auth('api')->user()->id;
            $item->declaration_id = 2;
            $item->tax_percentage = $request->tax_percentage;
            $item->tax_file_id = $file->id;
            $item->report_number = $request->report_number;
            $item->notes = $request->notes;
            $item->total = $request->total;
            $item->save();
            
            //if ($request->activities && is_array($request->activities)) {

               // foreach ($request->activities as $act) {
                   // $item->activities()->create([
                    //    'business_name'  => $act['business_name'] ?? null,
                      //  'address'           => $act['address'] ?? null,
                     //   'value'    => $act['value'] ?? null,
                     //   'total'    => $act['total'] ?? null,
                     //   'directorate_id'    => $act['directorate_id'] ?? null,
                       
                   // ]);
               // }
            //}
            
            $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "اضافة اقرار دخل سنوي - منشأت صغيرة";
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
           
            return response()->json([
                'message' => 'تم انشاء الطلب بنجاح',
                'status' => 200,
                'type' => 'add_large_taxpayers',
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
    
     public function update_small_taxpayers(Request $request, $id)
    {
        try {
    
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
            ];
    
            $attributes = [
                'customer_id' => 'المكلف',
                
                'discount_percentage_image' => 'صورة اعتماد خصم الغرامة',
                'payment_statement_image' => 'صورة كشف سداد تحت الحساب',
                'calculation_image' => 'صورة بيانات الاحتساب',
                'tax_image' => 'صورة اقرار ضريبة المهن الحرة',
               
            ];
    
            $request->validate([
                'customer_id' => 'required',
                
                'year' => 'required',
                'value' => 'required',
    
                'discount_percentage_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'payment_statement_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'calculation_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'tax_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    
            ], $messages, $attributes);
    
            // جلب السجل
            $item = CustomerTaxBalance::findOrFail($id);
    
            // حفظ الصور
            if ($file = $request->file('discount_percentage_image')) {
                $item->discount_percentage_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->discount_percentage_image);
            }
    
            if ($file = $request->file('payment_statement_image')) {
                $item->payment_statement_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->payment_statement_image);
            }
    
            if ($file = $request->file('calculation_image')) {
                $item->calculation_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->calculation_image);
            }
    
            if ($file = $request->file('tax_image')) {
                $item->tax_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->tax_image);
            }
    
            // تحديث البيانات الأساسية
           
            $oldData = $item->toArray();
            
            $item->year = $request->year;
           
            $item->tax_status_id = 1;
            $item->report_number = $request->report_number;
            $item->notes = $request->notes;
            $item->tax_percentage = $request->tax_percentage;
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
    
           $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = json_encode($changes, JSON_UNESCAPED_UNICODE);
            $log->notes = $item->notes;
            $log->value = $item->value;
            $log->total = $item->total;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
    
            
            // if ($request->activities && is_array($request->activities)) 
            // {
            //    $item->activities()->delete();
            //    foreach ($request->activities as $act) {
                  //  $item->activities()->create([
                  //       'business_name'  => $act['business_name'] ?? null,
                  //      'address'           => $act['address'] ?? null,
                   //     'value'    => $act['value'] ?? null,
                   //     'total'    => $act['total'] ?? null,
                     //   'directorate_id'    => $act['directorate_id'] ?? null,
                       
                   // ]);
              //  }
          //  }
    
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    public function approve_small_taxpayers(Request $request, $id)
    {
        try {
    
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
            ];
    
            $attributes = [
                'customer_id' => 'المكلف',
                'tax_type_id' => 'نوع الضريبة',
                'discount_percentage_image' => 'صورة اعتماد خصم الغرامة',
                'payment_statement_image' => 'صورة كشف سداد تحت الحساب',
                'calculation_image' => 'صورة بيانات الاحتساب',
                'tax_image' => 'صورة اقرار ضريبة المهن الحرة',
               
               
            ];
    
            $request->validate([
                'customer_id' => 'nullable',
                'tax_type_id' => 'nullable',
                'year' => 'required',
               'value' => 'required',
                
    
                'discount_percentage_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'payment_statement_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'calculation_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'tax_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    
            ], $messages, $attributes);
    
            // جلب السجل
            $item = CustomerTaxBalance::findOrFail($id);
    
            // حفظ الصور
            if ($file = $request->file('discount_percentage_image')) {
                $item->discount_percentage_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->discount_percentage_image);
            }
    
            if ($file = $request->file('payment_statement_image')) {
                $item->payment_statement_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->payment_statement_image);
            }
    
            if ($file = $request->file('calculation_image')) {
                $item->calculation_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->calculation_image);
            }
    
            if ($file = $request->file('tax_image')) {
                $item->tax_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->tax_image);
            }
    
            // تحديث البيانات الأساسية
            
          
            $item->year = $request->year;
            $item->value = $request->value;
            $item->total = $request->total;
            $item->notes = $request->notes;
            $item->tax_status_id = 2; 
            $item->tax_percentage = $request->tax_percentage;
    
            $item->save();
    
            $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "اعتماد اقرار دخل سنوي - منشأت صغيرة";
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
         
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    public function refuse_small_taxpayers(Request $request, $id)
    {
        try {
    
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
            ];
    
            $attributes = [
                
                'tax_type_id' => 'نوع الضريبة',
                'discount_percentage_image' => 'صورة اعتماد خصم الغرامة',
                'payment_statement_image' => 'صورة كشف سداد تحت الحساب',
                'calculation_image' => 'صورة بيانات الاحتساب',
                'tax_image' => 'صورة اقرار ضريبة المهن الحرة',
                'value' => 'المبلغ',
                'notes'=> 'الملاحظات',
               
            ];
    
            $request->validate([
                'customer_id' => 'nullable',
                'tax_type_id' => 'nullable',
               
                'value' => 'nullable',
                'notes' => 'required',
    
                'discount_percentage_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'payment_statement_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'calculation_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'tax_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    
            ], $messages, $attributes);
    
            // جلب السجل
            $item = CustomerTaxBalance::findOrFail($id);
    
            // حفظ الصور
            if ($file = $request->file('discount_percentage_image')) {
                $item->discount_percentage_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->discount_percentage_image);
            }
    
            if ($file = $request->file('payment_statement_image')) {
                $item->payment_statement_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->payment_statement_image);
            }
    
            if ($file = $request->file('calculation_image')) {
                $item->calculation_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->calculation_image);
            }
    
            if ($file = $request->file('tax_image')) {
                $item->tax_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->tax_image);
            }
    
            // تحديث البيانات الأساسية
        
            $item->notes = $request->notes;
            
            $item->tax_status_id = 3; 
        
    
            $item->save();
    
           $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "رفض اقرار دخل سنوي - منشأت صغيرة";
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
    
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    
    public function print_small_taxpayers(Request $request, $id)
    {
        try {
    
            
    
            // جلب السجل
            $item = CustomerTaxBalance::findOrFail($id);
    
            $item->tax_status_id = 4; 
        
    
            $item->save();
    
            $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "طباعة حافظة دخل سنوي - منشأت صغيرة";
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
    
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    
    public function finance_small_taxpayers(Request $request, $id)
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
            
            
                'payment_type_id' => 'required',
            
              //  'payment_receipt_image' => 'required_if:payment_type_id,1|image|mimes:jpg,jpeg,png|max:2048',
                    'bank_id'      => 'required_if:payment_type_id,2',
                'check_number' => 'required_if:payment_type_id,2',
                'check_value' => 'required_if:payment_type_id,2',
                'check_image'  => 'required_if:payment_type_id,2|image|mimes:jpg,jpeg,png|max:2048',
                'supply_voucher_image'  => 'required|image|mimes:jpg,jpeg,png|max:2048',

            ], $messages, $attributes);
    
            // جلب السجل
            $item = CustomerTaxBalance::findOrFail($id);
            if ($file = $request->file('payment_receipt_image')) {
                $item->payment_receipt_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->payment_receipt_image);
            }
             $supply_voucher_image = null;
            if ($file = $request->file('supply_voucher_image')) {
                $supply_voucher_image = time() . $file->getClientOriginalName();
                $file->move('balance', $supply_voucher_image);
            }
            
            $extra_image = null;
            if ($file = $request->file('extra_image')) {
                $extra_image = time() . $file->getClientOriginalName();
                $file->move('balance', $extra_image);
            }
            
            $check_image = null;
            if ($file = $request->file('check_image')) {
                $check_image = time() . $file->getClientOriginalName();
                $file->move('balance', $check_image);
            }
            
            $item->tax_status_id = 5; 
            $item->payment_receipt_number = $request->payment_receipt_number;
            $item->payment_type_id = $request->payment_type_id;
            $item->bank_id = $request->bank_id;
            $item->check_number = $request->check_number;
            $item->check_value = $request->check_value;
            $item->check_image = $check_image;
            $item->supply_voucher_image = $supply_voucher_image;  
            $item->payment_date = $request->payment_date;
            $item->extra_image = $extra_image;
            $item->payment_value = $request->payment_value;
            $item->save();
    
            $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "تم التحصيل";
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
    
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    
    public function tax_table_by_id($id)
    {
           $item = CustomerTaxBalance::find($id)->get();
           
           return response()->json([
                'message' => 'تم',
                'status' => 200,
                'data' => $item,
            ], 200);
           
    
    }
    
    
    function normalizeNumber($value)
    {
        if (!$value) return 0;
    
        // تحويل الأرقام العربية إلى إنجليزية
        $arabic  = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        $english = ['0','1','2','3','4','5','6','7','8','9'];
    
        $value = str_replace($arabic, $english, $value);
    
        // حذف أي شيء غير الأرقام والنقطة
        $value = preg_replace('/[^0-9.]/', '', $value);
    
        return (float) $value;
    }


   public function owned_properties(Request $request, $customer_id)
    {
        $request->validate([
            'year' => 'required',    
        ]);
        $year = $request->year ?? now()->year;
    
        $yearStart = Carbon::createFromDate($year, 1, 1)->startOfMonth();
        $yearEnd   = Carbon::createFromDate($year, 12, 31)->endOfMonth();
    
        $properties = BranchRenewal::where('owner_id', $customer_id)
            ->whereNotNull('rent_start_date')
            ->with([
                'license_branch:id,address,license_id',
                'license_branch.license:id,customer_id',
                'license_branch.license.customer:id,name',
            ])
            ->get()
            ->map(function ($item) use ($yearStart, $yearEnd) {
    
                $rentStart = Carbon::parse($item->rent_start_date)->startOfMonth();
    
                $rentEnd = $item->rent_end_date
                    ? Carbon::parse($item->rent_end_date)->endOfMonth()
                    : $yearEnd;
    
                $effectiveStart = $rentStart->lt($yearStart) ? $yearStart : $rentStart;
                $effectiveEnd   = $rentEnd->gt($yearEnd) ? $yearEnd : $rentEnd;
    
                if ($effectiveStart->gt($effectiveEnd)) {
                    $actualMonths = 0;
                } else {
                    $actualMonths = $effectiveStart->diffInMonths($effectiveEnd) + 1;
                }
    
                return [
                    'id'              => $item->id,
                    'address'         => $item->license_branch->address ?? '',
                    'tenant_name'     => optional(optional($item->license_branch->license)->customer)->name ?? '',
                    'usage'           => 'تجاري',
                    'monthly_rent'    => $this->normalizeNumber($item->rent_value),
                    'actual_months'   => $actualMonths,
                    'currency_id'     => $item->currency_id,
                    'tax_value'       => ($item->rent_value * $actualMonths * $item->currency_price) / 12,
                    'currency_price'  => $item->currency_price,
                ];
            });
    
        return response()->json($properties);
    }








     public function real_state(Request $request)
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
                'tax_type_id' => 'نوع الضريبة',
            
                'discount_percentage_image' => 'صورة اعتماد خصم الغرامة',
            
                'payment_statement_image' => 'صورة كشف سداد تحت الحساب',
                'rent_exclusion_image' => 'صورة مذكرة استبعاد أشهر الايجار'
                ,
                'rent_tax_image' => 'صورة اقرار ضريبة العقار',
                'value' => 'المبلغ',
                'year.min' => 'لا يمكن إدخال سنة قديمة، يجب أن تكون السنة الحالية أو أكبر.',
                'year.integer' => 'يجب ان يكون رقما',
                'report_number' => 'رقم الاقرار',
            ];
            
            $request->validate([
                'customer_id' => 'required',
                // 'tax_type_id' => 'required',
                'year' => 'required',
                'report_number' => 'required',
                'type_report' => 'required',
            
                'discount_percentage_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'payment_statement_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                
                'rent_exclusion_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'rent_tax_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'report_number' => 'required',
             
                'buildings' => 'required|array',  
                'buildings.*.tenant_name' => 'required|string',
                'buildings.*.usage' => 'required|string',
                'buildings.*.monthly_rent' => 'required',
                'buildings.*.excluded_months' => 'required',
                'buildings.*.tax_value' => 'required',
                'buildings.*.excluded_months' => 'required',
                            // 'value' => 'required',
                
            ], $messages, $attributes);


            $discount_percentage_image = null;
            if ($file = $request->file('discount_percentage_image')) {
                $discount_percentage_image = time() . $file->getClientOriginalName();
                $file->move('balance', $discount_percentage_image);
            }
            
            
            $payment_statement_image = null;
            if ($file = $request->file('payment_statement_image')) {
                $payment_statement_image = time() . $file->getClientOriginalName();
                $file->move('balance', $payment_statement_image);
            }
            
            $rent_exclusion_image = null;
            if ($file = $request->file('rent_exclusion_image')) {
                $rent_exclusion_image = time() . $file->getClientOriginalName();
                $file->move('balance', $rent_exclusion_image);
            }
            
            
            $rent_tax_image = null;
            if ($file = $request->file('rent_tax_image')) {
                $rent_tax_image = time() . $file->getClientOriginalName();
                $file->move('balance', $rent_tax_image);
            }
            
              $supply_voucher_image = null;
            if ($file = $request->file('supply_voucher_image')) {
                $supply_voucher_image = time() . $file->getClientOriginalName();
                $file->move('balance', $supply_voucher_image);
            }
            
            $extra_image = null;
            if ($file = $request->file('extra_image')) {
                $extra_image = time() . $file->getClientOriginalName();
                $file->move('balance', $extra_image);
            }
            
            $check_image = null;
            if ($file = $request->file('check_image')) {
                $check_image = time() . $file->getClientOriginalName();
                $file->move('balance', $check_image);
            }
            
            $file = TaxFile::where('customer_id', $request->customer_id)->first();
            
            $item = new CustomerTaxBalance();
            $item->tax_type_id = 1;
            $item->type_report = $request->type_report;
            $item->customer_id = $request->customer_id;
            $item->year = $request->year;
            $item->value = $request->value;
            $item->discount_percentage_image = $discount_percentage_image;
            $item->payment_statement_image = $payment_statement_image;
            $item->rent_exclusion_image = $rent_exclusion_image;
            $item->rent_tax_image = $rent_tax_image;
            $item->tax_status_id = 1;
            $item->user_id = auth('api')->user()->id;
            $item->declaration_id = 1;
            $item->tax_percentage = $request->tax_percentage;
            $item->tax_file_id = $file->id;
            $item->report_number = $request->report_number;
            $item->notes = $request->notes;
            $item->save();
            
            if ($request->buildings && is_array($request->buildings)) {

                foreach ($request->buildings as $build) {
                    $item->buildings()->create([
                        'tenant_name'  => $build['tenant_name'] ?? null,
                        'address'           => $build['address'] ?? null,
                        'usage'    => $build['usage'] ?? null,
                        'monthly_rent'    => $build['monthly_rent'] ?? null,
                        'actual_months'    => $build['actual_months'] ?? null,
                        'excluded_months'    => $build['excluded_months'] ?? null,
                       'tax_value'    => $build['tax_value'] ?? null,
                    ]);
                }
            }
           
            $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "اضافة أقرار ضريبة ريع عقار";
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
            return response()->json([
                'message' => 'تم انشاء الطلب بنجاح',
                'status' => 200,
                'type' => 'add_large_taxpayers',
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
    
    public function update_real_state(Request $request, $id)
    {
        try {
    
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
            ];
    
            $attributes = [
                'customer_id' => 'المكلف',
                
                'discount_percentage_image' => 'صورة اعتماد خصم الغرامة',
                'payment_statement_image' => 'صورة كشف سداد تحت الحساب',
                'rent_exclusion_image' => 'صورة بيانات الاحتساب',
                'rent_tax_image' => 'صورة اقرار ضريبة ريع العقار',
               
            ];
    
            $request->validate([
                
                
                'year' => 'required',
                // 'value' => 'required', value is not requird
    
                'discount_percentage_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'payment_statement_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'rent_exclusion_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'rent_tax_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    
            ], $messages, $attributes);
    
            // جلب السجل
            $item = CustomerTaxBalance::findOrFail($id);
    
            // حفظ الصور
            if ($file = $request->file('discount_percentage_image')) {
                $item->discount_percentage_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->discount_percentage_image);
            }
    
            if ($file = $request->file('payment_statement_image')) {
                $item->payment_statement_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->payment_statement_image);
            }
    
            if ($file = $request->file('rent_exclusion_image')) {
                $item->rent_exclusion_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->rent_exclusion_image);
            }
    
            if ($file = $request->file('rent_tax_image')) {
                $item->rent_tax_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->rent_tax_image);
            }
    
            // تحديث البيانات الأساسية
           
            
            $item->year = $request->year;
            //$item->value = $request->value;
            $item->tax_status_id = 1;
            $item->report_number = $request->report_number;
            $item->save();
    
            if ($request->buildings && is_array($request->buildings)) {

                $item->buildings()->delete();
                foreach ($request->buildings as $build) {
                    $item->buildings()->create([
                        'tenant_name'  => $build['tenant_name'] ?? null,
                        'address'           => $build['address'] ?? null,
                        'usage'    => $build['usage'] ?? null,
                        'monthly_rent'    => $build['monthly_rent'] ?? null,
                        'actual_months'    => $build['actual_months'] ?? null,
                        'excluded_months'    => $build['excluded_months'] ?? null,
                       'tax_value'    => $build['tax_value'] ?? null,
                    ]);
                }
            }
    
             $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "اتعديل اقرار ضريبة ريع العقار";
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
            
            
    
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    
    public function approve_real_state(Request $request, $id)
    {
        try {
    
            $messages = [
                'required' => 'حقل :attribute مطلوب.',
                'image' => 'حقل :attribute يجب أن يكون صورة.',
                'mimes' => 'حقل :attribute يجب أن يكون بصيغة jpg أو jpeg أو png.',
                'max' => 'حقل :attribute يجب ألا يتجاوز 2 ميجا.',
            ];
    
            $attributes = [
                'customer_id' => 'المكلف',
                'tax_type_id' => 'نوع الضريبة',
                'discount_percentage_image' => 'صورة اعتماد خصم الغرامة',
                'payment_statement_image' => 'صورة كشف سداد تحت الحساب',
                'rent_exclusion_image' => 'صورة مذكرة استبعاد اشهر الايجار',
                'rent_tax_image' => 'صورة اقرار ضريبة ريع العقار',
                'value' => 'المبلغ',
               
               
            ];
    
            $request->validate([
                'customer_id' => 'nullable',
                'tax_type_id' => 'nullable',
                'year' => 'required',
                'value' => 'nullable',
              
    
                'discount_percentage_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'payment_statement_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'rent_exclusion_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'rent_tax_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    
            ], $messages, $attributes);
    
            // جلب السجل
            $item = CustomerTaxBalance::findOrFail($id);
    
            // حفظ الصور
            if ($file = $request->file('discount_percentage_image')) {
                $item->discount_percentage_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->discount_percentage_image);
            }
    
            if ($file = $request->file('payment_statement_image')) {
                $item->payment_statement_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->payment_statement_image);
            }
    
            if ($file = $request->file('rent_exclusion_image')) {
                $item->rent_exclusion_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->rent_exclusion_image);
            }
    
            if ($file = $request->file('rent_tax_image')) {
                $item->rent_tax_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->rent_tax_image);
            }
    
            // تحديث البيانات الأساسية
            
          
            $item->year = $request->year;
           
            $item->notes = $request->notes;
            
            $item->tax_status_id = 2; 
        
    
            $item->save();
            
            if ($request->buildings && is_array($request->buildings)) {

                $item->buildings()->delete();
                foreach ($request->buildings as $build) {
                    $item->buildings()->create([
                        'tenant_name'  => $build['tenant_name'] ?? null,
                        'address'           => $build['address'] ?? null,
                        'usage'    => $build['usage'] ?? null,
                        'monthly_rent'    => $build['monthly_rent'] ?? null,
                        'actual_months'    => $build['actual_months'] ?? null,
                        'excluded_months'    => $build['excluded_months'] ?? null,
                       'tax_value'    => $build['tax_value'] ?? null,
                    ]);
                }
            }
            
    
            $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "اعتماد";
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
         
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    public function refuse_real_state(Request $request, $id)
    {
        try {
    
            $item = CustomerTaxBalance::findOrFail($id);
        
            $item->notes = $request->notes;
            
            $item->tax_status_id = 3; 
        
    
            $item->save();
    
            $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "رفض اعتماد";
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
           
    
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    
    public function print_real_state(Request $request, $id)
    {
        try {
    
            
    
            // جلب السجل
            $item = CustomerTaxBalance::findOrFail($id);
    
            $item->tax_status_id = 4; 
        
    
            $item->save();
    
            $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "طباعة حافظة";
            $log->notes = $item->notes;
            $log->save();
            
            $item->update([
                'notes' => null,
            ]);
    
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    
    public function finance_real_state(Request $request, $id)
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
            
            
                'payment_type_id' => 'required',
            
               // 'payment_receipt_image' => 'required_if:payment_type_id,1|image|mimes:jpg,jpeg,png|max:2048',
                    'bank_id'      => 'required_if:payment_type_id,2',
                'check_number' => 'required_if:payment_type_id,2',
                'check_value' => 'required_if:payment_type_id,2',
                'check_image'  => 'required_if:payment_type_id,2|image|mimes:jpg,jpeg,png|max:2048',
                'supply_voucher_image'  => 'required|image|mimes:jpg,jpeg,png|max:2048',


            ], $messages, $attributes);
    
            // جلب السجل
            $item = CustomerTaxBalance::findOrFail($id);
            if ($file = $request->file('payment_receipt_image')) {
                $item->payment_receipt_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->payment_receipt_image);
            }
            
            if ($file = $request->file('extra_image')) {
                $item->extra_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->extra_image);
            }
            
            if ($file = $request->file('check_image')) {
                $item->check_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->check_image);
            }
            
            if ($file = $request->file('supply_voucher_image')) {
                $item->supply_voucher_image = time() . $file->getClientOriginalName();
                $file->move('balance', $item->supply_voucher_image);
            }
            
            $item->tax_status_id = 5; 
            $item->payment_receipt_number = $request->payment_receipt_number;
            $item->payment_type_id = $request->payment_type_id;
            $item->bank_id = $request->bank_id;
            $item->check_number = $request->check_number;
            $item->check_value = $request->check_value;
            $item->check_image = $check_image;
            $item->supply_voucher_image = $supply_voucher_image;  
            $item->payment_date = $request->payment_date;
            $item->extra_image = $extra_image;
            $item->payment_value = $request->payment_value;
            $item->save();
            
            
    
            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح',
                'status' => 200,
                'data' => $item,
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
    
    public function get_logs_taxes(Request $request ,$id)
    {
        $logs = CustomerTaxBalanceLog::where('customer_tax_balance_id', $id);
    
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
    
    public function get_tax_details($id)
    {
        $tax = CustomerTaxBalance::findOrFail($id);
    
        $data = [
            'id'                           => $tax->id,
            'declaration_name'             => $tax->declaration_name,
            'declaration_id'               => $tax->declaration_id,
            'tax_type_id'                  => $tax->tax_type_id,
            'tax_type_name'                => $tax->tax_type_name,
            'customer_name'                => $tax->customer_name,
            'customer_identity_number'     => $tax->customer_identity_number,
            'customer_identity_name'       => $tax->customer_identity_name,
            'customer_country_name'        => $tax->customer_country_name,
            'customer_identity_start_date'        => $tax->customer_identity_start_date,
            'customer_identity_end_date'        => $tax->customer_identity_end_date,
            'report_number'                => $tax->report_number,
            'payment_receipt_number'    =>$tax->payment_receipt_number,
            'payment_value'    =>$tax->total_amount,
            'check_image'    =>$tax->check_image,
            'check_image_url'    =>$tax->check_image_url,
            'extra_image'    =>$tax->extra_image,
            'extra_image_url'    =>$tax->extra_image_url,
            'bank_id'    =>$tax->bank_id,
            'check_number'    =>$tax->check_number,
            'check_value'    =>$tax->check_value,
            'notes'    =>$tax->notes,
            'payment_receipt_image'    =>$tax->payment_receipt_image,
            'payment_receipt_image_url'    =>$tax->payment_receipt_image_url,
            'supply_voucher_image'    =>$tax->supply_voucher_image,
            'supply_voucher_image_url'    =>$tax->supply_voucher_image_url,
            
        ];
    
        return response()->json([
            'status' => 200,
            'data'   => $data,
        ], 200);
    }
    
    public function get_customer_tax_details(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
    
        $taxBalanceDetails = collect($customer->tax_balance_details);
    
        if ($request->filled('from')) {
            $taxBalanceDetails = $taxBalanceDetails->where('created_at', '>=', $request->from);
        }
    
        if ($request->filled('to')) {
            $taxBalanceDetails = $taxBalanceDetails->where('created_at', '<=', $request->to);
        }
    
        $customerData = [
            'id'                  => $customer->id,
            'name'                => $customer->name,
            'country_name'        => $customer->country_name,
            'identity_name'       => $customer->identity_name,
            'identity_number'     => $customer->identity_number,
            'phone_1'             => $customer->phone_1,
            'profile_image_url'   => $customer->profile_image_url,
            'identity_start_date' => $customer->identity_start_date,
            'address'             => $customer->address,
            'tax_file_id'         => $customer->tax_file_id,
            'tax_balance_details' => $customer->tax_balance_details,
        ];
    
        return response()->json([
            'status' => 200,
            'data'   => $customerData,
        ]);
    }


    
    public function customers_tax_balances(Request $request,$id)
    {
        $query = CustomerTaxBalance::where('customer_id',$id)->with(['customer','tax_type']);

        if ($request->filled('tax_type_id')) {
            $query->where('tax_type_id', $request->tax_type_id);
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

            // نحسب تفاصيل الفلوس
            $item = $this->calculateFees($item, $collection);

            return [
                'customer_id'        => $item->customer_id,
                'customer_name'      => $item->customer->name ?? '-',

             
                'tax_type'           => $item->tax_type->name ?? '-',
               
                'year' => $item->year,
                'amount' => $item->total_amount,
                'fees'               => $item->fees,
            ];
        });

        return response()->json([
            'message' => 'سجل الرصيد ',
            'status'  => 200,
            'data'    => $data,
        ]);
    }

    public function approve_pulck_tax_finance(Request $request)
    {
        try {
    
            // ✅ Validation
            $request->validate([
                'ids'   => 'required|array|min:1',
                'ids.*' => 'required|integer|exists:customer_tax_balance,id',
            ]);
    
            // ✅ اعتماد الكل مرة واحدة
            CustomerTaxBalance::whereIn('id', $request->ids)
                ->update([
                    'tax_status_id' => 6,
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
    
 
    
    
    public function approve_tax_finance(Request $request, $id)
    {
        $renewal = CustomerTaxBalance::findOrFail($id);
    
        $renewal->update([
            'tax_status_id' => 6, // معتمد
            'is_matched'    => 1,
        ]);
    
        return response()->json([
            'message' => 'تم الاعتماد بنجاح',
            'status'  => 200,
            'type'    => 'approved',
            'data'    => $renewal,
        ], 200);
    }

    
    
    public function refuse_tax_finance(Request $request, $id)
    {
        $renewal = CustomerTaxBalance::findOrFail($id);
    
        $renewal->update([
            'tax_status_id' => 7
        ]);
    
        return response()->json([
            'message' => 'تم الرفض بنجاح',
            'status'  => 200,
            'type'    => 'refused',
            'data'    => [],
        ], 200);
    }
    
    public function update_finance_tax(Request $request, $id)
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
           $item = CustomerTaxBalance::findOrFail($id);
            
            if ($file = $request->file('payment_receipt_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('balance', $filename);
                $item->payment_receipt_image = $filename;
            }
            
            if ($file = $request->file('supply_voucher_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('balance', $filename);
                $item->supply_voucher_image = $filename;
            }
            
            if ($file = $request->file('extra_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('balance', $filename);
                $item->extra_image = $filename;
            }
             
             
            if ($file = $request->file('check_image')) {
                $filename = time() . $file->getClientOriginalName();
                $file->move('balance', $filename);
                $item->check_image = $filename;
            }
             
         
            
            $item->tax_status_id = 5; 
            $item->payment_receipt_number = $request->payment_receipt_number;
            $item->payment_type_id = $request->payment_type_id;
            $item->bank_id = $request->bank_id;
            $item->check_number = $request->check_number;
            $item->check_value = $request->check_value;
            $item->payment_date = $request->payment_date;
            $item->payment_value = $request->payment_value;
            $item->save();
    
            $log = new CustomerTaxBalanceLog();
            $log->user_id = auth('api')->user()->id;
            $log->customer_tax_balance_id = $item->id;
            $log->department_id = auth('api')->user()->department_id;
            $log->details = "تم التحصيل";
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
    
    public function matching_tax_table(Request $request)
    {
       $query = CustomerTaxBalance::whereIn('tax_status_id', [4,5])->whereNotNull('declaration_id');
    
        if ($request->filled('search')) {
    
            $search = $request->search;
    
            $query->where(function ($q) use ($search) {
    
                // البحث في الملف الضريبي أو ID
                $q->where('tax_file_id', $search)
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
                'user_name' => $item->user_name,
                'status_id' => $item->tax_status_id,
                'created_at' => $item->created_at->format('Y-m-d'),
                'payment_receipt_number' => $item->payment_receipt_number,
                'amount' =>$item->total_amount,
                'sub_name' => $item->declaration_name,
           
            ];
        });
    
        return response()->json([
            'message' => 'تم',
            'status'  => 200,
            'data'    => $data,
        ]);
   
    }
    
    
public function supply_print_tax()
{
    $renewals = CustomerTaxBalance::where('is_matched', 1)
        ->where('is_printed', 0)
        ->latest()
        ->get();

    $result = [];

    $categories = $renewals->pluck('tax_type_id')->unique();

    $tabConfig = [
        1 => [
            'services' => [
                ['code' =>'1-2-1-5', 'name' => 'الضريبة على ريع العقارات', 'type' => 'مشترك'],
            ],
            'penalties' => [
                ['code' => '3-3-2-4', 'name' => 'غرامات و مصادرات', 'type' => 'مشترك'],
            ],
        ],
        2 => [
            'services' => [
                ['code' =>'1-2-1-4', 'name' => 'ضريبة أرباح المهن الحرة', 'type' => 'محلي'],
            ],
            'penalties' => [
                ['code' => '3-3-2-1', 'name' => 'غرامات محلية', 'type' => 'محلي'],
            ],
        ],
        3 => [
            'services' => [
                ['code' =>'1-3-1-1', 'name' => 'ضرائب كسب العمل', 'type' => 'مشترك'],
            ],
            'penalties' => [
                ['code' => '3-3-2-4', 'name' => 'غرامات و مصادرات', 'type' => 'مشترك'],
            ],
        ],
    ];

    foreach ($categories as $catId) {

        $category_renewals = $renewals->where('tax_type_id', $catId);

        $total_tax     = 0;
        $total_penalty = 0;

        foreach ($category_renewals as $item) {

            $eldreba  = 0;
            $el8ramat = 0;

            /* =========================
               حساب الضريبة
            ========================= */

            // تحت الحساب فقط
            if ($item->declaration_id === null) {

                $eldreba = $item->value;

            }
            // إقرار 5 – مهن حرة
            elseif ($item->declaration_id == 5 && $item->tax_type_id == 3) {

                $eldreba = $item->employees()->sum('total');

            }
            // إقرار 2
            elseif ($item->declaration_id == 2 && $item->tax_type_id == 2) {

                $eldreba = $item->total;

                $dueDate = Carbon::create($item->year, 6, 30)->addYear();

                if ($item->created_at && Carbon::parse($item->created_at)->gt($dueDate)) {
                    $lateMonths = $dueDate->diffInMonths($item->created_at);
                    $el8ramat = $eldreba * 0.02 * $lateMonths;
                }
            }
            // إقرار 1 – عقارات
            elseif ($item->declaration_id == 1 && $item->tax_type_id == 1) {

                $eldreba = $item->buildings()->sum('tax_value');

                $dueDate = Carbon::create($item->year, 6, 30)->addYear();

                if ($item->created_at && Carbon::parse($item->created_at)->gt($dueDate)) {
                    $lateMonths = $dueDate->diffInMonths($item->created_at);
                    $el8ramat = $eldreba * 0.02 * $lateMonths;
                }
            }

            $total_tax     += $eldreba;
            $total_penalty += $el8ramat;
        }

        /* =========================
           بيانات عامة
        ========================= */

        $directorate_name = $category_renewals->pluck('directorate_name')->unique()->implode(' - ');
        $created_at_list  = $category_renewals->pluck('created_at')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->unique()
            ->implode(' - ');

        $ids_list = $category_renewals->pluck('id')->implode(' - ');
        $category_name = $category_renewals->pluck('category_name')->unique()->implode(' - ');

        $paymentNumbers = $category_renewals
            ->pluck('payment_receipt_number')
            ->filter()
            ->unique()
            ->implode(' - ');

        $details = [];

        $serviceTabs = $tabConfig[$catId]['services'] ?? [];
        $penaltyTabs = $tabConfig[$catId]['penalties'] ?? [];

        /* =========================
           تفاصيل الضريبة
        ========================= */

        if ($total_tax > 0) {
            $details[] = [
                'directorate_name' => $directorate_name,
                'amount'           => $total_tax,
                'currency_name'    => "ريال يمني",
                'created_at'       => $created_at_list,
                'auto_number'      => $ids_list,
                'office'           => $category_name,
                'payment_receipt_number' => $paymentNumbers,
                'money'            => "سند سداد نقدية المديرية",
                'approve_status'   => "لا",
                'bank'             => "بنك",
                'code'             => implode(' - ', array_column($serviceTabs, 'code')),
                'type'             => implode(' - ', array_column($serviceTabs, 'type')),
                'code_name'        => implode(' - ', array_column($serviceTabs, 'name')),
            ];
        }

        /* =========================
           تفاصيل الغرامات
        ========================= */

        if ($total_penalty > 0) {
            $details[] = [
                'directorate_name' => $directorate_name,
                'amount'           => $total_penalty,
                'currency_name'    => "ريال يمني",
                'created_at'       => $created_at_list,
                'auto_number'      => $ids_list,
                'office'           => $category_name,
                'payment_receipt_number' => $paymentNumbers,
                'money'            => "سند سداد نقدية المديرية",
                'approve_status'   => "لا",
                'bank'             => "بنك",
                'code'             => implode(' - ', array_column($penaltyTabs, 'code')),
                'type'             => implode(' - ', array_column($penaltyTabs, 'type')),
                'code_name'        => implode(' - ', array_column($penaltyTabs, 'name')),
            ];
        }

        $grand_total = $total_tax + $total_penalty;

        /* =========================
           الصف النهائي
        ========================= */

        $result[] = [
            'directorate_name'       => $directorate_name,
            'amount'                 => $grand_total,
            'currency_name'          => "ريال يمني",
            'created_at'             => $created_at_list,
            'auto_number'            => $ids_list,
            'office'                 => $category_name,
            'payment_receipt_number' => $paymentNumbers,
            'money'                  => "سند سداد نقدية المديرية",
            'approve_status'         => "لا",
            'bank'                   => "بنك",
            'code'                   => "",
            'type'                   => "",
            'details'                => $details,
        ];
    }

    return response()->json([
        'status' => true,
        'data'   => $result,
    ]);
}


public function supply_print_tax_by_ids(Request $request)
{
    $request->validate([
        'ids' => 'required|array|min:1',
        'ids.*' => 'integer|exists:customer_tax_balance,id'
    ]);

    CustomerTaxBalance::whereIn('id', $request->ids)
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

    
public function liquidation_of_custody_tax()
{
    $renewals = CustomerTaxBalance::where('is_printed', 1)
         ->whereDate('printed_at', Carbon::today())
        ->latest()
        ->get();

    $result = [];

    $tabConfig = [
        1 => [
            'services' => [
                ['code' =>'1-2-1-5', 'name' => 'الضريبة على ريع العقارات', 'type' => 'مشترك'],
            ],
            'penalties' => [
                ['code' => '3-3-2-4', 'name' => 'غرامات و مصادرات', 'type' => 'مشترك'],
            ],
        ],
        2 => [
            'services' => [
                ['code' =>'1-2-1-4', 'name' => 'ضريبة أرباح المهن الحرة', 'type' => 'محلي'],
            ],
            'penalties' => [
                ['code' => '3-3-2-1', 'name' => 'غرامات محلية', 'type' => 'محلي'],
            ],
        ],
        3 => [
            'services' => [
                ['code' =>'1-3-1-1', 'name' => 'ضرائب كسب العمل', 'type' => 'مشترك'],
            ],
            'penalties' => [
                ['code' => '3-3-2-4', 'name' => 'غرامات و مصادرات', 'type' => 'مشترك'],
            ],
        ],
    ];

    foreach ($renewals as $item) {

        $eldreba  = 0;
        $el8ramat = 0;

        /* =========================
           حساب الضريبة (نفس المنطق)
        ========================= */

        if ($item->declaration_id === null) {

            $eldreba = $item->value;

        } elseif ($item->declaration_id == 5 && $item->tax_type_id == 3) {

            $eldreba = $item->employees()->sum('total');

        } elseif ($item->declaration_id == 2 && $item->tax_type_id == 2) {

            $eldreba = $item->total;

            $dueDate = Carbon::create($item->year, 6, 30)->addYear();

            if ($item->created_at && Carbon::parse($item->created_at)->gt($dueDate)) {
                $lateMonths = $dueDate->diffInMonths($item->created_at);
                $el8ramat = $eldreba * 0.02 * $lateMonths;
            }

        } elseif ($item->declaration_id == 1 && $item->tax_type_id == 1) {

            $eldreba = $item->buildings()->sum('tax_value');

            $dueDate = Carbon::create($item->year, 6, 30)->addYear();

            if ($item->created_at && Carbon::parse($item->created_at)->gt($dueDate)) {
                $lateMonths = $dueDate->diffInMonths($item->created_at);
                $el8ramat = $eldreba * 0.02 * $lateMonths;
            }
        }

        $directorate_name = $item->directorate_name;
        $category_name    = $item->category_name;
        $paymentNumber    = $item->payment_receipt_number;
        $created_at       = Carbon::parse($item->created_at)->format('Y-m-d');

        $serviceTabs = $tabConfig[$item->tax_type_id]['services'] ?? [];
        $penaltyTabs = $tabConfig[$item->tax_type_id]['penalties'] ?? [];

        /* =========================
           سطر الضريبة
        ========================= */
        if ($eldreba > 0) {
            $result[] = [
                'directorate_name' => $directorate_name,
                'amount'           => $eldreba,
                'currency_name'    => "ريال يمني",
                'created_at'       => $created_at,
                'auto_number'      => $item->id,
                'office'           => $category_name,
                'payment_receipt_number' => $paymentNumber,
                'money'            => "سند سداد نقدية المديرية",
                'approve_status'   => "لا",
                'bank'             => "بنك",
                'code'             => implode(' - ', array_column($serviceTabs, 'code')),
                'type'             => implode(' - ', array_column($serviceTabs, 'type')),
                'code_name'        => implode(' - ', array_column($serviceTabs, 'name')),
            ];
        }

        /* =========================
           سطر الغرامات
        ========================= */
        if ($el8ramat > 0) {
            $result[] = [
                'directorate_name' => $directorate_name,
                'amount'           => $el8ramat,
                'currency_name'    => "ريال يمني",
                'created_at'       => $created_at,
                'auto_number'      => $item->id,
                'office'           => $category_name,
                'payment_receipt_number' => $paymentNumber,
                'money'            => "سند سداد نقدية المديرية",
                'approve_status'   => "لا",
                'bank'             => "بنك",
                'code'             => implode(' - ', array_column($penaltyTabs, 'code')),
                'type'             => implode(' - ', array_column($penaltyTabs, 'type')),
                'code_name'        => implode(' - ', array_column($penaltyTabs, 'name')),
            ];
        }
    }

    return response()->json([
        'status' => true,
        'data'   => $result,
    ]);
}

  
    
}
