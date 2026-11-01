<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassRoom extends Model
{
    protected $table = 'classes';

    protected $fillable = ['branch_id', 'grade_level_id', 'stream_id'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'class_id');
    }

    /** "Grade 1 Blue" */
    public function getNameAttribute(): string
    {
        return $this->gradeLevel->name.' '.$this->stream->name;
    }

    /**
     * The single place where branch restriction is applied to classes.
     * Users with a branch_id only see that branch; null means all branches.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->branch_id
            ? $query->where('branch_id', $user->branch_id)
            : $query;
    }
}
