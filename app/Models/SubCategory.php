<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubCategory extends Model
{
    use HasFactory;
    protected $table = "sub_categories";
    protected $guarded = [];
    protected $hidden = ['category','created_at','updated_at'];

    protected $appends = ['category_name'];

    public function category()
    {
        return $this->belongsTo(Category::class,'category_id');
    }

    public function getCategoryNameAttribute()
    {
        return $this->category ? $this->category->name : null;
    }
}
