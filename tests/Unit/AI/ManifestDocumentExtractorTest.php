<?php

namespace Tests\Unit\AI;

use App\Services\AI\Documents\ManifestDocumentExtractor;
use PHPUnit\Framework\TestCase;

class ManifestDocumentExtractorTest extends TestCase
{
    public function test_plain_text_manifest_is_extracted_without_vision(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'manifest_') . '.txt';
        file_put_contents($path, "SKU | Product | Qty | Cost\nABC-1 | Booster Box | 12 | 75.00\n");

        try {
            $result = (new ManifestDocumentExtractor())->extract($path);

            $this->assertSame('text', $result->kind);
            $this->assertFalse($result->requiresVision);
            $this->assertCount(2, $result->textRows);
            $this->assertStringContainsString('Booster Box', $result->textRows[1]);
        } finally {
            @unlink($path);
        }
    }

    public function test_native_image_is_marked_for_vision(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'manifest_') . '.png';
        file_put_contents($path, 'not-real-image-bytes');

        try {
            $result = (new ManifestDocumentExtractor())->extract($path);

            $this->assertSame('image', $result->kind);
            $this->assertTrue($result->requiresVision);
            $this->assertSame([], $result->textRows);
        } finally {
            @unlink($path);
        }
    }
}
