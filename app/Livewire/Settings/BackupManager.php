<?php

namespace App\Livewire\Settings;

use App\Services\BackupService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupManager extends Component
{
    public function mount(): void
    {
        $this->authorize('settings.manage');
    }

    public function backupNow(BackupService $backups): void
    {
        $this->authorize('settings.manage');
        $backups->create();
        session()->flash('success', __('Backup created.'));
    }

    public function download(string $name, BackupService $backups): ?BinaryFileResponse
    {
        $this->authorize('settings.manage');
        $path = $backups->path($name);

        return $path ? response()->download($path) : null;
    }

    public function delete(string $name, BackupService $backups): void
    {
        $this->authorize('settings.manage');
        $backups->delete($name);
    }

    public function render(): View
    {
        return view('livewire.settings.backup-manager', [
            'backups' => app(BackupService::class)->list(),
        ])->title(__('Backup'));
    }
}
