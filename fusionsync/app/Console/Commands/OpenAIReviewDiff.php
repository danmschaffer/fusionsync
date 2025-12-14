<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OpenAIService;

class OpenAIReviewDiff extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Accepts an optional file path. If omitted, the command will read from STDIN.
     */
    protected $signature = 'openai:review-diff {path?} {--write-patches}';

    /**
     * The console command description.
     */
    protected $description = 'Use OpenAI to review a git diff and propose fixes (outputs JSON).';

    protected OpenAIService $openai;

    public function __construct(OpenAIService $openai)
    {
        parent::__construct();
        $this->openai = $openai;
    }

    public function handle(): int
    {
        $path = $this->argument('path');

        if ($path) {
            if (!file_exists($path) || !is_readable($path)) {
                $this->error("File not found or not readable: {$path}");
                return 1;
            }

            $diff = file_get_contents($path);
        } else {
            // read from STDIN
            $this->info('Reading diff from STDIN (end with Ctrl+D or send EOF).');
            $diff = stream_get_contents(STDIN);
            if ($diff === false || trim($diff) === '') {
                $this->error('No input received from STDIN. Provide a file path or pipe a diff in.');
                return 1;
            }
        }

        $this->info('Sending diff to OpenAI for analysis...');

        try {
            $result = $this->openai->analyzeDiff($diff);
        } catch (\Throwable $e) {
            $this->error('OpenAI request failed: ' . $e->getMessage());
            return 2;
        }

        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->line($json ?: '');

        if ($this->option('write-patches') && isset($result['suggestions']) && is_array($result['suggestions'])) {
            $outDir = storage_path('app/openai-suggestions');
            if (!is_dir($outDir)) {
                @mkdir($outDir, 0755, true);
            }

            foreach ($result['suggestions'] as $idx => $sugg) {
                $patch = $sugg['patch'] ?? ($sugg['diff'] ?? null);
                $title = preg_replace('/[^a-z0-9-_\.]/i', '_', $sugg['title'] ?? "suggestion_{$idx}");
                $file = $outDir . DIRECTORY_SEPARATOR . "{$idx}_{$title}.diff";
                file_put_contents($file, $patch ?? '');
                $this->info("Wrote patch: {$file}");
            }
        }

        return 0;
    }
}
