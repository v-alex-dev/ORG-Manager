<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = [
        'org_instance_id',
        'service_id',
        'poj_title',
        'task_description',
        'status',
        'reference_code',
    ];

    protected $casts = [
        'status'=>'string',
    ];

    public function orgInstance():BelongsTo{
        return $this->belongsTo(OrgInstance::class);
    }

    public function service():BelongsTo{
        return $this->belongsTo(Service::class);
    }
}
