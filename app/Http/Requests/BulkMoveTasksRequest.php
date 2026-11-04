<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkMoveTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'task_ids'        => ['required', 'array', 'min:1', 'max:100'],
            'task_ids.*'      => ['integer', 'distinct', 'exists:tasks,id'],
            'org_instance_id' => ['required', 'integer', 'exists:org_instances,id'],
        ];
    }
}
