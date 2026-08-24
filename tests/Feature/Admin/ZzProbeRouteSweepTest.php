<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Throwaway probe: parameterised GET routes, fed real record ids. */
class ZzProbeRouteSweepTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, ?string $role = null): User
    {
        $u = User::factory()->create(['email' => $email]);

        if ($role) {
            Role::findOrCreate($role, 'web');
            $u->assignRole($role);
        }

        return $u->fresh();
    }

    /** Create one row of every model that has a factory, so ids resolve. */
    private function makeOneOfEach(): array
    {
        $made = [];

        foreach (glob(base_path('database/factories/*Factory.php')) as $file) {
            $model = 'App\\Models\\' . str_replace('Factory', '', basename($file, '.php'));

            if (! class_exists($model)) {
                continue;
            }

            try {
                $made[class_basename($model)] = $model::factory()->create()->getKey();
            } catch (\Throwable $e) {
                $made[class_basename($model)] = 'FACTORY FAILED: ' . substr($e->getMessage(), 0, 100);
            }
        }

        return $made;
    }

    public function test_param_routes(): void
    {
        $made = $this->makeOneOfEach();
        fwrite(STDERR, "\n===== FACTORIES =====\n");
        foreach ($made as $m => $v) {
            if (is_string($v)) {
                fwrite(STDERR, "  $m: $v\n");
            }
        }

        $roles = [
            'owner'    => $this->user('dbellcreations@gmail.com'),
            'admin'    => $this->user('admin@probe.test', 'admin'),
            'streamer' => $this->user('streamer@probe.test', 'streamer'),
        ];

        $routes = collect(Route::getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods()) && str_contains($r->uri(), '{'))
            ->reject(fn ($r) => Str::startsWith($r->uri(), ['_', 'livewire', 'horizon', 'storage']))
            ->values();

        $failures = [];

        foreach ($roles as $label => $user) {
            foreach ($routes as $route) {
                // Fill every {param} with 1 — every factory made exactly one row.
                $uri = preg_replace('/\{[^}]+\}/', '1', $route->uri());

                try {
                    $status = $this->actingAs($user)->get('/' . ltrim($uri, '/'))->getStatusCode();
                } catch (\Throwable $e) {
                    $failures[] = sprintf('[%s] /%s THREW %s: %s @ %s:%d',
                        $label, $uri, class_basename($e), substr($e->getMessage(), 0, 200),
                        str_replace(base_path() . '/', '', $e->getFile()), $e->getLine());
                    continue;
                }

                if ($status >= 500) {
                    $failures[] = sprintf('[%s] /%s -> %d (%s)', $label, $uri, $status, $route->getName());
                }
            }
        }

        fwrite(STDERR, "\n===== PARAM SWEEP (" . $routes->count() . " routes x " . count($roles) . ") =====\n");
        fwrite(STDERR, $failures ? implode("\n", $failures) . "\n" : "no 5xx\n");
        fwrite(STDERR, "===== END =====\n");

        $this->assertTrue(true);
    }
}
