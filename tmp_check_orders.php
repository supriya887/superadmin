<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = Illuminate\Support\Facades\DB::connection('pgsql_billing');
$countByPartner = $db->table('orders')->where('partner_id', 25)->count();
$countByCustomerJoin = $db->table('orders as o')
    ->leftJoin('customers as c', 'o.user_id', '=', 'c.id')
    ->where('c.partner_id', 25)
    ->count();
$sample = $db->table('orders')
    ->select('id', 'partner_id', 'user_id', 'order_type', 'status', 'domain_name', 'created_at')
    ->where(function ($q) {
        $q->where('partner_id', 25)
          ->orWhereIn('user_id', function ($qq) {
              $qq->select('id')->from('customers')->where('partner_id', 25);
          });
    })
    ->orderByDesc('id')
    ->limit(10)
    ->get();

echo "partner_id_count={$countByPartner}\n";
echo "customer_join_count={$countByCustomerJoin}\n";
echo json_encode($sample, JSON_PRETTY_PRINT) . "\n";
