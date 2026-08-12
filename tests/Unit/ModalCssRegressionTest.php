<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * .fi-modal is Filament's invisible role="dialog" positioning-context wrapper
 * — .fi-modal-window is the actual visible card. A rule that puts overflow or
 * a transform-driven animation directly on .fi-modal makes it a new
 * containing block for its position:fixed children (the backdrop overlay and
 * the window itself), which then position/clip relative to that wrapper's own
 * near-zero-height box instead of the viewport. The whole modal renders
 * invisible while still intercepting clicks — every button behind it goes
 * dead until a hard reload. Confirmed and fixed via a real headless-browser
 * reproduction; this guards against reintroducing the same mistake.
 */
class ModalCssRegressionTest extends TestCase
{
    public function test_app_css_never_targets_the_bare_fi_modal_selector(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])\.fi-modal\s*\{/',
            $css,
            'Found a rule targeting the bare .fi-modal selector — this must target .fi-modal-window instead (see class doc comment).'
        );
    }

    /**
     * Filament's own default CSS makes .fi-modal-window the scroll container
     * for slide-overs (the notifications panel included) and tall centered
     * modals. `overflow: hidden !important` on that selector silently clips
     * content with no way to scroll to the rest of it — confirmed live: the
     * notification bell's dropdown couldn't be scrolled to see older
     * notifications. Horizontal clipping for rounded corners is fine;
     * vertical must stay auto.
     */
    public function test_app_css_never_hides_overflow_on_fi_modal_window(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        preg_match_all('/\.fi-modal-window\s*\{([^}]*)\}/', $css, $matches);

        $this->assertNotEmpty($matches[1], 'Expected at least one .fi-modal-window rule block in app.css.');

        foreach ($matches[1] as $block) {
            $this->assertDoesNotMatchRegularExpression(
                '/overflow(-y)?\s*:\s*hidden/',
                $block,
                'Found overflow(-y): hidden on .fi-modal-window — this breaks scrolling inside slide-overs like the notifications panel.'
            );
        }
    }
}
