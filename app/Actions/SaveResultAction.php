<?php

namespace App\Actions;

use App\Actions\Contracts\ActionInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SaveResultAction implements ActionInterface
{
    public function name(): string
    {
        return 'save_result';
    }

    public function handle(array $input): array
    {
        $reference = 'action-'.Str::lower((string) Str::uuid());
        $directory = 'action-results/'.now()->format('Y/m/d');
        $path = $directory.'/'.$reference.'.json';

        $payload = [
            'reference' => $reference,
            'saved_at' => now()->toIso8601String(),
            'data' => $input,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        Storage::disk('local')->put($path, $json === false ? '{}' : $json);

        return [
            'saved' => true,
            'reference' => $reference,
            'storage_disk' => 'local',
            'path' => $path,
            'status' => 'ok',
        ];
    }
}
