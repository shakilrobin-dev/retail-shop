<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Database backup written in plain PHP (works on shared hosting where
 * mysqldump is not available). Files are gzip SQL in storage/app/backups.
 */
class BackupService
{
    private const DIRECTORY = 'backups';

    private const KEEP = 14;

    public function create(): string
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $pdo = $connection->getPdo();
        $schema = in_array($driver, ['mysql', 'mariadb'], true) ? $connection->getDatabaseName() : null;
        $tables = collect($connection->getSchemaBuilder()->getTables($schema))->pluck('name')
            ->reject(fn (string $table): bool => in_array($table, ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'], true) || str_starts_with($table, 'sqlite_'));

        $filename = self::DIRECTORY.'/backup-'.now()->format('Y-m-d-His').'.sql.gz';
        $path = Storage::disk('local')->path($filename);
        @mkdir(dirname($path), 0755, true);
        $file = gzopen($path, 'wb6');

        gzwrite($file, '-- '.config('app.name').' backup '.now()->toDateTimeString()."\n");

        if ($driver === 'mysql' || $driver === 'mariadb') {
            gzwrite($file, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        }

        foreach ($tables as $table) {
            $create = $driver === 'sqlite'
                ? DB::selectOne('select sql from sqlite_master where type = ? and name = ?', ['table', $table])->sql
                : (array) DB::selectOne("SHOW CREATE TABLE `{$table}`");
            $createSql = is_array($create) ? array_values($create)[1] : $create;

            gzwrite($file, "DROP TABLE IF EXISTS `{$table}`;\n{$createSql};\n");

            DB::table($table)->orderByRaw('1')->chunk(500, function (Collection $rows) use ($file, $table, $pdo): void {
                $values = $rows->map(function (object $row) use ($pdo): string {
                    return '('.collect((array) $row)->map(fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value))->implode(',').')';
                });

                $columns = '`'.implode('`,`', array_keys((array) $rows->first())).'`';
                gzwrite($file, "INSERT INTO `{$table}` ({$columns}) VALUES\n".$values->implode(",\n").";\n");
            });

            gzwrite($file, "\n");
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            gzwrite($file, "SET FOREIGN_KEY_CHECKS=1;\n");
        }

        gzclose($file);
        $this->removeOld();

        return $filename;
    }

    /**
     * @return Collection<int, array{name: string, size: int, date: int}>
     */
    public function list(): Collection
    {
        $disk = Storage::disk('local');

        return collect($disk->files(self::DIRECTORY))
            ->filter(fn (string $file): bool => Str::endsWith($file, '.sql.gz'))
            ->map(fn (string $file): array => ['name' => basename($file), 'size' => $disk->size($file), 'date' => $disk->lastModified($file)])
            ->sortByDesc('date')
            ->values();
    }

    public function path(string $name): ?string
    {
        $file = self::DIRECTORY.'/'.basename($name);

        return Storage::disk('local')->exists($file) ? Storage::disk('local')->path($file) : null;
    }

    public function delete(string $name): void
    {
        Storage::disk('local')->delete(self::DIRECTORY.'/'.basename($name));
    }

    private function removeOld(): void
    {
        $this->list()->slice(self::KEEP)->each(fn (array $backup) => $this->delete($backup['name']));
    }
}
