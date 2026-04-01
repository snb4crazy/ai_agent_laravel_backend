<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RunActionsPipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $pipelineNames = array_keys((array) config('pipelines.definitions', []));

        return [
            'pipeline' => ['nullable', 'string', Rule::in($pipelineNames)],
            'skip_actions' => ['nullable', 'array'],
            'skip_actions.*' => ['string', 'distinct'],
            'input' => ['nullable', 'array'],
            'input_by_action' => ['nullable', 'array'],
            'input_by_action.*' => ['array'],
            'meta' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $pipelineName = (string) ($this->input('pipeline') ?: config('pipelines.default', 'all_actions'));
            $definitions = (array) config('pipelines.definitions', []);
            $allowedActions = array_values(array_filter((array) data_get($definitions, $pipelineName.'.actions', []), 'is_string'));

            $skipActions = (array) $this->input('skip_actions', []);
            foreach ($skipActions as $index => $action) {
                if (! is_string($action)) {
                    continue;
                }

                if (! in_array($action, $allowedActions, true)) {
                    $validator->errors()->add(
                        'skip_actions.'.$index,
                        'Action ['.$action.'] is not part of selected pipeline ['.$pipelineName.'].'
                    );
                }
            }
        });
    }
}
