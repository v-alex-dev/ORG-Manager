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
        'task_description',
        'status',
        'reference_code',
    ];

    protected $casts = [
        'status'=>'string',
    ];

    public function orgInstance():BelongsTo{
        return $this->belongsTo(OrgInstance::class, 'organization_id');
    }

    public function service():BelongsTo{
        return $this->belongsTo(Service::class);
    }
}
