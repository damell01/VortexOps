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
}
