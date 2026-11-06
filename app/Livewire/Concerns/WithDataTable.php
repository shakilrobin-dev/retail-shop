<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared list behaviour (like Odoo / Zoho list views):
 * sortable columns, rows per page, row selection for bulk actions and CSV export.
 *
 * The component lists its sortable columns in sortableColumns().
 */
trait WithDataTable
{
    use WithPagination;

    #[Url(as: 'sort', except: '')]
    public string $sortField = '';

    #[Url(as: 'dir', except: '')]
    public string $sortDirection = '';

    #[Url(as: 'per', except: 25)]
    public int $perPage = 25;

    /** @var list<string> */
    public array $selected = [];

    /**
     * Column key => SQL column (or expression) used for ORDER BY.
     *
     * @return array<string, string>
     */
    abstract protected function sortableColumns(): array;

    public function sortBy(string $field): void
    {
        if (! array_key_exists($field, $this->sortableColumns())) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 25;
        $this->resetPage();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    /**
     * Select / unselect all rows on the current page.
     *
     * @param  list<int|string>  $pageIds
     */
    public function togglePage(array $pageIds): void
    {
        $pageIds = array_map('strval', $pageIds);
        $allSelected = array_diff($pageIds, $this->selected) === [];

        $this->selected = $allSelected
            ? array_values(array_diff($this->selected, $pageIds))
            : array_values(array_unique([...$this->selected, ...$pageIds]));
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applySorting(Builder $query, string $defaultColumn, string $defaultDirection = 'asc'): Builder
    {
        $columns = $this->sortableColumns();

        if ($this->sortField !== '' && isset($columns[$this->sortField])) {
            return $query->orderByRaw($columns[$this->sortField].' '.($this->sortDirection === 'desc' ? 'desc' : 'asc'));
        }

        return $query->orderBy($defaultColumn, $defaultDirection);
    }

    /**
     * Download rows as CSV (opens in Excel; UTF-8 BOM keeps Bangla readable).
     *
     * @param  list<string>  $header
     * @param  iterable<int, list<mixed>>|Collection<int, list<mixed>>  $rows
     */
    protected function csvDownload(string $name, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $header);

            foreach ($rows as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        }, $name.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
