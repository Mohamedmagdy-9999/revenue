<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\TaxType;
use App\Models\ZakahType;
class Customer extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable,SoftDeletes;
    protected $table = "customers";
    protected $guarded = [];
    
    protected $hidden = ['user','user_id','country','country_id','identity','identity_type_id','qualifications','specializations','tax_file'];

    protected $appends = ['user_name','country_name','identity_name','profile_image_url','front_image_url','back_image_url','request_application_image_url','preview_location_application_image_url','health_checkup_image_url','qualification_image_url','supervisor_qualification_image_url', 'qualification_name','specialization_name','has_tax_file','tax_file_id','tax_balance_details','zakah_balance_details','profile_image_base','zakah_number_id','commercial_name'];
    

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    // ✅ مطلوب من JWT
    public function getJWTCustomClaims()
    {
        return [
            'id' => $this->id,
            'identity_number' => $this->identity_number,
            'name' => $this->name,
            'email' => $this->email,
            'phone_1' => $this->phone_1,
            'identity_start_date' => $this->identity_start_date,
            'identity_start_date' => $this->identity_start_date,
            'profile_image_url' => $this->profile_image_url,
            
        ];
    }

    public function tax_file()
    {
        return $this->hasOne(TaxFile::class,'customer_id');
    }
    
    public function getHasTaxFileAttribute()
    {
        return $this->tax_file ? true : false;
    }
    
    public function getTaxFileIdAttribute()
    {
        return $this->tax_file ? $this->tax_file->id : null;
    }
    
    public function tax_balance()
    {
        return $this->hasMany(CustomerTaxBalance::class,'customer_id');
    }
    
    
   //public function getTaxBalanceDetailsAttribute()
   // {
     //   $balance = CustomerTaxBalance::where('customer_id', $this->id)->exists();
        
      //  $details = CustomerTaxBalance::where('customer_id', $this->id)->get();
        
    
      //  return [
        //    'balance'     => $balance,
         //   'details'    => $details,
            
       // ];
  //  }




    public function getTaxBalanceDetailsAttribute()
    {
        $balances = CustomerTaxBalance::where('customer_id', $this->id)
            ->with('tax_type','employees','activities','buildings') // لو عندك relation
            ->get()
            ->groupBy('tax_type_id');
    
        $cards = [];
    
        foreach ($balances as $taxTypeId => $items) {
            $taxType = TaxType::find($taxTypeId);
    
            $cards[] = [
                'tax_type_id'   => $taxTypeId,
                'tax_type_name' => $taxType ? $taxType->name : null,
                'count'         => $items->count(),
                'years'         => $items->pluck('year')->unique()->values(),
                'total_value'   => $items->sum(function ($item) {
                    return $item->value ?? 0;
                }),
                'details'       => $items, // لو محتاج التفاصيل
            ];
        }
    
        return [
            'has_balance' => $balances->count() > 0,
            'cards'       => $cards,
        ];
    }
    
    
    
    public function zakah_number()
    {
        return $this->hasOne(ZakahNumber::class,'customer_id');
    }
    
    public function getHasZakahNumberAttribute()
    {
        return $this->zakah_number ? true : false;
    }
    
    public function getZakahNumberIdAttribute()
    {
        return $this->zakah_number ? $this->zakah_number->id : null;
    }
    
    public function zakah_balance()
    {
        return $this->hasMany(CustomerZakahBalance::class,'customer_id');
    }
    
    public function getZakahBalanceDetailsAttribute()
    {
        $balances = CustomerZakahBalance::where('customer_id', $this->id)
            ->with('zakah_type','employees') // لو عندك relation
            ->get()
            ->groupBy('zakah_type_id');
    
        $cards = [];
    
        foreach ($balances as $zakahTypeId => $items) {
            $zakahType = ZakahType::find($zakahTypeId);
    
            $cards[] = [
                'zakah_type_id'   => $zakahTypeId,
                'zakah_type_name' => $zakahType ? $zakahType->name : null,
                'count'         => $items->count(),
                'years'         => $items->pluck('year')->unique()->values(),
                'total_value'   => $items->sum(function ($item) {
                    return $item->value ?? 0;
                }),
                'details'       => $items, // لو محتاج التفاصيل
            ];
        }
    
        return [
            'has_balance' => $balances->count() > 0,
            'cards'       => $cards,
        ];
    }
    
   

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function getUserNameAttribute()
    {
        return $this->user ? $this->user->name : null;
    }
    
    
    public function country()
    {
        return $this->belongsTo(Country::class,'country_id');
    }

    public function getCountryNameAttribute()
    {
        return $this->country ? $this->country->name : null;
    }
    
    public function identity()
    {
        return $this->belongsTo(IdentityType::class,'identity_type_id');
    }

    public function getIdentityNameAttribute()
    {
        return $this->identity ? $this->identity->name : null;
    }
    
    public function getProfileImageUrlAttribute()
    {
        if ($this->profile_picture) {
            return asset('customers/' . $this->profile_picture);
        }
        return null;
    }
    
    
    public function getProfileImageBaseAttribute()
    {
        if ($this->profile_picture) {

            $path = public_path('customers/' . $this->profile_picture);
    
            if (file_exists($path)) {
                    $base64 = base64_encode(file_get_contents($path));
                    $mime = mime_content_type($path);
        
                    return "data:$mime;base64,$base64";
            }
        }
    
        return null;
    }
    
    
    public function getFrontImageUrlAttribute()
    {
        if ($this->identity_front_image	) {
            return asset('customers/' . $this->identity_front_image);
        }
        return null;
    }
    
    public function getBackImageUrlAttribute()
    {
        if ($this->identity_back_image	) {
            return asset('customers/' . $this->identity_back_image);
        }
        return null;
    }
    
    public function getRequestApplicationImageUrlAttribute()
    {
        if ($this->request_application_image	) {
            return asset('customers/' . $this->request_application_image);
        }
        return null;
    }
    
    public function getPreviewLocationApplicationImageUrlAttribute()
    {
        if ($this->preview_location_application_image	) {
            return asset('customers/' . $this->preview_location_application_image);
        }
        return null;
    }
    
    public function getHealthCheckupImageUrlAttribute()
    {
        if ($this->health_checkup_image	) {
            return asset('customers/' . $this->health_checkup_image);
        }
        return null;
    }
    
    public function getQualificationImageUrlAttribute()
    {
        if ($this->qualification_image	) {
            return asset('customers/' . $this->qualification_image);
        }
        return null;
    }
    
    
    
    public function getSupervisorQualificationImageUrlAttribute()
    {
        if ($this->supervisor_qualification_image	) {
            return asset('customers/' . $this->supervisor_qualification_image);
        }
        return null;
    }
    
    public function licenses()
    {
        return $this->hasMany(License::class,'customer_id');
    }
    
    public function getCommercialNameAttribute()
    {
        return $this->licenses->first()?->business_name ?? null;
    }
    
    public function qualifications()
    {
        return $this->belongstO(Qualification::class,'qualification_id');
    }
    
    public function specializations()
    {
        return $this->belongstO(Specialization::class,'specialization_id');
    }
    
    public function getQualificationNameAttribute()
    {
        return $this->qualifications ? $this->qualifications->name : null;
    }
    
    public function getSpecializationNameAttribute()
    {
        return $this->specializations ? $this->specializations->name : null;
    }
    
    
    public function ownedProperties()
    {
        return $this->hasMany(BranchRenewal::class, 'owner_id');
    }
    
}
