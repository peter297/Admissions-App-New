<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Term extends Model
{
    //
    protected $fillable = ['academic_year_id','number','is_current'];

    public function academicYear(){
        return $this->belongsTo(AcademicYear::class);
    }

    

    
}
