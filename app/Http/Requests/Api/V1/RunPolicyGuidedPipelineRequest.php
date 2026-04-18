<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class RunPolicyGuidedPipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Base input forwarded to both action steps.
            'input' => ['sometimes', 'array'],

            // Optional per-step input overrides keyed by action name.
            'input_by_action' => ['sometimes', 'array'],
            'input_by_action.*' => ['array'],

            // The two actions to run after the policy is loaded.
            // Defaults to ['analyze_sentiment', 'generate_reply'] if omitted.
            'actions' => ['sometimes', 'array', 'size:2'],
            'actions.*' => ['string', 'max:100'],

            // Arbitrary caller metadata stored in task.meta_json.
            'meta' => ['sometimes', 'array'],
        ];
    }
}

