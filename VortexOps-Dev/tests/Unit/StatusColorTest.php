<?php

namespace Tests\Unit;

use App\Support\StatusColor;
use PHPUnit\Framework\TestCase;

class StatusColorTest extends TestCase
{
    public function test_the_same_token_always_gets_the_same_colour(): void
    {
        // "pending" used to be grey on the streamer log and amber elsewhere.
        $this->assertSame('warning', StatusColor::for('pending'));
        $this->assertSame('warning', StatusColor::for('pending_review'));
        $this->assertSame('warning', StatusColor::for('pending_approval'));
    }

    public function test_semantic_buckets(): void
    {
        $this->assertSame('gray', StatusColor::for('draft'));
        $this->assertSame('gray', StatusColor::for('inactive'));
        $this->assertSame('info', StatusColor::for('approved'));
        $this->assertSame('success', StatusColor::for('paid'));
        $this->assertSame('success', StatusColor::for('reconciled'));
        $this->assertSame('success', StatusColor::for('admin_approved'));
        $this->assertSame('danger', StatusColor::for('refunded'));
        $this->assertSame('danger', StatusColor::for('rejected'));
    }

    public function test_matching_is_case_insensitive(): void
    {
        // Imported ledger statuses arrive capitalised.
        $this->assertSame('success', StatusColor::for('Completed'));
        $this->assertSame('warning', StatusColor::for('  Processing  '));
    }

    public function test_unknown_and_null_fall_back_to_gray(): void
    {
        $this->assertSame('gray', StatusColor::for('totally_made_up'));
        $this->assertSame('gray', StatusColor::for(''));
        $this->assertSame('gray', StatusColor::for(null));
    }
}
