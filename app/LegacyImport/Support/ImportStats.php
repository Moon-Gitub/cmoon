<?php

namespace App\LegacyImport\Support;

class ImportStats
{
    /** @var array<string, array{created: int, skipped: int, errors: int}> */
    private array $counts = [];

    public function inc(string $entity, string $action): void
    {
        if (! isset($this->counts[$entity])) {
            $this->counts[$entity] = ['created' => 0, 'skipped' => 0, 'errors' => 0];
        }

        $this->counts[$entity][$action] = ($this->counts[$entity][$action] ?? 0) + 1;
    }

    /** @return array<string, array{created: int, skipped: int, errors: int}> */
    public function all(): array
    {
        return $this->counts;
    }

    public function merge(self $other): void
    {
        foreach ($other->all() as $entity => $actions) {
            foreach ($actions as $action => $count) {
                for ($i = 0; $i < $count; $i++) {
                    $this->inc($entity, $action);
                }
            }
        }
    }
}
