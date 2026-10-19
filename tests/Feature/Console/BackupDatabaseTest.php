<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private string $dbPath;
    private string $backupDir;
    private string $originalConnection;
    private array $originalSqliteConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = config('database.default');
        $this->originalSqliteConfig = config('database.connections.sqlite');

        $this->dbPath    = storage_path('test-source.sqlite');
        $this->backupDir = storage_path('app/backups');

        // Point the default connection at a real SQLite file so copy() works
        $source = new \PDO('sqlite:'.$this->dbPath);
        $source->exec('CREATE TABLE IF NOT EXISTS backup_probe (id INTEGER PRIMARY KEY, value TEXT)');
        $source->exec("INSERT INTO backup_probe (value) VALUES ('restore me')");
        $source = null;
        config([
            'database.default'                       => 'sqlite',
            'database.connections.sqlite.database'   => $this->dbPath,
        ]);
    }

    protected function tearDown(): void
    {
        // RefreshDatabase must roll back the connection it opened in setUp,
        // rather than trying to open the SQLite fixture during MySQL teardown.
        config(['database.default' => $this->originalConnection, 'database.connections.sqlite' => $this->originalSqliteConfig]);
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }

        // Clean up any backups created during the test
        foreach (glob($this->backupDir . '/*.sqlite') ?: [] as $f) {
            unlink($f);
            @unlink($f.'.sha256');
        }

        parent::tearDown();
    }

    public function test_backup_command_exits_successfully(): void
    {
        $this->artisan('db:backup')->assertExitCode(0);
    }

    public function test_backup_creates_file_in_backups_directory(): void
    {
        $before = glob($this->backupDir . '/*.sqlite') ?: [];

        $this->artisan('db:backup')->assertExitCode(0);

        $after = glob($this->backupDir . '/*.sqlite') ?: [];
        $new   = array_diff($after, $before);

        $this->assertCount(1, $new, 'Expected exactly one new backup file');
        $this->assertFileExists(reset($new));
    }

    public function test_backup_filename_contains_timestamp(): void
    {
        $this->artisan('db:backup')->assertExitCode(0);

        $files = glob($this->backupDir . '/*.sqlite') ?: [];
        $this->assertNotEmpty($files);

        $filename = basename(reset($files));
        // Format: YYYY-MM-DD_HHMMSS_database.sqlite
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}_\d{6}_/', $filename);
    }

    public function test_backup_prunes_old_files_beyond_retention(): void
    {
        if (! is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }

        // Create a file stamped 30 days ago
        $oldFile = $this->backupDir . '/2020-01-01_000000_old.sqlite';
        file_put_contents($oldFile, 'old');
        touch($oldFile, strtotime('-30 days'));

        $this->artisan('db:backup', ['--prune' => 14])->assertExitCode(0);

        $this->assertFileDoesNotExist($oldFile, 'Old backup should have been pruned');
    }

    public function test_backup_retains_recent_files_when_pruning(): void
    {
        if (! is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }

        // Create a file from 5 days ago — should survive a 14-day prune window
        $recentFile = $this->backupDir . '/2020-06-01_000000_recent.sqlite';
        file_put_contents($recentFile, 'recent');
        touch($recentFile, strtotime('-5 days'));

        $this->artisan('db:backup', ['--prune' => 14])->assertExitCode(0);

        $this->assertFileExists($recentFile, 'Recent backup should not be pruned');

        unlink($recentFile);
    }

    public function test_zero_prune_retains_all_old_files(): void
    {
        if (! is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }

        $oldFile = $this->backupDir . '/2000-01-01_000000_very_old.sqlite';
        file_put_contents($oldFile, 'ancient');
        touch($oldFile, strtotime('-365 days'));

        $this->artisan('db:backup', ['--prune' => 0])->assertExitCode(0);

        $this->assertFileExists($oldFile, 'File should be kept when prune=0');

        unlink($oldFile);
    }

    public function test_a_restored_backup_contains_committed_wal_data_and_a_valid_checksum(): void
    {
        $source = new \PDO('sqlite:'.$this->dbPath);
        $source->exec('PRAGMA journal_mode=WAL');
        $source->exec('PRAGMA wal_autocheckpoint=0');
        $source->exec("INSERT INTO backup_probe (value) VALUES ('committed in WAL')");
        $before = glob($this->backupDir.'/*.sqlite') ?: [];
        $this->artisan('db:backup')->assertSuccessful();
        $new = array_values(array_diff(glob($this->backupDir.'/*.sqlite') ?: [], $before));
        $this->assertCount(1, $new);
        $restored = new \PDO('sqlite:'.$new[0]);
        $this->assertSame('ok', $restored->query('PRAGMA quick_check')->fetchColumn());
        $this->assertSame('committed in WAL', $restored->query('SELECT value FROM backup_probe ORDER BY id DESC LIMIT 1')->fetchColumn());
        $this->assertStringStartsWith(hash_file('sha256', $new[0]), file_get_contents($new[0].'.sha256'));
        $source = null;
        $restored = null;
    }

}
