<?php

namespace App\Services\Catalog;

class CatalogSyncReport
{
    public const CREATED = 'created';

    public const ADOPTED = 'adopted';

    public const UPDATED = 'updated';

    public const UNCHANGED = 'unchanged';

    public const RETIRED = 'retired';

    public const OUTCOMES = [self::CREATED, self::ADOPTED, self::UPDATED, self::UNCHANGED, self::RETIRED];

    /**
     * @var array<string, array<string, int>>
     */
    private array $counts = [];

    /**
     * @var array<int, string>
     */
    private array $changes = [];

    /**
     * @var array<int, string>
     */
    private array $warnings = [];

    private bool $committed = false;

    public function __construct(public readonly bool $dryRun = false)
    {
        foreach (CatalogLoader::TYPES as $type) {
            $this->counts[$type] = array_fill_keys(self::OUTCOMES, 0);
        }
    }

    /**
     * @param  array<int, string>  $changedFields
     */
    public function record(string $type, string $outcome, string $slug, array $changedFields = []): void
    {
        $this->counts[$type][$outcome]++;

        if ($outcome === self::UNCHANGED) {
            return;
        }

        $this->changes[] = $changedFields === []
            ? "{$type} [{$slug}]: {$outcome}"
            : "{$type} [{$slug}]: {$outcome}, changed ".implode(', ', $changedFields);
    }

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function markCommitted(bool $committed): void
    {
        $this->committed = $committed;
    }

    /**
     * Whether the changes were written to the database.
     */
    public function committed(): bool
    {
        return $this->committed;
    }

    public function count(string $type, string $outcome): int
    {
        return $this->counts[$type][$outcome] ?? 0;
    }

    public function total(string $outcome): int
    {
        return array_sum(array_column($this->counts, $outcome));
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function counts(): array
    {
        return $this->counts;
    }

    /**
     * @return array<int, string>
     */
    public function changes(): array
    {
        return $this->changes;
    }

    /**
     * @return array<int, string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }

    public function hasChanges(): bool
    {
        return $this->changes !== [];
    }
}
