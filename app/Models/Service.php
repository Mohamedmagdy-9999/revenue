<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ServicePrice;
class Service extends Model
{
    use HasFactory;
    
        protected $table = "services";

        protected $guarded = [];
        
        protected $hidden = ['category'];
        
        protected $appends = ['category_name','price_value'];
        
        public function category()
        {
            return $this->belongsTo(Category::class,'category_id');
        }
        
        public function getCategoryNameAttribute()
        {
            return $this->category ? $this->category->name : null;
        }
        
        public function prices()
        {
            return $this->hasMany(ServicePrice::class , 'service_id');
        }
        
      public function getPriceValueAttribute()
    {
        $serviceTypeId = request('service_type_id'); // يجي من API
    
        if (!$serviceTypeId) {
            return null;
        }
    
        return $this->prices
            ->where('service_type_id', $serviceTypeId)
            ->first()
            ->price ?? null;
    }

}
