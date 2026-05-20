<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreRtmfFrontendRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'spec_id' => ['required', 'string', 'max:64', Rule::unique('rtmf_frontends', 'spec_id')->whereNull('deleted_at')],
            'module_id' => 'required|integer|exists:rtmf_modules,id',
            'sub_module_id' => 'nullable|integer|exists:rtmf_sub_modules,id',
            'actor_ids' => 'nullable|array',
            'actor_ids.*' => 'integer|exists:rtmf_actors,id',
            'vue_path' => 'nullable|string|max:512',
            'url_dev' => 'nullable|string|max:1024',
            'url_stg' => 'nullable|string|max:1024',
            'url_prd' => 'nullable|string|max:1024',
            'confidence_id' => 'nullable|integer|exists:rtmf_confidences,id',
            'tab_code' => 'nullable|string|max:64',
            'title' => 'required|string|max:255',
            'business_requirement' => 'nullable|string',
            'stakeholder_requirement' => 'nullable|string',
            'description' => 'nullable|string',
            'is_done' => 'nullable|boolean',
            'assignees' => 'nullable|array',
            'assignees.*' => 'nullable|array',
        ];
    }
}
