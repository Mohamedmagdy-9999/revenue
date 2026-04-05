<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZakahDeclaration extends Model
{
    use HasFactory;
    
    protected $table = "zakah_declarations";
    protected $guarded = [];
    protected $hidden = ['tax_type'];
    protected $appends = ['zakah_type_name','declaration_path'];
    
    public function zakah_type()
    {
        return $this->belongsTo(ZakahType::class,'zakah_type_id');
    }
    
    
    public function getZakahTypeNameAttribute()
    {
        return $this->zakah_type ? $this->zakah_type->name : null;
    }
    
    public function getDeclarationPathAttribute()
    {
        $types = [
            1  => "zakat_internal_public_mixed_declaration",
            2  => "zakat_hidden_declaration_private_sector_form",
            3  => "zakat_small_taxpayers_declaration_form",
            4  => "zakat_middle_inner_individuals_declaration_form",
            5  => "zakat_income_declaration_small_taxpayers_form",
            6  => "zakat_middle_income_individuals_declaration_form",
            7  => "zakat_property_rental_declaration_form",
            8  => "zakat_property_sale_declaration_form",
            9  => "zakat_property_rental_vehicle_declaration_form",
            10 => "zakat_al_fitr_declaration_form",
        ];
    
        return $types[$this->id]
            ?? "zakat_payment_collection_management";
    }


}
