<?php

namespace App\Http\Requests;

class ReorderRtmfSubModulesRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'integer',
        ];
    }
}
