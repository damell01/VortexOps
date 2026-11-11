<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** storage:report only reads: it lists folders and tables and changes nothing. */
class StorageReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_folders_and_tables(): void
    {
        $this->artisan('storage:report', ['--tables' => 5])
            ->expectsOutputToContain('Folders')
            ->expectsOutputToContain('Largest database tables')
            ->assertSuccessful();
    }
}
