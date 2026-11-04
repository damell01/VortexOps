<?php

namespace Tests\Unit;

use App\Services\FulfillmentRunService;
use PHPUnit\Framework\TestCase;

class FulfillmentRunLetterTest extends TestCase
{
    public function test_letters_continue_beyond_z(): void
    {
        $this->assertSame('A', FulfillmentRunService::letter(0));
        $this->assertSame('Z', FulfillmentRunService::letter(25));
        $this->assertSame('AA', FulfillmentRunService::letter(26));
        $this->assertSame('AB', FulfillmentRunService::letter(27));
        $this->assertSame('AZ', FulfillmentRunService::letter(51));
        $this->assertSame('BA', FulfillmentRunService::letter(52));
    }

    public function test_negative_index_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FulfillmentRunService::letter(-1);
    }
}
