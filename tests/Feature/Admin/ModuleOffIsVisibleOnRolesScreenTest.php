<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\RoleResource;
use App\Support\AdminModules;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Roles screen has to admit when a page is closed by something it does not
 * control.
 *
 * Ticking Visible on a page whose module is off grants nothing — the module
 * check runs first and refuses everyone — so the row promised access the
 * setting could not deliver, and the page 403'd anyway. The existing "code
 * rule" tag missed these: it looks for canAccess() declared in the page's own
 * file, and for module-gated pages the rule lives in the HasModuleAccess trait.
 */
class ModuleOffIsVisibleOnRolesScreenTest extends TestCase
{
    use RefreshDatabase;

    private function setModules(array $slugs): void
    {
        Setting::set('enabled_admin_modules', json_encode($slugs));
        AdminModules::flushMemo();
    }

    public function test_a_page_whose_module_is_off_is_flagged(): void
    {
        $this->setModules(['streams']);

        $this->assertSame(
            'timekeeping',
            RoleResource::disabledModuleFor(\App\Filament\Pages\Timekeeping::class),
        );
    }

    public function test_a_page_whose_module_is_on_is_not_flagged(): void
    {
        $this->setModules(['timekeeping']);

        $this->assertNull(RoleResource::disabledModuleFor(\App\Filament\Pages\Timekeeping::class));
    }

    public function test_a_page_with_no_module_is_never_flagged(): void
    {
        $this->setModules([]);

        // Not module-gated at all — flagging it would be a warning about a
        // restriction that does not exist.
        $this->assertNull(RoleResource::disabledModuleFor(\App\Filament\Pages\DemoData::class));
    }

    public function test_the_flag_reaches_the_rendered_label(): void
    {
        $this->setModules([]);

        $label = (string) $this->invokeLabel(\App\Filament\Pages\Timekeeping::class, 'Timekeeping');

        $this->assertStringContainsString('module off', $label);
        $this->assertStringContainsString('timekeeping', $label);
    }

    private function invokeLabel(string $class, string $label): \Illuminate\Support\HtmlString
    {
        $method = new \ReflectionMethod(RoleResource::class, 'pageAccessLabel');
        $method->setAccessible(true);

        return $method->invoke(null, $class, $label);
    }
}
