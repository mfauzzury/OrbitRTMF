<?php

namespace App\Http\Requests;

use App\Models\RtmfProject;

class StoreRtmfProjectMemberRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'      => 'required|integer|exists:users,id',
            'project_role' => 'required|string|in:' . implode(',', RtmfProject::MEMBER_ROLES),
        ];
    }
}
