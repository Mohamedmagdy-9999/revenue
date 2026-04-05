<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchRenewalLog extends Model
{
    use HasFactory;
    
    protected $table = "branch_renewal_logs";
    protected $guarded = [];
    
    protected $hidden = ['user','department'];
    
    protected $appends = ['user_name','department_name'];
    

    
    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }
    
    public function getUserNameAttribute()
    {
        return $this->user ? $this->user->name : null;
    }
    
    public function department()
    {
        return $this->belongsTo(Department::class,'department_id');
    }
    
    public function getDepartmentNameAttribute()
    {
        return $this->department ? $this->department->name : null;
    }
    
    
    
    
}
