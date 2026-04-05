<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchRenewalNzafaPayment extends Model
{
    use HasFactory;
    
    protected $table = "branch_renewal_nzafa_payments";
    protected $guarded = [];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
