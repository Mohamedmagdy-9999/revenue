<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchRenewalFinancePayment extends Model
{
    use HasFactory;
    
    protected $table = "branch_renewal_finance_payments";
    protected $guarded = [];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
