<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;
    

    protected $table = "categories";

    protected $guarded = [];

    public function subs()
    {
        return $this->hasMany(SubCategory::class,'category_id');
    }
}
