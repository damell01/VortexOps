<?php

namespace Tests\Feature\Admin;

use App\Filament\Concerns\DownloadsTableCsv;
use App\Filament\Resources\VendorResource\Pages\ListVendors;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Support\AdminModules;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/** Every list has a "Download CSV" that exports what the list is showing. */
class DownloadTableCsvTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();
    }

    public function test_every_list_page_offers_the_download(): void
    {
        $missing = [];
        foreach (Finder::create()->files()->in(app_path('Filament'))->name('*.php') as $file) {
            $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], substr($file->getRealPath(), strlen(app_path()) + 1));
            if (! class_exists($class) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }
            $isList = is_subclass_of($class, ListRecords::class)
                || (is_subclass_of($class, \Filament\Pages\Page::class) && is_subclass_of($class, HasTable::class));
            if ($isList && ! in_array(DownloadsTableCsv::class, class_uses_recursive($class), true)) {
                $missing[] = $class;
            }
        }

        // Has its own Download CSV in the page header with fuller columns.
        $missing = array_diff($missing, [\App\Filament\Pages\PalletReceivingHistory::class]);

        $this->assertSame([], array_values($missing), 'List pages without Download CSV');
    }

    public function test_it_downloads_the_searched_rows(): void
    {
        Vendor::create(['name' => 'Acme Cards']);
        Vendor::create(['name' => '=Formula Supplies']);
        Vendor::create(['name' => 'Zeta Wholesale']);

        $component = Livewire::test(ListVendors::class)
            ->assertTableActionExists('downloadCsv')
            ->callTableAction('downloadCsv')
            ->assertFileDownloaded();

        $csv = base64_decode($component->effects['download']['content']);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Zeta Wholesale', $csv);
        $this->assertStringContainsString("'=Formula Supplies", $csv, 'formula cells are defused');

        $component->searchTable('Acme')->callTableAction('downloadCsv');
        $csv = base64_decode($component->effects['download']['content']);
        $this->assertStringContainsString('Acme Cards', $csv);
        $this->assertStringNotContainsString('Zeta Wholesale', $csv);
    }

    public function test_a_second_list_downloads_with_a_header_row(): void
    {
        Vendor::create(['name' => 'Acme Cards']);

        $component = Livewire::test(ListVendors::class)->callTableAction('downloadCsv')->assertFileDownloaded();
        $lines = array_values(array_filter(explode("\n", base64_decode($component->effects['download']['content']))));

        $this->assertGreaterThanOrEqual(2, count($lines));
        $this->assertStringContainsString('Name', $lines[0]);
        $this->assertStringContainsString('Acme Cards', $lines[1]);
    }
}
