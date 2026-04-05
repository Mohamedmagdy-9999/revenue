<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZakahNumber extends Model
{
    use HasFactory;
    
    protected $table = "zakah_numbers";
    protected $guarded = [];
}
