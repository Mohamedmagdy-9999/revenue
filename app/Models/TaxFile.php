<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxFile extends Model
{
    use HasFactory;
    
    protected $table = "tax_files";
    protected $guarded = [];
    protected $hidden = ['customer'];
    
    protected $appends = ['customer_name'];
    
    public function customer()
    {
        return $this->belongsTo(Customer::class,'customer_id');
    }
    
    public function getCustomerNameAttribute()
    {
        return $this->customer ? $this->customer->name : null;
    }
}
