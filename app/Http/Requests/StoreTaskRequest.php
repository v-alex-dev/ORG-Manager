<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    /**
     * Authentication is already handled by the auth:sanctum middleware.
     */
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
            'org_instance_id' => ['required', 'integer', 'exists:org_instances,id'],
            'service_id'      => ['required', 'integer', 'exists:services,id'],
            'poj_title'       => ['required', 'string', 'max:100'],
            'poj_description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
