<?php
// Isolated CI-only fictional account. Never run on production.
if (getenv('APP_ENV') === 'production' || getenv('DBELLOPS_DEMO') !== 'true') {
    fwrite(STDERR, "DBellOps recording seeder refuses non-demo environment.\n");
    exit(1);
}
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
$user = App\Models\User::firstOrCreate(
    ['email' => getenv('DEMO_RECORD_EMAIL')],
    ['name' => 'Demo Seller', 'password' => getenv('DEMO_RECORD_PASSWORD')]
);
$user->assignRole('admin');
echo "Fictional admin account created in isolated SQLite database.\n";
