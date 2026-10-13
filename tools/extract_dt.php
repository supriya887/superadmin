<?php
$h = file_get_contents('c:/xampp/htdocs/tizzy/tools/b2c_p.html');
$needle = ".DataTable({";
$p = strpos($h, $needle);
echo "pos=$p\n";
if ($p !== false) {
    echo substr($h, $p, 2200);
}
echo "\n---\n";
$p2 = strrpos($h, 'b2c-customers-list-table');
echo "urlpos=$p2\n";
if ($p2 !== false) {
    echo substr($h, max(0, $p2 - 200), 2000);
}
