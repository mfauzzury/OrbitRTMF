<?php

namespace App\Http\Requests;

class StoreRtmfSubModulePhotoRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:jpg,jpeg,png,gif,webp|max:20480',
        ];
    }
}
