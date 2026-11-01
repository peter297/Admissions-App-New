<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Learner extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'full_name', 'assessment_number', 'admission_number', 'gender',
        'date_of_birth', 'birth_certificate_number', 'nationality',
        'kemis_code', 'contact_person',
    ];

    protected $casts = ['date_of_birth' => 'date'];

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
