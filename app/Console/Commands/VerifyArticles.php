<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class VerifyArticles extends Command
{
    protected $signature = 'articles:lint
                            {--input=storage/export/articles : Input directory relative to project root}
                            {--strict : Also fail on unresolved IMAGE_PENDING and TEMPLATE_PENDING placeholders}';

    protected $description = 'Lint transformed articles for MOO residue and unresolved placeholders.';

    public function handle(): int
    {
        $input = $this->resolvePath($this->option('input'));

        if (!is_dir($input)) {
            $this->error("Input directory does not exist: {$input}");
            return self::FAILURE;
        }

        $finder = new Finder;
        $finder->files()->in($input)->name('*.md')->sortByName();

        $checks = [
            'moo_word' => ['pattern' => '/\bmoo\b/i', 'label' => 'Standalone "moo"'],
            'moo_domain' => ['pattern' => '/moo\.com/i', 'label' => 'moo.com domain'],
            'moo_email' => ['pattern' => '/@moo\.com/i', 'label' => '@moo.com email'],
            'printfinity' => ['pattern' => '/\bPrintfinity\b/i', 'label' => 'Printfinity'],
            'minicard' => ['pattern' => '/\bMiniCard/i', 'label' => 'MiniCard'],
            'mohawk' => ['pattern' => '/\bMohawk\b/i', 'label' => 'Mohawk'],
            'fsc' => ['pattern' => '/FSC-C/i', 'label' => 'FSC certification'],
        ];

        $findings = [];
        $placeholderFindings = [];
        $totalFiles = 0;

        foreach ($finder as $file) {
            $totalFiles++;
            $relative = $file->getRelativePathname();
            $content = file_get_contents($file->getPathname());

            foreach ($checks as $key => $check) {
                if (preg_match_all($check['pattern'], $content, $matches)) {
                    foreach ($matches[0] as $match) {
                        $findings[] = [
                            'file' => $relative,
                            'type' => $check['label'],
                            'match' => $match,
                        ];
                    }
                }
            }

            if (preg_match_all('/\{\{([A-Z_]+)(?::([^}]+))?\}\}/', $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $full = $match[0];
                    $name = $match[1];
                    $value = $match[2] ?? null;

                    if ($this->option('strict') || !str_starts_with($name, 'IMAGE_') && !str_starts_with($name, 'TEMPLATE_')) {
                        $placeholderFindings[] = [
                            'file' => $relative,
                            'placeholder' => $full,
                        ];
                    }
                }
            }
        }

        $this->info("Scanned {$totalFiles} file(s).");

        if (empty($findings) && empty($placeholderFindings)) {
            $this->info("No issues found.");
            return self::SUCCESS;
        }

        if (!empty($findings)) {
            $this->newLine();
            $this->warn("Found " . count($findings) . " MOO residue occurrence(s):");
            foreach ($findings as $finding) {
                $this->line("  [{$finding['type']}] {$finding['file']}: {$finding['match']}");
            }
        }

        if (!empty($placeholderFindings)) {
            $this->newLine();
            $this->warn("Found " . count($placeholderFindings) . " unresolved placeholder(s):");
            $summary = [];
            foreach ($placeholderFindings as $finding) {
                $summary[$finding['placeholder']][] = $finding['file'];
            }
            foreach ($summary as $placeholder => $files) {
                $uniqueFiles = array_unique($files);
                $this->line("  {$placeholder} in " . count($uniqueFiles) . " file(s)");
            }
        }

        $report = [
            'timestamp' => now()->toIso8601String(),
            'files_scanned' => $totalFiles,
            'findings' => $findings,
            'placeholders' => $placeholderFindings,
        ];

        $reportDir = storage_path('export/reports');
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0755, true);
        }
        $reportPath = $reportDir . '/lint-report-' . now()->format('Ymd-His') . '.json';
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info("Report saved to: {$reportPath}");

        return self::FAILURE;
    }

    protected function resolvePath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return rtrim($path, '\\/');
        }

        return rtrim(base_path($path), '\\/');
    }

    protected function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return true;
        }

        return strlen($path) >= 3
            && ctype_alpha($path[0])
            && $path[1] === ':'
            && ($path[2] === '\\' || $path[2] === '/');
    }
}
