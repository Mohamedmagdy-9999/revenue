<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PanelType extends Model
{
    use HasFactory;
    protected $table = "panel_types";
    protected $guarded = [];
}
