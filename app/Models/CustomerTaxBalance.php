<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
class CustomerTaxBalance extends Model
{
    use HasFactory;
    
    protected $table = "customer_tax_balance";
    protected $guarded = [];
    protected $hidden = ['customer','tax_type','status','declaration','user'];
    protected $appends = ['customer_name','customer_profile_image_url','tax_type_name','ownership_image_url','electric_image_url','manual_image_url','other_image_url','status_name','declaration_name','directorate_name','declaration_type','tax_image_url','calculation_image_url','payment_statement_image_url','discount_percentage_image_url','payment_receipt_image_url','rent_exclusion_image_url','rent_tax_image_url','beneficiary_image_url','check_image_url','supply_voucher_image_url','customer_identity_number','customer_identity_name','customer_country_name','settlement_result',
        'tax_commitment_text',
        'final_balance_status_text',
        'record_status_text','customer_address','customer_tax_file_id','total_amount','department_name','dashboard_status_name','dashboard_status_color','customer_identity_start_date', 'customer_identity_end_date','user_name'];
    
    public function getDepartmentNameAttribute()
    {
        return match ((int)$this->tax_status_id) {
            1 => 'المراجعة',
            2 => 'الادخال',
            3 => 'ألادخال',
            4 => 'المالية',
            5 => 'الادخال',
            default => null,
        };
    }
    
