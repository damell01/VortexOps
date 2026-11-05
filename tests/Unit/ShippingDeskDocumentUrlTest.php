<?php

namespace Tests\Unit;

use App\Filament\Resources\FulfillmentResource\Pages\ShippingDesk;
use PHPUnit\Framework\TestCase;

class ShippingDeskDocumentUrlTest extends TestCase
{
    public function test_https_label_url_is_accepted(): void
    {
        $url = 'https://example.com/label.pdf?signature=abc';
        $this->assertSame($url, ShippingDesk::safeDocumentUrl($url));
    }

    public function test_non_https_or_malformed_urls_are_rejected(): void
    {
        foreach (['javascript:alert(1)', 'http://example.com/label.pdf', '//example.com/label.pdf',
            'https://user:pass@example.com/label.pdf', "https://example.com/\nlabel.pdf", null] as $url) {
            $this->assertNull(ShippingDesk::safeDocumentUrl($url));
        }
    }
}
