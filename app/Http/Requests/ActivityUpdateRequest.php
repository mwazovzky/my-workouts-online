<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActivityUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sets' => ['required', 'array', 'min:1'],
            'sets.*.order' => ['required', 'integer', 'min:1'],
            'sets.*.repetitions' => ['required', 'integer', 'min:0'],
            'sets.*.weight' => ['required', 'numeric', 'min:0'],
        ];
    }
}
