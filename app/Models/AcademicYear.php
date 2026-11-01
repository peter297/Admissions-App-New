<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    //
    protected $fillable = ['name','is_current'];

    protected $casts = [
        'is_current' => 'boolean',
    ];

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function terms(){
        return $this->hasMany(Term::class);
    }



}
