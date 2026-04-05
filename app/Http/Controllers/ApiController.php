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
class ApiController extends Controller
{
    
    public function payment_types()
    {
        $items = PaymentType::get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
   
    public function banks()
    {
        $items = Bank::get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }

    public function countries()
    {
        $items = Country::get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }

    public function currencies()
    {
        $items = Currency::latest()->get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
    
    public function governorates()
    {
        $items = Government::get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
    
    public function directorates($id)
    {
        $items = Directorate::where('governorate_id',$id)->get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
    
    public function all_directorates()
    {
        $items = Directorate::where('governorate_id',1)->latest()->get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
    
    public function departments()
    {
        $items = Department::get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
    
    public function prices()
    {
        $items = Price::get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
    
    public function identity_types()
    {
        $items = IdentityType::get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }


    public function categories()
    {
        $items = Category::get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }

    public function sub_categories($category_id)
    {
        $items = Service::where('category_id', $category_id)
            ->whereRaw('LENGTH(code) = 3')->where('active',1)
            ->orderBy('code', 'asc')
            ->get();
    
        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $items,
        ]);
    }
        
    public function second_child($category_id, $sub_code)
    {
        $item = Service::where('category_id', $category_id)
            ->where('code', 'like', $sub_code . '%')   // يبدأ بـ 301
            ->whereRaw('LENGTH(code) > ?', [strlen($sub_code)]) // أطول من الأب
            ->orderBy('code', 'asc')
            ->paginate(10);
    
        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $item,
        ]);
    }



    

    public function branch_types()
    {
        $items = BranchType::get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
    
    public function services()
    {
        $items = Service::get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
    
    public function search_services(Request $request)
    {
        $serviceTypeId = $request->service_type_id;
    
        $items = Service::with(['prices' => function ($q) use ($serviceTypeId) {
            $q->where('service_type_id', $serviceTypeId);
        }])
        ->where('category_id', $request->category_id)
        ->where('code', $request->code)
        ->paginate(8);
    
        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $items,
        ]);
    }

    
    public function rental_types()
    {
        $items = RentalType::get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
    
    public function panel_types()
    {
        $items = PanelType::get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
    
    public function status()
    {
        $items = Status::get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
    
    public function filter_service_by_category(Request $request)
    {
        $services = Service::where('category_id',$request->category_id)->latest()->paginate(8);
        
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $services,
        ]);
        
        
    }
    
    public function search_by_service_name(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
        ]);
    
        $services = Service::where('name', 'LIKE', '%' . $request->name . '%')
                            ->OrWhere('code',$request->name)->latest()
                            ->paginate(10);
    
        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $services,
        ]);
    }
    
    
    
    public function qualifications()
    {
        $items = Qualification::get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }

    public function specializations($id)
    {
        $items = Specialization::where('qualifications_id',$id)->get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
    
    public function tax_types()
    {
        $items = TaxType::get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
    
    
    public function declarations()
    {
        $items = Declaration::get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
    
    
    public function unit_types()
    {
        $items = UnitType::get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
   
    public function service_type()
    {
        $data = ServiceType::whereIn('id', [62, 63, 64, 65])->get();
    
        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $data,
        ]);
    }


     public function zakah_declarations()
    {
        $items = ZakahDeclaration::get();
        return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $items,
        ]);
    }
    
}
