<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\Country;
use Str;
use App\Models\Currency;
use App\Models\Government;
use Illuminate\Support\Carbon;
use App\Models\Department;
use App\Models\Price;
use App\Models\IdentityType;
use Illuminate\Validation\ValidationException;
use App\Models\Category;
use App\Models\SubCategory;
use App\Models\Directorate;
use App\Models\BranchType;
use App\Models\Service;
use App\Models\RentalType;
use App\Models\PanelType;
use App\Models\Status;
use App\Models\Qualification;
use App\Models\Specialization;
use App\Models\TaxType;
use App\Models\Declaration;
use App\Models\UnitType;
use App\Models\ServiceType;
use App\Models\Bank;
use App\Models\PaymentType;
use App\Models\ZakahDeclaration;
use App\Models\CustomerZakahBalance;
use App\Models\CustomerTaxBalance;
use App\Models\BranchRenewal;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\Customer;
use App\Models\TaxFile;
use App\Models\ZakahNumber;
use App\Models\ServicePrice;
use App\Models\TaxStatus;
use App\Models\ZakahStatus;
use App\Models\Application;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;


class DashboardApiController extends Controller
{
    
    
    private function range($period)
    {
        return match ($period) {
            '7_days'  => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            'monthly' => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            '3_month' => [now()->subMonths(3)->startOfDay(), now()->endOfDay()],
            '6_month' => [now()->subMonths(6)->startOfDay(), now()->endOfDay()],
            'year'    => [now()->startOfYear(), now()->endOfDay()],
            default   => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
        };
    }

    /* ==============================
     | 1️⃣ Summary Cards
     ============================== */
    public function summary(Request $request)
    {
        [$from, $to] = $this->range($request->period);

        $licenses = BranchRenewal::whereBetween('created_at', [$from, $to])->get()->sum('amount');
        $taxes    = CustomerTaxBalance::whereBetween('created_at', [$from, $to])->get()->sum('total_amount');
        $zakah    = CustomerZakahBalance::whereBetween('created_at', [$from, $to])->get()->sum('amount');

        return response()->json([
            'total'     => $licenses + $taxes + $zakah,
            'licenses'  => $licenses,
            'taxes'     => $taxes,
            'zakah'     => $zakah,
        ]);
    }

    /* ==============================
     | 2️⃣ Revenue Distribution
     ============================== */
    public function distribution(Request $request)
    {
        [$from, $to] = $this->range($request->period);

        $licenses = BranchRenewal::whereBetween('created_at', [$from, $to])->get()->sum('amount');
        $taxes    = CustomerTaxBalance::whereBetween('created_at', [$from, $to])->get()->sum('total_amount');
        $zakah    = CustomerZakahBalance::whereBetween('created_at', [$from, $to])->get()->sum('amount');

        return response()->json([
            'labels' => ['التراخيص', 'الضرائب', 'الواجبات'],
            'data'   => [$licenses, $taxes, $zakah],
        ]);
    }

    private function isMonthly($period)
    {
        return in_array($period, ['3_month', '6_month', 'year']);
    }
    /* ==============================
     | 3️⃣ Revenue Trend
     ============================== */
    public function trend(Request $request)
    {
        $period = $request->period ?? '7_days';
        [$from, $to] = $this->range($period);
    
        $isMonthly = $this->isMonthly($period);
    
        // إنشاء التواريخ
        $dates = $isMonthly
            ? collect(Carbon::parse($from)->monthsUntil($to))
                ->map(fn ($d) => $d->format('Y-m'))
            : collect(Carbon::parse($from)->daysUntil($to))
                ->map(fn ($d) => $d->toDateString());
    
        return response()->json([
            'dates' => $dates,
    
            'licenses' => $dates->map(function ($d) use ($isMonthly) {
                return BranchRenewal::when($isMonthly,
                    fn ($q) => $q->whereYear('created_at', substr($d, 0, 4))
                                 ->whereMonth('created_at', substr($d, 5, 2)),
                    fn ($q) => $q->whereDate('created_at', $d)
                )->get() ->sum('amount');
            }),
    
            'taxes' => $dates->map(function ($d) use ($isMonthly) {
                return CustomerTaxBalance::when($isMonthly,
                    fn ($q) => $q->whereYear('created_at', substr($d, 0, 4))
                                 ->whereMonth('created_at', substr($d, 5, 2)),
                    fn ($q) => $q->whereDate('created_at', $d)
                )->get()->sum('total_amount');
            }),
    
            'zakah' => $dates->map(function ($d) use ($isMonthly) {
                return CustomerZakahBalance::when($isMonthly,
                    fn ($q) => $q->whereYear('created_at', substr($d, 0, 4))
                                 ->whereMonth('created_at', substr($d, 5, 2)),
                    fn ($q) => $q->whereDate('created_at', $d)
                )->get()->sum('amount');
            }),
        ]);
    }


    /* ==============================
     | 4️⃣ Latest Transactions
     ============================== */
  
