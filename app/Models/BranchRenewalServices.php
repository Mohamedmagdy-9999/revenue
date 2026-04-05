<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ServicePrice;

class BranchRenewalServices extends Model
{
    use HasFactory;
    
    protected $table = "branch_renewal_services";
    protected $guarded = [];
    protected $appends = ['price_value'];
    protected $hidden = ['branchRenewal'];
    
    
   public function branchRenewal()
{
    return $this->belongsTo(BranchRenewal::class, 'branch_renewal_id');
}

public function service()
{
    return $this->belongsTo(Service::class, 'service_id');
}
    
    public function getPriceValueAttribute()
    {
        $serviceTypeId = $this->branchRenewal->service_type_id ?? null;
    
        if (!$serviceTypeId) {
            return null;
        }
    
        return ServicePrice::where('service_id', $this->service_id)
            ->where('service_type_id', $serviceTypeId)
            ->value('price');
    }

}