    public function getDashboardStatusAttribute()
    {
        
    
        return match ((int)$this->tax_status_id) {
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
    
     public function calculateFees($collection = null)
    {
        $fees = [];

        /* ✅ مبلغ تحت الحساب */
        if ($this->declaration_id === null) {

            $value = $this->value;

            $fees = [
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
        elseif ($this->declaration_id == 5 && $this->tax_type_id == 3) {

            $khasm = $collection
                ? $collection->where('tax_type_id', 3)->whereNull('declaration_id')->sum('value')
                : 0;

            $eldreba = $this->employees()->sum('total');

            $fees = [
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
        elseif ($this->declaration_id == 2 && $this->tax_type_id == 2) {

            $khasm = $collection
                ? $collection->where('tax_type_id', 2)->whereNull('declaration_id')->sum('value')
                : 0;

            $eldreba = $this->total;

            $fees = [
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
        elseif ($this->declaration_id == 1 && $this->tax_type_id == 1) {

            $khasm = $collection
                ? $collection->where('tax_type_id', 1)->whereNull('declaration_id')->sum('value')
                : 0;

            $eldreba = $this->buildings()->sum('tax_value');
            $el8ramat = 0;

            $dueDate = Carbon::create($this->year, 6, 30)->addYear();
            $paymentDate = $this->created_at
                ? Carbon::parse($this->created_at)
                : now();

            if ($paymentDate->greaterThan($dueDate)) {
                $months = $dueDate->diffInMonths($paymentDate);
                $el8ramat = $eldreba * 0.02 * $months;
            }

            $fees = [
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

        return $fees;
    }

    /*
    |--------------------------------------------------------------------------
    | حساب الإجمالي فقط
    |--------------------------------------------------------------------------
    */
    public function calculateTotal($collection = null)
    {
        $fees = $this->calculateFees($collection);

        $total = collect($fees)->firstWhere('key', 'elegmaly');

        return $total ? $total['value'] : 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor يرجع الإجمالي في الـ API
    |--------------------------------------------------------------------------
    */
    public function getTotalAmountAttribute()
    {
        return $this->calculateTotal();
    }

    
    public function declaration()
    {
        return $this->belongsTo(Declaration::class,'declaration_id');
    }
    
    public function getDeclarationNameAttribute()
    {
        return $this->declaration ? $this->declaration->name : "رصيد تحت الحساب";
    }
    
    public function getDeclarationTypeAttribute()
    {
        $types = [
            1 => "annual-income-declaration-management",
            2 => "annual-small-business-income-management",
            3 => "annual-medium-business-income-management",
            4 => "annual-high-income-declaration-management",
            5 => "annual-payroll-income-management",
        ];
    
        $id = $this->declaration->id ?? null;
    
        return $types[$id] ?? "payment-collection-management";
    }
    
    
    public function customer()
    {
        return $this->belongsTo(Customer::class,'customer_id');
    }
    
    public function getCustomerNameAttribute()
    {
        return $this->customer ? $this->customer->name : null;
    }
    
   
    public function getCustomerTaxFileIdAttribute()
    {
        return $this->customer ? $this->customer->tax_file_id : null;
    }
    
    
    public function getCustomerIdentityNumberAttribute()
    {
        return $this->customer ? $this->customer->identity_number : null;
    }
    
    public function getCustomerIdentityNameAttribute()
    {
        return $this->customer ? $this->customer->identity_name : null;
    }
    
    public function getCustomerCountryNameAttribute()
    {
        return $this->customer ? $this->customer->country_name : null;
    }
    
    public function getCustomerIdentityStartDateAttribute()
    {
        return $this->customer ? $this->customer->identity_start_date : null;
    }
    
    
    public function getCustomerIdentityEndDateAttribute()
    {
        return $this->customer ? $this->customer->identity_end_date : null;
    }
    
    
    
    public function getCustomerProfileImageUrlAttribute()
    {
        return $this->customer ? $this->customer->profile_image_url : null;
    }
    
    public function getCustomerAddressAttribute()
    {
        return $this->customer ? $this->customer->address : null;
    }
    
    
    public function status()
    {
        return $this->belongsTo(TaxStatus::class,'tax_status_id');
    }
    
    public function getStatusNameAttribute()
    {
        return $this->status ? $this->status->name : null;
    }
    
    
    public function tax_type()
    {
        return $this->belongsTo(TaxType::class,'tax_type_id');
    }
    
    public function getTaxTypeNameAttribute()
    {
        return $this->tax_type ? $this->tax_type->name : null;
    }
    
    public function getOwnershipImageUrlAttribute()
    {
        if ($this->ownership_image	) {
            return asset('balance/' . $this->ownership_image);
        }
        return null;
    }
    
    public function getElectricImageUrlAttribute()
    {
        if ($this->electric_image	) {
            return asset('balance/' . $this->electric_image);
        }
        return null;
    }
    
    public function getManualImageUrlAttribute()
    {
        if ($this->manual_image	) {
            return asset('balance/' . $this->manual_image);
        }
        return null;
    }
    
    public function getOtherImageUrlAttribute()
    {
        if ($this->other_image	) {
            return asset('balance/' . $this->other_image);
        }
        return null;
    }
    
    
    
    public function getRentExclusionImageUrlAttribute()
    {
        if ($this->rent_exclusion_image	) {
            return asset('balance/' . $this->rent_exclusion_image);
        }
        return null;
    }
    
    public function getRentTaxImageUrlAttribute()
    {
        if ($this->rent_tax_image	) {
            return asset('balance/' . $this->rent_tax_image);
        }
        return null;
    }
    
    
    
    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }
    
    public function getUserNameAttribute()
    {
        return $this->user ? $this->user->name : null;
    }
    
    
    
    public function getDirectorateNameAttribute()
    {
        return $this->user ? $this->user->directorate_name : null;
    }
    
    public function employees()
    {
            return $this->hasMany(TaxBalanceEmployee::class,'customer_tax_balance_id');
    }
    
    
    public function activities()
    {
            return $this->hasMany(TaxBalanceActivity::class,'customer_tax_balance_id');
    }
    
    
    public function buildings()
    {
            return $this->hasMany(TaxBalanceBulinding::class,'customer_tax_balance_id');
    }
    
    
    
    
    
    public function getPaymentReceiptImageUrlAttribute()
    {
        if ($this->payment_receipt_image) {
            return asset('balance/' . $this->payment_receipt_image);
        }
        return null;
    }
    
    public function getDiscountPercentageImageUrlAttribute()
    {
        if ($this->discount_percentage_image	) {
            return asset('balance/' . $this->discount_percentage_image);
        }
        return null;
    }
    
    public function getPaymentStatementImageUrlAttribute()
    {
        if ($this->payment_statement_image	) {
            return asset('balance/' . $this->payment_statement_image);
        }
        return null;
    }
    
    public function getCalculationImageUrlAttribute()
    {
        if ($this->calculation_image	) {
            return asset('balance/' . $this->calculation_image);
        }
        return null;
    }
    
    
    public function getTaxImageUrlAttribute()
    {
        if ($this->tax_image	) {
            return asset('balance/' . $this->tax_image);
        }
        return null;
    }
    
    public function getBeneficiaryImageUrlAttribute()
    {
        if ($this->beneficiary_image	) {
            return asset('balance/' . $this->beneficiary_image);
        }
        return null;
    }
    
    public function getCheckImageUrlAttribute()
    {
        if ($this->check_image	) {
            return asset('balance/' . $this->check_image);
        }
        return null;
    }
    
    public function getSupplyVoucherImageUrlAttribute()
    {
        if ($this->supply_voucher_image	) {
            return asset('balance/' . $this->supply_voucher_image);
        }
        return null;
    }
    
    public function getSettlementResultAttribute()
    {
        if (empty($this->fees) || !is_array($this->fees)) {
            return 0;
        }
    
        return collect($this->fees)
            ->firstWhere('key', 'total')['value'] ?? 0;
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
    
}