    public function latest(Request $request)
    {
        $perPage = 8;
        $page    = $request->get('page', 1);
        $type    = $request->get('type', 'all'); // licenses | taxes | zakah | all
    
        $data = collect();
    
        if ($type === 'licenses' || $type === 'all') {
            $data = $data->merge(
                BranchRenewal::latest()->get()->map(fn ($r) => [
                    'id'       => $r->id,
                    'date'     => $r->created_at,
                    'type'     => 'تراخيص',
                    'name'     => $r->customer_name,
                    'amount'   => $r->amount + $r->amount_nazafa,
                    'sub_name' => $r->sub_name,
                    'customer_id' => $r->customer_id,
                    'route' => "/dashboard/paymentDetails/{$r->id}",
                ])
            );
        }
    
        if ($type === 'taxes' || $type === 'all') {
            $data = $data->merge(
                CustomerTaxBalance::latest()->get()->map(fn ($r) => [
                    'id'       => $r->id,
                    'date'     => $r->created_at,
                    'type'     => 'ضرائب',
                    'name'     => $r->customer_name,
                    'amount'   => $r->total_amount,
                    'sub_name' => $r->tax_type_name,
                    'customer_id' => $r->customer_id,
                    'route' => "/dashboard/customer_controller/dashboard_tax_details/{$r->id}/{$r->customer_id}",
                ])
            );
        }
    
        if ($type === 'zakah' || $type === 'all') {
            $data = $data->merge(
                CustomerZakahBalance::latest()->get()->map(fn ($r) => [
                    'id'       => $r->id,
                    'date'     => $r->created_at,
                    'type'     => 'واجبات',
                    'name'     => $r->customer_name,
                    'amount'   => $r->amount,
                    'sub_name' => $r->zakah_type_name,
                    'customer_id' => $r->customer_id,
                    'route' => "/dashboard/customer_controller/zakah_declaration_details/{$r->id}/{$r->customer_id}",
                ])
            );
        }
    
        // ترتيب + Pagination
           $data = $data->sortByDesc('date')->values();
    
        $paginator = new LengthAwarePaginator(
            $data->forPage($page, $perPage)->values()->toArray(), // 👈 هنا المهم
            $data->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
        
        // تنسيق التاريخ
        $paginator->setCollection(
            collect($paginator->items())->map(function ($item) {
                $item['date'] = \Carbon\Carbon::parse($item['date'])->toDateString();
                return $item;
            })
        );
        
        return response()->json([
            'current_page' => $paginator->currentPage(),
            'data'         => $paginator->items(), // 👈 Array مش Object
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
            'last_page'    => $paginator->lastPage(),
        ]);
    }

    
    public function get_bank()
    {
        $items = Bank::paginate(8);
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
    public function add_bank(Request $request)
    {
        try {
    
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:bankes,name',
            ]);
    
            $bank = Bank::create($validated);
    
            return response()->json([
                'status'  => true,
                'message' => 'Bank added successfully',
                'data'    => $bank,
            ], 201);
    
        }
        // ❌ أخطاء الـ Validation
        catch (ValidationException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'VALIDATION_ERROR',
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
        // ❌ أخطاء قاعدة البيانات (Duplicate – SQL)
        catch (QueryException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'DATABASE_ERROR',
                'message'    => 'Database error',
                'errors'     => [
                    'sql' => $e->getCode(),
                ],
            ], 409);
        }
        // ❌ أي خطأ غير متوقع
        catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => 'Unexpected error occurred',
            ], 500);
        }
    }
    public function edit_bank(Request $request, $id)
    {
        try {
    
            $bank = Bank::findOrFail($id);
    
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:bankes,name,' . $bank->id,
            ]);
    
            $bank->update($validated);
    
            return response()->json([
                'status'  => true,
                'message' => 'Bank updated successfully',
                'data'    => $bank,
            ], 200);
    
        }
        // ❌ Validation
        catch (ValidationException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'VALIDATION_ERROR',
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
        // ❌ Database
        catch (QueryException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'DATABASE_ERROR',
                'message'    => 'Database error',
            ], 409);
        }
        // ❌ Not Found
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'NOT_FOUND',
                'message'    => 'Bank not found',
            ], 404);
        }
        // ❌ Server
        catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => 'Unexpected error occurred',
            ], 500);
        }
    }

    public function toggle_bank(Request $request, $id)
    {
        try {
    
            // ===============================
            // Validation
            // ===============================
            $validated = $request->validate([
                'active' => 'required|boolean',
            ]);
    
            // ===============================
            // Find Service
            // ===============================
            $bank = Bank::findOrFail($id);
    
            // ===============================
            // Update Status
            // ===============================
            $bank->update([
                'active' => $validated['active']
            ]);
    
            return response()->json([
                'status'  => true,
                'message' => 'bank status updated successfully',
                'data'    => $bank,
            ], 200);
    
        }
        // ❌ Validation Errors
        catch (ValidationException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'VALIDATION_ERROR',
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
        // ❌ Database Errors
        catch (QueryException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'DATABASE_ERROR',
                'message'    => 'Database error',
            ], 409);
        }
        // ❌ Server Errors
        catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => 'Unexpected error occurred',
            ], 500);
        }
    }


    public function get_currency()
    {
        
        $items = Currency::latest()->paginate(8);
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
    public function add_currency(Request $request)
    {
        try {
    
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:currencies,name',
                'price' => 'required|numeric|min:1',
            ]);
    
            $bank = Currency::create($validated);
    
            return response()->json([
                'status'  => true,
                'message' => 'currency added successfully',
                'data'    => $bank,
            ], 201);
    
        }
        // ❌ أخطاء الـ Validation
        catch (ValidationException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'VALIDATION_ERROR',
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
        // ❌ أخطاء قاعدة البيانات (Duplicate – SQL)
        catch (QueryException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'DATABASE_ERROR',
                'message'    => 'Database error',
                'errors'     => [
                    'sql' => $e->getCode(),
                ],
            ], 409);
        }
        // ❌ أي خطأ غير متوقع
        catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => 'Unexpected error occurred',
            ], 500);
        }
    }
    
    public function edit_currency(Request $request, $id)
    {
        try {
    
            // 🔍 التأكد إن العملة موجودة
            $currency = Currency::findOrFail($id);
    
            // ✅ Validation (مع استثناء الـ record الحالي)
            $validated = $request->validate([
                'name'  => 'required|string|max:255|unique:currencies,name,' . $currency->id,
                'price' => 'required|numeric|min:1',
            ]);
    
            // 💾 Update
            $currency->update($validated);
    
            return response()->json([
                'status'  => true,
                'message' => 'Currency updated successfully',
                'data'    => $currency,
            ], 200);
    
        }
        // ❌ Validation Errors
        catch (ValidationException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'VALIDATION_ERROR',
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
        // ❌ Database Errors
        catch (QueryException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'DATABASE_ERROR',
                'message'    => 'Database error',
            ], 409);
        }
        // ❌ Not Found
        catch (ModelNotFoundException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'NOT_FOUND',
                'message'    => 'Currency not found',
            ], 404);
        }
        // ❌ Server Error
        catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => 'Unexpected error occurred',
            ], 500);
        }
    }
    
    public function toggle_currency(Request $request, $id)
    {
        try {
    
            // ===============================
            // Validation
            // ===============================
            $validated = $request->validate([
                'active' => 'required|boolean',
            ]);
    
            // ===============================
            // Find Service
            // ===============================
            $currency = Currency::findOrFail($id);
    
            // ===============================
            // Update Status
            // ===============================
            $currency->update([
                'active' => $validated['active']
            ]);
            
    
            return response()->json([
                'status'  => true,
                'message' => 'currency status updated successfully',
                'data'    => $currency,
            ], 200);
    
        }
        // ❌ Validation Errors
        catch (ValidationException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'VALIDATION_ERROR',
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
        // ❌ Database Errors
        catch (QueryException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'DATABASE_ERROR',
                'message'    => 'Database error',
            ], 409);
        }
        // ❌ Server Errors
        catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => 'Unexpected error occurred',
            ], 500);
        }
    }
    
    
    public function get_country()
    {
        $items = Country::paginate(8);
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
    public function add_country(Request $request)
    {
        try {
    
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:countries,name',
            ]);
    
            $bank = Country::create($validated);
    
            return response()->json([
                'status'  => true,
                'message' => 'Country added successfully',
                'data'    => $bank,
            ], 201);
    
        }
        // ❌ أخطاء الـ Validation
        catch (ValidationException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'VALIDATION_ERROR',
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
        // ❌ أخطاء قاعدة البيانات (Duplicate – SQL)
        catch (QueryException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'DATABASE_ERROR',
                'message'    => 'Database error',
                'errors'     => [
                    'sql' => $e->getCode(),
                ],
            ], 409);
        }
        // ❌ أي خطأ غير متوقع
        catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => 'Unexpected error occurred',
            ], 500);
        }
    }
    public function edit_country(Request $request, $id)
    {
        try {
    
            $bank = Country::findOrFail($id);
    
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:countries,name,' . $bank->id,
            ]);
    
            $bank->update($validated);
    
            return response()->json([
                'status'  => true,
                'message' => 'Country updated successfully',
                'data'    => $bank,
            ], 200);
    
        }
        // ❌ Validation
        catch (ValidationException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'VALIDATION_ERROR',
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
        // ❌ Database
        catch (QueryException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'DATABASE_ERROR',
                'message'    => 'Database error',
            ], 409);
        }
        // ❌ Not Found
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'NOT_FOUND',
                'message'    => 'Bank not found',
            ], 404);
        }
        // ❌ Server
        catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => 'Unexpected error occurred',
            ], 500);
        }
    }
    public function toggle_country(Request $request, $id)
    {
        try {
    
            // ===============================
            // Validation
            // ===============================
            $validated = $request->validate([
                'active' => 'required|boolean',
            ]);
    
            // ===============================
            // Find Service
            // ===============================
            $country = Country::findOrFail($id);
    
            // ===============================
            // Update Status
            // ===============================
            $country->update([
                'active' => $validated['active']
            ]);
    
            return response()->json([
                'status'  => true,
                'message' => 'country status updated successfully',
                'data'    => $country,
            ], 200);
    
        }
        // ❌ Validation Errors
        catch (ValidationException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'VALIDATION_ERROR',
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
        // ❌ Database Errors
        catch (QueryException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'DATABASE_ERROR',
                'message'    => 'Database error',
            ], 409);
        }
        // ❌ Server Errors
        catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => 'Unexpected error occurred',
            ], 500);
        }
    }
    
    
    public function get_customer(Request $request)
    {
        $customers = Customer::query()
    
            ->when($request->filled('name'), function ($q) use ($request) {
                $q->where('name', 'like', '%' . trim($request->name) . '%');
            })
    
            ->when($request->filled('email'), function ($q) use ($request) {
                $q->where('email', 'like', '%' . trim($request->email) . '%');
            })
    
            ->when($request->filled('identity_number'), function ($q) use ($request) {
                $q->where('identity_number', 'like', '%' . trim($request->identity_number) . '%');
            })
            
            ->when($request->filled('country_id'), function ($q) use ($request) {
                $q->where('country_id', $request->country_id);
            })
            
            ->when($request->filled('identity_type_id'), function ($q) use ($request) {
                $q->where('identity_type_id', $request->identity_type_id);
            })
            ->latest()
            ->paginate(15)
            ->appends($request->query()); // ⭐ يحافظ على الفلاتر مع pagination
    
        $customers->getCollection()->transform(function ($customer) {
            return [
                'id'                    => $customer->id,
                'name'                  => $customer->name,
                'email'                 => $customer->email,
                'country_name'          => $customer->country_name,
                'country_id'            => $customer->country_id,
                'identity_number'       => $customer->identity_number,
                'phone_1'               => $customer->phone_1,
                'identity_start_date'   => $customer->identity_start_date,
                'identity_end_date'     => $customer->identity_end_date,
                'tel_1'                 => $customer->tel_1,
                'tel_2'                 => $customer->tel_2,
                'profile_image_url'     => $customer->profile_image_url,
                'address'               => $customer->address,
                'front_image_url'       => $customer->front_image_url,
                'back_image_url'        => $customer->back_image_url,
                'identity_name'         => $customer->identity_name,
                'profile_picture'       => $customer->profile_picture,
                'identity_front_image'  => $customer->identity_front_image,
                'identity_back_image'   => $customer->identity_back_image,
                'identity_type_id'      => $customer->identity_type_id,
            ];
        });
    
        return response()->json([
            'status'  => 200,
            'message' => 'success',
            'data'    => $customers
        ]);
    }


    
    public function add_customer(Request $request)
    {
        try {
            

            $rules = [
                'name' => 'required|string|max:255',
                'phone_1' => 'required',
                'identity_number' => 'required|unique:customers',
                'email' => 'nullable|email|unique:customers',
                'identity_type_id' => 'required',
                'country_id' => 'required',
                'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'identity_front_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'identity_back_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'identity_start_date' => 'required|date|before_or_equal:today',
                'identity_end_date'   => 'required|date|after:identity_start_date',
                'address'   => 'required',
            ];

            // ⬅️ لو نوع الهوية = 1 → خلي رقم الهوية 11 رقم
            if ($request->identity_type_id == 1) {
                $rules['identity_number'] .= '|digits:12';
            }

            $request->validate($rules, [
                'name.required' => 'من فضلك أدخل الاسم',
                'name.string' => 'الاسم يجب أن يكون نصًا',
                'name.max' => 'الاسم يجب ألا يزيد عن 255 حرف',

                'phone_1.required' => 'رقم الهاتف مطلوب',
                'phone_1.unique' => 'رقم الهاتف مستخدم بالفعل',

                'identity_number.required' => 'رقم الهوية مطلوب',
                'identity_number.unique' => 'رقم الهوية مستخدم بالفعل',
                'identity_number.digits' => 'رقم الهوية يجب أن يكون 12 رقم',

                'email.unique' => 'ألبريد الالكترونى مستخدم بالفعل',


                'identity_type_id.required' => 'اختر نوع الهوية',

                'country_id.required' => 'اختر الدولة',

                'profile_picture.required' => 'الصورة الشخصية مطلوبة',
                'profile_picture.image' => 'الصورة الشخصية يجب أن تكون صورة',
                'profile_picture.mimes' => 'الصورة الشخصية يجب أن تكون من نوع jpg أو jpeg أو png',
                'profile_picture.max' => 'حجم الصورة الشخصية يجب ألا يزيد عن 2 ميجابايت',

                'identity_front_image.required' => 'صورة الهوية الأمامية مطلوبة',
                'identity_front_image.image' => 'صورة الهوية الأمامية يجب أن تكون صورة',
                'identity_front_image.mimes' => 'صورة الهوية الأمامية يجب أن تكون من نوع jpg أو jpeg أو png',
                'identity_front_image.max' => 'حجم صورة الهوية الأمامية يجب ألا يزيد عن 2 ميجابايت',

                'identity_back_image.required' => 'صورة الهوية الخلفية مطلوبة',
                'identity_back_image.image' => 'صورة الهوية الخلفية يجب أن تكون صورة',
                'identity_back_image.mimes' => 'صورة الهوية الخلفية يجب أن تكون من نوع jpg أو jpeg أو png',
                'identity_back_image.max' => 'حجم صورة الهوية الخلفية يجب ألا يزيد عن 2 ميجابايت',
                 'identity_start_date.required' => 'تاريخ بداية الهوية مطلوب',
                'identity_start_date.date' => 'تاريخ بداية الهوية غير صالح',
            
                'identity_end_date.required' => 'تاريخ انتهاء الهوية مطلوب',
                'identity_end_date.date' => 'تاريخ انتهاء الهوية غير صالح',
                'identity_end_date.after' => 'تاريخ انتهاء الهوية يجب أن يكون بعد تاريخ البداية',
                'address' =>'required',
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

        } catch (ValidationException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'VALIDATION_ERROR',
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
        // ❌ Database
        catch (QueryException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'DATABASE_ERROR',
                'message'    => 'Database error',
            ], 409);
        }
        // ❌ Server
        catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => 'Unexpected error occurred',
            ], 500);
        }
    }


    public function edit_customer(Request $request,$id)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'phone_1' => 'required',
               
            
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
            return response()->json([
                'status'     => false,
                'error_code' => 'VALIDATION_ERROR',
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
        // ❌ Database
        catch (QueryException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'DATABASE_ERROR',
                'message'    => 'Database error',
            ], 409);
        }
        // ❌ Server
        catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => 'Unexpected error occurred',
            ], 500);
        }
    }
    
    
    public function add_application(Request $request)
    {
        try {
            // ===============================
            // Validation
            // ===============================
            $validated = $request->validate([
                'count' => 'required|integer|min:1|max:500'
            ]);
    
            $count = $validated['count'];
            $applications = [];
    
            for ($i = 0; $i < $count; $i++) {
    
                // توليد رقم عشوائي فريد
                do {
                    $number = mt_rand(100000, 999999);
                } while (Application::where('number', $number)->exists());
    
                // إنشاء الاستمارة
                $application = Application::create([
                    'number' => $number
                ]);
    
                $applications[] = [
                    'number' => $number,
                    'pdf_url' => url("/api/application/print/{$number}")
                ];
            }
    
            return response()->json([
                'status'  => true,
                'message' => 'تم طباعة الاستمارة بنجاح',
                'total'   => count($applications),
                'data'    => $applications
            ], 201);
    
        }
        // ❌ Validation Errors
        catch (ValidationException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'VALIDATION_ERROR',
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
        // ❌ Database Errors
        catch (QueryException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'DATABASE_ERROR',
                'message'    => 'Database error',
                'errors'     => [
                    'sql' => $e->getCode()
                ],
            ], 409);
        }
        // ❌ أي خطأ غير متوقع
        catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => 'Unexpected error occurred',
            ], 500);
        }
    }
   
    public function get_service_type()
    {
        $items = ServiceType::get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
    
    public function add_service_type(Request $request)
    {
        try {
    
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:service_types,name',
            ]);
    
            $bank = ServiceType::create($validated);
    
            return response()->json([
                'status'  => true,
                'message' => 'ServiceType added successfully',
                'data'    => $bank,
            ], 201);
    
        }
        // ❌ أخطاء الـ Validation
        catch (ValidationException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'VALIDATION_ERROR',
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
        // ❌ أخطاء قاعدة البيانات (Duplicate – SQL)
        catch (QueryException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'DATABASE_ERROR',
                'message'    => 'Database error',
                'errors'     => [
                    'sql' => $e->getCode(),
                ],
            ], 409);
        }
        // ❌ أي خطأ غير متوقع
        catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => 'Unexpected error occurred',
            ], 500);
        }
    }
    public function edit_service_type(Request $request, $id)
    {
        try {
    
            $bank = ServiceType::findOrFail($id);
    
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:service_types,name,' . $bank->id,
            ]);
    
            $bank->update($validated);
    
            return response()->json([
                'status'  => true,
                'message' => 'ServiceType updated successfully',
                'data'    => $bank,
            ], 200);
    
        }
        // ❌ Validation
        catch (ValidationException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'VALIDATION_ERROR',
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
        // ❌ Database
        catch (QueryException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'DATABASE_ERROR',
                'message'    => 'Database error',
            ], 409);
        }
        // ❌ Not Found
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'NOT_FOUND',
                'message'    => 'Bank not found',
            ], 404);
        }
        // ❌ Server
        catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => 'Unexpected error occurred',
            ], 500);
        }
    }
    
    public function get_service(Request $request)
    {
        $items = Service::query()
            ->when($request->search, function ($q, $v) {
                $q->where(function ($query) use ($v) {
                    $query->where('name', 'like', "%{$v}%")
                          ->orWhere('code', $v);
                });
            })
            ->when($request->category_id, function ($q, $v) {
                $q->where('category_id', $v);
            })
            ->whereHas('prices')
            ->with('prices')
            ->latest()
            ->paginate(20);
    
        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $items,
        ]);
    }

    
    public function add_service(Request $request)
    {
        try {
            // ===============================
            // Validation
            // ===============================
            $validated = $request->validate([
                'name'        => 'required|string|max:255',
                'code'        => 'required|string|max:50',
                'category_id' => 'required|exists:categories,id',
                'prices'      => 'required|array|min:1',
                'prices.*.service_type_id' => 'required|exists:service_types,id',
                'prices.*.price'           => 'required|numeric|min:1',
            ]);
    
            // ===============================
            // Create Service
            // ===============================
            $service = Service::create([
                'name'        => $validated['name'],
                'code'        => $validated['code'],
                'category_id' => $validated['category_id'],
            ]);
    
            // ===============================
            // Create ServicePrices for each ServiceType
            // ===============================
            foreach ($validated['prices'] as $p) {
                ServicePrice::create([
                    'service_id'      => $service->id,
                    'service_type_id' => $p['service_type_id'],
                    'price'           => $p['price'],
                ]);
            }
    
            return response()->json([
                'status'  => true,
                'message' => 'Service added successfully',
                'data'    => $service->load('prices', 'category')
            ], 201);
    
        } 
        // ❌ Validation Errors
        catch (ValidationException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'VALIDATION_ERROR',
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
        // ❌ Database Errors
        catch (QueryException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'DATABASE_ERROR',
                'message'    => 'Database error',
                'errors'     => [
                    'sql' => $e->getCode(),
                ],
            ], 409);
        }
        // ❌ Server Errors
        catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => 'Unexpected error occurred',
            ], 500);
        }
    }

    
    public function edit_service(Request $request, $id)
    {
        try {
            // جلب الخدمة
            $service = Service::findOrFail($id);
    
            // ===============================
            // Validation
            // ===============================
            $validated = $request->validate([
                'name'        => 'required|string|max:255',
                'code'        => 'required|string|max:50',
                'category_id' => 'required|exists:categories,id',
                'prices'      => 'required|array|min:1',
                'prices.*.service_type_id' => 'required|exists:service_types,id',
                'prices.*.price'           => 'required|numeric|min:1',
            ]);
    
            // ===============================
            // Update Service
            // ===============================
            $service->update([
                'name'        => $validated['name'],
                'code'        => $validated['code'],
                'category_id' => $validated['category_id'],
            ]);
    
            // ===============================
            // Update ServicePrices
            // ===============================
            foreach ($validated['prices'] as $p) {
                ServicePrice::updateOrCreate(
                    [
                        'service_id'      => $service->id,
                        'service_type_id' => $p['service_type_id'],
                    ],
                    [
                        'price' => $p['price'],
                    ]
                );
            }
    
            return response()->json([
                'status'  => true,
                'message' => 'Service updated successfully',
                'data'    => $service->load('prices', 'category')
            ], 200);
    
        } 
        // ❌ Validation Errors
        catch (ValidationException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'VALIDATION_ERROR',
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
        // ❌ Database Errors
        catch (QueryException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'DATABASE_ERROR',
                'message'    => 'Database error',
                'errors'     => [
                    'sql' => $e->getCode(),
                ],
            ], 409);
        }
        // ❌ Server Errors
        catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => 'Unexpected error occurred',
            ], 500);
        }
    }
    
    
    public function toggle_services(Request $request, $id)
    {
        try {
    
            // ===============================
            // Validation
            // ===============================
            $validated = $request->validate([
                'active' => 'required|boolean',
            ]);
    
            // ===============================
            // Find Service
            // ===============================
            $service = Service::findOrFail($id);
    
            // ===============================
            // Update Status
            // ===============================
            $service->update([
                'active' => $validated['active']
            ]);
    
            return response()->json([
                'status'  => true,
                'message' => 'Service status updated successfully',
                'data'    => $service->load('prices', 'category')
            ], 200);
    
        }
        // ❌ Validation Errors
        catch (ValidationException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'VALIDATION_ERROR',
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
        // ❌ Database Errors
        catch (QueryException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'DATABASE_ERROR',
                'message'    => 'Database error',
            ], 409);
        }
        // ❌ Server Errors
        catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => 'Unexpected error occurred',
            ], 500);
        }
    }
    
    
    public function license(Request $request)
    {
        $items = BranchRenewal::query()
            ->join('license_branches', 'license_branches.id', '=', 'branch_renewals.license_branch_id')
            ->join('licenses', 'licenses.id', '=', 'license_branches.license_id')
            ->leftJoin('customers', 'customers.id', '=', 'licenses.customer_id')
            ->leftJoin('directorates', 'directorates.id', '=', 'license_branches.directorate_id')
            ->select('branch_renewals.*')
    
            ->when($request->license_id, function ($q, $v) {
                $q->where('branch_renewals.id', $v);
            })
    
            ->when($request->customer_name, function ($q, $v) {
                $q->where('customers.name', 'like', "%$v%");
            })
    
            ->when($request->business_name, function ($q, $v) {
                $q->where('licenses.business_name', 'like', "%$v%");
            })
    
            ->when($request->customer_identity_number, function ($q, $v) {
                $q->where('customers.identity_number', 'like', "%$v%");
            })
    
            ->when($request->directorate_id, function ($q, $v) {
                $q->where('directorates.id', 'like', $v);
            })
    
            ->when($request->status_id, function ($q, $v) {
                $q->where('branch_renewals.status_id', $v);
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
    
            ->latest()
            ->paginate(8);
        $items->getCollection()->transform(function ($item) {
            return [
                'license_id'               => $item->id,
                'category_name'             => $item->category_name,
                'sub_category_name'=>$item->sub_name,
                'directorate_name' =>$item->directorate_name,
                'customer_name' =>$item->customer_name,
                'business_name' =>$item->business_name,
                'customer_identity_number' =>$item->customer_identity_number,
                'status_id' =>$item->status_id,
                'status_name' =>$item->dashboard_status_name,
                'status_color' =>$item->dashboard_status_color,
                'department_name' =>$item->department_name,
                'customer_id' =>$item->customer_id,
                
                
            ];
        });
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
    
    public function license_count()
    {
        $all = BranchRenewal::count();
    
        // ===============================
        // العد حسب الـ category
        // ===============================
        $counts = BranchRenewal::join('license_branches', 'license_branches.id', '=', 'branch_renewals.license_branch_id')
            ->join('licenses', 'licenses.id', '=', 'license_branches.license_id')
            ->select('licenses.category_id', \DB::raw('COUNT(branch_renewals.id) as total'))
            ->groupBy('licenses.category_id')
            ->pluck('total', 'category_id');
    
        // ===============================
        // العد حسب Dashboard Status
        // ===============================
        $statusCounts = BranchRenewal::with('license_branch.license')
            ->get()
            ->map(function($item) {
                // نفس logic getDashboardStatusAttribute
                $status = $item->getDashboardStatusAttribute()['label'];
                return $status;
            })
            ->countBy(); // يحسب كل label عدد مرات ظهوره
    
        return response()->json([
            'status'   => 200,
            'message'  => 'success',
            'all'      => $all,
            // حسب category
            'elash8al' => $counts[1] ?? 0,
            'elskafa'  => $counts[2] ?? 0,
            'elsya7a'  => $counts[3] ?? 0,
            'else7a'   => $counts[4] ?? 0,
            // حسب status
            'status_counts' => $statusCounts,
        ]);
    }

   
    public function tax(Request $request)
    {
        $items = CustomerTaxBalance::query()
            ->join('customers', 'customers.id', '=', 'customer_tax_balance.customer_id')
            ->join('tax_files', 'tax_files.customer_id', '=', 'customer_tax_balance.customer_id')
            ->leftJoin('users', 'users.id', '=', 'customer_tax_balance.user_id')
            ->leftJoin('directorates', 'directorates.id', '=', 'users.directorate_id')
            // join على أول license لكل عميل لتجنب التكرار
            ->join('licenses as l', function($join) {
                $join->on('l.customer_id', '=', 'customers.id')
                     ->whereRaw('l.id = (SELECT MIN(id) FROM licenses WHERE licenses.customer_id = customers.id)');
            })
            ->select(
                'customer_tax_balance.*',
                'l.business_name as business_name',
                'customers.name as customer_name',
                'customers.identity_number as customer_identity_number'
            )
    
            // ===============================
            // فلاتر البحث
            // ===============================
            ->when($request->tax_file_id, function ($q, $v) {
                $q->where('tax_files.id', $v);
            })
            ->when($request->business_name, function ($q, $v) {
                $q->where('l.business_name', 'like', "%$v%");
            })
            ->when($request->customer_name, function ($q, $v) {
                $q->where('customers.name', 'like', "%$v%");
            })
            ->when($request->customer_identity_number, function ($q, $v) {
                $q->where('customers.identity_number', 'like', "%$v%");
            })
            ->when($request->directorate_id, function ($q, $v) {
                $q->where('directorates.id', $v);
            })
            ->when($request->status_id, function ($q, $v) {
                $q->where('customer_tax_balance.tax_status_id', $v);
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
            ->latest()
            ->paginate(8);
    
        // ===============================
        // صياغة النتائج للـ API
        // ===============================
        $items->getCollection()->transform(function ($item) {
            return [
                'id'                      => $item->id,
                'customer_tax_file_id'    => $item->customer_tax_file_id,
                'customer_name'           => $item->customer_name,
                'customer_identity_number'=> $item->customer_identity_number,
                'tax_type_name'           => $item->tax_type_name,
                'directorate_name'        => $item->directorate_name,
                'year'                    => $item->year,
                'business_name'           => $item->business_name,
                'status_name'             => $item->dashboard_status_name,
                'status_color'             => $item->dashboard_status_color,
                'status_id'               => $item->tax_status_id,
                'amount'                  => $item->total_amount, // الاجمالي فقط
                'department_name'         => $item->department_name,
                'customer_id' =>$item->customer_id,
            ];
        });
    
        return response()->json([
            'status'  => 200,
            'message' => 'success',
            'data'    => $items,
        ]);
    }



    public function tax_count()
    {
        $all = CustomerTaxBalance::count();
    
        $realstate = CustomerTaxBalance::where('tax_type_id',1)->count();
        $professions = CustomerTaxBalance::where('tax_type_id',2)->count();
        $earning  = CustomerTaxBalance::where('tax_type_id',3)->count();
        $mo3tamd = CustomerTaxBalance::where('tax_status_id',2)->count();
        $marfod = CustomerTaxBalance::where('tax_status_id',3)->count();
        $qidelta7sel = CustomerTaxBalance::where('tax_status_id',4)->count();
        $tmelta7sel = CustomerTaxBalance::where('tax_status_id',5)->count();
        $under_review = CustomerTaxBalance::where('tax_status_id',1)->count();
        return response()->json([
            'status'   => 200,
            'message'  => 'success',
            'all'      => $all,
            'realstate' => $realstate,
            'professions' => $professions,
            'earning' => $earning,
            'mo3tamd' => $mo3tamd,
            'marfod' => $marfod,
            'qidelta7sel' => $qidelta7sel,
            'tmelta7sel' => $tmelta7sel, 
            'under_review' =>$under_review,
        ]);
    }

    public function tax_status()
    {
        $items = TaxStatus::get();
    
       
        return response()->json([
            'status'   => 200,
            'message'  => 'success',
            'items'      => $items,
           
        ]);
    }


    public function zakah(Request $request)
    {
        $items = CustomerZakahBalance::query()
            ->join('customers', 'customers.id', '=', 'customer_zakah_balance.customer_id')
            ->join('zakah_numbers', 'zakah_numbers.customer_id', '=', 'customer_zakah_balance.customer_id')
            ->leftJoin('users', 'users.id', '=', 'customer_zakah_balance.user_id')
            ->leftJoin('directorates', 'directorates.id', '=', 'users.directorate_id')
            // join على أول license لكل عميل لتجنب التكرار
            ->join('licenses as l', function($join) {
                $join->on('l.customer_id', '=', 'customers.id')
                     ->whereRaw('l.id = (SELECT MIN(id) FROM licenses WHERE licenses.customer_id = customers.id)');
            })
            ->select(
                'customer_zakah_balance.*',
                'l.business_name as business_name',
                'customers.name as customer_name',
                'customers.identity_number as customer_identity_number'
            )
    
            // ===============================
            // فلاتر البحث
            // ===============================
            ->when($request->zakah_number_id, function ($q, $v) {
                $q->where('zakah_numbers.id', $v);
            })
            ->when($request->business_name, function ($q, $v) {
                $q->where('l.business_name', 'like', "%$v%");
            })
            ->when($request->customer_name, function ($q, $v) {
                $q->where('customers.name', 'like', "%$v%");
            })
            ->when($request->customer_identity_number, function ($q, $v) {
                $q->where('customers.identity_number', 'like', "%$v%");
            })
            ->when($request->directorate_id, function ($q, $v) {
                $q->where('directorates.id', $v);
            })
            ->when($request->status_id, function ($q, $v) {
                $q->where('customer_zakah_balance.zakah_status_id', $v);
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
            ->latest()
            ->paginate(8);
    
        // ===============================
        // صياغة النتائج للـ API
        // ===============================
        $items->getCollection()->transform(function ($item) {
            return [
                'id'                      => $item->id,
                'customer_zakah_number_id' => $item->customer_zakah_number_id,
                'customer_name'           => $item->customer_name,
                'customer_identity_number'=> $item->customer_identity_number,
                'zakah_type_name'           => $item->zakah_type_name,
                'directorate_name'        => $item->directorate_name,
                'year'                    => $item->year,
                'business_name'           => $item->business_name,
                'status_name'             => $item->status_name,
                'status_id'               => $item->zakah_status_id,
                'amount'                  => $item->total, // الاجمالي فقط
                'department_name'         => $item->department_name,
                'customer_id' =>$item->customer_id,
            ];
        });
    
        return response()->json([
            'status'  => 200,
            'message' => 'success',
            'data'    => $items,
        ]);
    }
    
    public function zakah_count()
    {
        $all = CustomerZakahBalance::count();
    
        $keta33amw5as = CustomerZakahBalance::where('zakah_type_id',1)->count();
        $khas = CustomerZakahBalance::where('zakah_type_id',2)->count();
        $afrad  = CustomerZakahBalance::where('zakah_type_id',3)->count();
        $other  = CustomerZakahBalance::where('zakah_type_id',4)->count();
        $mo3tamd = CustomerZakahBalance::where('zakah_status_id',4)->count();
        $marfod = CustomerZakahBalance::where('zakah_status_id',5)->count();
        $qidelta7sel = CustomerZakahBalance::where('zakah_status_id',6)->count();
        $tmelta7sel = CustomerZakahBalance::where('zakah_status_id',8)->count();
        $under_review = CustomerZakahBalance::where('zakah_status_id',1)->count();
        return response()->json([
            'status'   => 200,
            'message'  => 'success',
            'all'      => $all,
            'keta33amw5as' => $keta33amw5as,
            'khas' => $khas,
            'afrad' => $afrad,
            'other' =>$other,
            'mo3tamd' => $mo3tamd,
            'marfod' => $marfod,
            'qidelta7sel' => $qidelta7sel,
            'tmelta7sel' => $tmelta7sel, 
            'under_review' =>$under_review,
        ]);
    }
    
    
    public function zakah_status()
    {
        $items = ZakahStatus::get();
    
       
        return response()->json([
            'status'   => 200,
            'message'  => 'success',
            'items'      => $items,
           
        ]);
    }

    public function users(Request $request)
    {
        $items = User::query()
        
            ->when($request->name, function ($q, $v) {
                $q->where('name', 'like', "%{$v}%");
                 
            })
            
            ->when($request->email, function ($q, $v) {
                $q->where('email', $v);
            })
            
            ->when($request->department_id, function ($q, $v) {
                $q->where('department_id', $v);
            })
            
            ->when($request->role, function ($q, $v) {
                $q->where('role', $v);
            })
            
            ->when($request->has('active'), function ($q) use ($request) {
                $q->where('active', $request->active);
            })
            ->when($request->from, function ($q, $v) {
                $q->whereDate('created_at', '>=', $v);
            })
            ->when($request->to, function ($q, $v) {
                $q->whereDate('created_at', '<=', $v);
            })
            ->latest()
            ->paginate(20);
    
       
        return response()->json([
            'status'   => 200,
            'message'  => 'success',
            'items'      => $items,
           
        ]);
    }
    
    public function departments()
    {
        $items = Department::get();
    
       
        return response()->json([
            'status'   => 200,
            'message'  => 'success',
            'items'      => $items,
           
        ]);
    }
    
    public function add_user(Request $request)
    {
        try {
            
          $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|string|email|unique:users',
                'phone' => 'nullable|unique:users',
                'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'directorate_id' => 'required',
                'role' => 'required',
                'department_id' => 'required',
            ]);

            $name = null;
            if ($file = $request->file('image')) {
                $name = time() . $file->getClientOriginalName();
                $file->move('users', $name);
            }

            $user = User::create([
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'password' => bcrypt('123456789'),
                'image' => $name,
                'directorate_id' => $request->directorate_id,
                'role' => $request->role,
                'department_id' => $request->department_id,
               
            ]);
    
            
            return response()->json([
                'status'  => true,
                'message' => 'User added successfully',
                
            ], 201);
    
        } 
        // ❌ Validation Errors
        catch (ValidationException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'VALIDATION_ERROR',
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
        // ❌ Database Errors
        catch (QueryException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'DATABASE_ERROR',
                'message'    => 'Database error',
                'errors'     => [
                    'sql' => $e->getCode(),
                ],
            ], 409);
        }
        // ❌ Server Errors
        catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => 'Unexpected error occurred',
            ], 500);
        }
    }
    
    public function edit_user(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id); // التأكد إن المستخدم موجود
    
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|string|email|unique:users,email,' . $user->id,
                'phone' => 'nullable|unique:users,phone,' . $user->id,
                'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'directorate_id' => 'required',
                'role' => 'required',
                'department_id' => 'required',
            ]);
    
            // معالجة رفع الصورة
            if ($file = $request->file('image')) {
                // حذف الصورة القديمة لو موجودة
                if ($user->image && file_exists(public_path('users/' . $user->image))) {
                    unlink(public_path('users/' . $user->image));
                }
    
                $name = time() . $file->getClientOriginalName();
                $file->move('users', $name);
                $user->image = $name;
            }
    
            // تحديث البيانات
            $user->name = $request->name;
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->directorate_id = $request->directorate_id;
            $user->role = $request->role;
            $user->department_id = $request->department_id;
            $user->save();
    
            return response()->json([
                'status' => true,
                'message' => 'User updated successfully',
                'data' => $user
            ], 200);
    
        } 
        catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
        catch (QueryException $e) {
            return response()->json([
                'status' => false,
                'error_code' => 'DATABASE_ERROR',
                'message' => 'Database error',
                'errors' => [
                    'sql' => $e->getCode(),
                ],
            ], 409);
        }
        catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error_code' => 'SERVER_ERROR',
                'message' => 'Unexpected error occurred',
                'exception' => $e->getMessage()
            ], 500);
        }
    }
    
    public function toggle_user(Request $request, $id)
    {
        try {
    
            // ===============================
            // Validation
            // ===============================
            $validated = $request->validate([
                'active' => 'required|boolean',
            ]);
    
            // ===============================
            // Find Service
            // ===============================
            $user = User::findOrFail($id);
    
            // ===============================
            // Update Status
            // ===============================
            $user->update([
                'active' => $validated['active']
            ]);
    
            return response()->json([
                'status'  => true,
                'message' => 'user status updated successfully',
                'data'    => $user,
            ], 200);
    
        }
        // ❌ Validation Errors
        catch (ValidationException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'VALIDATION_ERROR',
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
        // ❌ Database Errors
        catch (QueryException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'DATABASE_ERROR',
                'message'    => 'Database error',
            ], 409);
        }
        // ❌ Server Errors
        catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => 'Unexpected error occurred',
            ], 500);
        }
    }
    
    
     public function get_directorate()
    {
        $items = Directorate::paginate(8);
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
    public function add_directorate(Request $request)
    {
        try {
    
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:directorates,name',
            ]);
    
            $bank =  new Directorate();
            $bank->name = $request->name;
            $bank->governorate_id  = 1;
            $bank->save();
    
            return response()->json([
                'status'  => true,
                'message' => 'directorate added successfully',
                'data'    => $bank,
            ], 201);
    
        }
        // ❌ أخطاء الـ Validation
        catch (ValidationException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'VALIDATION_ERROR',
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
        // ❌ أخطاء قاعدة البيانات (Duplicate – SQL)
        catch (QueryException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'DATABASE_ERROR',
                'message'    => 'Database error',
                'errors'     => [
                    'sql' => $e->getCode(),
                ],
            ], 409);
        }
        // ❌ أي خطأ غير متوقع
        catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => 'Unexpected error occurred',
            ], 500);
        }
    }
    public function edit_directorate(Request $request, $id)
    {
        try {
    
            $bank = Directorate::findOrFail($id);
    
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:directorates,name,' . $bank->id,
            ]);
    
            $bank->update($validated);
    
            return response()->json([
                'status'  => true,
                'message' => 'directorate updated successfully',
                'data'    => $bank,
            ], 200);
    
        }
        // ❌ Validation
        catch (ValidationException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'VALIDATION_ERROR',
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
        // ❌ Database
        catch (QueryException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'DATABASE_ERROR',
                'message'    => 'Database error',
            ], 409);
        }
        // ❌ Not Found
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'NOT_FOUND',
                'message'    => 'Bank not found',
            ], 404);
        }
        // ❌ Server
        catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => 'Unexpected error occurred',
            ], 500);
        }
    }
    
    public function toggle_directorate(Request $request, $id)
    {
        try {
    
            // ===============================
            // Validation
            // ===============================
            $validated = $request->validate([
                'active' => 'required|boolean',
            ]);
    
            // ===============================
            // Find Service
            // ===============================
            $directorate = Directorate::findOrFail($id);
    
            // ===============================
            // Update Status
            // ===============================
            $directorate->update([
                'active' => $validated['active']
            ]);
    
            return response()->json([
                'status'  => true,
                'message' => 'directorate status updated successfully',
                'data'    => $directorate,
            ], 200);
    
        }
        // ❌ Validation Errors
        catch (ValidationException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'VALIDATION_ERROR',
                'message'    => 'Validation failed',
                'errors'     => $e->errors(),
            ], 422);
        }
        // ❌ Database Errors
        catch (QueryException $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'DATABASE_ERROR',
                'message'    => 'Database error',
            ], 409);
        }
        // ❌ Server Errors
        catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'SERVER_ERROR',
                'message'    => 'Unexpected error occurred',
            ], 500);
        }
    }
    
}
