<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\RoleResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Roles screen has to admit which pages it cannot actually grant.
 *
 * Roughly half the panel enforces its own admin-or-owner rule on top of the
 * visibility list, so ticking Visible does not open those pages. The tag that
 * says so was looking for canAccess() declared in the page's own file — and
 * almost every rule here is reached through a trait, which reports a different
 * file — so the warning appeared on a handful of rows while dozens of others
 * read as plain switches and then answered 403.
 */
class CodeRuleTagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        filament()->setCurrentPanel(filament()->getPanel('admin'));
    }

    public function test_a_page_with_its_own_rule_is_tagged(): void
    {
        // Owner-only, written directly on the page.
        $this->assertTrue(RoleResource::hasOwnAccessRule(\App\Filament\Pages\WhatnotBackfill::class));
    }

    public function test_a_rule_reached_through_a_trait_is_tagged(): void
    {
        // The case the old check missed entirely: the restriction is real, but
        // the method's file is the trait's, not the resource's.
        $this->assertTrue(RoleResource::hasOwnAccessRule(\App\Filament\Resources\ActivityLogResource::class));
        $this->assertTrue(RoleResource::hasOwnAccessRule(\App\Filament\Pages\InventoryScanner::class));
    }

    public function test_module_gating_alone_is_not_tagged(): void
    {
        // HasModuleAccess without an overridden check adds nothing beyond the
        // module state and the visibility list, and both of those have their
        // own tag and their own row. Tagging it too would mark 67 of 76 rows,
        // which carries the same information as marking none.
        $plain = collect(RoleResource::roleControlledPages())
            ->reject(fn (string $c) => RoleResource::hasOwnAccessRule($c));

        $this->assertGreaterThan(
            10,
            $plain->count(),
            'Nearly every row is tagged, so the tag no longer distinguishes anything.'
        );
    }

    public function test_the_tag_reaches_the_rendered_label(): void
    {
        $method = new \ReflectionMethod(RoleResource::class, 'pageAccessLabel');
        $method->setAccessible(true);

        $label = (string) $method->invoke(null, \App\Filament\Resources\ActivityLogResource::class, 'Activity Log');

        $this->assertStringContainsString('code rule', $label);
    }

    public function test_the_tag_is_not_claimed_for_every_page(): void
    {
        $tagged = collect(RoleResource::roleControlledPages())
            ->filter(fn (string $c) => RoleResource::hasOwnAccessRule($c));

        $this->assertGreaterThan(20, $tagged->count(), 'Rules reached through traits are being missed again.');
        $this->assertLessThan(count(RoleResource::roleControlledPages()), $tagged->count());
    }
}
