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
use App\Models\Application;
use Barryvdh\DomPDF\Facade\Pdf; // إذا لم تضف الـ Facade



class LicensesController extends Controller
{
   
   // public function add_application()
   // {
     //   $application = new Application();
    
      //  do {
       //     $number = mt_rand(100000, 999999); // رقم عشوائي من 6 أرقام
      //  } while (Application::where('number', $number)->exists());
    
       // $application->number = $number;
       // $application->save();
    
       // return response()->json([
                //    'status' => 200,
                //    'message' => 'success',
                 //   'data' => $application->number,
        //]);
   // }
   
    


    

    public function add_application(Request $request)
    {
        
           $messages = [
                'count.required'    => 'من فضلك أدخل العدد',
                'count.integer'     => 'العدد يجب أن يكون رقمًا صحيحًا',
                'count.min'         => 'العدد لا يمكن أن يقل عن 1',
                'count.max'         => 'العدد لا يمكن أن يزيد عن 500',
            
                'category_id.required' => 'اختر الفئة',
                'category_id.exists'   => 'الفئة المختارة غير موجودة بالنظام',
            ];
            
            $attributes = [
                'count' => 'العدد',
                'category_id' => 'الفئة',
            ];
            
            $request->validate([
                'count' => 'required|integer|min:1|max:500',
                'category_id' => 'required|exists:categories,id'
            ], $messages, $attributes);

           
        
        $count = $request->count;
        $applications = [];
    
        for ($i = 0; $i < $count; $i++) {
            do {
                $number = mt_rand(100000, 999999);
            } while (Application::where('number', $number)->exists());
    
            $application = Application::create([
                'number' => $number,
                'category_id' => $request->category_id,
            ]);
    
            $applications[] = [
                'number' => $number,
                'pdf_url' => url("/api/application/print/{$number}")
            ];
        }
    
        return response()->json([
            'status' => 200,
            'message' => 'تم طباعة الاستمارة بنجاح',
            'total' => count($applications),
            'data' => $applications
        ]);
    }


    
    
    public function print_form($number)
    {
        $application = Application::where('number', $number)->firstOrFail();
    
        $pdf = \PDF::loadView('application_form', [
            'number' => $application->number
        ]);
    
        return $pdf->stream("application_{$number}.pdf");
    }


    
    
}
