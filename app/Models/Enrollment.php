<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Enrollment extends Model
{
    protected $fillable = [
        'learner_id', 'academic_year_id', 'class_id', 'branch_id',
        'status', 'status_changed_term_id', 'status_changed_at', 'status_note',
        'takes_lunch', 'transport_route_id', 'bus_id',
    ];

    protected $casts = [
        'takes_lunch' => 'boolean',
        'status_changed_at' => 'date',
    ];

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function transportRoute(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class);
    }

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'activity_enrollment');
    }
}
