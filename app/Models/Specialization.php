<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Specialization extends Model
{
    use HasFactory;
    
    protected $table = "specializations";
    protected $guarded = [];
    protected $hidden = ['qualification'];
    protected $appends = ['qualification_name'];
    
    public function qualification()
    {
        return $this->belongsTo(Qualification::class, 'qualifications_id');
    }
    
    public function getQualificationNameAttribute()
    {
        return $this->qualification ? $this->qualification->name : null;
    }
}
