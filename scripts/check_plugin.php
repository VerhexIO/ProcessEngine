<?php
$db = new mysqli('db', 'mantis', 'mantis123', 'mantis');
if ($db->connect_error) { echo "ERR: " . $db->connect_error; exit(1); }

echo "=== PLUGIN TABLE ===\n";
$r = $db->query("SELECT * FROM mantis_plugin_table WHERE basename='ProcessEngine'");
while ($row = $r->fetch_assoc()) { print_r($row); }

$db->close();
