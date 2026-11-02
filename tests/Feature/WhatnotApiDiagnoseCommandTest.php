<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatnotApiDiagnoseCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagnostics_are_read_only_and_report_configuration_without_secrets(): void
    {
        $this->artisan('whatnot:api-diagnose --json')
            ->expectsOutputToContain('"channels"')
            ->expectsOutputToContain('"contract_verified"')
            ->assertSuccessful();
    }

    public function test_live_connection_checks_are_blocked_outside_production(): void
    {
        $this->artisan('whatnot:api-diagnose --check')->assertFailed();
    }
}
