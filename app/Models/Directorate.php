<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Directorate extends Model
{
    use HasFactory;
    protected $table = "directorates";

    protected $guarded = [];

     protected $hidden = ['governorate','created_at','updated_at'];

    protected $appends = ['governorate_name'];

    public function governorate()
    {
        return $this->belongsTo(Government::class,'governorate_id');
    }

    public function getGovernorateNameAttribute()
    {
        return $this->governorate ? $this->governorate->name : null;
    }
}
