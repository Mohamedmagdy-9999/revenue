<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $guarded = [];
    
    protected $appends = ['department_name','image_url','directorate_name'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        
        'department',
       
        'directorate',
       
    ];

    /**
     * Relations
     */
     
     public function getJWTIdentifier()
    {
        return $this->getKey(); // عادة user_id
    }

    public function getJWTCustomClaims()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'department_id' => $this->department_id,
            'department_name' => $this->department ? $this->department->name : null,
            'role' => $this->role,
            'image' => $this->image_url,
            'directorate_id' => $this->directorate_id,
            'directorate_name' => $this->directorate ? $this->directorate->name : null,
            
        ];
    }
    
    
    public function directorate()
    {
        return $this->belongsTo(Directorate::class,'directorate_id');
    }

    public function getDirectorateNameAttribute()
    {
        return $this->directorate ? $this->directorate->name : null;
    }
    
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Accessors
     */
    public function getDepartmentNameAttribute()
    {
        return $this->department ? $this->department->name : null;
    }
    
    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset('users/' . $this->image);
        }
        return null;
    }
}
