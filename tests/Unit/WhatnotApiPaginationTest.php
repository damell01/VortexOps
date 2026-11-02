<?php

namespace Tests\Unit;

use App\Services\WhatnotApiPagination;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class WhatnotApiPaginationTest extends TestCase
{
    public function test_missing_cursor_is_end_of_pagination(): void
    {
        $this->assertNull(WhatnotApiPagination::nextCursor(['data' => []], 'meta.next_cursor'));
    }

    public function test_reads_nested_cursor(): void
    {
        $this->assertSame('page-two', WhatnotApiPagination::nextCursor(['meta' => ['next_cursor' => 'page-two']], 'meta.next_cursor'));
    }

    public function test_detects_repeated_cursor(): void
    {
        $seen = [];
        WhatnotApiPagination::assertUnseen('page-two', $seen);
        $this->expectException(RuntimeException::class);
        WhatnotApiPagination::assertUnseen('page-two', $seen);
    }

    public function test_rejects_unverified_cursor_path(): void
    {
        $this->expectException(RuntimeException::class);
        WhatnotApiPagination::nextCursor([], 'untrusted.url');
    }
}
