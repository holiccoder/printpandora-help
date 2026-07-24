<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class ArchiveArticles extends Command
{
    protected $signature = 'articles:archive
                            {--source=storage/export/articles : Source directory relative to project root}
                            {--target=storage/export/articles_archived : Target archive directory}
                            {--rules=storage/export/archive-rules.json : JSON file with archive rules}
                            {--dry-run : Show what would be archived without moving files}';

    protected $description = 'Archive irrelevant articles by moving them to a separate directory.';

    public function handle(): int
    {
        $source = $this->resolvePath($this->option('source'));
        $target = $this->resolvePath($this->option('target'));
        $rulesPath = $this->resolvePath($this->option('rules'));

        if (!is_dir($source)) {
            $this->error("Source directory does not exist: {$source}");
            return self::FAILURE;
        }

        if (!file_exists($rulesPath)) {
            $this->error("Rules file does not exist: {$rulesPath}");
            return self::FAILURE;
        }

        $rules = json_decode(file_get_contents($rulesPath), true);
        if (!is_array($rules)) {
            $this->error("Invalid rules file: {$rulesPath}");
            return self::FAILURE;
        }

        $fs = new Filesystem;
        if (!$this->option('dry-run') && !$fs->isDirectory($target)) {
            $fs->makeDirectory($target, 0755, true);
        }

        $directories = $rules['directories'] ?? [];
        $files = $rules['files'] ?? [];
        $highRiskStrings = $rules['high_risk_strings'] ?? [];

        $movedDirs = [];
        $movedFiles = [];
        $highRiskFindings = [];

        foreach ($directories as $dir) {
            $srcPath = $source . DIRECTORY_SEPARATOR . $dir;
            if (!is_dir($srcPath)) {
                $this->warn("Directory not found: {$dir}");
                continue;
            }

            $destPath = $target . DIRECTORY_SEPARATOR . $dir;
            if ($this->option('dry-run')) {
                $this->line("[dry-run] Would archive directory: {$dir}");
            } else {
                $parent = dirname($destPath);
                if (!$fs->isDirectory($parent)) {
                    $fs->makeDirectory($parent, 0755, true);
                }
                $fs->moveDirectory($srcPath, $destPath);
            }
            $movedDirs[] = $dir;
        }

        foreach ($files as $file) {
            $srcPath = $source . DIRECTORY_SEPARATOR . $file;
            if (!file_exists($srcPath)) {
                $this->warn("File not found: {$file}");
                continue;
            }

            $destPath = $target . DIRECTORY_SEPARATOR . $file;
            if ($this->option('dry-run')) {
                $this->line("[dry-run] Would archive file: {$file}");
            } else {
                $parent = dirname($destPath);
                if (!$fs->isDirectory($parent)) {
                    $fs->makeDirectory($parent, 0755, true);
                }
                rename($srcPath, $destPath);
            }
            $movedFiles[] = $file;
        }

        if (!$this->option('dry-run')) {
            $highRiskFindings = $this->scanHighRiskStrings($source, $highRiskStrings);
            foreach ($highRiskFindings as $finding) {
                $this->warn("High-risk string still present: {$finding['string']} in {$finding['file']}");
            }
        }

        $report = [
            'timestamp' => now()->toIso8601String(),
            'source' => $source,
            'target' => $target,
            'dry_run' => $this->option('dry-run'),
            'directories_archived' => $movedDirs,
            'files_archived' => $movedFiles,
            'high_risk_findings' => $highRiskFindings,
        ];

        $reportDir = storage_path('export/reports');
        if (!$fs->isDirectory($reportDir)) {
            $fs->makeDirectory($reportDir, 0755, true);
        }
        $reportPath = $reportDir . '/archive-report-' . now()->format('Ymd-His') . '.json';
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->newLine();
        $this->info("Archived " . count($movedDirs) . " directorie(s) and " . count($movedFiles) . " file(s).");
        $this->info("Report saved to: {$reportPath}");

        if (count($highRiskFindings) > 0) {
            $this->warn(count($highRiskFindings) . " high-risk string(s) still present and will be handled in Phase 2.");
        }

        return self::SUCCESS;
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

    protected function scanHighRiskStrings(string $source, array $strings): array
    {
        if (empty($strings)) {
            return [];
        }

        $findings = [];
        $finder = new Finder;
        $finder->files()->in($source)->name('*.md');

        foreach ($finder as $file) {
            $content = file_get_contents($file->getPathname());
            foreach ($strings as $string) {
                if (str_contains($content, $string)) {
                    $findings[] = [
                        'string' => $string,
                        'file' => $file->getRelativePathname(),
                    ];
                }
            }
        }

        return $findings;
    }
}
