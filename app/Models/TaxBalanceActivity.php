<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxBalanceActivity extends Model
{
    use HasFactory;
    
    protected $table = "tax_balance_activities";
    protected $guarded = [];
    protected $hidden = ['directorate'];
    
    protected $appends = ['directorate_name'];
    
    public function directorate()
    {
        return $this->belongsTo(Directorate::class , 'directorate_id');
    }
    
    public function getDirectorateNameAttribute()
    {
        return $this->directorate ? $this->directorate->name : null;
    }
}
