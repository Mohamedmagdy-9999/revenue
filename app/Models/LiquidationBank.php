<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiquidationBank extends Model
{
    use HasFactory;
    
    protected $table = "liquidation_bank";
    protected $guarded = [];
}
