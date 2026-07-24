<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class ReplacePolicyParams extends Command
{
    protected $signature = 'articles:replace-policy
                            {--input=storage/export/articles : Input directory relative to project root}
                            {--config=storage/export/policy-config.json : JSON file with policy parameters}
                            {--dry-run : Show what would change without modifying files}';

    protected $description = 'Replace service policy parameters across articles.';

    public function handle(): int
    {
        $input = $this->resolvePath($this->option('input'));
        $configPath = $this->resolvePath($this->option('config'));

        if (!is_dir($input)) {
            $this->error("Input directory does not exist: {$input}");
            return self::FAILURE;
        }

        if (!file_exists($configPath)) {
            $this->error("Config file does not exist: {$configPath}");
            return self::FAILURE;
        }

        $config = json_decode(file_get_contents($configPath), true);
        if (!is_array($config)) {
            $this->error("Invalid config file: {$configPath}");
            return self::FAILURE;
        }

        $replacements = $this->buildReplacements($config);

        $finder = new Finder;
        $finder->files()->in($input)->name('*.md')->sortByName();

        $processed = 0;
        $changed = 0;
        $errors = 0;
        $counts = [];

        foreach ($finder as $file) {
            $processed++;
            $relative = $file->getRelativePathname();

            try {
                $content = file_get_contents($file->getPathname());
                $newContent = $content;

                foreach ($replacements as $rule) {
                    $find = $rule['find'];
                    $replace = $rule['replace'];
                    $count = 0;
                    $newContent = str_replace($find, $replace, $newContent, $count);
                    if ($count > 0) {
                        $counts["{$find} => {$replace}"] = ($counts["{$find} => {$replace}"] ?? 0) + $count;
                    }
                }

                if ($newContent !== $content) {
                    $changed++;
                    if ($this->option('dry-run')) {
                        $this->line("[dry-run] Would replace policy params: {$relative}");
                    } else {
                        file_put_contents($file->getPathname(), $this->normalizeMarkdown($newContent));
                    }
                }
            } catch (\Throwable $e) {
                $this->error("[error] {$relative}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Processed {$processed} file(s), changed {$changed} file(s).");

        if (!empty($counts)) {
            $this->info("Replacement counts:");
            foreach ($counts as $key => $count) {
                $this->line("  {$key}: {$count}");
            }
        }

        if ($errors > 0) {
            $this->error("{$errors} error(s) occurred.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function buildReplacements(array $config): array
    {
        $replacements = [];
        $timeWindows = $config['time_windows'] ?? [];

        $mapping = [
            '{{CANCEL_ORDER_HOURS}}' => $timeWindows['cancel_order_hours'] ?? null,
            '{{DESIGN_EDIT_HOURS}}' => $timeWindows['design_edit_hours'] ?? null,
            '{{SAVED_PROJECT_DAYS}}' => $timeWindows['saved_project_days'] ?? null,
            '{{REFUND_DAYS}}' => $timeWindows['refund_days'] ?? null,
            '{{NEXT_DAY_CUTOFF}}' => $timeWindows['next_day_cutoff'] ?? null,
            '{{SUPPORT_HOURS}}' => $timeWindows['support_hours'] ?? null,
            '{{DELIVERY_DISPUTE_DAYS}}' => $timeWindows['delivery_dispute_days'] ?? null,
            '{{BROCHURE_TURNAROUND_DAYS}}' => $timeWindows['brochure_turnaround_days'] ?? null,
            '{{CARRIERS}}' => isset($config['carriers']) ? implode(', ', $config['carriers']) : null,
            '{{SHIPPING_ORIGIN}}' => $config['shipping_origin'] ?? null,
            '{{DELIVERY_REGION}}' => $config['delivery_region'] ?? null,
            '{{SUPPORT_RESPONSE_PROMISE}}' => $config['support_response_promise'] ?? null,
        ];

        foreach ($mapping as $placeholder => $value) {
            if ($value !== null) {
                $replacements[] = ['find' => $placeholder, 'replace' => (string) $value];
            }
        }

        if (isset($config['contacts'])) {
            $contacts = $config['contacts'];
            if (isset($contacts['email'])) {
                $replacements[] = ['find' => '{{SUPPORT_EMAIL}}', 'replace' => $contacts['email']];
            }
            if (isset($contacts['phone'])) {
                $replacements[] = ['find' => '{{SUPPORT_PHONE}}', 'replace' => $contacts['phone']];
            }
        }

        foreach ($config['replacements'] ?? [] as $rule) {
            $replacements[] = $rule;
        }

        return $replacements;
    }

    protected function normalizeMarkdown(string $markdown): string
    {
        $markdown = preg_replace('/\r\n?/', "\n", $markdown);

        return rtrim($markdown) . "\n";
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
