<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class BranchRenewal extends Model
{
    use HasFactory;
    
    protected $table = "branch_renewals";
    protected $guarded = [];
    
    protected $hidden = ['currency','owner','panel_type','status','license_branch','rental_type','super','service_type'];
    
    protected $appends = ['currency_name','currency_price','owner_name','panel_type_name','status_name','directorate_name','directorate_id','address','lat','lang','name','customer_name','customer_id','rental_name','category_name','sub_name','sub_category_id','rent_image_url','electric_image_url','shop_front_image_url','shop_back_image_url','application_image_url','previous_professional_license_image_url','customer_identity_number','customer_phone','customer_identity_name','customer_country_name','customer_tel','customer_license_id','business_name','commercial_registration_image_url','owner_identity_number','owner_identity_name','type_name','previous_professional_license_image_url','suspension_form_image_url','active','category_id','commercial_registration_image','facility_plan_image_url','license_main_facility_image_url','facility_licenses_image_url','first_review_report_image_url','second_review_report_image_url','sub_fake_id','request_application_image_url','preview_location_application_image_url','qualification_image_url','supervisor_qualification_image_url','supervisor_preview_location_application_image_url','supervisor_qualification_image_url','supervisor_qualification_image_url','customer_qualification_name','customer_specialization_name','request_application_image','preview_location_application_image','qualification_image','supervisor_qualification_image','supervisor_request_application_image','supervisor_preview_location_application_image','supervisor_qualification_image','supervisor_qualification_image','qualification_id','specialization_id','graduation_date','supervisor_graduation_date','graduation_country','supervisor_graduation_country','profession','supervisor_profession','supervisor_name','supervisor_qualification_id','supervisor_specialization_id','health_checkup_image_url',
        'health_checkup_image','employee_contract_url','payment_receipt_image_url','services_total','customer_Profile_image_url','qualification_name',
    'specialization_name','supervisor_specialization_name','supervisor_qualification_name','customer_Profile_image_base','customer_address','branch_status','service_type_name','payment_receipt_image_nzafa_url','check_image_nzafa_url','check_image_url','supply_voucher_image_url','supply_voucher_image_nzafa_url','extra_image_url','extra_image_nzafa_url','payment_type_name','finance_user_name','amount','resource_type','amount_nzafa','payment_type_name_nzafa','nzafa_user_name','dashboard_status_name','dashboard_status_color','department_name','finance_paid',
    'nzafa_paid',
    'payment_status','finance_payment_user_name','finance_payment_receipt_number','finance_value','customer_identity_start_date','customer_identity_end_date'];
    
    
    protected $casts = [
        'is_nzafa' => 'boolean',
    ];
    
    public function finance()
    {
        return $this->hasOne(BranchRenewalFinance::class,'branch_renewal_id');
    }
    
    
    
  
    
    public function nzafa()
    {
        return $this->hasOne(BranchRenewalNzafa::class,'branch_renewal_id');
    }
    
    
    
    
    public function financePayments()
    {
        return $this->hasMany(BranchRenewalFinancePayment::class, 'branch_renewal_id');
    }
    
    public function getFinancePaymentUserNameAttribute()
    {
        $lastPayment = $this->financePayments()->latest()->first();
    
        return $lastPayment?->user?->name;
    }
    
    public function getFinancePaymentReceiptNumberAttribute()
    {
        $lastPayment = $this->financePayments()->latest()->first();
    
        return $lastPayment?->payment_receipt_number;
    }
    
    public function getFinanceValueAttribute()
    {
        $lastPayment = $this->financePayments()->latest()->first();
    
        return $lastPayment?->value;
    }
    
    public function nzafaPayments()
    {
        return $this->hasMany(BranchRenewalNzafaPayment::class, 'branch_renewal_id');
    }
    
    public function getFinancePaidAttribute()
    {
        return $this->financePayments()->exists();
    }
    
    public function getNzafaPaidAttribute()
    {
        return $this->nzafaPayments()->exists();
    }
    
    public function getPaymentStatusAttribute()
    {
        if ($this->finance_paid && $this->nzafa_paid) {
            return 'paid_all';
        }
    
        if ($this->finance_paid) {
            return 'finance_only';
        }
    
        if ($this->nzafa_paid) {
            return 'nzafa_only';
        }
    
        return 'not_paid';
    }


    
    public function getResourceTypeAttribute()
    {
        return match ((int) $this->category_id) {
            4 => 'مشترك',
            default => 'محلي',
        };
    }
    
    public function getAmountNzafaAttribute()
    {
        return match ((int) $this->payment_type_id_nzafa) {
            1 => $this->payment_value_nzafa,
            2 => $this->check_value_nzafa,
            default => 0,
        };
    }
    
    public function getAmountAttribute()
    {
        return match ((int) $this->payment_type_id) {
            1 => $this->payment_value,
            2 => $this->check_value,
            default => 0,
        };
    }

        
    public function getBranchStatusAttribute()
    {
        return $this->license_branch ? $this->license_branch->status : null;
    }
    
    public function getHealthCheckupImageUrlAttribute()
    {
        return $this->license_branch ? $this->license_branch->health_checkup_image_url : null;
    }
    
    public function getHealthCheckupImageAttribute()
    {
        return $this->license_branch ? $this->license_branch->health_checkup_image : null;
    }
    
    public function getPaymentReceiptImageUrlAttribute()
    {
        if ($this->payment_receipt_image) {
            return asset('renewal/' . $this->payment_receipt_image);
        }
        return null;
    }
    
    public function getPaymentReceiptImageNzafaUrlAttribute()
    {
        if ($this->payment_receipt_image_nzafa) {
            return asset('renewal/' . $this->payment_receipt_image_nzafa);
        }
        return null;
    }
    
    public function getCheckImageNzafaUrlAttribute()
    {
        if ($this->check_image_nzafa) {
            return asset('renewal/' . $this->check_image_nzafa);
        }
        return null;
    }
    
    public function getCheckImageUrlAttribute()
    {
        if ($this->check_image) {
            return asset('renewal/' . $this->check_image);
        }
        return null;
    }
    
    
    
    public function getSupplyVoucherImageNzafaUrlAttribute()
    {
        if ($this->supply_voucher_image_nzafa) {
            return asset('renewal/' . $this->supply_voucher_image_nzafa);
        }
        return null;
    }
    
    public function getSupplyVoucherImageUrlAttribute()
    {
        if ($this->supply_voucher_image) {
            return asset('renewal/' . $this->supply_voucher_image);
        }
        return null;
    }
    
    
    public function getExtraImageNzafaUrlAttribute()
    {
        if ($this->extra_image_nzafa) {
            return asset('renewal/' . $this->extra_image_nzafa);
        }
        return null;
    }
    
   public function getExtraImageUrlAttribute()
    {
        if ($this->extra_image) {
            return asset('renewal/' . $this->extra_image);
        }
        return null;
    }
    
    
    public function getFacilityPlanImageUrlAttribute()
    {
        if ($this->facility_plan_image) {
            return asset('renewal/' . $this->facility_plan_image);
        }
        return null;
    }
    
    public function getLicenseMainFacilityImageUrlAttribute()
    {
        if ($this->license_main_facility_image) {
            return asset('renewal/' . $this->license_main_facility_image);
        }
        return null;
    }
    
    
    public function getEmployeeContractUrlAttribute()
    {
        if ($this->employee_contract) {
            return asset('renewal/' . $this->employee_contract);
        }
        return null;
    }
    
    
    public function getFacilityLicensesImageUrlAttribute()
    {
        if ($this->facility_licenses_image) {
            return asset('renewal/' . $this->facility_licenses_image);
        }
        return null;
    }
    
    public function getFirstReviewReportImageUrlAttribute()
    {
        if ($this->first_review_report_image) {
            return asset('renewal/' . $this->first_review_report_image);
        }
        return null;
    }
    
    public function getSecondReviewReportImageUrlAttribute()
    {
        if ($this->second_review_report_image) {
            return asset('renewal/' . $this->second_review_report_image);
        }
        return null;
    }
    
    
    public function getRentImageUrlAttribute()
    {
        if ($this->rent_image) {
            return asset('renewal/' . $this->rent_image);
        }
        return null;
    }
    
    public function getSuspensionFormImageUrlAttribute()
    {
        if ($this->suspension_form_image) {
            return asset('renewal/' . $this->suspension_form_image);
        }
        return null;
    }
    
    public function getElectricImageUrlAttribute()
    {
        if ($this->electric_image) {
            return asset('renewal/' . $this->electric_image);
        }
        return null;
    }
    
    public function getShopFrontImageUrlAttribute()
    {
        if ($this->shop_front_image) {
            return asset('renewal/' . $this->shop_front_image);
        }
        return null;
    }
    
    public function getShopBackImageUrlAttribute()
    {
        if ($this->shop_back_image) {
            return asset('renewal/' . $this->shop_back_image);
        }
        return null;
    }
    
    public function getApplicationImageUrlAttribute()
    {
        if ($this->application_image) {
            return asset('renewal/' . $this->application_image);
        }
        return null;
    }
    
    public function getPreviousProfessionalLicenseImageUrlAttribute()
    {
        if ($this->previous_professional_license_image) {
            return asset('renewal/' . $this->previous_professional_license_image);
        }
        return null;
    }
    
    public function currency()
    {
        return $this->belongsTo(Currency::class,'currency_id');
    }
    
    public function getCurrencyNameAttribute()
    {
        return $this->currency ? $this->currency->name : null;
    }
    public function getCurrencyPriceAttribute()
    {
        return $this->currency ? $this->currency->price : null;
    }
    
    public function owner()
    {
        return $this->belongsTo(Customer::class,'owner_id');
    }
    
    public function super()
    {
        return $this->belongsTo(Customer::class,'supervisor_id');
    }
    
    public function getOwnerNameAttribute()
    {
        return $this->owner ? $this->owner->name : null;
    }
    
    public function getSupervisorNameAttribute()
    {
        return $this->super ? $this->super->name : null;
    }
    
    public function getOwnerIdentityNumberAttribute()
    {
        return $this->owner ? $this->owner->identity_number : null;
    }
    
    public function getOwnerIdentityNameAttribute()
    {
        return $this->owner ? $this->owner->identity_name : null;
    }
    
    public function panel_type()
    {
        return $this->belongsTo(PanelType::class,'panel_type_id');
    }
    
    public function getPanelTypeNameAttribute()
    {
        return $this->panel_type ? $this->panel_type->name : null;
    }
    
    
    public function status()
    {
        return $this->belongsTo(Status::class,'status_id');
    }
    
    public function getStatusNameAttribute()
    {
        
    
        return $this->status ? $this->status->name : null;
    }
    
    
    public function getDashboardStatusAttribute()
    {
        // الحالات المرتبطة بتاريخ الانتهاء
        if ($this->status_id >= 6 && $this->end_date) {
    
            return $this->end_date > now()
                ? ['label' => 'سارية',  'color' => '#34D399'] // أخضر
                : ['label' => 'منتهية', 'color' => '#DC3545']; // أحمر
        }
    
        // الحالات الثابتة
        return match ((int)$this->status_id) {
            1 => ['label' => 'قيد المراجعة', 'color' => '#F59E0B'], // برتقالي
            2 => ['label' => 'معتمد',       'color' => '#28A745'], // أخضر
            3 => ['label' => 'مرفوض',       'color' => '#EF4444'], // أحمر
            4 => ['label' => 'قيد التحصيل', 'color' => '#3B82F6'], // أزرق
            5 => ['label' => 'تم التحصيل', 'color'=>'#10B981'],
            default => [
                'label' => $this->status?->name ?? 'غير محدد',
                'color' => '#6B7280', // رمادي
            ],
        };
    }

    
    public function getDashboardStatusNameAttribute()
    {
        return $this->dashboard_status['label'];
    }
    
    public function getDashboardStatusColorAttribute()
    {
        return $this->dashboard_status['color'];
    }

    
    public function getDepartmentNameAttribute()
    {
        return match ((int)$this->status_id) {
            1 => 'المراجعة',
            2 => 'الادخال',
            3 => 'ألادخال',
            4 => 'المالية',
            5 => 'الادخال',
            default => null,
        };
}

    
    
    
    public function license_branch()
    {
        return $this->belongsTo(LicenseeBranch::class,'license_branch_id');
    }
    
    public function getDirectorateNameAttribute()
    {
        return $this->license_branch ? $this->license_branch->directorate_name : null;
    }
    
    public function getTypeNameAttribute()
    {
        return $this->license_branch ? $this->license_branch->type_name : null;
    }
    
    public function getDirectorateIdAttribute()
    {
        return $this->license_branch ? $this->license_branch->directorate_id : null;
    }
    
    public function getAddressAttribute()
    {
        return $this->license_branch ? $this->license_branch->address : null;
    }
    
    public function getLatAttribute()
    {
        return $this->license_branch ? $this->license_branch->lat : null;
    }
    
    public function getLangAttribute()
    {
        return $this->license_branch ? $this->license_branch->lang : null;
    }
    
    public function getNameAttribute()
    {
        return $this->license_branch ? $this->license_branch->name : null;
    }
    
    public function getActiveAttribute()
    {
        return $this->license_branch ? $this->license_branch->status : null;
    }
    
    public function getCustomerNameAttribute()
    {
        return $this->license_branch ? $this->license_branch->customer_name : null;
    }
    public function getCustomerIdAttribute()
    {
        return $this->license_branch ? $this->license_branch->customer_id : null;
    }
    
    public function getCustomerProfileImageUrlAttribute()
    {
        return $this->license_branch ? $this->license_branch->customer_profile_image_url : null;
    }
    
    public function getCustomerProfileImageBaseAttribute()
    {
        return $this->license_branch ? $this->license_branch->customer_profile_image_base : null;
    }
    
    public function getCustomerAddressAttribute()
    {
        return $this->license_branch ? $this->license_branch->customer_address : null;
    }
    
    public function getCategoryIdAttribute()
    {
        return $this->license_branch ? $this->license_branch->category_id : null;
    }
    
    public function getCustomerIdentityNumberAttribute()
    {
        return $this->license_branch ? $this->license_branch->customer_identity_number : null;
    }
    
    public function getCustomerPhoneAttribute()
    {
        return $this->license_branch ? $this->license_branch->customer_phone : null;
    }
    
    public function getCustomerTelAttribute()
    {
        return $this->license_branch ? $this->license_branch->customer_tel : null;
    }
    
    public function getCustomerIdentityNameAttribute()
    {
        return $this->license_branch ? $this->license_branch->customer_identity_name : null;
    }
    
    public function getCustomerIdentityStartDateAttribute()
    {
        return $this->license_branch ? $this->license_branch->customer_identity_start_date : null;
    }
    
    
    
    public function getCustomerIdentityEndDateAttribute()
    {
        return $this->license_branch ? $this->license_branch->customer_identity_end_date : null;
    }
    
    
    
    
    public function getCustomerCountryNameAttribute()
    {
        return $this->license_branch ? $this->license_branch->customer_country_name : null;
    }
    
     public function getCategoryNameAttribute()
    {
        return $this->license_branch ? $this->license_branch->category_name : null;
    }
    
     public function getcustomerLicenseIdAttribute()
    {
        return $this->license_branch ? $this->license_branch->license_id : null;
    }
    
     public function getBusinessNameAttribute()
    {
        return $this->license_branch ? $this->license_branch->business_name : null;
    }
    
     public function getCommercialRegistrationImageUrlAttribute()
    {
        return $this->license_branch ? $this->license_branch->commercial_registration_image_url : null;
    }
    
    public function getCommercialRegistrationImageAttribute()
    {
        return $this->license_branch ? $this->license_branch->commercial_registration_image : null;
    }
    
     public function getSubNameAttribute()
    {
        return $this->license_branch ? $this->license_branch->sub_name : null;
    }
    
    public function getSubCategoryIdAttribute()
    {
        return $this->license_branch ? $this->license_branch->sub_category_id : null;
    }
    
    
    public function rental_type()
   {
        return $this->belongsTo(RentalType::class,'rental_type_id');
   }
    
    public function getRentalNameAttribute()
    {
        return $this->rental_type ? $this->rental_type->name : null;
    }
    
   ///public function services()
   //{
      //  if($this->type === "ايقاف خدمة")
        //{
          //  return $this->hasMany(BranchRenewalServices::class, 'branch_renewal_id')->where('status',1);
        //}else{
            
            
         //   return $this->hasMany(BranchRenewalServices::class, 'branch_renewal_id')->where('status',0);
       // }
        
    //}
    public function services()
    {
        return $this->hasMany(BranchRenewalServices::class, 'branch_renewal_id');
    }
    
     public function getSubFakeIdAttribute()
    {
        return $this->license_branch ? $this->license_branch->sub_fake_id : null;
    }
    
     public function getRequestApplicationImageUrlAttribute()
    {
        return $this->license_branch ? $this->license_branch->request_application_image_url : null;
    }
    
    public function getPreviewLocationApplicationImageUrlAttribute()
    {
        return $this->license_branch ? $this->license_branch->preview_location_application_image_url : null;
    }
    
    public function getQualificationImageUrlAttribute()
    {
        return $this->license_branch ? $this->license_branch->qualification_image_url : null;
    }
    
    public function getSupervisorQualificationImageUrlAttribute()
    {
        return $this->super ? $this->super->supervisor_qualification_image_url : null;
    }
    
    public function getSupervisorRequestApplicationImageUrlAttribute()
    {
        return $this->license_branch ? $this->license_branch->request_application_image_url : null;
    }
    
    public function getSupervisorPreviewLocationApplicationImageUrlAttribute()
    {
        return $this->super ? $this->super->preview_location_application_image_url : null;
    }
    
   
    
    public function getSupervisorSupervisorQualificationImageUrlAttribute()
    {
        return $this->super ? $this->super->qualification_image_url : null;
    }
    
    public function getCustomerQualificationNameAttribute()
    {
        return $this->license_branch ? $this->license_branch->qualification_name : null;
    }
    
    public function getCustomerSpecializationNameAttribute()
    {
        return $this->license_branch ? $this->license_branch->specialization_name : null;
    }
    
    
    public function getQualificationIdAttribute()
    {
        return $this->license_branch ? $this->license_branch->qualification_id : null;
    }
    
    public function getSpecializationIdAttribute()
    {
        return $this->license_branch ? $this->license_branch->specialization_id : null;
    }
    
    
    public function getRequestApplicationImageAttribute()
    {
        return $this->license_branch ? $this->license_branch->request_application_image : null;
    }
    
    public function getPreviewLocationApplicationImageAttribute()
    {
        return $this->license_branch ? $this->license_branch->preview_location_application_image : null;
    }
    
    public function getQualificationImageAttribute()
    {
        return $this->license_branch ? $this->license_branch->qualification_image : null;
    }
    
    public function getSupervisorQualificationImageAttribute()
    {
        return $this->super ? $this->super->supervisor_qualification_image : null;
    }
    
    
    
    public function getSupervisorRequestApplicationImageAttribute()
    {
        return $this->super ? $this->super->request_application_image : null;
    }
    
    public function getSupervisorPreviewLocationApplicationImageAttribute()
    {
        return $this->super ? $this->super->preview_location_application_image : null;
    }
    
    
    public function getProfessionAttribute()
    {
        return $this->license_branch ? $this->license_branch->profession : null;
    }
    
   
    
    
    public function getGraduationDateAttribute()
    {
        return $this->license_branch ? $this->license_branch->graduation_date : null;
    }
    
    public function getGraduationCountryAttribute()
    {
        return $this->license_branch ? $this->license_branch->graduation_country : null;
    }
    
   
    
  
   
   public function getSupervisorProfessionAttribute()
    {
        return $this->super ? $this->super->profession : null;
    }
    
    
   
    
    public function getSupervisorGraduationDateAttribute()
    {
        return $this->super ? $this->super->graduation_date : null;
    }
    
   
    
    
    public function getSupervisorGraduationcountryAttribute()
    {
        return $this->super ? $this->super->graduation_country : null;
    }
    
    
    public function getSupervisorQualificationIdAttribute()
    {
        return $this->super ? $this->super->qualification_id : null;
    }
    
    public function getSupervisorSpecializationIdAttribute()
    {
        return $this->super ? $this->super->specialization_id : null;
    }
    
    
    public function getServicesTotalAttribute()
    {
        return $this->services->sum('price_value');
    }
    
    public function getExtraFeesAttribute()
    {
        // سعر نوع اللوحة حسب panel_type_id مباشرة لأنه نفس الـ id في جدول Price
        $panelFee = Price::find($this->panel_type_id)->value ?? 0;
    
    
        // سعر رسوم العوائق
        $obstacleMeterPrice = Price::where('name', 'رسوم عوائق')->value('value') ?? 0;
        $obstacleFee = $this->obstacle_space * $obstacleMeterPrice;
        
        
        $prints = Price::where('name', 'مطبوعات')->value('value') ?? 0;
    
        // الإجمالي
        return $panelFee + $prints + $obstacleFee;
    }
    
    public function getFeesDetailsAttribute()
    {
        // نوع اللوحة
        $panelFee = Price::find($this->panel_type_id)->value ?? 0;
    
        // رسوم العوائق
        $obstacleMeterPrice = Price::where('name', 'رسوم عوائق')->value('value') ?? 0;
        $obstacleFee = $this->obstacle_space * $obstacleMeterPrice;
    
        // مطبوعات
        $printsFee = Price::where('name', 'مطبوعات')->value('value') ?? 0;
        
        $mehenaTahseenPrice = Price::where('name', 'مهنة تحسين')->value('value') ?? 0;
        $mehenaTahseenTotal = $this->services->count() * $mehenaTahseenPrice;
    
        return [
            
            'panel_fee'     => $panelFee,
            'prints_fee'    => $printsFee,
            'obstacle_fee'  => $obstacleFee,
            'mehena_tahseenTotal' => $mehenaTahseenTotal,
            'total_services' => $this->services_total,
            'total_amount'  => $this->services_total + ($panelFee + $printsFee + $obstacleFee + $mehenaTahseenTotal),
        ];
    }


    public function getQualificationNameAttribute()
    {
        return $this->license_branch ? $this->license_branch->qualification_name : null;
    }
    
    
    public function getSpecializationNameAttribute()
    {
        return $this->license_branch ? $this->license_branch->specialization_name : null;
    }

    
    public function getSupervisorQualificationNameAttribute()
    {
        return $this->super ? $this->super->qualification_name : null;
    }
    
    public function getSupervisorSpecializationNameAttribute()
    {
        return $this->super ? $this->super->specialization_name : null;
    }
    
    
    public function service_type()
    {
        return $this->belongsTo(ServiceType::class,'service_type_id');
    }

    public function getServiceTypeNameAttribute()
    {
        return $this->service_type ? $this->service_type->name : null;
    }
    
    public function payment_type()
    {
        return $this->belongsTo(PaymentType::class, 'payment_type_id');
    }
    
    public function getPaymentTypeNameAttribute()
    {
        return $this->payment_type ? $this->payment_type->name : null;
    }
    
    
    public function payment_type_nzafa()
    {
        return $this->belongsTo(PaymentType::class, 'payment_type_id_nzafa');
    }
    
    public function getPaymentTypeNameNzafaAttribute()
    {
        return $this->payment_type_nzafa ? $this->payment_type_nzafa->name : null;
    }
    
    public function finance_user()
    {
        return $this->belongsTo(User::class, 'finance_user_id');
    }
    
    public function getFinanceUserNameAttribute()
    {
        return $this->finance_user ? $this->finance_user->name : null;
    }
    
    public function nzafa_user()
    {
        return $this->belongsTo(User::class, 'nzafa_user_id');
    }
    
    public function getNzafaUserNameAttribute()
    {
        return $this->nzafa_user ? $this->nzafa_user->name : null;
    
    }
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function getUserNameAttribute()
    {
        return $this->user ? $this->user->name : null;
    
    }
    
    
    
    
}
