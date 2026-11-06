<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Applicant extends Model
{
    protected $fillable = [
        'academic_year_id', 'branch_id', 'grade_level_id',
        'full_name', 'assessment_number', 'date_of_birth', 'gender',
        'parent_name', 'parent_type', 'parent_phone',
        'mpesa_code', 'result', 'notes', 'learner_id', 'promoted_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'promoted_at' => 'datetime',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    /** Users tied to a branch only see that branch's applicants. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->branch_id ? $query->where('branch_id', $user->branch_id) : $query;
    }

    /**
     * Shared by the page and the export so both always show the same applicants.
     * "passed" means passed and not yet admitted; "admitted" means already promoted.
     */
    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['academic_year_id'] ?? null, fn ($q, $v) => $q->where('academic_year_id', $v))
            ->when($f['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($f['grade_level_id'] ?? null, fn ($q, $v) => $q->where('grade_level_id', $v))
            ->when(($f['result'] ?? 'all') !== 'all', fn ($q) => match ($f['result']) {
                'pending' => $q->where('result', 'pending'),
                'failed' => $q->where('result', 'failed'),
                'passed' => $q->where('result', 'passed')->whereNull('promoted_at'),
                'admitted' => $q->whereNotNull('promoted_at'),
                default => $q,
            });
    }
}
