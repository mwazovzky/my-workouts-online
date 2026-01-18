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

    public function messages(): array
    {
        return [
            'sets.*.order.required' => 'Order is required for each set',
            'sets.*.repetitions.required' => 'Repetitions are required for each set',
            'sets.*.weight.required' => 'Weight is required for each set',
        ];
    }

    public function attributes(): array
    {
        return [
            'sets' => 'sets',
            'sets.*.order' => 'set order',
            'sets.*.repetitions' => 'set repetitions',
            'sets.*.weight' => 'set weight',
        ];
    }
}
