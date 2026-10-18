<?php
/**
 * Deterministic, entirely fictional DBellOps data. CI/local SQLite only.
 * Never run against a live seller database.
 */
if (getenv('APP_ENV') === 'production' || getenv('DBELLOPS_DEMO') !== 'true' || getenv('DB_CONNECTION') !== 'sqlite') {
    fwrite(STDERR, "Refusing to seed outside isolated DBellOps SQLite demo.\n");
    exit(1);
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function demoInsert(string $table, array $identity, array $values): void {
    if (!Schema::hasTable($table)) throw new RuntimeException("Demo table missing: ".$table);
    $columns = array_flip(Schema::getColumnListing($table));
    $row = array_intersect_key(array_merge($identity, $values), $columns);
    $where = array_intersect_key($identity, $columns);
    if (count($where) !== count($identity)) throw new RuntimeException("Demo identity column missing: ".$table);
    $now = now()->toDateTimeString();
    if (isset($columns['created_at'])) $row['created_at'] = $now;
    if (isset($columns['updated_at'])) $row['updated_at'] = $now;
    DB::table($table)->updateOrInsert($where, $row);
}

$channels = [
    ['DBell Collectibles', 'dbellcollectibles'],
    ['Coastal Card Vault', 'coastalcardvault'],
    ['The Hobby Market', 'thehobbymarket'],
];
foreach ($channels as [$name, $username]) {
    demoInsert('whatnot_channels', ['whatnot_username'=>$username], [
        'name'=>$name, 'display_title'=>$name, 'status'=>'active',
        'include_in_import'=>false, 'mascot'=>null,
    ]);
}
$channelIds = DB::table('whatnot_channels')->pluck('id','whatnot_username');
$names = [
    'Saturday Night Sports Card Break','Vintage Baseball Singles','Modern Basketball Hits',
    'Football Rookie Showcase','Sunday Collector Auction','Hobby Box Flash Sale',
    'Midweek Card Market','Premium Slab Showcase','Baseball Chrome Night',
    'Weekend Collector Picks','Basketball Value Finds','Trading Card Mega Sale',
];
foreach (range(0,35) as $i) {
    $channel = $channels[$i % count($channels)][1];
    $date = now()->subDays(27)->addDays($i)->toDateString();
    $gross = round(640 + (($i * 137) % 1780) + ($i % 4) * 38.5,2);
    demoInsert('shows',['whatnot_show_id'=>'DBELL-DEMO-'.str_pad((string)$i,4,'0',STR_PAD_LEFT)],[
        'whatnot_channel_id'=>$channelIds[$channel],
        'title'=>$names[$i % count($names)],
        'show_date'=>$date, 'start_time'=>$date.' 19:00:00',
        'status'=>$i >= 28 ? 'scheduled' : 'closed',
        'gross_revenue'=>$i >= 28 ? 0 : $gross,
        'whatnot_net'=>$i >= 28 ? 0 : round($gross*.82,2),
        'completed_earnings'=>$i >= 28 ? null : round($gross*.82,2),
        'units_sold'=>$i >= 28 ? 0 : 20 + (($i*7)%90),
        'buyers_count'=>$i >= 28 ? 0 : 12 + (($i*3)%47),
        'giveaways_count'=>$i >= 28 ? 0 : $i%5,
        'import_source'=>'dbellops_fictional_demo',
    ]);
}
demoInsert('inventory_locations',['name'=>'Main Warehouse'],[
    'type'=>'main_storage','status'=>'active',
    'whatnot_channel_id'=>$channelIds['dbellcollectibles'],
]);
$locationId=DB::table('inventory_locations')->where('name','Main Warehouse')->value('id');
$items=[
    ['2024 Football Rookie Cards', 'Football', 18, 59.99, 46],
    ['Baseball Chrome Hobby Boxes', 'Baseball', 72, 149.99, 28],
    ['Basketball Prizm Packs', 'Basketball', 8, 24.99, 112],
    ['Vintage Baseball Singles', 'Baseball', 12, 29.99, 84],
    ['Graded Rookie Card Assortment', 'Football', 42, 89.99, 23],
    ['Trading Card Collector Bundles', 'Multi-sport', 26, 54.99, 61],
    ['Hobby Break Supplies', 'Supplies', 3, 12.99, 145],
    ['Premium Sports Card Slabs', 'Multi-sport', 105, 199.99, 16],
];
foreach ($items as $i=>$item) {
    [$name,$sport,$cost,$price,$qty]=$item;
    $sku='DBELL-DEMO-'.str_pad((string)($i+1),3,'0',STR_PAD_LEFT);
    demoInsert('products',['sku'=>$sku],[
        'name'=>$name,'sport'=>$sport,'category'=>'Sports Cards',
        'unit_cost'=>$cost,'average_cost'=>$cost,'sale_price'=>$price,
        'is_active'=>true,'is_container'=>false,'reorder_level'=>10,
    ]);
    $productId=DB::table('products')->where('sku',$sku)->value('id');
    demoInsert('inventory_stock',['inventory_item_id'=>$productId,'inventory_location_id'=>$locationId],['quantity'=>$qty]);
}
echo "Seeded ".count($channels)." fictional seller channels, 36 shows, and ".count($items)." inventory products.\n";
