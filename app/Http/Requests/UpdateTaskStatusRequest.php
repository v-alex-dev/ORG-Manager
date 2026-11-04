<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only these three fields can be edited.
     * reference_code, status and organization_id are never accepted here.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'service_id'      => ['required', 'integer', 'exists:services,id'],
            'poj_title'       => ['required', 'string', 'max:100'],
            'poj_description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
