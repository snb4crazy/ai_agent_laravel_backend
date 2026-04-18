<?php

namespace App\Actions;

use App\Actions\Contracts\ActionInterface;

/**
 * Stub: Load rules and policies that should govern downstream action behaviour.
 *
 * In production this could read from a DB table, a config file, a remote
 * policy service, etc.  Right now it returns a hard-coded set of rules so
 * the rest of the pipeline can be built and tested without any external
 * dependency.
 *
 * The returned array is injected as `policy_context` into every subsequent
 * step's input by the policy-guided pipeline controller method.
 */
class LoadPoliciesAction implements ActionInterface
{
    public function name(): string
    {
        return 'load_policies';
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input): array
    {
        // TODO: replace with a real policy loader (DB, YAML, remote API, …).
        return [
            'flag' => 'POLICY_STUB_V1',
            'loaded_at' => now()->toIso8601String(),
            'rules' => [
                [
                    'id' => 'RULE_TONE',
                    'description' => 'All generated replies must use a professional, neutral tone.',
                    'enabled' => true,
                ],
                [
                    'id' => 'RULE_MAX_LENGTH',
                    'description' => 'Output must not exceed 500 words.',
                    'enabled' => true,
                    'params' => ['max_words' => 500],
                ],
                [
                    'id' => 'RULE_NO_PII',
                    'description' => 'Do not include personally identifiable information in the output.',
                    'enabled' => true,
                ],
                [
                    'id' => 'RULE_LANGUAGE',
                    'description' => 'Respond in the same language as the input.',
                    'enabled' => false, // disabled by default – flip to true to activate
                ],
            ],
            'meta' => [
                'source' => 'stub',
                'version' => '0.1.0',
            ],
        ];
    }
}

