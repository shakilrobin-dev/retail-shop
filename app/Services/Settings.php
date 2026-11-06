<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * Reads and writes admin settings.
 *
 * Lookup order: branch value → global value → default in config/shop.php.
 * Values are stored JSON-encoded so booleans, numbers and arrays keep their type.
 */
class Settings
{
    /** @var Collection<int, Setting>|null */
    private ?Collection $rows = null;

    public function get(string $key, ?int $branchId = null, mixed $default = null): mixed
    {
        $rows = $this->rows();

        if ($branchId !== null) {
            $branchRow = $rows->first(fn (Setting $row): bool => $row->key === $key && $row->branch_id === $branchId);

            if ($branchRow) {
                return json_decode($branchRow->value, true);
            }
        }

        $globalRow = $rows->first(fn (Setting $row): bool => $row->key === $key && $row->branch_id === null);

        if ($globalRow) {
            return json_decode($globalRow->value, true);
        }

        return config("shop.defaults.{$key}", $default);
    }

    public function bool(string $key, ?int $branchId = null): bool
    {
        return (bool) $this->get($key, $branchId);
    }

    /**
     * Save a setting. Pass a branch id to save a branch-only override.
     */
    public function set(string $key, mixed $value, ?int $branchId = null): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key, 'branch_id' => $branchId],
            ['value' => json_encode($value, JSON_UNESCAPED_UNICODE)],
        );

        $this->rows = null;
    }

    /**
     * Remove a branch override so the branch uses the global value again.
     */
    public function forgetBranchValue(string $key, int $branchId): void
    {
        Setting::query()->where('key', $key)->where('branch_id', $branchId)->delete();

        $this->rows = null;
    }

    /**
     * @return Collection<int, Setting>
     */
    private function rows(): Collection
    {
        return $this->rows ??= Setting::query()->get();
    }
}
