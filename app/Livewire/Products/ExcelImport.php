<?php

namespace App\Livewire\Products;

use App\Exceptions\InsufficientStockException;
use App\Models\User;
use App\Services\CurrentBranch;
use App\Services\Imports\BrandImport;
use App\Services\Imports\CategoryImport;
use App\Services\Imports\ImportSheet;
use App\Services\Imports\ImportWorkbook;
use App\Services\Imports\ProductImport;
use App\Services\Imports\StockImport;
use App\Services\Imports\UnitImport;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Bulk import from Excel: download a sample, fill it in, upload, check every row, then import.
 */
class ExcelImport extends Component
{
    use WithFileUploads;

    /** Rows kept for the preview table (errors are always kept). */
    private const PREVIEW_LIMIT = 500;

    /** Import type => [sheet class, permission]. */
    private const TYPES = [
        'categories' => [CategoryImport::class, 'products.manage'],
        'brands' => [BrandImport::class, 'products.manage'],
        'units' => [UnitImport::class, 'products.manage'],
        'products' => [ProductImport::class, 'products.manage'],
        'stock' => [StockImport::class, 'stock.adjust'],
    ];

    #[Url]
    public string $type = 'products';

    /** Stock import: "add" to the current stock or "set" it to a count. */
    public string $mode = 'add';

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    /** @var list<array{row: int, status: string, label: string, detail: string, errors: list<string>}> */
    public array $preview = [];

    /** @var array{new: int, update: int, skip: int, error: int} */
    public array $counts = ['new' => 0, 'update' => 0, 'skip' => 0, 'error' => 0];

    public string $filter = 'all';

    /** @var array{type: string, new: int, update: int, skip: int, error: int}|null */
    public ?array $done = null;

    public function mount(): void
    {
        $allowed = $this->allowedTypes();
        abort_if($allowed === [], 403);

        if (! in_array($this->type, $allowed, true)) {
            $this->type = $allowed[0];
        }
    }

    /**
     * @return list<string>
     */
    public function allowedTypes(): array
    {
        /** @var User $user */
        $user = Auth::user();

        return array_values(array_filter(array_keys(self::TYPES), fn (string $type): bool => $user->can(self::TYPES[$type][1])));
    }

    public function updatedType(): void
    {
        abort_unless(in_array($this->type, $this->allowedTypes(), true), 403);
        $this->clear();
        $this->done = null;
    }

    public function updatedMode(): void
    {
        if (! in_array($this->mode, StockImport::MODES, true)) {
            $this->mode = 'add';
        }

        if ($this->file) {
            $this->check();
        }
    }

    public function updatedFile(): void
    {
        $this->done = null;
        $this->check();
    }

    /**
     * Read the uploaded file and show what will happen to every row. Nothing is saved.
     */
    public function check(): void
    {
        $this->authorize(self::TYPES[$this->type][1]);
        $this->resetErrorBag();
        $this->preview = [];
        $this->filter = 'all';

        try {
            $results = $this->checkedRows();
        } catch (ValidationException $exception) {
            $this->file = null;
            $this->addError('file', collect($exception->errors())->flatten()->first());

            return;
        }

        $this->counts = ['new' => 0, 'update' => 0, 'skip' => 0, 'error' => 0];
        $kept = 0;

        foreach ($results as $result) {
            $this->counts[$result['status']]++;

            if ($result['status'] === 'error' || $kept < self::PREVIEW_LIMIT) {
                unset($result['data']);
                $this->preview[] = $result;
                $kept += $result['status'] === 'error' ? 0 : 1;
            }
        }
    }

    /**
     * Save every row that passed the check, all together (nothing is saved if one fails).
     */
    public function import(CurrentBranch $currentBranch): void
    {
        $this->authorize(self::TYPES[$this->type][1]);

        try {
            $results = $this->checkedRows();
            $valid = array_values(array_filter($results, fn (array $result): bool => in_array($result['status'], ['new', 'update'], true)));

            if ($valid === []) {
                $this->addError('file', __('There is nothing to import.'));

                return;
            }

            DB::transaction(fn () => $this->sheet()->apply($valid, $currentBranch->get(), ['mode' => $this->mode]));
        } catch (ValidationException $exception) {
            $this->addError('file', collect($exception->errors())->flatten()->first());

            return;
        } catch (InsufficientStockException $exception) {
            $this->addError('file', $exception->getMessage());

            return;
        }

        $counts = array_count_values(array_column($results, 'status')) + ['new' => 0, 'update' => 0, 'skip' => 0, 'error' => 0];
        $this->done = ['type' => $this->type, 'new' => $counts['new'], 'update' => $counts['update'], 'skip' => $counts['skip'], 'error' => $counts['error']];

        activity()->causedBy(Auth::user())->withProperties($this->done)->log('Excel import: '.$this->type);

        $this->clear();
        $this->dispatch('toast', message: __('Import finished.'));
    }

    public function downloadSample(ImportWorkbook $workbook): BinaryFileResponse
    {
        $this->authorize(self::TYPES[$this->type][1]);

        return response()->download($workbook->sample($this->sheet()), $this->type.'-import-sample.xlsx')->deleteFileAfterSend();
    }

    public function clear(): void
    {
        $this->file = null;
        $this->preview = [];
        $this->counts = ['new' => 0, 'update' => 0, 'skip' => 0, 'error' => 0];
        $this->filter = 'all';
        $this->resetErrorBag();
    }

    /**
     * @return list<array{row: int, status: string, label: string, detail: string, errors: list<string>, data: array<string, mixed>}>
     *
     * @throws ValidationException
     */
    private function checkedRows(): array
    {
        if (! $this->file instanceof TemporaryUploadedFile) {
            throw ValidationException::withMessages(['file' => __('Choose a file to upload.')]);
        }

        $this->validate(['file' => ['required', 'file', 'max:10240', 'extensions:xlsx,xls,csv']], [], ['file' => __('file')]);

        $sheet = $this->sheet();
        $rows = app(ImportWorkbook::class)->read($this->file->getRealPath(), $this->file->getClientOriginalExtension(), $sheet);

        return $sheet->check($rows, app(CurrentBranch::class)->get(), ['mode' => $this->mode]);
    }

    private function sheet(): ImportSheet
    {
        return app(self::TYPES[$this->type][0]);
    }

    public function render(CurrentBranch $currentBranch): View
    {
        $sheet = $this->sheet();
        $rows = $this->filter === 'all' ? $this->preview : array_values(array_filter($this->preview, fn (array $row): bool => $row['status'] === $this->filter));

        return view('livewire.products.excel-import', [
            'types' => $this->allowedTypes(),
            'columns' => $sheet->columns(),
            'rows' => $rows,
            'branch' => $currentBranch->get(),
            'toImport' => $this->counts['new'] + $this->counts['update'],
        ])->title(__('Excel import'));
    }
}
