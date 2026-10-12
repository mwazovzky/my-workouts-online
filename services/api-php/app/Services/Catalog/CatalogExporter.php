<?php

namespace App\Services\Catalog;

use BackedEnum;
use Symfony\Component\Yaml\Yaml;

/**
 * Renders the PHP catalog data files as the YAML files under the repository's
 * catalog/ directory, and compares the two. The YAML keeps the authoring form
 * of the PHP files (the `'sets' => 3` shorthand, absent optional keys) and
 * may carry comments, so they are compared as data, never as text.
 */
class CatalogExporter
{
    public function __construct(
        private readonly string $sourcePath,
        private readonly string $targetPath,
    ) {}

    /**
     * The data files to export, as source path => target path, both relative.
     *
     * @return array<string, string>
     */
    public function files(): array
    {
        $files = [];

        foreach (['equipment.php', 'categories.php', 'programs.php', 'retired.php'] as $file) {
            if (is_file($this->sourcePath.DIRECTORY_SEPARATOR.$file)) {
                $files[$file] = $this->yamlName($file);
            }
        }

        foreach (['exercises', 'workout-templates'] as $directory) {
            $paths = glob($this->sourcePath.DIRECTORY_SEPARATOR.$directory.DIRECTORY_SEPARATOR.'*.php') ?: [];
            sort($paths);

            foreach ($paths as $path) {
                $file = $directory.DIRECTORY_SEPARATOR.basename($path);
                $files[$file] = $this->yamlName($file);
            }
        }

        return $files;
    }

    /**
     * The data of one PHP data file, with enum cases replaced by their values.
     *
     * @return array<int|string, mixed>
     */
    public function read(string $sourceFile): array
    {
        return $this->plain(require $this->sourcePath.DIRECTORY_SEPARATOR.$sourceFile);
    }

    /**
     * The YAML text for one PHP data file.
     */
    public function render(string $sourceFile): string
    {
        $data = $this->read($sourceFile);

        if (basename($sourceFile) === 'retired.php') {
            return $this->renderRetired($data);
        }

        return $data === [] ? "[]\n" : implode('', array_map(fn (array $record) => $this->renderRecord($record), $data));
    }

    /**
     * Write every YAML file. Existing files are kept unless $force is set, so
     * comments added to them by hand survive.
     *
     * @return array<string, bool> Target file => whether it was written
     */
    public function export(bool $force = false): array
    {
        $written = [];

        foreach ($this->files() as $source => $target) {
            $path = $this->targetPath.DIRECTORY_SEPARATOR.$target;

            if (is_file($path) && ! $force) {
                $written[$target] = false;

                continue;
            }

            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            file_put_contents($path, $this->render($source));
            $written[$target] = true;
        }

        return $written;
    }

    /**
     * Compare every PHP data file with its YAML file as data.
     *
     * @return array<int, string> Differences; empty when the files agree
     */
    public function check(): array
    {
        $differences = [];
        $expected = [];

        foreach ($this->files() as $source => $target) {
            $expected[] = $target;
            $path = $this->targetPath.DIRECTORY_SEPARATOR.$target;

            if (! is_file($path)) {
                $differences[] = "{$target}: missing (exported from {$source}).";

                continue;
            }

            $yaml = Yaml::parseFile($path);

            if ($this->canonical($yaml ?? []) !== $this->canonical($this->read($source))) {
                $differences[] = "{$target}: differs from {$source}.";
            }
        }

        foreach ($this->yamlFiles() as $target) {
            if (! in_array($target, $expected, true)) {
                $differences[] = "{$target}: has no PHP data file.";
            }
        }

        return $differences;
    }

    /**
     * @return array<int, string>
     */
    private function yamlFiles(): array
    {
        $files = [];

        foreach (['', 'exercises', 'workout-templates'] as $directory) {
            $prefix = $directory === '' ? '' : $directory.DIRECTORY_SEPARATOR;

            foreach (glob($this->targetPath.DIRECTORY_SEPARATOR.$prefix.'*.yaml') ?: [] as $path) {
                $files[] = $prefix.basename($path);
            }
        }

        return $files;
    }

    private function yamlName(string $sourceFile): string
    {
        return preg_replace('/\.php$/', '.yaml', $sourceFile);
    }

    /**
     * One record as a block mapping: lists of scalars and the small maps of
     * activities and schedules are written inline, translations as blocks.
     *
     * @param  array<string, mixed>  $record
     */
    private function renderRecord(array $record): string
    {
        $lines = [];

        foreach ($record as $key => $value) {
            if ($key === 'translations') {
                $lines[] = 'translations:';

                foreach ($value as $locale => $fields) {
                    $lines[] = "  {$locale}:";

                    foreach ($fields as $field => $text) {
                        $lines[] = "    {$field}: ".Yaml::dump($text);
                    }
                }

                continue;
            }

            if (is_array($value) && $value !== [] && array_is_list($value) && is_array($value[0])) {
                $lines[] = "{$key}:";

                foreach ($value as $item) {
                    $lines[] = '  - '.Yaml::dump($item, 0);
                }

                continue;
            }

            $lines[] = "{$key}: ".Yaml::dump($value, 0);
        }

        return '- '.implode("\n  ", $lines)."\n";
    }

    /**
     * @param  array<string, mixed>  $retired
     */
    private function renderRetired(array $retired): string
    {
        $text = '';

        foreach ($retired as $type => $entries) {
            if ($entries === []) {
                $text .= "{$type}: []\n";

                continue;
            }

            $text .= "{$type}:\n";

            foreach ($entries as $entry) {
                $text .= '  - '.Yaml::dump($entry, 0)."\n";
            }
        }

        return $text;
    }

    private function plain(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return is_array($value) ? array_map(fn (mixed $item) => $this->plain($item), $value) : $value;
    }

    /**
     * Maps sorted by key at every level, so key order does not count.
     */
    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(fn (mixed $item) => $this->canonical($item), $value);

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
