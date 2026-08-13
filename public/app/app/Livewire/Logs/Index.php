<?php

namespace App\Livewire\Logs;

use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public string $filterLevel = 'all';
    public string $search      = '';
    public bool   $showTrace   = false;

    public function clearLog(): void
    {
        $path = storage_path('logs/laravel.log');
        if (file_exists($path)) {
            file_put_contents($path, '');
        }
        $this->success('Log cleared.');
    }

    private function parseLog(): array
    {
        $path = storage_path('logs/laravel.log');

        if (!file_exists($path) || filesize($path) === 0) {
            return [];
        }

        // Read last 300KB to stay memory-safe on large logs
        $fileSize = filesize($path);
        $handle   = fopen($path, 'r');
        fseek($handle, max(0, $fileSize - 300 * 1024));
        $content  = fread($handle, 300 * 1024);
        fclose($handle);

        $lines   = explode("\n", $content);
        $entries = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.*)$/', $line, $m)) {
                if ($current) {
                    $entries[] = $current;
                }
                $current = [
                    'datetime' => $m[1],
                    'env'      => $m[2],
                    'level'    => strtolower($m[3]),
                    'message'  => trim($m[4]),
                    'trace'    => '',
                ];
            } elseif ($current && trim($line) !== '') {
                $current['trace'] .= $line . "\n";
            }
        }

        if ($current) {
            $entries[] = $current;
        }

        return array_reverse($entries);
    }

    public function render()
    {
        $entries = collect($this->parseLog());

        if ($this->filterLevel !== 'all') {
            $entries = $entries->filter(fn($e) => $e['level'] === $this->filterLevel);
        }

        if ($this->search) {
            $search  = strtolower($this->search);
            $entries = $entries->filter(fn($e) => str_contains(strtolower($e['message']), $search));
        }

        $counts = collect($this->parseLog())->groupBy('level')->map->count();

        return view('livewire.logs.index', [
            'entries' => $entries->take(200)->values(),
            'counts'  => $counts,
        ]);
    }
}
