<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerTaxBalanceLog extends Model
{
    use HasFactory;
    
    protected $table = "customer_tax_balance_logs";
    protected $guarded = [];
}
