<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerZakahBalanceLog extends Model
{
    use HasFactory;
    
    protected $table = "customer_zakah_balance_logs";
    protected $guarded = [];
    protected $hidden = ['user'];
    protected $appends = ['user_name'];
    
    public function user()
    {
        return $this->belongsTo(User::class,'user_id');    
    
    }
    
    public function getUserNameAttribute()
    {
        return $this->user ? $this->user->name : null;
    
    }
    
}
