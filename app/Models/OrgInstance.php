<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgInstance extends Model
{
    use HasFactory;
    protected $fillable = [
        'type',
        'recurrence_type',
        'date_meeting',
        'is_archived'
    ];

    protected $casts = [
        'date_meeting' => 'datetime',
        'is_archived' => 'boolean'
    ];

    public function tasks(): hasMany{
        return $this->hasMany(Task::class);
    }
}
