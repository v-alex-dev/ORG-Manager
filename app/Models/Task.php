<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'service_id',
        'poj_title',
        'poj_description',
        'status',
        'has_duplicate',
        'reference_task_id',
        'reference_code',
    ];

    // Default value, so has_duplicate is also present in the JSON of a freshly created task
    protected $attributes = [
        'has_duplicate' => false,
    ];

    protected $casts = [
        'status'        => 'string',
        'has_duplicate' => 'boolean',
    ];

    public function orgInstance(): BelongsTo
    {
        return $this->belongsTo(OrgInstance::class, 'organization_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * The original task this task was duplicated from (null if it is not a copy).
     */
    public function referenceTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'reference_task_id');
    }
}
