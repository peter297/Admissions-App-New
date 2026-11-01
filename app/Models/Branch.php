<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
   protected $fillable = ['name','code','final_grade_level_id'];

   public function classRooms()
   {
       return $this->hasMany(ClassRoom::class);
   }

   public function gradeLevel(){
        return $this->belongsTo(GradeLevel::class, 'final_grade_level_id');
   }
}
