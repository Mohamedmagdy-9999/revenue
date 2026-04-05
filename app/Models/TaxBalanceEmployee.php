<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxBalanceEmployee extends Model
{
    use HasFactory;
    
    protected $table = "tax_balance_employees";
    protected $guarded = [];
}
