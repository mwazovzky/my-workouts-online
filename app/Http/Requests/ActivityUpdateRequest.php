<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivityUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $activity = $this->route('activity');
        $activityId = is_object($activity) ? $activity->getKey() : $activity;

        return [
            'sets' => ['required', 'array', 'min:1'],
            'sets.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('sets', 'id')->where('activity_id', $activityId),
            ],
            'sets.*.order' => ['required', 'integer', 'min:1', 'distinct'],
            'sets.*.repetitions' => ['required', 'integer', 'min:0'],
            'sets.*.weight' => ['required', 'numeric', 'min:0'],
            'sets.*.is_completed' => ['sometimes', 'boolean'],
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
            'sets.*.is_completed' => 'set completion',
        ];
    }
}
