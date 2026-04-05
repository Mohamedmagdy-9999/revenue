<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerZakahBalanceEmployee extends Model
{
    use HasFactory;
    
    protected $table = "customer_zakah_balance_employees";
    protected $guarded = [];
}
