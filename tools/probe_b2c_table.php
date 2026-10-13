<?php
$cookieFile = 'c:/xampp/htdocs/tizzy/tools/b2c_c2.txt';
$page = 'c:/xampp/htdocs/tizzy/tools/b2c_p2.html';

$ch = curl_init('https://superadmin.tizzygroup.com/superadmin/b2c-customers-list');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$html = curl_exec($ch);
file_put_contents($page, $html);
curl_close($ch);

preg_match('/name="csrf-token" content="([^"]+)"/', $html, $m);
$token = $m[1] ?? '';

$cols = ['id','name','email','mobile','gstin','company_name','status','action'];
$post = [
    '_token' => $token,
    'draw' => 1,
    'start' => 0,
    'length' => 10,
    'order' => [['column' => 0, 'dir' => 'asc']],
    'search' => ['value' => ''],
];
foreach ($cols as $i => $c) {
    $post['columns'][$i] = [
        'data' => $c,
        'name' => $c,
        'searchable' => 'true',
        'orderable' => $c === 'action' ? 'false' : 'true',
        'search' => ['value' => ''],
    ];
}

$ch = curl_init('https://superadmin.tizzygroup.com/superadmin/b2c-customers-list-table');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($post),
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_HTTPHEADER => [
        'X-Requested-With: XMLHttpRequest',
        'X-CSRF-TOKEN: ' . $token,
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HEADER => true,
]);
$resp = curl_exec($ch);
curl_close($ch);

// Print status + message only (no tokens)
if (preg_match('/^HTTP\/\S+\s+(\d+)/m', $resp, $hm)) {
    echo "HTTP {$hm[1]}\n";
}
$body = substr($resp, strpos($resp, "\r\n\r\n") + 4);
$json = json_decode($body, true);
if (is_array($json)) {
    echo 'message=' . ($json['message'] ?? '(none)') . "\n";
    if (!empty($json['exception'])) echo 'exception=' . $json['exception'] . "\n";
    if (!empty($json['file'])) echo 'file=' . $json['file'] . ':' . ($json['line'] ?? '') . "\n";
    if (isset($json['recordsTotal'])) echo 'recordsTotal=' . $json['recordsTotal'] . "\n";
} else {
    echo substr(preg_replace('/eyJ[^"\s]{20,}/', '[redacted]', $body), 0, 800) . "\n";
}
