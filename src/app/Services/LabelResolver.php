<?php

namespace App\Services;

/**
 * Resolves label names to IDs.
 *
 * Handles case-insensitive matching and system label names.
 */
class LabelResolver
{
    private array $labelsMap = [];
    private array $idToName = [];

    /**
     * Loads labels from API response.
     */
    public function load(array $labels): self
    {
        $this->labelsMap = [];
        $this->idToName = [];

        foreach ($labels as $label) {
            $id = $label['id'];
            $name = $label['name'];

            $this->labelsMap[strtolower($name)] = $id;
            $this->idToName[$id] = $name;
        }

        return $this;
    }

    /**
     * Resolves label name or ID to label ID.
     *
     * Returns null if not found.
     */
    public function resolve(string $nameOrId): ?string
    {
        // Check if it's already an ID
        if (isset($this->idToName[$nameOrId])) {
            return $nameOrId;
        }

        // Try case-insensitive name lookup
        $lower = strtolower($nameOrId);
        if (isset($this->labelsMap[$lower])) {
            return $this->labelsMap[$lower];
        }

        return null;
    }

    /**
     * Resolves multiple labels.
     *
     * @return array{resolved: string[], notFound: string[]}
     */
    public function resolveMany(array $namesOrIds): array
    {
        $resolved = [];
        $notFound = [];

        foreach ($namesOrIds as $nameOrId) {
            $id = $this->resolve($nameOrId);
            if ($id !== null) {
                $resolved[] = $id;
            } else {
                $notFound[] = $nameOrId;
            }
        }

        return ['resolved' => $resolved, 'notFound' => $notFound];
    }

    /**
     * Gets label name by ID.
     */
    public function getName(string $id): ?string
    {
        return $this->idToName[$id] ?? null;
    }

    /**
     * Returns all loaded labels.
     */
    public function all(): array
    {
        return $this->idToName;
    }
}
