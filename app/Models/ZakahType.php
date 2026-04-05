<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZakahType extends Model
{
    use HasFactory;
    
    protected $table = "zakah_types";
    protected $guarded = [];
}
