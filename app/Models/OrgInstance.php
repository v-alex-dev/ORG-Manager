<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Exceptions\ArchivedOrgException;
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

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'organization_id');
    }



    /**
     * Stop the request if this ORG is archived.
     * The caller provides the message so each endpoint keeps its own wording.
     *
     * @throws ArchivedOrgException
     */
    public function ensureNotArchived(string $message): void
    {
        if ($this->is_archived) {
            throw new ArchivedOrgException($message);
        }
    }
}
