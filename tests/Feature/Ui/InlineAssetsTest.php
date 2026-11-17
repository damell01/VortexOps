<?php

namespace Tests\Feature\Ui;

use App\Http\Middleware\ExternalizeInlineAssets;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Large shared inline blocks become cached files; everything that must stay inline does. */
class InlineAssetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        File::deleteDirectory(ExternalizeInlineAssets::directory());
        $this->actingAs(User::factory()->create());
        $this->startSession();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(ExternalizeInlineAssets::directory());
        parent::tearDown();
    }

    public function test_a_repeated_large_block_moves_to_a_cached_file_and_the_rest_stay(): void
    {
        $big = str_repeat('window.vxTest = (window.vxTest || 0) + 1; ', 60);
        $css = '.vx-test{color:red}'.str_repeat(' .vx-pad{margin:0}', 120);
        $html = '<html><head><style>'.$css.'</style></head><body>'
            .'<script>'.$big.'</script>'
            .'<script>var small = 1;</script>'
            .'<script type="application/json" id="data">'.json_encode(['x' => str_repeat('y', 3000)]).'</script>'
            .'<script>var token = "'.csrf_token().'";'.str_repeat(' ', 2000).'</script>'
            .'</body></html>';

        $mw = new ExternalizeInlineAssets;
        $mw->rewrite($html);
        $mw->rewrite($html);
        $this->assertSame([], glob(ExternalizeInlineAssets::directory().'/*') ?: [], 'nothing moves before it has been seen three times');

        $out = $mw->rewrite($html);
        $this->assertStringNotContainsString($big, $out);
        $this->assertStringNotContainsString($css, $out);
        $this->assertMatchesRegularExpression('#<script src="[^"]*/admin/inline-assets/[a-f0-9]{32}\.js"></script>#', $out);
        $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="[^"]*/admin/inline-assets/[a-f0-9]{32}\.css">#', $out);
        $this->assertStringContainsString('var small = 1;', $out, 'small blocks stay inline');
        $this->assertStringContainsString('type="application/json"', $out, 'data blocks stay inline');
        $this->assertStringContainsString(csrf_token(), $out, 'blocks carrying the session token stay inline');

        preg_match('#/admin/inline-assets/([a-f0-9]{32}\.js)#', $out, $m);
        $this->get('/admin/inline-assets/'.$m[1])->assertOk()->assertHeader('Cache-Control', 'immutable, max-age=31536000, private');
        auth()->logout();
        $this->get('/admin/inline-assets/'.$m[1])->assertRedirect();
    }

    public function test_repeated_icons_are_drawn_once_and_special_icons_stay(): void
    {
        $path = '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12"/>';
        $icon = fn (string $class) => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none">'.$path.'</svg>';
        $gradient = '<svg viewBox="0 0 10 10"><defs><linearGradient id="g1"><stop offset="0"/></linearGradient></defs><rect fill="url(#g1)" width="10" height="10"/></svg>';
        $html = '<html><body>'.$icon('a h-5').$icon('b h-4').$icon('c').$gradient.$gradient
            .'<script>var s = \''.$icon('in-script').'\';</script></body></html>';

        $out = (new ExternalizeInlineAssets)->dedupeSvgs($html);

        $this->assertSame(1, substr_count($out, 'id="vx-icon-sprite"'));
        $this->assertSame(1, substr_count($out, '<symbol '));
        $this->assertSame(3, substr_count($out, '<use href="#vxi-'));
        $this->assertStringContainsString('<svg class="b h-4" viewBox="0 0 24 24" fill="none"><use', $out, 'each copy keeps its own class and size');
        $this->assertSame(2, substr_count($out, 'id="g1"'), 'icons with their own ids stay as they are');
        $this->assertStringContainsString("var s = '".$icon('in-script')."'", $out, 'icons inside scripts are untouched');
        $this->assertLessThan(strpos($out, '</body>'), strpos($out, 'vx-icon-sprite'));
    }
}
