<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Declaration extends Model
{
    use HasFactory;
    
    protected $table = "declarations";
    protected $guarded = [];
    protected $hidden = ['tax_type'];
    protected $appends = ['tax_type_name'];
    
    public function tax_type()
    {
        return $this->belongsTo(TaxType::class,'tax_type_id');
    }
    
    
    public function getTaxTypeNameAttribute()
    {
        return $this->tax_type ? $this->tax_type->name : null;
    }
}
