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
use Illuminate\Support\Facades\DB;

class ReportsApiController extends Controller
{
    
 
    public function revenues_reports(Request $request)
    {
        if($request->report_type == "license_general")
        {
                /*
            |--------------------------------------------------------------------------
            | 1️⃣ أول تاريخ لكل license_id (لتحديد جديد / تجديد)
            |--------------------------------------------------------------------------
            */
            $firstLicenses = BranchRenewal::join(
                    'license_branches',
                    'license_branches.id',
                    '=',
                    'branch_renewals.license_branch_id'
                )
                ->select(
                    'license_branches.license_id',
                    DB::raw('MIN(branch_renewals.created_at) as first_created_at')
                )
                ->groupBy('license_branches.license_id')
                ->get()
                ->keyBy('license_id');
    
            /*
            |--------------------------------------------------------------------------
            | 2️⃣ Query أساسي + الفلاتر
            |--------------------------------------------------------------------------
            */
            $query = BranchRenewal::with('license_branch')

            // ✅ فلترة من تاريخ
            ->when($request->filled('from'), function ($q) use ($request) {
                $q->whereDate('created_at', '>=', $request->from);
            })
        
            // ✅ فلترة إلى تاريخ
            ->when($request->filled('to'), function ($q) use ($request) {
                $q->whereDate('created_at', '<=', $request->to);
            })
            
            
            // ✅ فلترة بالمديرية
            ->when($request->filled('directorate'), function ($q) use ($request) {
                $q->whereHas('license_branch', function ($qq) use ($request) {
                    $qq->where('directorate_id', $request->directorate);
                });
            });

    
            $data = $query->get();
    
            /*
            |--------------------------------------------------------------------------
            | 3️⃣ تحديد نوع الحركة (new / renew)
            |--------------------------------------------------------------------------
            */
            $prepared = $data->map(function ($item) use ($firstLicenses) {
    
                $licenseId = $item->license_branch?->license_id;
                $firstDate = $firstLicenses[$licenseId]->first_created_at ?? null;
    
                $item->type = ($firstDate && $item->created_at->eq($firstDate))
                    ? 'new'
                    : 'renew';
    
                return $item;
            });
    
     
            $reportRows = collect();

            $report = $data
                ->groupBy(fn ($item) => $item->license_branch?->category_name ?? 'غير محدد')
                ->map(function ($categoryItems, $category) use ($request, &$reportRows) {
            
                    $categoryItems
                        ->groupBy(fn ($item) => $item->license_branch?->directorate_name ?? 'غير محدد')
                        ->each(function ($items, $directorate) use ($category, $request, &$reportRows) {
            
                            $new   = $items->filter(fn ($i) => $i->type === 'new');
                            $renew = $items->filter(fn ($i) => $i->type === 'renew');
            
                            $row = [
                                'category'     => $category,
                                'directorate'  => $directorate,
                            ];
            
                            if ($request->type === 'new' || !$request->type) {
                                $row += [
                                    'new_under_review' => $new->where('status_id', 1)->count(),
                                    'new_approved'     => $new->where('status_id', 2)->count(),
                                    'new_rejected'     => $new->where('status_id', 3)->count(),
                                    'new_collecting'   => $new->where('status_id', 4)->count(),
                                    'new_collected'    => $new->where('status_id', 5)->count(),
                                    'new_total'        => $new->count(),
                                ];
                            }
            
                            if ($request->type === 'renew' || !$request->type) {
                                $row += [
                                    'renew_under_review' => $renew->where('status_id', 1)->count(),
                                    'renew_approved'     => $renew->where('status_id', 2)->count(),
                                    'renew_rejected'     => $renew->where('status_id', 3)->count(),
                                    'renew_collecting'   => $renew->where('status_id', 4)->count(),
                                    'renew_collected'    => $renew->where('status_id', 5)->count(),
                                    'renew_total'        => $renew->count(),
                                ];
                            }
            
                            $row['grand_total'] = $items->count();
            
                            // 👈 هنا صف تقرير واحد
                            $reportRows->push($row);
                        });
                });
                $page    = $request->get('page', 1);
                $perPage = $request->get('per_page', 10);
                
                $paginated = new LengthAwarePaginator(
                    $reportRows->forPage($page, $perPage)->values(),
                    $reportRows->count(),
                    $perPage,
                    $page,
                    [
                        'path'  => $request->url(),
                        'query' => $request->query(),
                    ]
                );
            
                return response()->json([
                    'filters' => [
                        'from'        => $request->from,
                        'to'          => $request->to,
                        'directorate' => $request->directorate,
                        'type'        => $request->type,
                       
                    ],
                    'pagination' => [
                        'current_page' => $paginated->currentPage(),
                        'per_page'     => $paginated->perPage(),
                        'total'        => $paginated->total(),
                        'last_page'    => $paginated->lastPage(),
                    ],
                    'data' => $paginated->items(), // 👈 صفوف التقرير فقط
                ]);


        }elseif ($request->report_type == "license_movements")
        {
            $items = BranchRenewal::query()
                ->join('license_branches', 'license_branches.id', '=', 'branch_renewals.license_branch_id')
                ->join('licenses', 'licenses.id', '=', 'license_branches.license_id')
                ->leftJoin('customers', 'customers.id', '=', 'licenses.customer_id')
                ->leftJoin('directorates', 'directorates.id', '=', 'license_branches.directorate_id')
                ->select('branch_renewals.*')
        
                ->when($request->license_id, fn ($q, $v) =>
                    $q->where('branch_renewals.id', $v))
                    
                ->when($request->application_number, fn ($q, $v) =>
                    $q->where('branch_renewals.application_number', $v))
        
                ->when($request->customer_name, fn ($q, $v) =>
                    $q->where('customers.name', 'like', "%$v%"))
        
                ->when($request->business_name, fn ($q, $v) =>
                    $q->where('licenses.business_name', 'like', "%$v%"))
        
                ->when($request->customer_identity_number, fn ($q, $v) =>
                    $q->where('customers.identity_number', 'like', "%$v%"))
        
                ->when($request->directorate_id, fn ($q, $v) =>
                    $q->where('directorates.id', $v))
        
                ->when($request->status_id, fn ($q, $v) =>
                    $q->where('branch_renewals.status_id', $v))
        
                ->when($request->category_id, fn ($q, $v) =>
                    $q->where('licenses.category_id', $v))
                    
                ->when($request->filled('year'), function ($q) use ($request) {
                        $q->whereYear('branch_renewals.created_at', $request->year);
                  })
        
        
                ->when($request->from, fn ($q, $v) =>
                    $q->whereDate('branch_renewals.created_at', '>=', $v))
        
                ->when($request->to, fn ($q, $v) =>
                    $q->whereDate('branch_renewals.created_at', '<=', $v))
                    
                ->when($request->department, function ($q, $v) {
                    match ($v) {
                        'المراجعة' => $q->where('branch_renewals.status_id', 1),
                        'المالية'  => $q->where('branch_renewals.status_id', 4),
                        'الادخال'  => $q->whereIn('branch_renewals.status_id', [2,3,5]),
                        default    => null,
                    };
                })
        
                ->latest()
                ->get();
        
            /*
            |--------------------------------------------------------------------------
            | 🔁 Pagination يدوي (نفس الأول)
            |--------------------------------------------------------------------------
            */
            $page    = $request->get('page', 1);
            $perPage = $request->get('per_page', 8);
        
            $paginated = new LengthAwarePaginator(
                $items->forPage($page, $perPage)->values(),
                $items->count(),
                $perPage,
                $page,
                [
                    'path'  => $request->url(),
                    'query' => $request->query(),
                ]
            );
        
            $paginated->getCollection()->transform(function ($item) {
                return [
                    'application_number' => $item->application_number, 'license_id' => $item->id, 'category_name' => $item->category_name, 'sub_category_name'=>$item->sub_name, 'directorate_name' =>$item->directorate_name, 'customer_name' =>$item->customer_name, 'business_name' =>$item->business_name, 'customer_identity_number' =>$item->customer_identity_number, 'status_id' =>$item->status_id, 'status_name' =>$item->dashboard_status_name, 'status_color' =>$item->dashboard_status_color, 'department_name' =>$item->department_name, 'customer_id' =>$item->customer_id,
                    'amount'       => $item->amount,
                    'year' => Carbon::parse($item->start_date)->format('Y'),
                    'notes' => $item->notes,
                ];
            });
        
            return response()->json([
                'filters' => [
                    'application_number' => $request->application_number,
                    'license_id' => $request->license_id,
                    'customer_name' => $request->customer_name,
                    'business_name' => $request->business_name,
                    'customer_identity_number' => $request->customer_identity_number,
                    'directorate_id' => $request->directorate_id,
                    'category_id' => $request->category_id,
                    'from' => $request->from,
                    'to' => $request->to,
                    'status_id' => $request->status_id,
                    'department' => $request->department,
                    'year' => $request->year,
                    
                ],
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
                'data' => $paginated->items(),
            ]);
        }elseif ($request->report_type == "license_taxpayers") {

            /*
            |--------------------------------------------------------------------------
            | Query: عدد العملاء (customers) لكل مديرية
            |--------------------------------------------------------------------------
            */
            $query = DB::table('license_branches')
                ->join('licenses', 'licenses.id', '=', 'license_branches.license_id')
                ->join('customers', 'customers.id', '=', 'licenses.customer_id')
                ->join('directorates', 'directorates.id', '=', 'license_branches.directorate_id')
                ->select(
                    'directorates.id as directorate_id',
                    'directorates.name as directorate_name',
                    DB::raw('COUNT(DISTINCT customers.id) as customers_count')
                )
                ->groupBy('directorates.id', 'directorates.name');
        
            /*
            |--------------------------------------------------------------------------
            | فلترة بالمديرية
            |--------------------------------------------------------------------------
            */
            if ($request->filled('directorate_id')) {
                $query->where('directorates.id', $request->directorate_id);
            }
        
            /*
            |--------------------------------------------------------------------------
            | فلترة بعدد العملاء (Range)
            |--------------------------------------------------------------------------
            | from_customers = أقل عدد
            | to_customers   = أكبر عدد
            |--------------------------------------------------------------------------
            
            */
            if ($request->filled('from_customers')) {
                $query->having('customers_count', '>=', (int) $request->from_customers);
            }
        
            if ($request->filled('to_customers')) {
                $query->having('customers_count', '<=', (int) $request->to_customers);
            }
        
            /*
            
            |--------------------------------------------------------------------------
            | Pagination
            |--------------------------------------------------------------------------
            */
            if ($request->filled('customers_count')) {
                 $query->having('customers_count', '=', (int) $request->customers_count);
            }
            
            $items = $query
                ->orderBy('customers_count', 'desc')
                ->paginate($request->get('per_page', 10));
        
            /*
            |--------------------------------------------------------------------------
            | Response
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'filters' => [
                    'directorate_id' => $request->directorate_id,
                    'from_customers' => $request->from_customers,
                    'to_customers'   => $request->to_customers,
                    'customers_count' => $request->customers_count,
                ],
                'pagination' => [
                    'current_page' => $items->currentPage(),
                    'per_page'     => $items->perPage(),
                    'total'        => $items->total(),
                    'last_page'    => $items->lastPage(),
                ],
                'data' => $items->items(),
            ]);
        }
        elseif ($request->report_type == "license_users") 
        {
            // أول حركة لكل license
            $firstMovements = DB::table('branch_renewals')
                ->join('license_branches', 'license_branches.id', '=', 'branch_renewals.license_branch_id')
                ->select(
                    'license_branches.license_id',
                    DB::raw('MIN(branch_renewals.created_at) as first_created_at')
                )
                ->groupBy('license_branches.license_id');
        
            // التقرير
            $query = DB::table('branch_renewals')
                ->join('license_branches', 'license_branches.id', '=', 'branch_renewals.license_branch_id')
                ->join('licenses', 'licenses.id', '=', 'license_branches.license_id')
                ->join('categories', 'categories.id', '=', 'licenses.category_id')
                ->join('users', 'users.id', '=', 'branch_renewals.user_id')
                ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
                ->leftJoin('directorates', 'directorates.id', '=', 'license_branches.directorate_id')
                ->leftJoinSub($firstMovements, 'first_moves', function ($join) {
                    $join->on('first_moves.license_id', '=', 'licenses.id');
                })
                ->select(
                    'users.id as user_id',
                    'users.name as user_name',
                    'departments.name as department_name',
                    'directorates.name as directorate_name',
                    'categories.name as category_name',
                    DB::raw("SUM(CASE WHEN branch_renewals.created_at = first_moves.first_created_at THEN 1 ELSE 0 END) as new_count"),
                    DB::raw("SUM(CASE WHEN branch_renewals.created_at != first_moves.first_created_at THEN 1 ELSE 0 END) as renew_count")
                )
                ->groupBy(
                    'users.id',
                    'users.name',
                    'departments.name',
                    'directorates.name',
                    'categories.name'
                );
        
            // فلاتر
            if ($request->filled('user_name')) {
                $query->where('users.name', 'like', '%' . $request->user_name . '%');
            }
            
            if ($request->filled('category_id')) {
                $query->where('categories.id', $request->category_id);
            }
        
            if ($request->filled('department_id')) {
                $query->where('departments.id', $request->department_id);
            }
        
            if ($request->filled('directorate_id')) {
                $query->where('directorates.id', $request->directorate_id);
            }
        
            if ($request->filled('from')) {
                $query->whereDate('branch_renewals.created_at', '>=', $request->from);
            }
        
            if ($request->filled('to')) {
                $query->whereDate('branch_renewals.created_at', '<=', $request->to);
            }
          

            
            
        
        
            // Pagination
            $items = $query
                ->orderBy('users.name')
                ->get();
        
            // جمع categories لكل user
            $data = $items->groupBy('user_id')->map(function ($userItems) {
                $first = $userItems->first();
                return [
                    'user_id' => $first->user_id,
                    'user_name' => $first->user_name,
                    'department_name' => $first->department_name,
                    'directorate_name' => $first->directorate_name,
                    'categories' => $userItems->map(function ($cat) {
                        return [
                            'name' => $cat->category_name,
                            'new_count' => $cat->new_count,
                            'renew_count' => $cat->renew_count,
                        ];
                    })->values(),
                ];
            })->values();
        
            // Pagination يدوي
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);
            $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
                $data->forPage($page, $perPage)->values(),
                $data->count(),
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );
        
            return response()->json([
                'filters' => [
                    'user_name' => $request->user_name,
                    'department' => $request->department,
                    'category_id' => $request->category_id,
                    'directorate_id' => $request->directorate_id,
                    'from' => $request->from,
                    'to' => $request->to,
                ],
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
                'data' => $paginated->items(),
            ]);
        }

        elseif ($request->report_type == "license_directorates") 
        {

            /*
            |-------------------------------------------------------------------------- 
            | SubQuery: أول حركة لكل license
            |-------------------------------------------------------------------------- 
            */
            $firstMovements = DB::table('branch_renewals')
                ->join('license_branches', 'license_branches.id', '=', 'branch_renewals.license_branch_id')
                ->select(
                    'license_branches.license_id',
                    DB::raw('MIN(branch_renewals.created_at) as first_created_at')
                )
                ->groupBy('license_branches.license_id');
        
            /*
            |-------------------------------------------------------------------------- 
            | Query التقرير
            |-------------------------------------------------------------------------- 
            */
            $query = DB::table('branch_renewals')
                ->join('license_branches', 'license_branches.id', '=', 'branch_renewals.license_branch_id')
                ->join('licenses', 'licenses.id', '=', 'license_branches.license_id')
                ->join('categories', 'categories.id', '=', 'licenses.category_id')
                ->leftJoin('directorates', 'directorates.id', '=', 'license_branches.directorate_id')
        
                ->leftJoinSub($firstMovements, 'first_moves', function ($join) {
                    $join->on('first_moves.license_id', '=', 'licenses.id');
                })
        
                ->select(
                    'directorates.id as directorate_id',
                    'directorates.name as directorate_name',
                    'categories.name as category_name',
        
                    DB::raw("
                        SUM(
                            CASE 
                                WHEN branch_renewals.created_at = first_moves.first_created_at 
                                THEN 1 ELSE 0 
                            END
                        ) as new_count
                    "),
        
                    DB::raw("
                        SUM(
                            CASE 
                                WHEN branch_renewals.created_at != first_moves.first_created_at 
                                THEN 1 ELSE 0 
                            END
                        ) as renew_count
                    ")
                )
                ->groupBy(
                    'directorates.id',
                    'directorates.name',
                    'categories.name'
                );
        
            /*
            |-------------------------------------------------------------------------- 
            | Filters
            |-------------------------------------------------------------------------- 
            */
            if ($request->filled('directorate_id')) {
                $query->where('directorates.id', $request->directorate_id);
            }
        
            if ($request->filled('category_id')) {
                $query->where('categories.id', $request->category_id);
            }
        
            if ($request->filled('from')) {
                $query->whereDate('branch_renewals.created_at', '>=', $request->from);
            }
        
            if ($request->filled('to')) {
                $query->whereDate('branch_renewals.created_at', '<=', $request->to);
            }
        
            /*
            |-------------------------------------------------------------------------- 
            | Get Data
            |-------------------------------------------------------------------------- 
            */
            $items = $query
                ->orderBy('directorates.name')
                ->get();
        
            /*
            |-------------------------------------------------------------------------- 
            | Format Response: تجميع categories لكل directorate
            |-------------------------------------------------------------------------- 
            */
            $data = $items->groupBy('directorate_id')->map(function ($rows) {
                $first = $rows->first();
                return [
                    'directorate_id'   => $first->directorate_id,
                    'directorate_name' => $first->directorate_name,
                    'categories'       => $rows->map(function ($row) {
                        return [
                            'category_name' => $row->category_name,
                            'new_count'     => (int) $row->new_count,
                            'renew_count'   => (int) $row->renew_count,
                        ];
                    })->values(),
                ];
            })->values();
        
            /*
            |-------------------------------------------------------------------------- 
            | Pagination يدوي
            |-------------------------------------------------------------------------- 
            */
            $page    = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);
        
            $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
                $data->forPage($page, $perPage)->values(),
                $data->count(),
                $perPage,
                $page,
                [
                    'path'  => $request->url(),
                    'query' => $request->query(),
                ]
            );
        
            /*
            |-------------------------------------------------------------------------- 
            | Response
            |-------------------------------------------------------------------------- 
            */
            return response()->json([
                'filters' => [
                    'directorate_id' => $request->directorate_id,
                    'category_id'    => $request->category_id,
                    'from'           => $request->from,
                    'to'             => $request->to,
                ],
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
                'data' => $paginated->items(),
            ]);
        }
        elseif ($request->report_type == "license_types") 
        {

            /*
            |--------------------------------------------------------------------------
            | 1️⃣ أول حركة لكل License (لتحديد new / renew / not_renewed)
            |--------------------------------------------------------------------------
            */
            $firstLicenses = BranchRenewal::join(
                    'license_branches',
                    'license_branches.id',
                    '=',
                    'branch_renewals.license_branch_id'
                )
                ->select(
                    'license_branches.license_id',
                    DB::raw('MIN(branch_renewals.created_at) as first_created_at'),
                    DB::raw('COUNT(branch_renewals.id) as renewals_count')
                )
                ->groupBy('license_branches.license_id')
                ->get()
                ->keyBy('license_id');
        
            /*
            |--------------------------------------------------------------------------
            | 2️⃣ Query أساسي + الفلاتر
            |--------------------------------------------------------------------------
            */
            $query = BranchRenewal::with('license_branch');
        
            
            if ($request->filled('from')) {
                $query->whereDate('branch_renewals.created_at', '>=', $request->from);
            }
            
            /*
            |--------------------------------------------------------------------------
            | فلترة إلى تاريخ
            |--------------------------------------------------------------------------
            */
            if ($request->filled('to')) {
                $query->whereDate('branch_renewals.created_at', '<=', $request->to);
            }
            
            if ($request->filled('status_id')) {
                $query->where('branch_renewals.status_id', $request->status_id);
            }
            
            if ($request->filled('category_id')) {
                $query->whereHas('license_branch.license', function ($q) use ($request) {
                    $q->where('category_id', $request->category_id);
                });
            }
        
            
        
            if ($request->filled('directorate')) {
                $query->whereHas('license_branch', function ($q) use ($request) {
                    $q->where('directorate_id', $request->directorate);
                });
            }
        
            $data = $query->get();
        
            /*
            |--------------------------------------------------------------------------
            | 3️⃣ تحديد النوع (new / renew / not_renewed)
            |--------------------------------------------------------------------------
            */
            $prepared = $data->map(function ($item) use ($firstLicenses) {
        
                $licenseId = $item->license_branch?->license_id;
                $info      = $firstLicenses[$licenseId] ?? null;
        
                if (!$info) {
                    $item->type = 'unknown';
                    $item->not_renewed = false;
                    return $item;
                }
        
                $item->type = $item->created_at->eq($info->first_created_at)
                    ? 'new'
                    : 'renew';
        
                $item->not_renewed = ($info->renewals_count == 1);
        
                return $item;
            });
        
            /*
            |--------------------------------------------------------------------------
            | 4️⃣ بناء صفوف التقرير النهائية (FLAT ROWS)
            |--------------------------------------------------------------------------
            */
            $rows = collect();
        
            $prepared
                ->groupBy(fn ($item) => $item->license_branch?->sub_name ?? 'غير محدد')
                ->each(function ($licenseTypeItems, $licenseType) use (&$rows) {
        
                    $licenseTypeItems
                        ->groupBy(fn ($item) => $item->license_branch?->category_name ?? 'غير محدد')
                        ->each(function ($categoryItems, $category) use (&$rows, $licenseType) {
        
                            $categoryItems
                                ->groupBy(fn ($item) => $item->license_branch?->directorate_name ?? 'غير محدد')
                                ->each(function ($dirItems, $directorate) use (&$rows, $licenseType, $category) {
        
                                    $new   = $dirItems->where('type', 'new');
                                    $renew = $dirItems->where('type', 'renew');
        
                                    $rows->push([
                                        'license_type' => $licenseType,
                                        'category'     => $category,
                                        'directorate'  => $directorate,
        
                                        // جديد
                                        'new_under_review' => $new->where('status_id', 1)->count(),
                                        'new_approved'     => $new->where('status_id', 2)->count(),
                                        'new_rejected'     => $new->where('status_id', 3)->count(),
                                        'new_collecting'   => $new->where('status_id', 4)->count(),
                                        'new_collected'    => $new->where('status_id', 5)->count(),
                                        'new_total'        => $new->count(),
        
                                        // تجديد
                                        'renew_under_review' => $renew->where('status_id', 1)->count(),
                                        'renew_approved'     => $renew->where('status_id', 2)->count(),
                                        'renew_rejected'     => $renew->where('status_id', 3)->count(),
                                        'renew_collecting'   => $renew->where('status_id', 4)->count(),
                                        'renew_collected'    => $renew->where('status_id', 5)->count(),
                                        'renew_total'        => $renew->count(),
        
                                        // لم يُجدد
                                        'not_renewed' => $dirItems->where('not_renewed', true)->count(),
        
                                        // الإجمالي
                                        'grand_total' => $dirItems->count(),
                                    ]);
                                });
                        });
                });
        
            /*
            |--------------------------------------------------------------------------
            | 5️⃣ Pagination على صفوف التقرير ✅
            |--------------------------------------------------------------------------
            */
            $page    = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);
        
            $paginated = new LengthAwarePaginator(
                $rows->forPage($page, $perPage)->values(),
                $rows->count(),
                $perPage,
                $page,
                [
                    'path'  => $request->url(),
                    'query' => $request->query(),
                ]
            );
        
            /*
            |--------------------------------------------------------------------------
            | 6️⃣ Response
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'filters' => [
                    'from'        => $request->from,
                    'to'          => $request->to,
                    'directorate' => $request->directorate,
                    'type' => $request->type,
                    'status_id' =>$request->status_id,
                    'category_id' =>$request->category_id,
                ],
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
                'data' => $paginated->items(), // 👈 صفوف التقرير فقط
            ]);
        }
        if ($request->report_type == 'tax_general') 
        {

            /*
            |--------------------------------------------------------------------------
            | 1️⃣ Query أساسي + Relations
            |--------------------------------------------------------------------------
            */
            $query = CustomerTaxBalance::with(['user', 'tax_type']);
        
            // فلترة بالتاريخ
            if ($request->filled('from')) {
                $from = Carbon::parse($request->from)->startOfDay();
                $query->where('created_at', '>=', $from);
            }
            
            // إلى تاريخ
            if ($request->filled('to')) {
                $to = Carbon::parse($request->to)->endOfDay();
                $query->where('created_at', '<=', $to);
            }
                    
            // فلترة بالمديرية
            if ($request->filled('directorate')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where('directorate_id', $request->directorate);
                });
            }
        
            $data = $query->get();
        
            /*
            |--------------------------------------------------------------------------
            | 2️⃣ تجهيز صفوف التقرير (Category + Directorate)
            |--------------------------------------------------------------------------
            */
            $reportRows = collect();
        
            $data
                ->groupBy(fn ($item) => $item->tax_type_name ?? 'غير محدد')
                ->each(function ($categoryItems, $category) use (&$reportRows) {
        
                    $categoryItems
                        ->groupBy(fn ($item) => $item->directorate_name ?? 'غير محدد')
                        ->each(function ($items, $directorate) use ($category, &$reportRows) {
        
                            $row = [
                                'category'     => $category,
                                'directorate'  => $directorate,
                                'not_submitted' => 0,
                                
                                'under_review' => $items->where('tax_status_id', 1)->count(),
                                'approved'     => $items->where('tax_status_id', 2)->count(),
                                'rejected'     => $items->where('tax_status_id', 3)->count(),
                                'collecting'   => $items->where('tax_status_id', 4)->count(),
                                'collected'    => $items->where('tax_status_id', 5)->count(),
                                
                                'total'        => $items->count(),
                                'global_total' => $items->count(),
                            ];
        
                            $reportRows->push($row);
                        });
                });
        
            /*
            |--------------------------------------------------------------------------
            | 3️⃣ Pagination يدوي (نفس license_general)
            |--------------------------------------------------------------------------
            */
            $page    = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);
        
            $paginated = new LengthAwarePaginator(
                $reportRows->forPage($page, $perPage)->values(),
                $reportRows->count(),
                $perPage,
                $page,
                [
                    'path'  => $request->url(),
                    'query' => $request->query(),
                ]
            );
        
            /*
            |--------------------------------------------------------------------------
            | 4️⃣ Response
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'filters' => [
                    'from'        => $request->from,
                    'to'          => $request->to,
                    'directorate' => $request->directorate,
                ],
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
                'data' => $paginated->items(), // ✅ نفس license_general
            ]);
        }
        elseif ($request->report_type == "tax_movements")
        {
            /*
            |--------------------------------------------------------------------------
            | 1️⃣ Query أساسي
            |--------------------------------------------------------------------------
            */
            $items = CustomerTaxBalance::query()
                ->with(['customer', 'user', 'tax_type', 'declaration'])
        
                ->when($request->customer_name, fn ($q, $v) =>
                    $q->whereHas('customer', fn ($qq) =>
                        $qq->where('name', 'like', "%{$v}%")
                    )
                )
                
              
                ->when($request->customer_identity_number, fn ($q, $v) =>
                    $q->whereHas('customer', fn ($qq) =>
                        $qq->where('identity_number', 'like', "%{$v}%")
                    )
                )
        
                ->when($request->directorate_id, fn ($q, $v) =>
                    $q->whereHas('user', fn ($qq) =>
                        $qq->where('directorate_id', $v)
                    )
                )
        
                ->when($request->tax_type_id, fn ($q, $v) =>
                    $q->where('tax_type_id', $v)
                )
                
                ->when($request->id, fn ($q, $v) =>
                    $q->where('id', $v)
                )
                
                ->when($request->tax_file_id, fn ($q, $v) =>
                    $q->where('tax_file_id', $v)
                )
        
                ->when($request->status_id, fn ($q, $v) =>
                    $q->where('tax_status_id', $v)
                )
        
                ->when($request->from, fn ($q, $v) =>
                    $q->whereDate('created_at', '>=', $v)
                )
        
                ->when($request->to, fn ($q, $v) =>
                    $q->whereDate('created_at', '<=', $v)
                )
                
                ->when($request->year, fn ($q, $v) =>
                    $q->where('year', $v)
                )
                
                ->when($request->department, function ($q, $v) {
                    match ($v) {
                        'المراجعة' => $q->where('tax_status_id', 1),
                
                        'المالية'  => $q->where('tax_status_id', 4),
                
                        'الادخال'  => $q->whereIn('tax_status_id', [2, 3, 5]),
                
                        default    => null,
                    };
                })
                        
                ->latest()
                ->get();
        
            /*
            |--------------------------------------------------------------------------
            | 2️⃣ Pagination يدوي
            |--------------------------------------------------------------------------
            */
            $page    = $request->get('page', 1);
            $perPage = $request->get('per_page', 8);
        
            $paginated = new LengthAwarePaginator(
                $items->forPage($page, $perPage)->values(),
                $items->count(),
                $perPage,
                $page,
                [
                    'path'  => $request->url(),
                    'query' => $request->query(),
                ]
            );
        
            /*
            |--------------------------------------------------------------------------
            | 3️⃣ Transform البيانات
            |--------------------------------------------------------------------------
            */
            $paginated->getCollection()->transform(function (CustomerTaxBalance $item) {
        
                return [
                    'tax_balance_id' => $item->id,
        
                    'customer_id'    => $item->customer_id,
                    'customer_name'  => $item->customer_name,
                    'customer_identity_number' => $item->customer_identity_number,
                    'customer_tax_file_id'     => $item->customer_tax_file_id,
        
                    'tax_type_id'   => $item->tax_type_id,
                    'tax_type_name' => $item->tax_type_name,
        
                    'declaration_name' => $item->declaration_name,
                    'declaration_type' => $item->declaration_type,
        
                    'directorate_name' => $item->directorate_name,
                    'department_name'  => $item->department_name,
        
                    'status_id'    => $item->tax_status_id,
                    'status_name'  => $item->dashboard_status_name,
                    'status_color' => $item->dashboard_status_color,
        
                    'total_amount' => $item->total_amount,
        
                    'tax_commitment' => $item->tax_commitment_text,
                    'final_balance_status' => $item->final_balance_status_text,
                    
                    'year' => $item->year ?? optional($item->created_at)->format('Y'),
                    'created_at' => $item->created_at?->toDateTimeString(),
                ];
            });
        
            /*
            |--------------------------------------------------------------------------
            | 4️⃣ Response
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'filters' => [
                    'customer_name' => $request->customer_name,
                    'customer_identity_number' => $request->customer_identity_number,
                    'directorate_id' => $request->directorate_id,
                    'tax_type_id' => $request->tax_type_id,
                    'status_id' => $request->status_id,
                    'from' => $request->from,
                    'to' => $request->to,
                    'id' => $request->id,
                    'tax_file_id' => $request->tax_file_id,
                    'department' =>$request->department,
                    'year' => $request->year,
                ],
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
                'data' => $paginated->items(),
            ]);
        }elseif ($request->report_type == "tax_users")
        {
            /*
            |-------------------------------------------------------------------------- 
            | Query التقرير
            |-------------------------------------------------------------------------- 
            */
           $query = DB::table('customer_tax_balance')
            ->join('users', 'users.id', '=', 'customer_tax_balance.user_id')
            ->leftJoin('directorates', 'directorates.id', '=', 'users.directorate_id')
            ->leftJoin('declarations', 'declarations.id', '=', 'customer_tax_balance.declaration_id')
            ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
            ->select(
                'users.id as user_id',
                'users.name as user_name',
                'directorates.name as directorate_name',
        
                // ✅ قسم اليوزر الحقيقي
                'departments.id as user_department_id',
                'departments.name as user_department_name',
        
                'declarations.id as declaration_id',
                DB::raw("COALESCE(declarations.name, 'تحت الحساب') as declaration_name"),
                DB::raw('COUNT(customer_tax_balance.id) as declarations_count')
            )
            ->groupBy(
                'users.id',
                'users.name',
                'directorates.name',
                'departments.id',
                'departments.name',
                'declarations.id',
                'declarations.name'
            );


        
            /*
            |-------------------------------------------------------------------------- 
            | Filters
            |-------------------------------------------------------------------------- 
            */
            if ($request->filled('user_name')) {
                $query->where('users.name', 'like', '%' . $request->user_name . '%');
            }
            
            if ($request->filled('directorate_id')) {
                $query->where('users.directorate_id', $request->directorate_id);
            }
            
            if ($request->filled('declaration_id')) {
                $query->where('customer_tax_balance.declaration_id', $request->declaration_id);
            }
            
            if ($request->filled('department_id')) {
                // ✅ فلترة بقسم اليوزر الحقيقي
                $query->where('users.department_id', $request->department_id);
            }
            
            if ($request->filled('from')) {
                $query->whereDate('customer_tax_balance.created_at', '>=', $request->from);
            }
            
            if ($request->filled('to')) {
                $query->whereDate('customer_tax_balance.created_at', '<=', $request->to);
            }


            /*
            |-------------------------------------------------------------------------- 
            | Pagination
            |-------------------------------------------------------------------------- 
            */
            $items = $query
                ->orderBy('users.name')
                ->get();
        
            /*
            |-------------------------------------------------------------------------- 
            | Format Response: تجميع declarations لكل user
            |-------------------------------------------------------------------------- 
            */
            $data = $items->groupBy('user_id')->map(function ($rows) {
                $first = $rows->first();
            
                return [
                    'user_id'   => $first->user_id,
                    'user_name' => $first->user_name,
            
                    // ✅ قسم اليوزر الحقيقي
                    'department' => $first->user_department_name,
            
                    'directorate_name' => $first->directorate_name,
            
                    'declarations' => $rows->map(function ($row) {
                        return [
                            'declaration_id'   => $row->declaration_id,
                            'declaration_name' => $row->declaration_name,
                            'count'            => $row->declarations_count,
                        ];
                    })->values(),
                ];
            })->values();

        
            /*
            |-------------------------------------------------------------------------- 
            | Pagination يدوي
            |-------------------------------------------------------------------------- 
            */
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);
            $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
                $data->forPage($page, $perPage)->values(),
                $data->count(),
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );
        
            return response()->json([
                'filters' => [
                    'user_name'        => $request->user_name,
                    'directorate_id' => $request->directorate_id,
                    'declaration_id' => $request->declaration_id,
                    'from'           => $request->from,
                    'to'             => $request->to,
                    'department_id' => $request->department_id,
                ],
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
                'data' => $paginated->items(),
            ]);
        }
        elseif ($request->report_type == "tax_directorates") 
        {

            // جلب البيانات
            $balances = CustomerTaxBalance::with('declaration', 'user.directorate')
                ->when($request->filled('directorate_id'), fn($q) => $q->whereHas('user.directorate', fn($q) => $q->where('id', $request->directorate_id)))
                ->when($request->filled('declaration_id'), fn($q) => $q->where('declaration_id', $request->declaration_id))
                ->when($request->filled('from'), fn($q) => $q->whereDate('created_at', '>=', $request->from))
                ->when($request->filled('to'), fn($q) => $q->whereDate('created_at', '<=', $request->to))
                ->get();
        
            // ترتيب وتجميع البيانات
            $data = $balances->groupBy(fn($b) => $b->directorate_name ?? 'غير محدد')
                ->map(function($directorateBalances, $directorateName) {
        
                    $declarations = $directorateBalances->groupBy(fn($b) => $b->declaration_name ?? 'رصيد تحت الحساب')
                        ->map(function($declBalances) {
                            return [
                                'قيد التنفيذ' => $declBalances->where('tax_status_id', 1)->count(),
                                'تم السداد'   => $declBalances->where('tax_status_id', 5)->count(),
                                'لم يقدم' => $declBalances->groupBy('customer_id')->filter(function($customerBalances) {
                                    $currentYear = now()->year;
                                
                                    // هل دفع أي سنة قبل السنة الحالية؟
                                    $paidPreviousYears = $customerBalances
                                        ->where('tax_status_id', 5)
                                        ->where('year', '<', $currentYear)
                                        ->count() > 0;
                                
                                    // هل لم يدفع السنة الحالية؟
                                    $notPaidCurrentYear = $customerBalances
                                        ->where('year', $currentYear)
                                        ->where('tax_status_id', '!=', 5)
                                        ->count() > 0;
                                
                                    return $paidPreviousYears && $notPaidCurrentYear;
                                })->count(),

                            ];
                        });
        
                    // حساب الإجمالي لكل صف
                    $total = [
                        'قيد التنفيذ' => $declarations->sum(fn($v) => $v['قيد التنفيذ']),
                        'تم السداد'   => $declarations->sum(fn($v) => $v['تم السداد']),
                        'لم يقدم'     => $declarations->sum(fn($v) => $v['لم يقدم']),
                    ];
        
                    return [
                        'directorate_name' => $directorateName,
                        'declarations' => $declarations,
                        'total' => $total,
                    ];
                })->values();
        
            return response()->json([
                'filters' => [
                    'directorate_id' => $request->directorate_id,
                    'declaration_id' => $request->declaration_id,
                    'from'           => $request->from,
                    'to'             => $request->to,
                ],
                'data' => $data,
            ]);
        }
        elseif ($request->report_type == "tax_taxpayers") 
        {
            $query = CustomerTaxBalance::with('customer', 'user.directorate')
                ->when(
                    $request->filled('directorate_id'),
                    fn($q) =>
                        $q->whereHas(
                            'user.directorate',
                            fn($q) => $q->where('id', $request->directorate_id)
                        )
                )
                ->select(
                    'user_id',
                    DB::raw('user_id as directorate_id'),
                    DB::raw('COUNT(DISTINCT customer_id) as customers_count')
                )
                ->groupBy('user_id');
        
            /*
            |--------------------------------------------------------------------------
            | فلترة عدد المكلفين
            |--------------------------------------------------------------------------
            */
        
            // ✅ رقم واحد (exact)
            if ($request->filled('customers_count')) {
                $query->having('customers_count', '=', $request->customers_count);
            }
        
            // ✅ Range (من → إلى)
            if ($request->filled('from_customers') && $request->filled('to_customers')) {
                $query->havingBetween('customers_count', [
                    $request->from_customers,
                    $request->to_customers
                ]);
            }
        
            // ✅ من فقط
            if ($request->filled('from_customers') && !$request->filled('to_customers')) {
                $query->having('customers_count', '>=', $request->from_customers);
            }
        
            // ✅ إلى فقط
            if ($request->filled('to_customers') && !$request->filled('from_customers')) {
                $query->having('customers_count', '<=', $request->to_customers);
            }
        
            // Pagination
            $items = $query
                ->orderBy('customers_count', 'desc')
                ->paginate($request->get('per_page', 10));
        
            $data = $items->map(function ($item) {
                return [
                    'directorate_id'   => $item->directorate_id,
                    'directorate_name' => optional($item->user?->directorate)->name ?? 'غير محدد',
                    'customers_count'  => $item->customers_count,
                ];
            });
        
            return response()->json([
                'filters' => [
                    'directorate_id'   => $request->directorate_id,
                    'customers_count'  => $request->customers_count,
                    'from_customers'   => $request->from_customers,
                    'to_customers'     => $request->to_customers,
                ],
                'pagination' => [
                    'current_page' => $items->currentPage(),
                    'per_page'     => $items->perPage(),
                    'total'        => $items->total(),
                    'last_page'    => $items->lastPage(),
                ],
                'data' => $data,
            ]);
        }
        elseif ($request->report_type == "tax_types") 
        {

            /*
            |--------------------------------------------------------------------------
            | 1️⃣ Query أساسي + Relations
            |--------------------------------------------------------------------------
            */
            $query = CustomerTaxBalance::with(['user', 'tax_type']);
        
            // فلترة بالتاريخ
            if ($request->filled('from')) {
                $from = Carbon::parse($request->from)->startOfDay();
                $query->where('created_at', '>=', $from);
            }
            
            // To فقط
            if ($request->filled('to')) {
                $to = Carbon::parse($request->to)->endOfDay();
                $query->where('created_at', '<=', $to);
            }
        
            // فلترة بالمديرية
            if ($request->filled('directorate')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where('directorate_id', $request->directorate);
                });
            }
            
            if ($request->filled('status_id')) {
               
                    $query->where('tax_status_id', $request->status_id);
                
            }
            
             if ($request->filled('tax_type_id')) 
             {
               
                    $query->where('tax_type_id', $request->tax_type_id);
                
            }
        
            $data = $query->get();
        
            /*
            |--------------------------------------------------------------------------
            | 2️⃣ تجهيز صفوف التقرير (Declaration + Directorate)
            |--------------------------------------------------------------------------
            */
            $reportRows = collect();
        
            $data
                ->groupBy(fn ($item) => $item->tax_type_name ?? 'غير محدد')
                ->each(function ($declarationItems, $tax_type) use (&$reportRows) {
        
                    $declarationItems
                        ->groupBy(fn ($item) => $item->user?->directorate_name ?? 'غير محدد')
                        ->each(function ($items, $directorate) use ($tax_type, &$reportRows) {
        
                            $row = [
                                'category'       => $tax_type, // 👈 نفس key بتاع tax_general
                                'directorate'    => $directorate,
        
                                'not_submitted'  => 0, // جاهز لو هتزوده بعدين
        
                                'under_review'   => $items->where('tax_status_id', 1)->count(),
                                'approved'       => $items->where('tax_status_id', 2)->count(),
                                'rejected'       => $items->where('tax_status_id', 3)->count(),
                                'collecting'     => $items->where('tax_status_id', 4)->count(),
                                'collected'      => $items->where('tax_status_id', 5)->count(),
        
                                'total'          => $items->count(),
                                'global_total'   => $items->count(),
                            ];
        
                            $reportRows->push($row);
                        });
                });
        
            /*
            |--------------------------------------------------------------------------
            | 3️⃣ Pagination يدوي (نفس tax_general)
            |--------------------------------------------------------------------------
            */
            $page    = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);
        
            $paginated = new LengthAwarePaginator(
                $reportRows->forPage($page, $perPage)->values(),
                $reportRows->count(),
                $perPage,
                $page,
                [
                    'path'  => $request->url(),
                    'query' => $request->query(),
                ]
            );
        
            /*
            |--------------------------------------------------------------------------
            | 4️⃣ Response (نفس الشكل)
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'filters' => [
                    'from'        => $request->from,
                    'to'          => $request->to,
                    'directorate' => $request->directorate,
                    'status_id' => $request->status_id,
                    'tax_type_id' => $request->tax_type_id,
                ],
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
                'data' => $paginated->items(), // ✅ Flat rows
            ]);
        }
        elseif ($request->report_type == "tax_debts") 
        {

            /*
            |--------------------------------------------------------------------------
            | 1️⃣ Query أساسي
            |--------------------------------------------------------------------------
            */
            $query = CustomerTaxBalance::with(['user', 'tax_type']);
        
            // فلترة بالتاريخ
            if ($request->filled('from')) {
                $from = Carbon::parse($request->from)->startOfDay();
                $query->where('created_at', '>=', $from);
            }
            
            // To فقط
            if ($request->filled('to')) {
                $to = Carbon::parse($request->to)->endOfDay();
                $query->where('created_at', '<=', $to);
            }
                    
            // فلترة بالمديرية
            if ($request->filled('directorate_id')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where('directorate_id', $request->directorate_id);
                });
            }
            
            if ($request->filled('tax_type_id')) {
               
                    $query->where('tax_type_id', $request->tax_type_id);
               
            }
        
            $data = $query->get();
        
            /*
            |--------------------------------------------------------------------------
            | 2️⃣ تجهيز الصفوف (مطابقة للصورة)
            |--------------------------------------------------------------------------
            */
            $reportRows = collect();
        
            $data
            ->groupBy(fn ($item) => $item->user?->directorate_name ?? 'غير محدد')
            ->each(function ($directorateItems, $directorate) use (&$reportRows) {
        
                $directorateItems
                    ->groupBy(fn ($item) => $item->tax_type_name ?? 'غير محدد')
                    ->each(function ($items, $category) use ($directorate, &$reportRows) {
        
                        // قيد التحصيل
                        $collecting = $items->where('tax_status_id', 4);
        
                        // تم التحصيل
                        $collected  = $items->where('tax_status_id', 5);
        
                        $row = [
                            'directorate' => $directorate,
                            'category'    => $category,
        
                            // قيد التحصيل
                            'collecting_count'  => $collecting->count(),
                            'collecting_amount' => $collecting->sum('amount'),
        
                            // تم التحصيل
                            'collected_count'   => $collected->count(),
                            'collected_amount'  => $collected->sum('amount'),
        
                            // الإجمالي
                            'total_count'  => $collecting->count() + $collected->count(),
                            'total_amount' => $collecting->sum('amount') + $collected->sum('amount'),
                        ];
        
                        $reportRows->push($row);
                    });
            });

        
            /*
            |--------------------------------------------------------------------------
            | 3️⃣ Pagination
            |--------------------------------------------------------------------------
            */
            $page    = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);
        
            $paginated = new LengthAwarePaginator(
                $reportRows->forPage($page, $perPage)->values(),
                $reportRows->count(),
                $perPage,
                $page,
                [
                    'path'  => $request->url(),
                    'query' => $request->query(),
                ]
            );
        
            /*
            |--------------------------------------------------------------------------
            | 4️⃣ Response مطابق للصورة
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'filters' => [
                    'from'           => $request->from,
                    'to'             => $request->to,
                    'directorate_id' => $request->directorate_id,
                    'tax_type_id' => $request->tax_type_id,
                ],
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
                'data' => $paginated->items(),
            ]);
        }

    elseif ($request->report_type == "tax_balances") 
    {
       
        /*
        |------------------------------------------------------------------
        | 1️⃣ Query أساسي مع كل الفلاتر على الأعمدة الحقيقية
        |------------------------------------------------------------------
        */
        $query = CustomerTaxBalance::with(['user', 'customer', 'status', 'tax_type', 'declaration'])
            ->whereIn('tax_status_id', [4, 5]);
    
        // فلترة بالتاريخ
        if ($request->filled('from')) {
            $query->where('created_at', '>=', Carbon::parse($request->from)->startOfDay());
        }
    
        if ($request->filled('to')) {
            $query->where('created_at', '<=', Carbon::parse($request->to)->endOfDay());
        }
    
        // فلترة بالمديرية
        if ($request->filled('directorate_id')) {
            $query->whereHas('user', fn($q) => $q->where('directorate_id', $request->directorate_id));
        }
    
        // فلترة باسم المكلف
        if ($request->filled('taxpayer_name')) {
            $query->whereHas('customer', fn($q) => $q->where('name', 'like', "%{$request->taxpayer_name}%"));
        }
    
        // فلترة بالتصنيف
        if ($request->filled('classification')) {
            $query->whereHas('tax_type', fn($q) => $q->where('name', 'like', "%{$request->classification}%"));
        }
    
        // فلترة بالاسم التجاري
        if ($request->filled('commercial_name')) {
    $query->whereHas('customer.licenses', fn($q) =>
        $q->where('business_name', 'like', "%{$request->commercial_name}%")
    );
}
    
        // فلترة بالحالة
        if ($request->filled('status_id')) {
            $query->where('tax_status_id', $request->status_id);
        }
        
        if ($request->filled('tax_file_id')) {
            $query->where('tax_file_id', $request->tax_file_id);
        }
    
        // فلترة بالسنة
        if ($request->filled('activity_year')) {
            $query->where('year', $request->activity_year);
        }
    
        // فلترة بالالتزام
        if ($request->filled('commitment')) {
            $query->where('tax_commitment_text', 'like', "%{$request->commitment}%");
        }
    
        // فلترة بنتيجة التسوية
        if ($request->filled('settlement_result')) {
            // لن نستخدم هنا عمود في DB لأنه محسوب بـ Accessor
        }
    
        /*
        |------------------------------------------------------------------
        | 2️⃣ جلب البيانات وترتيبها
        |------------------------------------------------------------------
        */
        $items = $query->orderBy('created_at', 'desc')->get();
    
        /*
        |------------------------------------------------------------------
        | 3️⃣ فلترة بالـ balance_status بعد جلب البيانات
        |------------------------------------------------------------------
        */
        if ($request->filled('balance_status')) {
            if ($request->balance_status == 'رصيد دائن') {
                $items = $items->filter(fn($i) => $i->settlement_result == 0);
            } elseif ($request->balance_status == 'تم التسوية') {
                $items = $items->filter(fn($i) => $i->settlement_result > 0);
            }
        }
    
        /*
        |------------------------------------------------------------------
        | 4️⃣ تجهيز الصفوف للـ API
        |------------------------------------------------------------------
        */
        $rows = $items->map(fn($item) => [
            
            'payment_date'     => $item->created_at?->format('Y-m-d') ?? '-',
            'file_number'      => $item->tax_file_id ?? '-',
            'classification'   => $item->tax_type_name ?? '-',
            'taxpayer_name'    => $item->customer_name ?? '-',
            'directorate'      => $item->directorate_name ?? '-',
            'commercial_name'  => $item->customer?->commercial_name ?? '-',
            'amount'           => (float) $item->total_amount,
            'activity_year'    => $item->year ?? '-',
            'status'           => $item->tax_status_id == 4 ? 'قيد التحصيل' : ($item->tax_status_id == 5 ? 'تم التحصيل' : '-'),
            'commitment'       => $item->tax_commitment_text ?? '-',
            'settlement_result'=> $item->settlement_result ?? '-',
            'balance_status'   => $item->final_balance_status_text == 'سليم' ? 'رصيد دائن' : 'تم التسوية',
        ]);
    
        /*
        |------------------------------------------------------------------
        | 5️⃣ Pagination يدوي
        |------------------------------------------------------------------
        */
        $page    = $request->get('page', 1);
        $perPage = $request->get('per_page', 10);
    
        $paginated = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]
        );
    
        /*
        |------------------------------------------------------------------
        | 6️⃣ Response نهائي
        |------------------------------------------------------------------
        */
        return response()->json([
            'filters' => [
                    'from'           => $request->from,
                    'to'             => $request->to,
                    'directorate_id' => $request->directorate_id,
                    'taxpayer_name' => $request->taxpayer_name,
                    'classification' => $request->classification,
                    'commercial_name' => $request->commercial_name,
                    'status_id' => $request->status_id,
                    'activity_year' => $request->activity_year,
                    'commitment' => $request->commitment,
                    'balance_status' => $request->balance_status,
                    'tax_file_id' => $request->tax_file_id,
                ],
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'last_page'    => $paginated->lastPage(),
            ],
            'data' => $paginated->items(),
        ]);
    }






        //zakah//

        
        
        if ($request->report_type == 'duty_general') 
        {

            /*
            |--------------------------------------------------------------------------
            | 1️⃣ Query أساسي + Relations
            |--------------------------------------------------------------------------
            */
            $query = CustomerZakahBalance::with(['user', 'zakah_type']);
        
            // فلترة بالتاريخ
             if ($request->filled('from')) {
                $query->where('created_at', '>=', Carbon::parse($request->from)->startOfDay());
            }
        
            if ($request->filled('to')) {
                $query->where('created_at', '<=', Carbon::parse($request->to)->endOfDay());
            }
        
            // فلترة بالمديرية
            if ($request->filled('directorate')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where('directorate_id', $request->directorate);
                });
            }
        
            $data = $query->get();
        
            /*
            |--------------------------------------------------------------------------
            | 2️⃣ تجهيز صفوف التقرير (Category + Directorate)
            |--------------------------------------------------------------------------
            */
            $reportRows = collect();
        
            $data
                ->groupBy(fn ($item) => $item->zakah_type_name ?? 'غير محدد')
                ->each(function ($categoryItems, $category) use (&$reportRows) {
        
                    $categoryItems
                        ->groupBy(fn ($item) => $item->directorate_name ?? 'غير محدد')
                        ->each(function ($items, $directorate) use ($category, &$reportRows) {
        
                            $row = [
                                'category'     => $category,
                                'directorate'  => $directorate,
                                'not_submitted' => 0,
                                
                                'first_review' => $items->where('zakah_status_id', 1)->count(),
                                'second_review' => $items->where('zakah_status_id', 2)->count(),
                                'approved'     => $items->where('zakah_status_id', 4)->count(),
                                'rejected'     => $items->where('zakah_status_id', 3)->count(),
                                'collecting'   => $items->where('zakah_status_id', 6)->count(),
                                'collected'    => $items->where('zakah_status_id', 7)->count(),
                                
                                'total'        => $items->count(),
                                'global_total' => $items->count(),
                            ];
        
                            $reportRows->push($row);
                        });
                });
        
            /*
            |--------------------------------------------------------------------------
            | 3️⃣ Pagination يدوي (نفس license_general)
            |--------------------------------------------------------------------------
            */
            $page    = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);
        
            $paginated = new LengthAwarePaginator(
                $reportRows->forPage($page, $perPage)->values(),
                $reportRows->count(),
                $perPage,
                $page,
                [
                    'path'  => $request->url(),
                    'query' => $request->query(),
                ]
            );
        
            /*
            |--------------------------------------------------------------------------
            | 4️⃣ Response
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'filters' => [
                    'from'        => $request->from,
                    'to'          => $request->to,
                    'directorate' => $request->directorate,
                ],
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
                'data' => $paginated->items(), // ✅ نفس license_general
            ]);
        }
        elseif ($request->report_type == "duty_movements")
        {
            /*
            |--------------------------------------------------------------------------
            | 1️⃣ Query أساسي
            |--------------------------------------------------------------------------
            */
            $items = CustomerZakahBalance::query()
                ->with(['customer', 'user', 'zakah_type', 'declaration'])
        
                ->when($request->customer_name, fn ($q, $v) =>
                    $q->whereHas('customer', fn ($qq) =>
                        $qq->where('name', 'like', "%{$v}%")
                    )
                )
        
                ->when($request->customer_identity_number, fn ($q, $v) =>
                    $q->whereHas('customer', fn ($qq) =>
                        $qq->where('identity_number', 'like', "%{$v}%")
                    )
                )
        
                ->when($request->directorate_id, fn ($q, $v) =>
                    $q->whereHas('user', fn ($qq) =>
                        $qq->where('directorate_id', $v)
                    )
                )
        
                ->when($request->zakah_type_id, fn ($q, $v) =>
                    $q->where('zakah_type_id', $v)
                )
        
                ->when($request->status_id, fn ($q, $v) =>
                    $q->where('zakah_status_id', $v)
                )
                
                ->when($request->zakah_number_id, fn ($q, $v) =>
                    $q->where('zakah_number_id', $v)
                )
        
        
                ->when($request->from, fn ($q, $v) =>
                    $q->whereDate('created_at', '>=', $v)
                )
        
                ->when($request->to, fn ($q, $v) =>
                    $q->whereDate('created_at', '<=', $v)
                )
                
                ->when($request->department, function ($q, $v) {
                    match ($v) {
                        'المراجعة' => $q->where('zakah_status_id', 1),
                
                        'المالية'  => $q->where('zakah_status_id', 4),
                
                        'الادخال'  => $q->whereIn('zakah_status_id', [2, 3, 5]),
                        
                        
                
                        default    => null,
                    };
                })
                
                ->when($request->year, fn ($q, $v) =>
                    $q->where('year', $v)
                )
                
                ->when($request->id, fn ($q, $v) =>
                    $q->where('id', $v)
                )
                
                
        
                ->latest()
                ->get();
        
            /*
            |--------------------------------------------------------------------------
            | 2️⃣ Pagination يدوي
            |--------------------------------------------------------------------------
            */
            $page    = $request->get('page', 1);
            $perPage = $request->get('per_page', 8);
        
            $paginated = new LengthAwarePaginator(
                $items->forPage($page, $perPage)->values(),
                $items->count(),
                $perPage,
                $page,
                [
                    'path'  => $request->url(),
                    'query' => $request->query(),
                ]
            );
        
            /*
            |--------------------------------------------------------------------------
            | 3️⃣ Transform البيانات
            |--------------------------------------------------------------------------
            */
            $paginated->getCollection()->transform(function (CustomerZakahBalance $item) {
        
                return [
                    'id' => $item->id,
        
                    'customer_id'    => $item->customer_id,
                    'customer_name'  => $item->customer_name,
                    'customer_identity_number' => $item->customer_identity_number,
                    'customer_zakah_number_id'     => $item->customer_zakah_number_id,
        
                    'zakah_type_id'   => $item->zakah_type_id,
                    'zakah_type_name' => $item->zakah_type_name,
        
                    'declaration_name' => $item->declaration_name,
                    'declaration_type' => $item->declaration_type,
        
                    'directorate_name' => $item->directorate_name,
                    'department_name'  => $item->department_name,
        
                    'status_id'    => $item->zakah_status_id,
                    'status_name'  => $item->dashboard_status_name,
                    'status_color' => $item->dashboard_status_color,
        
                    'total_amount' => $item->amount,
        
                    'tax_commitment' => $item->tax_commitment_text,
                    'final_balance_status' => $item->final_balance_status_text,
                    
                    'year' => $item->year ?? optional($item->created_at)->format('Y'),
                    'created_at' => $item->created_at?->toDateTimeString(),
                ];
            });
        
            /*
            |--------------------------------------------------------------------------
            | 4️⃣ Response
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'filters' => [
                    'customer_name' => $request->customer_name,
                    'customer_identity_number' => $request->customer_identity_number,
                    'directorate_id' => $request->directorate_id,
                    'zakah_type_id' => $request->zakah_type_id,
                    'status_id' => $request->status_id,
                    'from' => $request->from,
                    'to' => $request->to,
                    'zakah_number_id' => $request->zakah_number_id,
                    'department' => $request->department,
                    'year' => $request->year,
                    'id' => $request->id,
                ],
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
                'data' => $paginated->items(),
            ]);
        }elseif ($request->report_type == "duty_users")
        {
            /*
            |-------------------------------------------------------------------------- 
            | Query التقرير
            |-------------------------------------------------------------------------- 
            */
            $query = DB::table('customer_zakah_balance')
                ->join('users', 'users.id', '=', 'customer_zakah_balance.user_id')
                ->leftJoin('directorates', 'directorates.id', '=', 'users.directorate_id')
                ->leftJoin('zakah_types', 'zakah_types.id', '=', 'customer_zakah_balance.zakah_type_id')
                ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
                ->select(
                    'users.id as user_id',
                    'users.name as user_name',
                    'departments.id as user_department_id',
                    'departments.name as user_department_name',
                    'directorates.name as directorate_name',
                    'zakah_types.id as zakah_type_id',
                    DB::raw("COALESCE(zakah_types.name, '-') as zakah_type_name"),

                    DB::raw('COUNT(customer_zakah_balance.id) as types_count')
                )
                ->groupBy(
                    'users.id',
                    'users.name',
                    'departments.id',
                    'departments.name',
                    'directorates.name',
                    'zakah_types.id',
                    'zakah_types.name'
                );
        
            /*
            |-------------------------------------------------------------------------- 
            | Filters
            |-------------------------------------------------------------------------- 
            */
            if ($request->filled('user_id')) {
                $query->where('users.id', $request->user_id);
            }
        
            if ($request->filled('directorate_id')) {
                $query->where('users.directorate_id', $request->directorate_id);
            }
        
            if ($request->filled('zakah_type_id')) {
                $query->where('customer_zakah_balance.zakah_type_id', $request->zakah_type_id);
            }
            
            if ($request->filled('department')) {
                $query->where('users.department_id', $request->department);
            }
        
            if ($request->filled('from')) {
                $query->whereDate('customer_zakah_balance.created_at', '>=', $request->from);
            }
        
            if ($request->filled('to')) {
                $query->whereDate('customer_zakah_balance.created_at', '<=', $request->to);
            }
        
            /*
            |-------------------------------------------------------------------------- 
            | Pagination
            |-------------------------------------------------------------------------- 
            */
            $items = $query
                ->orderBy('users.name')
                ->get();
        
            /*
            |-------------------------------------------------------------------------- 
            | Format Response: تجميع declarations لكل user
            |-------------------------------------------------------------------------- 
            */
            $data = $items->groupBy('user_id')->map(function ($rows) {
                $first = $rows->first();
                return [
                    'user_id'          => $first->user_id,
                    'user_name'        => $first->user_name,
                    'directorate_name' => $first->directorate_name,
                    'department' => $first->user_department_name,
                    'types'     => $rows->map(function ($row) {
                        return [
                            'zakah_type_id'   => $row->zakah_type_id,
                            'zakah_type_name' => $row->zakah_type_name,
                            'count'            => $row->types_count,
                        ];
                    })->values(),
                ];
            })->values();
        
            /*
            |-------------------------------------------------------------------------- 
            | Pagination يدوي
            |-------------------------------------------------------------------------- 
            */
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);
            $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
                $data->forPage($page, $perPage)->values(),
                $data->count(),
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );
        
            return response()->json([
                'filters' => [
                    'user_id'        => $request->user_id,
                    'directorate_id' => $request->directorate_id,
                    'zakah_type_id' => $request->zakah_type_id,
                    'from'           => $request->from,
                    'to'             => $request->to,
                    'department'             => $request->department,
                ],
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
                'data' => $paginated->items(),
            ]);
        }
        elseif ($request->report_type == "duty_directorates") 
        {

            // جلب البيانات
            $balances = CustomerZakahBalance::with('zakah_type', 'user.directorate')
                ->when($request->filled('directorate_id'), fn($q) => $q->whereHas('user.directorate', fn($q) => $q->where('id', $request->directorate_id)))
                ->when($request->filled('zakah_type_id'), fn($q) => $q->where('zakah_type_id', $request->zakah_type_id))
                ->when($request->filled('from'), fn($q) => $q->whereDate('created_at', '>=', $request->from))
                ->when($request->filled('to'), fn($q) => $q->whereDate('created_at', '<=', $request->to))
                ->get();
        
            // ترتيب وتجميع البيانات
            $data = $balances->groupBy(fn($b) => $b->directorate_name ?? 'غير محدد')
                ->map(function($typeBalances, $directorateName) {
        
                    $types = $typeBalances->groupBy(fn($b) => $b->zakah_type_name)
                        ->map(function($declBalances) {
                            return [
                                'قيد التنفيذ' => $declBalances->where('zakah_status_id', 1)->count(),
                                'تم السداد'   => $declBalances->where('zakah_status_id', 5)->count(),
                                'لم يقدم' => $declBalances->groupBy('customer_id')->filter(function($customerBalances) {
                                    $currentYear = now()->year;
                                
                                    // هل دفع أي سنة قبل السنة الحالية؟
                                    $paidPreviousYears = $customerBalances
                                        ->where('zakah_status_id', 5)
                                        ->where('year', '<', $currentYear)
                                        ->count() > 0;
                                
                                    // هل لم يدفع السنة الحالية؟
                                    $notPaidCurrentYear = $customerBalances
                                        ->where('year', $currentYear)
                                        ->where('zakah_status_id', '!=', 5)
                                        ->count() > 0;
                                
                                    return $paidPreviousYears && $notPaidCurrentYear;
                                })->count(),

                            ];
                        });
        
                    // حساب الإجمالي لكل صف
                    $total = [
                        'قيد التنفيذ' => $types->sum(fn($v) => $v['قيد التنفيذ']),
                        'تم السداد'   => $types->sum(fn($v) => $v['تم السداد']),
                        'لم يقدم'     => $types->sum(fn($v) => $v['لم يقدم']),
                    ];
        
                    return [
                        'directorate_name' => $directorateName,
                        'types' => $types,
                        
                    ];
                })->values();
        
            return response()->json([
                'filters' => [
                    'directorate_id' => $request->directorate_id,
                    'zakah_type_id' => $request->zakah_type_id,
                    'from'           => $request->from,
                    'to'             => $request->to,
                ],
                'data' => $data,
            ]);
        }
        elseif ($request->report_type == "duty_taxpayers") 
        {
            $query = CustomerZakahBalance::with('customer', 'user.directorate')
                ->when(
                    $request->filled('directorate_id'),
                    fn($q) =>
                        $q->whereHas(
                            'user.directorate',
                            fn($q) => $q->where('id', $request->directorate_id)
                        )
                )
                ->select(
                    'user_id',
                    DB::raw('user_id as directorate_id'),
                    DB::raw('COUNT(DISTINCT customer_id) as customers_count')
                )
                ->groupBy('user_id');
        
            /*
            |--------------------------------------------------------------------------
            | فلترة عدد المكلفين
            |--------------------------------------------------------------------------
            */
        
            // ✅ رقم واحد (exact)
            if ($request->filled('customers_count')) {
                $query->having('customers_count', '=', $request->customers_count);
            }
        
            // ✅ Range (من → إلى)
            if ($request->filled('from_customers') && $request->filled('to_customers')) {
                $query->havingBetween('customers_count', [
                    $request->from_customers,
                    $request->to_customers
                ]);
            }
        
            // ✅ من فقط
            if ($request->filled('from_customers') && !$request->filled('to_customers')) {
                $query->having('customers_count', '>=', $request->from_customers);
            }
        
            // ✅ إلى فقط
            if ($request->filled('to_customers') && !$request->filled('from_customers')) {
                $query->having('customers_count', '<=', $request->to_customers);
            }
        
            // Pagination
            $items = $query
                ->orderBy('customers_count', 'desc')
                ->paginate($request->get('per_page', 10));
        
            $data = $items->map(function ($item) {
                return [
                    'directorate_id'   => $item->directorate_id,
                    'directorate_name' => optional($item->user?->directorate)->name ?? 'غير محدد',
                    'customers_count'  => $item->customers_count,
                ];
            });
        
            return response()->json([
                'filters' => [
                    'directorate_id'   => $request->directorate_id,
                    'customers_count'  => $request->customers_count,
                    'from_customers'   => $request->from_customers,
                    'to_customers'     => $request->to_customers,
                ],
                'pagination' => [
                    'current_page' => $items->currentPage(),
                    'per_page'     => $items->perPage(),
                    'total'        => $items->total(),
                    'last_page'    => $items->lastPage(),
                ],
                'data' => $data,
            ]);
        }
        elseif ($request->report_type == "duty_types") 
        {

            /*
            |--------------------------------------------------------------------------
            | 1️⃣ Query أساسي + Relations
            |--------------------------------------------------------------------------
            */
            $query = CustomerZakahBalance::with(['user', 'declaration']);
        
            // فلترة بالتاريخ
            if ($request->filled('from')) {
                $query->whereDate('created_at', '>=', $request->from);
            }
        
            if ($request->filled('to')) {
                $query->whereDate('created_at', '<=', $request->to);
            }
            // فلترة بالمديرية
            if ($request->filled('directorate')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where('directorate_id', $request->directorate);
                });
            }
            
            if ($request->filled('zakah_type_id')) {
               
                    $query->where('zakah_type_id', $request->zakah_type_id);
               
            }
            
            if ($request->filled('status_id')) {
               
                    $query->where('zakah_status_id', $request->status_id);
               
            }
            
            
        
            $data = $query->get();
        
            /*
            |--------------------------------------------------------------------------
            | 2️⃣ تجهيز صفوف التقرير (Declaration + Directorate)
            |--------------------------------------------------------------------------
            */
            $reportRows = collect();
        
            $data
                ->groupBy(fn ($item) => $item->declaration_name ?? 'غير محدد')
                ->each(function ($declarationItems, $declaration) use (&$reportRows) {
        
                    $declarationItems
                        ->groupBy(fn ($item) => $item->user?->directorate_name ?? 'غير محدد')
                        ->each(function ($items, $directorate) use ($declaration, &$reportRows) {
        
                            $row = [
                                'category'       => $declaration, // 👈 نفس key بتاع tax_general
                                'directorate'    => $directorate,
        
                                'not_submitted'  => 0, // جاهز لو هتزوده بعدين
        
                                'first_review' => $items->where('zakah_status_id', 1)->count(),
                                'second_review' => $items->where('zakah_status_id', 2)->count(),
                                'approved'     => $items->where('zakah_status_id', 4)->count(),
                                'rejected'     => $items->where('zakah_status_id', 3)->count(),
                                'collecting'   => $items->where('zakah_status_id', 6)->count(),
                                'collected'    => $items->where('zakah_status_id', 7)->count(),
        
                                'total'          => $items->count(),
                                'global_total'   => $items->count(),
                            ];
        
                            $reportRows->push($row);
                        });
                });
        
            /*
            |--------------------------------------------------------------------------
            | 3️⃣ Pagination يدوي (نفس tax_general)
            |--------------------------------------------------------------------------
            */
            $page    = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);
        
            $paginated = new LengthAwarePaginator(
                $reportRows->forPage($page, $perPage)->values(),
                $reportRows->count(),
                $perPage,
                $page,
                [
                    'path'  => $request->url(),
                    'query' => $request->query(),
                ]
            );
        
            /*
            |--------------------------------------------------------------------------
            | 4️⃣ Response (نفس الشكل)
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'filters' => [
                    'from'        => $request->from,
                    'to'          => $request->to,
                    'directorate' => $request->directorate,
                    'zakah_type_id' => $request->zakah_type_id,
                    'status_id' => $request->status_id,
                ],
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
                'data' => $paginated->items(), // ✅ Flat rows
            ]);
        }
        elseif ($request->report_type == "duty_debts") 
        {

            /*
            |--------------------------------------------------------------------------
            | 1️⃣ Query أساسي
            |--------------------------------------------------------------------------
            */
            $query = CustomerZakahBalance::with(['user', 'declaration']);
        
            // فلترة بالتاريخ
            if ($request->filled('from')) {
                $query->whereDate('created_at', '>=', $request->from);
            }
        
            if ($request->filled('to')) {
                $query->whereDate('created_at', '<=', $request->to);
            }
        
            // فلترة بالمديرية
            if ($request->filled('directorate_id')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where('directorate_id', $request->directorate_id);
                });
            }
            
            if ($request->filled('zakah_type_id')) {
               
                    $query->where('zakah_type_id', $request->zakah_type_id);
               
            }
        
            $data = $query->get();
        
            /*
            |--------------------------------------------------------------------------
            | 2️⃣ تجهيز الصفوف (مطابقة للصورة)
            |--------------------------------------------------------------------------
            */
            $reportRows = collect();
        
            $data
                ->groupBy(fn ($item) => $item->user?->directorate_name ?? 'غير محدد')
                ->each(function ($directorateItems, $directorate) use (&$reportRows) {
        
                    $directorateItems
                        ->groupBy(fn ($item) => $item->declaration_name ?? 'غير محدد')
                        ->each(function ($items, $category) use ($directorate, &$reportRows) {
        
                            // قيد التحصيل
                            $collecting = $items->where('zakah_status_id', 4);
                            // تم التحصيل
                            $collected  = $items->where('zakah_status_id', 5);
        
                            $row = [
                                'directorate' => $directorate,
                                'category'    => $category,
        
                                // قيد التحصيل
                                'collecting_count'  => $collecting->count(),
                                'collecting_amount' => $collecting->sum('amount'),
        
                                // تم التحصيل
                                'collected_count'   => $collected->count(),
                                'collected_amount'  => $collected->sum('amount'),
        
                                // الإجمالي
                                'total_count'  => $collecting->count() + $collected->count(),
                                'total_amount' => $collecting->sum('amount') + $collected->sum('amount'),
                            ];
        
                            $reportRows->push($row);
                        });
                });
        
            /*
            |--------------------------------------------------------------------------
            | 3️⃣ Pagination
            |--------------------------------------------------------------------------
            */
            $page    = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);
        
            $paginated = new LengthAwarePaginator(
                $reportRows->forPage($page, $perPage)->values(),
                $reportRows->count(),
                $perPage,
                $page,
                [
                    'path'  => $request->url(),
                    'query' => $request->query(),
                ]
            );
        
            /*
            |--------------------------------------------------------------------------
            | 4️⃣ Response مطابق للصورة
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'filters' => [
                    'from'           => $request->from,
                    'to'             => $request->to,
                    'directorate_id' => $request->directorate_id,
                    'zakah_type_id' => $request->zakah_type_id,
                ],
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
                'data' => $paginated->items(),
            ]);
        }

        elseif ($request->report_type == "duty_balances") 
        {
           
            /*
            |------------------------------------------------------------------
            | 1️⃣ Query أساسي مع كل الفلاتر على الأعمدة الحقيقية
            |------------------------------------------------------------------
            */
            $query = CustomerZakahBalance::with(['user', 'customer', 'status', 'zakah_type', 'declaration'])
                ->whereIn('zakah_status_id', [6, 7]);
        
            // فلترة بالتاريخ
            if ($request->filled('from')) {
                $query->where('created_at', '>=', Carbon::parse($request->from)->startOfDay());
            }
        
            if ($request->filled('to')) {
                $query->where('created_at', '<=', Carbon::parse($request->to)->endOfDay());
            }
        
            // فلترة بالمديرية
            if ($request->filled('directorate_id')) {
                $query->whereHas('user', fn($q) => $q->where('directorate_id', $request->directorate_id));
            }
        
            // فلترة باسم المكلف
            if ($request->filled('taxpayer_name')) {
                $query->whereHas('customer', fn($q) => $q->where('name', 'like', "%{$request->taxpayer_name}%"));
            }
        
            // فلترة بالتصنيف
            if ($request->filled('classification')) {
                $query->whereHas('tax_type', fn($q) => $q->where('name', 'like', "%{$request->classification}%"));
            }
        
            // فلترة بالاسم التجاري
            if ($request->filled('commercial_name')) {
                $query->whereHas('customer.licenses', fn($q) =>
                    $q->where('business_name', 'like', "%{$request->commercial_name}%")
                );
            }
        
            // فلترة بالحالة
            if ($request->filled('status_id')) {
                $query->where('zakah_status_id', $request->status_id);
            }
            
            if ($request->filled('zakah_number_id')) {
                $query->where('zakah_number_id', $request->zakah_number_id);
            }
        
            // فلترة بالسنة
            if ($request->filled('activity_year')) {
                $query->where('year', $request->activity_year);
            }
        
            // فلترة بالالتزام
            if ($request->filled('commitment')) {
                $query->where('tax_commitment_text', 'like', "%{$request->commitment}%");
            }
        
            // فلترة بنتيجة التسوية
            if ($request->filled('settlement_result')) {
                // لن نستخدم هنا عمود في DB لأنه محسوب بـ Accessor
            }
        
            /*
            |------------------------------------------------------------------
            | 2️⃣ جلب البيانات وترتيبها
            |------------------------------------------------------------------
            */
            $items = $query->orderBy('created_at', 'desc')->get();
        
            /*
            |------------------------------------------------------------------
            | 3️⃣ فلترة بالـ balance_status بعد جلب البيانات
            |------------------------------------------------------------------
            */
            if ($request->filled('balance_status')) {
                if ($request->balance_status == 'رصيد دائن') {
                    $items = $items->filter(fn($i) => $i->settlement_result == 0);
                } elseif ($request->balance_status == 'تم التسوية') {
                    $items = $items->filter(fn($i) => $i->settlement_result > 0);
                }
            }
        
            /*
            |------------------------------------------------------------------
            | 4️⃣ تجهيز الصفوف للـ API
            |------------------------------------------------------------------
            */
            $rows = $items->map(fn($item) => [
                
                'payment_date'     => $item->created_at?->format('Y-m-d') ?? '-',
                'file_number'      => $item->zakah_number_id ?? '-',
                'classification'   => $item->zakah_type_name ?? '-',
                'taxpayer_name'    => $item->customer_name ?? '-',
                'directorate'      => $item->directorate_name ?? '-',
                'commercial_name'  => $item->customer?->commercial_name ?? '-',
                'amount'           => (float) $item->total_amount,
                'activity_year'    => $item->year ?? '-',
                'status'           => $item->zakah_status_id == 6 ? 'قيد التحصيل' : ($item->zakah_status_id ==7 ? 'تم التحصيل' : '-'),
                'commitment'       => $item->tax_commitment_text ?? '-',
                'settlement_result'=> $item->settlement_result ?? '-',
                'balance_status'   => $item->final_balance_status_text == 'سليم' ? 'رصيد دائن' : 'تم التسوية',
            ]);
        
            /*
            |------------------------------------------------------------------
            | 5️⃣ Pagination يدوي
            |------------------------------------------------------------------
            */
            $page    = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);
        
            $paginated = new LengthAwarePaginator(
                $rows->forPage($page, $perPage)->values(),
                $rows->count(),
                $perPage,
                $page,
                [
                    'path'  => $request->url(),
                    'query' => $request->query(),
                ]
            );
        
            /*
            |------------------------------------------------------------------
            | 6️⃣ Response نهائي
            |------------------------------------------------------------------
            */
            return response()->json([
                'filters' => [
                        'from'           => $request->from,
                        'to'             => $request->to,
                        'directorate_id' => $request->directorate_id,
                        'taxpayer_name' => $request->taxpayer_name,
                        'classification' => $request->classification,
                        'commercial_name' => $request->commercial_name,
                        'status_id' => $request->status_id,
                        'activity_year' => $request->activity_year,
                        'commitment' => $request->commitment,
                        'balance_status' => $request->balance_status,
                        'zakah_number_id' => $request->zakah_number_id,
                    ],
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
                'data' => $paginated->items(),
            ]);
        }


        






            
    }
    
    

   




}

    

