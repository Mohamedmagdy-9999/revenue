<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LicenseeBranch extends Model
{
    use HasFactory;
    
    protected $table = "license_branches";
    protected $guarded = [];
    
    protected $hidden = ['type','directorate','license'];
    
    protected $appends = ['type_name','directorate_name','customer_name','customer_id','category_name','sub_name','sub_category_id','customer_identity_number','customer_phone','customer_identity_name','customer_country_name','customer_tel','business_name','commercial_registration_image_url','category_id','commercial_registration_image','sub_fake_id','request_application_image_url','preview_location_application_image_url','qualification_image_url','request_application_image','preview_location_application_image','qualification_image','qualification_id','specialization_id','graduation_date','graduation_country','profession','health_checkup_image_url',
        'health_checkup_image','customer_Profile_image_url','qualification_name',
    'specialization_name','customer_Profile_image_base','customer_address', 'customer_identity_start_date','customer_identity_end_date'];
    
    
    
    public function getHealthCheckupImageUrlAttribute()
    {
        return $this->license ? $this->license->health_checkup_image_url : null;
    }
    
    public function getHealthCheckupImageAttribute()
    {
        return $this->license ? $this->license->health_checkup_image : null;
    }
    
    
    
    public function type()
    {
        return $this->belongsTo(BranchType::class, 'branch_type_id');
    }
    
    public function getTypeNameAttribute()
    {
        return $this->type ? $this->type->name : null;
    }
    
    public function directorate()
    {
        return $this->belongsTo(Directorate::class, 'directorate_id');
    }
    
    public function getDirectorateNameAttribute()
    {
        return $this->directorate ? $this->directorate->name : null;
    }
    
    public function renewals()
    {
        return $this->hasMany(BranchRenewal::class,'license_branch_id');
    }
    
    public function license()
    {
        return $this->belongsTo(License::class, 'license_id');
    }
    
    public function getCustomerNameAttribute()
    {
        return $this->license ? $this->license->customer_name : null;
    }
    public function getCustomerAddressAttribute()
    {
        return $this->license ? $this->license->customer_address : null;
    }
    
    public function getCustomerIdAttribute()
    {
        return $this->license ? $this->license->customer_id : null;
    }
    public function getCategoryIdAttribute()
    {
        return $this->license ? $this->license->category_id : null;
    }
    public function getCustomerIdentityNumberAttribute()
    {
        return $this->license ? $this->license->customer->identity_number : null;
    }
    
    public function getCustomerIdentityNameAttribute()
    {
        return $this->license ? $this->license->customer->identity_name : null;
    }
    
    public function getCustomerCountryNameAttribute()
    {
        return $this->license ? $this->license->customer->country_name : null;
    }
    
    public function getCustomerIdentityStartDateAttribute()
    {
        return $this->license ? $this->license->customer->identity_start_date : null;
    }
    
    public function getCustomerIdentityEndDateAttribute()
    {
        return $this->license ? $this->license->customer->identity_end_date : null;
    }
    
  
    
    public function getCustomerProfileImageUrlAttribute()
    {
        return $this->license ? $this->license->customer_profile_image_url : null;
    }
    
    public function getCustomerProfileImageBaseAttribute()
    {
        return $this->license ? $this->license->customer_profile_image_base : null;
    }
    
    public function getCustomerPhoneAttribute()
    {
        return $this->license ? $this->license->customer->phone_1 : null;
    }
    
    public function getBusinessNameAttribute()
    {
        return $this->license ? $this->license->business_name : null;
    }
    
    public function getCommercialRegistrationImageUrlAttribute()
    {
        return $this->license ? $this->license->commercial_registration_image_url : null;
    }
    
    
    public function getCommercialRegistrationImageAttribute()
    {
        return $this->license ? $this->license->commercial_registration_image : null;
    }
    
    public function getCustomerTelAttribute()
    {
        return $this->license ? $this->license->customer->tel_1 : null;
    }
    
    public function getCategoryNameAttribute()
    {
        return $this->license ? $this->license->category_name : null;
    }
    
    public function getSubNameAttribute()
    {
        return $this->license ? $this->license->sub_name : null;
    }
    
    public function getSubCategoryIdAttribute()
    {
        return $this->license ? $this->license->sub_category_id : null;
    }
    
    public function getSubFakeIdAttribute()
    {
        return $this->license ? $this->license->sub_fake_id : null;
    }
    
    
    
    public function getRequestApplicationImageUrlAttribute()
    {
        return $this->license ? $this->license->request_application_image_url : null;
    }
    
    public function getPreviewLocationApplicationImageUrlAttribute()
    {
        return $this->license ? $this->license->preview_location_application_image_url : null;
    }
    
    public function getQualificationImageUrlAttribute()
    {
        return $this->license ? $this->license->qualification_image_url : null;
    }
    
   
    
    
    
    public function getRequestApplicationImageAttribute()
    {
        return $this->license ? $this->license->request_application_image : null;
    }
    
    public function getPreviewLocationApplicationImageAttribute()
    {
        return $this->license ? $this->license->preview_location_application_image : null;
    }
    
    public function getQualificationImageAttribute()
    {
        return $this->license ? $this->license->qualification_image : null;
    }
    
    public function getSupervisorQualificationImageAttribute()
    {
        return $this->license ? $this->license->supervisor_qualification_image : null;
    }
    
    
    
    public function getSupervisorRequestApplicationImageAttribute()
    {
        return $this->license ? $this->license->request_application_image : null;
    }
    
    public function getSupervisorPreviewLocationApplicationImageAttribute()
    {
        return $this->license ? $this->license->preview_location_application_image : null;
    }
    
   
 
    public function getQualificationIdAttribute()
    {
        return $this->license ? $this->license->qualification_id : null;
    }
    
    public function getSpecializationIdAttribute()
    {
        return $this->license ? $this->license->specialization_id : null;
    }
    
    
    
    public function getProfessionAttribute()
    {
        return $this->license ? $this->license->profession : null;
    }
    
    
    
    public function getGraduationDateAttribute()
    {
        return $this->license ? $this->license->graduation_date : null;
    }
    
    
    
    
    
    
    public function getGraduationcountryAttribute()
    {
        return $this->license ? $this->license->graduation_country : null;
    }
    
    
    public function getQualificationNameAttribute()
    {
        return $this->license ? $this->license->qualification_name : null;
    }
    
    
    public function getSpecializationNameAttribute()
    {
        return $this->license ? $this->license->specialization_name : null;
    }
    
    
  
}
