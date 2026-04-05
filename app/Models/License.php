<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    use HasFactory;
    
    protected $table = "licenses";
    protected $guarded = [];
    protected $hidden = ['category','sub','customer'];
    protected $appends = [
    'customer_name',
    'category_name',
    'sub_name',
    'commercial_registration_image_url',
    'first_start_date',
    'first_end_date',
    'first_status_name',
    'customer_identity_name',
    'customer_country_name',
    'sub_fake_id',
    'request_application_image_url',
    'preview_location_application_image_url',
    'qualification_image_url',
    'supervisor_qualification_image_url',
    'owner_request_application_image_url',
    'owner_preview_location_application_image_url',
    'owner_qualification_image_url',
    'owner_supervisor_qualification_image_url',
    'request_application_image',
    'preview_location_application_image',
    'qualification_image',
    'qualification_id',
    'specialization_id',
    'profession',
    'graduation_date',
    'graduation_country',
    'health_checkup_image_url',
    'health_checkup_image',
    'customer_profile_image_url',
    'qualification_name',
    'specialization_name',
    'customer_profile_image_base',
    'customer_address',
    'dashboard_status',
    'department_name',
    'dashboard_color',
    'directorate_name',
    'renewal_id',
    'coming_from',
    'depart',
    'status_id',
    'branch_id',
    'type',
];
    
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
    
    
    public function sub()
    {
        return $this->belongsTo(Service::class, 'sub_category_id');
    }
    
    public function getCategoryNameAttribute()
    {
        return $this->category ? $this->category->name : null;
    }
    
    public function getSubNameAttribute()
    {
        return $this->sub ? $this->sub->name : null;
    }
    
    public function getSubFakeIdAttribute()
    {
        return $this->sub ? $this->sub->fake_id : null;
    }
    
    public function branches()
    {
        return $this->hasMany(LicenseeBranch::class, 'license_id');
    }
    
    
    public function getCommercialRegistrationImageUrlAttribute()
    {
        if ($this->commercial_registration_image) {
            return asset('license/' . $this->commercial_registration_image);
        }
        return null;
    }
    
    
    
    public function renewals()
    {
        return $this->hasManyThrough(
            BranchRenewal::class,
            LicenseeBranch::class,
            'license_id',           // foreign key on license_branches table
            'license_branch_id',   // foreign key on branch_renewals table
            'id',                   // local key on licenses table
            'id'                    // local key on license_branches table
        );
    }
    
    public function firstRenewal()
    {
        return $this->hasOneThrough(
            BranchRenewal::class,
            LicenseeBranch::class,
            'license_id',         // FK في license_branches
            'license_branch_id',  // FK في branch_renewals
            'id',                 // PK في licenses
            'id'                  // PK في license_branches
        )->oldest(); // أول تجديد
    }
    public function getRenewalIdAttribute()
    {
        $last = $this->firstRenewal()->oldest()->first();
        return $last ? $last->id : null;
    }
    
    public function getStatusIdAttribute()
    {
        $last = $this->firstRenewal()->oldest()->first();
        return $last ? $last->status_id : null;
    }
    
    public function getBranchIdAttribute()
    {
        $last = $this->firstRenewal()->oldest()->first();
        return $last ? $last->license_branch_id  : null;
    }
    
    
    public function getFirstStartDateAttribute()
    {
        $last = $this->firstRenewal()->oldest()->first();
        return $last ? $last->start_date : null;
    }
    
    public function getFirstEndDateAttribute()
    {
        $last = $this->firstRenewal()->oldest()->first();
        return $last ? $last->end_date : null;
    }
    
    public function getFirstStatusNameAttribute()
    {
        $first = $this->firstRenewal()->oldest()->first();
        return $first ? $first->status_name : null;
    }
    
    public function getComingFromAttribute()
    {
        $first = $this->firstRenewal()->oldest()->first();
        return $first ? $first->coming_from : null;
    }
    
    public function getDepartAttribute()
    {
        $first = $this->firstRenewal()->oldest()->first();
        return $first ? $first->depart : null;
    }
    
    public function getTypeAttribute()
    {
        $first = $this->firstRenewal()->oldest()->first();
        return $first ? $first->type : null;
    }
    
    
    public function getDashboardStatusDetailAttribute()
    {
        $first = $this->firstRenewal()->oldest()->first();
    
        if (!$first) {
            return [
                'label' => 'غير محدد',
                'color' => '#6B7280'
            ];
        }
    
        if ($first->status_id >= 6 && $first->end_date) {
            return $first->end_date > now()
                ? ['label' => 'سارية',  'color' => '#34D399']
                : ['label' => 'منتهية', 'color' => '#DC3545'];
        }
    
        return match ((int)$first->status_id) {
            1 => ['label' => 'قيد المراجعة', 'color' => '#F59E0B'],
            2 => ['label' => 'معتمد',       'color' => '#28A745'],
            3 => ['label' => 'مرفوض',       'color' => '#EF4444'],
            4 => ['label' => 'قيد التحصيل', 'color' => '#3B82F6'],
            5 => ['label' => 'تم التحصيل',  'color'=>'#10B981'],
            default => ['label' => 'غير محدد', 'color' => '#6B7280'],
        };
    }


    public function getDashboardStatusAttribute()
    {
        return $this->dashboard_status_detail['label'] ?? null;
    }
    
    public function getDashboardColorAttribute()
    {
        return $this->dashboard_status_detail['color'] ?? null;
    }
        
    
    public function getDepartmentNameAttribute()
    {
        $first = $this->firstRenewal()->oldest()->first();
    
        if (!$first) {
            return null;
        }
    
        return match ((int) $first->status_id) {
            1 => 'المراجعة',
            2 => 'الادخال',
            3 => 'الادخال',
            4 => 'المالية',
            5 => 'الادخال',
            default => null,
        };
    }
  
    public function getDirectorateNameAttribute()
    {
        $first = $this->firstRenewal()->oldest()->first();
        return $first ? $first->directorate_name : null;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
    
    public function getCustomerNameAttribute()
    {
        return $this->customer ? $this->customer->name : null;
    }
    
    public function getCustomerAddressAttribute()
    {
        return $this->customer ? $this->customer->address : null;
    }
    
    public function getCustomerProfileImageUrlAttribute()
    {
        return $this->customer ? $this->customer->profile_image_url : null;
    }
    
    public function getCustomerProfileImageBaseAttribute()
    {
        return $this->customer ? $this->customer->profile_image_base : null;
    }
    
    public function getCustomerIdentityNameAttribute()
    {
        return $this->customer ? $this->customer->identity_name : null;
    }
    
    public function getCustomerCountryNameAttribute()
    {
        return $this->customer ? $this->customer->country_name : null;
    }
    
    public function getRequestApplicationImageUrlAttribute()
    {
        return $this->customer ? $this->customer->request_application_image_url : null;
    }
    
    public function getPreviewLocationApplicationImageUrlAttribute()
    {
        return $this->customer ? $this->customer->preview_location_application_image_url : null;
    }
    
    public function getQualificationImageUrlAttribute()
    {
        return $this->customer ? $this->customer->qualification_image_url : null;
    }
   
    
    public function getHealthCheckupImageUrlAttribute()
    {
        return $this->customer ? $this->customer->health_checkup_image_url : null;
    }
    
    public function getHealthCheckupImageAttribute()
    {
        return $this->customer ? $this->customer->health_checkup_image : null;
    }
    
    
    public function getSupervisorQualificationImageUrlAttribute()
    {
        return $this->customer ? $this->customer->supervisor_qualification_image_url : null;
    }
    
    
    
    public function getOwnerRequestApplicationImageUrlAttribute()
    {
        return $this->owner ? $this->owner->request_application_image_url : null;
    }
    
    public function getOwnerPreviewLocationApplicationImageUrlAttribute()
    {
        return $this->owner ? $this->owner->preview_location_application_image_url : null;
    }
    
    public function getOwnerQualificationImageUrlAttribute()
    {
        return $this->owner ? $this->owner->qualification_image_url : null;
    }
    
    public function getOwnerSupervisorQualificationImageUrlAttribute()
    {
        return $this->owner ? $this->owner->supervisor_qualification_image_url : null;
    }
    
    
    
    
    public function getRequestApplicationImageAttribute()
    {
        return $this->customer ? $this->customer->request_application_image : null;
    }
    
    public function getPreviewLocationApplicationImageAttribute()
    {
        return $this->customer ? $this->customer->preview_location_application_image : null;
    }
    
    public function getQualificationImageAttribute()
    {
        return $this->customer ? $this->customer->qualification_image : null;
    }
    
    public function getSupervisorQualificationImageAttribute()
    {
        return $this->customer ? $this->customer->supervisor_qualification_image : null;
    }
    
    
    
    public function getSupervisorRequestApplicationImageAttribute()
    {
        return $this->owner ? $this->owner->request_application_image : null;
    }
    
    public function getSupervisorPreviewLocationApplicationImageAttribute()
    {
        return $this->owner ? $this->owner->preview_location_application_image : null;
    }

    
   
    
    public function getQualificationIdAttribute()
    {
        return $this->customer ? $this->customer->qualification_id : null;
    }
    
    public function getQualificationNameAttribute()
    {
        return $this->customer ? $this->customer->qualification_name : null;
    }
    
    
    
    public function getSpecializationIdAttribute()
    {
        return $this->customer ? $this->customer->specialization_id : null;
    }
    
    public function getSpecializationNameAttribute()
    {
        return $this->customer ? $this->customer->specialization_name : null;
    }
    
    
    
    
    public function getProfessionAttribute()
    {
        return $this->customer ? $this->customer->profession : null;
    }
    
    
   
    
    public function getGraduationDateAttribute()
    {
        return $this->customer ? $this->customer->graduation_date : null;
    }
    
   
    
    
    
    
    public function getGraduationcountryAttribute()
    {
        return $this->customer ? $this->customer->graduation_country : null;
    }
    
   
   
   
    
}
