<?php
$db = new mysqli('db', 'mantis', 'mantis123', 'mantis');
if ($db->connect_error) { echo "ERR: " . $db->connect_error; exit(1); }

echo "=== FLOWS ===\n";
$r = $db->query("SELECT id, name, status, project_id FROM mantis_plugin_ProcessEngine_flow_definition_table");
while ($row = $r->fetch_assoc()) { echo implode(' | ', $row) . "\n"; }

echo "\n=== STEPS (Flow 1) ===\n";
$r = $db->query("SELECT id, name, mantis_status, step_type, child_flow_id, step_order FROM mantis_plugin_ProcessEngine_step_table WHERE flow_id=1 ORDER BY step_order");
if ($r) { while ($row = $r->fetch_assoc()) { echo implode(' | ', $row) . "\n"; } }

echo "\n=== STEPS (Flow 6) ===\n";
$r = $db->query("SELECT id, name, mantis_status, step_type, child_flow_id, step_order FROM mantis_plugin_ProcessEngine_step_table WHERE flow_id=6 ORDER BY step_order");
if ($r) { while ($row = $r->fetch_assoc()) { echo implode(' | ', $row) . "\n"; } }

echo "\n=== PROJECTS ===\n";
$r = $db->query("SELECT id, name FROM mantis_project_table WHERE enabled=1");
while ($row = $r->fetch_assoc()) { echo implode(' | ', $row) . "\n"; }

echo "\n=== USERS (admin) ===\n";
$r = $db->query("SELECT id, username, access_level FROM mantis_user_table WHERE username='administrator'");
while ($row = $r->fetch_assoc()) { echo implode(' | ', $row) . "\n"; }

echo "\n=== ACTIVE INSTANCES ===\n";
$r = $db->query("SELECT id, bug_id, flow_id, current_step_id, parent_instance_id, status FROM mantis_plugin_ProcessEngine_process_instance_table WHERE status IN ('ACTIVE','WAITING') ORDER BY id DESC LIMIT 10");
if ($r) { while ($row = $r->fetch_assoc()) { echo implode(' | ', array_map(function($v){return $v===null?'NULL':$v;}, $row)) . "\n"; } }

$db->close();
