<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicePrice extends Model
{
    use HasFactory;
    
    protected $table = "service_prices";
    protected $guarded = [];
    protected $appends = ['type_name'];
    
    public function type()
    {
        return $this->belongsTo(ServiceType::class, 'service_type_id');
    }
    
    public function getTypeNameAttribute()
    {
        // لو الـ relation موجود، رجع الاسم، وإلا null
        return $this->type ? $this->type->name : null;
    }
    
}
