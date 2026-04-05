<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    use HasFactory;
    
    protected $table = "resources";
    protected $guarded = [];
    protected $hidden = ['office','type'];
    protected $appends = ['office_name','type_name'];
    
    public function office()
    {
        return $this->belongsTo(Office::class,'office_id');    
    
    }
    
    public function getOfficeNameAttribute()
    {
        return $this->office ? $this->office->name : null;
    }
    
    public function type()
    {
        return $this->belongsTo(ResourceType::class,'resource_type_id');    
    
    }
    
    public function getTypeNameAttribute()
    {
        return $this->type ? $this->type->name : null;
    }
    
}
