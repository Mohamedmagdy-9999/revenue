<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerZakahBalance extends Model
{
    use HasFactory;
    
    protected $table = "customer_zakah_balance";
    protected $guarded = [];
    protected $hidden = ['customer','zakah_type','status','declaration','user'];
    protected $appends = ['customer_name','customer_profile_image_url','zakah_type_name','ownership_image_url','electric_image_url','manual_image_url','other_image_url','status_name','declaration_name','directorate_name','declaration_type','tax_image_url','calculation_image_url','payment_statement_image_url','discount_percentage_image_url','payment_receipt_image_url','beneficiary_image_url','check_image_url','supply_voucher_image_url','customer_identity_number','customer_identity_name','customer_country_name','settlement_result',
      
        'final_balance_status_text',
        'record_status_text','customer_address','advance_payment_statement_image_url','annual_zakat_declaration_image_url','last_payment_receipt_image_url','central_audit_authority_approval_image_url','detailed_income_statement_image_url','detailed_trial_balance_image_url','detailed_final_accounts_report_image_url','salaries_and_entitlements_report_image_url','cash_and_inventory_statement_image_url','creditors_report_image_url','debtors_report_image_url',
        'rental_owner_contract_image_url','detailed_revenue_report_image_url','detailed_expenses_report_image_url','clients_and_contracts_report_image_url','income_data_image_url',
        'sale_contract_image_url','prev_rental_contract_image_url','rent_exclusion_image_url','dependents_report_image_url','user_name',
            'amount','department_name','customer_zakah_number_id','total','dashboard_status_name','dashboard_status_color'
        ];
        
    public function getDepartmentNameAttribute()
    {
        return match ((int)$this->zakah_status_id) {
            1 => 'المراجعة',
            2 => 'الادخال',
            3 => 'ألادخال',
            4 => 'المالية',
            5 => 'الادخال',
            6 => 'الادخال',
            default => null,
        };
    }
    
    public function getDashboardStatusAttribute()
    {
        
    
        return match ((int)$this->zakah_status_id) {
            1 => ['label' => 'قيد المراجعة', 'color' => '#F59E0B'], // برتقالي
            2 => ['label' => 'معتمد',       'color' => '#28A745'], // أخضر
            3 => ['label' => 'مرفوض',       'color' => '#EF4444'], // أحمر
             4 => ['label' => 'اعتماد نهائي',       'color' => '#28A745'], // أخضر
            5 => ['label' => 'رفض نهائي',       'color' => '#EF4444'], //
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
    
    
    public function calculateFees($collection = null)
    {
        $fees = [];
        $total = 0;
    
        /* ✅ الحالة الأولى: مبلغ تحت الحساب */
        if ($this->zakah_declaration_id === null) {
    
            $amount = $this->value ?? 0;
    
            $fees = [
                ['key' => 'amrta7selt7tel7sab', 'label' => 'مبلغ تحت الحساب', 'value' => $amount],
                ['key' => 'elegmaly', 'label' => 'الاجمالي', 'value' => $amount],
            ];
    
            $total = $amount;
        }
    
        /* ✅ مباني */
        elseif ($this->zakah_type_id == 4 && $this->zakah_declaration_id == 7) {
    
            $khasm = $collection
                ? $collection->where('zakah_type_id', 4)->whereNull('zakah_declaration_id')->sum('value')
                : 0;
    
            $elzakah  = $this->buildings()->sum('zakat_value');
            $el8ramat = 0;
            $discount = $this->discount ?? 0;
    
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
        elseif ($this->zakah_type_id == 4 && $this->zakah_declaration_id == 10) {
    
            $khasm = $collection
                ? $collection->where('zakah_type_id', 4)->whereNull('zakah_declaration_id')->sum('value')
                : 0;
    
            $elzakah  = $this->employees()->sum('zakat_amount') * 0.025;
            $el8ramat = 0;
            $discount = $this->discount ?? 0;
    
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
        elseif ($this->zakah_declaration_id !== null) {

        // ✅ خصم تحت الحساب
        $khasm = $collection
            ? $collection->whereNull('zakah_declaration_id')->sum('value')
            : 0;
    
        // ✅ حساب الزكاة بأمان
        $revenue  = $this->total_revenue ?? 0;
        $expenses = $this->total_expenses ?? 0;
    
        $elzakah = $this->value ?? ($revenue - $expenses);
    
        // ✅ الغرامات والخصم
        $el8ramat = 0;
        $discount = $this->discount ?? 0;
    
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
    
    public function getTotalAttribute()
    {
        return $this->calculateFees()['total'];
    }


    
    public function getAmountAttribute()
    {
        return $this->calculateFees()['total'];
    }
    
    public function declaration()
    {
        return $this->belongsTo(ZakahDeclaration::class,'zakah_declaration_id');
    }
    
    public function getDeclarationNameAttribute()
    {
        return $this->declaration ? $this->declaration->name : "رصيد تحت الحساب";
    }
    
  
    public function getCustomerZakahNumberIdAttribute()
    {
        return $this->customer ? $this->customer->zakah_number_id : null;
    }
    
    public function getDeclarationTypeAttribute()
    {
        $items = [
            1  => "zakat_internal_public_mixed_declaration",
            2  => "zakat_hidden_declaration_private_sector_form",
            3  => "zakat_small_taxpayers_declaration_form",
            4  => "zakat_middle_inner_individuals_declaration_form",
            5  => "zakat_income_declaration_small_taxpayers_form",
            6  => "zakat_middle_income_individuals_declaration_form",
            7  => "zakat_property_rental_declaration_form",
            8  => "zakat_property_sale_declaration_form",
            9  => "zakat_property_rental_vehicle_declaration_form",
            10 => "zakat_al_fitr_declaration_form",
        ];
    
        $id = optional($this->declaration)->id; // لو مفيش declaration يبقى null
    
        return $items[$id] ?? "zakat_payment_collection_management";
    }
    
    
    public function customer()
    {
        return $this->belongsTo(Customer::class,'customer_id');
    }
    
    public function getCustomerNameAttribute()
    {
        return $this->customer ? $this->customer->name : null;
    }
    
    public function getCustomerIdentityNumberAttribute()
    {
        return $this->customer ? $this->customer->identity_number : null;
    }
    
    public function getCustomerIdentityNameAttribute()
    {
        return $this->customer ? $this->customer->customer_identity_name : null;
    }
    
    public function getCustomerCountryNameAttribute()
    {
        return $this->customer ? $this->customer->customer_country_name : null;
    }
    
    public function getCustomerAddressAttribute()
    {
        return $this->customer ? $this->customer->address : null;
    }
    
    public function getCustomerProfileImageUrlAttribute()
    {
        return $this->customer ? $this->customer->profile_image_url : null;
    }
    
    
    public function status()
    {
        return $this->belongsTo(ZakahStatus::class,'zakah_status_id');
    }
    
    public function getStatusNameAttribute()
    {
        return $this->status ? $this->status->name : null;
    }
    
    
    public function zakah_type()
    {
        return $this->belongsTo(ZakahType::class,'zakah_type_id');
    }
    
    public function getZakahTypeNameAttribute()
    {
        return $this->zakah_type ? $this->zakah_type->name : null;
    }
    
    public function getOwnershipImageUrlAttribute()
    {
        if ($this->ownership_image	) {
            return asset('zakah/' . $this->ownership_image);
        }
        return null;
    }
    
    public function getElectricImageUrlAttribute()
    {
        if ($this->electric_image	) {
            return asset('zakah/' . $this->electric_image);
        }
        return null;
    }
    
    public function getManualImageUrlAttribute()
    {
        if ($this->manual_image	) {
            return asset('zakah/' . $this->manual_image);
        }
        return null;
    }
    
    public function getOtherImageUrlAttribute()
    {
        if ($this->other_image	) {
            return asset('zakah/' . $this->other_image);
        }
        return null;
    }
    
    
    
    
    
    
    public function getAdvancepaymentstatementImageUrlAttribute()
    {
        if ($this->advance_payment_statement_image	) {
            return asset('zakah/' . $this->advance_payment_statement_image);
        }
        return null;
    }
    
    public function getAnnualZakatDeclarationImageUrlAttribute()
    {
        if ($this->annual_zakat_declaration_image	) {
            return asset('zakah/' . $this->annual_zakat_declaration_image);
        }
        return null;
    }
    
    public function getLastPaymentReceiptImageUrlAttribute()
    {
        if ($this->last_payment_receipt_image	) {
            return asset('zakah/' . $this->last_payment_receipt_image);
        }
        return null;
    }
    
    public function getCentralAuditAuthorityApprovalImageUrlAttribute()
    {
        if ($this->central_audit_authority_approval_image	) {
            return asset('zakah/' . $this->central_audit_authority_approval_image);
        }
        return null;
    }
    
    
    public function getDetailedIncomeStatementImageUrlAttribute()
    {
        if ($this->detailed_income_statement_image	) {
            return asset('zakah/' . $this->detailed_income_statement_image);
        }
        return null;
    }
    
    public function getDetailedTrialBalanceImageUrlAttribute()
    {
        if ($this->detailed_trial_balance_image	) {
            return asset('zakah/' . $this->detailed_trial_balance_image);
        }
        return null;
    }
    
    public function getDetailedFinalAccountsReportImageUrlAttribute()
    {
        if ($this->detailed_final_accounts_report_image	) {
            return asset('zakah/' . $this->detailed_final_accounts_report_image);
        }
        return null;
    }
    
    
    public function getSalariesAndEntitlementsReportImageUrlAttribute()
    {
        if ($this->salaries_and_entitlements_report_image	) {
            return asset('zakah/' . $this->salaries_and_entitlements_report_image);
        }
        return null;
    }
    
    public function getCashAndInventoryStatementImageUrlAttribute()
    {
        if ($this->cash_and_inventory_statement_image	) {
            return asset('zakah/' . $this->cash_and_inventory_statement_image);
        }
        return null;
    }
    
    
    public function getCreditorsReportImageUrlAttribute()
    {
        if ($this->creditors_report_image	) {
            return asset('zakah/' . $this->creditors_report_image);
        }
        return null;
    }
    
    public function getDebtorsReportImageUrlAttribute()
    {
        if ($this->debtors_report_image	) {
            return asset('zakah/' . $this->debtors_report_image);
        }
        return null;
    }
    
    public function getRentalOwnerContractImageUrlAttribute()
    {
        if ($this->rental_owner_contract_image	) {
            return asset('zakah/' . $this->rental_owner_contract_image);
        }
        return null;
    }
    
    public function getDetailedRevenueReportImageUrlAttribute()
    {
        if ($this->detailed_revenue_report_image	) {
            return asset('zakah/' . $this->detailed_revenue_report_image);
        }
        return null;
    }
    
    public function getDetailedExpensesReportImageUrlAttribute()
    {
        if ($this->detailed_expenses_report_image	) {
            return asset('zakah/' . $this->detailed_expenses_report_image);
        }
        return null;
    }
    
    public function getClientsAndContractsReportImageUrlAttribute()
    {
        if ($this->clients_and_contracts_report_image	) {
            return asset('zakah/' . $this->clients_and_contracts_report_image);
        }
        return null;
    }
    
    public function getIncomeDataImageUrlAttribute()
    {
        if ($this->income_data_image	) {
            return asset('zakah/' . $this->income_data_image);
        }
        return null;
    }
    
    
    public function getSaleContractImageUrlAttribute()
    {
        if ($this->sale_contract_image	) {
            return asset('zakah/' . $this->sale_contract_image);
        }
        return null;
    }
    
    public function getPrevRentalContractImageUrlAttribute()
    {
        if ($this->prev_rental_contract_image	) {
            return asset('zakah/' . $this->prev_rental_contract_image);
        }
        return null;
    }
    
    
    public function getRentExclusionImageUrlAttribute()
    {
        if ($this->rent_exclusion_image	) {
            return asset('zakah/' . $this->rent_exclusion_image);
        }
        return null;
    }
    
    public function getDependentsReportImageUrlAttribute()
    {
        if ($this->dependents_report_image	) {
            return asset('zakah/' . $this->dependents_report_image);
        }
        return null;
    }
 
    
    
    
    
    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }
    
    public function getDirectorateNameAttribute()
    {
        return $this->user ? $this->user->directorate_name : null;
    }
    
    public function getUserNameAttribute()
    {
        return $this->user ? $this->user->name : null;
    }
    
  
    
    
    
    
    
    public function getPaymentReceiptImageUrlAttribute()
    {
        if ($this->payment_receipt_image) {
            return asset('zakah/' . $this->payment_receipt_image);
        }
        return null;
    }
    
    public function getDiscountPercentageImageUrlAttribute()
    {
        if ($this->discount_percentage_image	) {
            return asset('zakah/' . $this->discount_percentage_image);
        }
        return null;
    }
    
    public function getPaymentStatementImageUrlAttribute()
    {
        if ($this->payment_statement_image	) {
            return asset('zakah/' . $this->payment_statement_image);
        }
        return null;
    }
    
    public function getCalculationImageUrlAttribute()
    {
        if ($this->calculation_image	) {
            return asset('zakah/' . $this->calculation_image);
        }
        return null;
    }
    
    
    public function getTaxImageUrlAttribute()
    {
        if ($this->tax_image	) {
            return asset('zakah/' . $this->tax_image);
        }
        return null;
    }
    
    public function getBeneficiaryImageUrlAttribute()
    {
        if ($this->beneficiary_image	) {
            return asset('zakah/' . $this->beneficiary_image);
        }
        return null;
    }
    
    public function getCheckImageUrlAttribute()
    {
        if ($this->check_image	) {
            return asset('zakah/' . $this->check_image);
        }
        return null;
    }
    
    public function getSupplyVoucherImageUrlAttribute()
    {
        if ($this->supply_voucher_image	) {
            return asset('zakah/' . $this->supply_voucher_image);
        }
        return null;
    }
    
     public function getSettlementResultAttribute()
    {
        if (!isset($this->fees)) {
            return 0;
        }

        $total = collect($this->fees)->firstWhere('key', 'elegmaly');

        return $total['value'] ?? 0;
    }

    /* 🔹 الالتزام الضريبي */
    public function getTaxCommitmentTextAttribute()
    {
        return $this->settlement_result == 0
            ? 'ملتزم'
            : 'غير ملتزم';
    }

    /* 🔹 حالة الرصيد */
    public function getFinalBalanceStatusTextAttribute()
    {
        return $this->settlement_result == 0
            ? 'سليم'
            : 'غير سليم';
    }

    /* 🔹 حالة القيد */
    public function getRecordStatusTextAttribute()
    {
        return match ($this->status_id) {
            1 => 'قيد التنفيذ',
            2 => 'تم التنفيذ',
            3 => 'قيد المراجعة',
            default => '-',
        };
    }
    
    public function employees()
    {
            return $this->hasMany(CustomerZakahBalanceEmployee::class,'customer_zakah_balance_id');
    }
    
    public function buildings()
    {
            return $this->hasMany(CustomerZakahBuilding::class,'customer_zakah_balance_id');
    }
    
}
