<?php
$db = new mysqli('db', 'mantis', 'mantis123', 'mantis');
if ($db->connect_error) { echo "ERR: " . $db->connect_error; exit(1); }

echo "=== TRANSITIONS (Flow 1) ===\n";
$r = $db->query("SELECT t.id, t.from_step_id, t.to_step_id, s1.name as from_name, s2.name as to_name
    FROM mantis_plugin_ProcessEngine_transition_table t
    LEFT JOIN mantis_plugin_ProcessEngine_step_table s1 ON t.from_step_id=s1.id
    LEFT JOIN mantis_plugin_ProcessEngine_step_table s2 ON t.to_step_id=s2.id
    WHERE t.flow_id=1 ORDER BY t.from_step_id");
if ($r) { while ($row = $r->fetch_assoc()) { echo implode(' | ', $row) . "\n"; } }

echo "\n=== TRANSITIONS (Flow 6) ===\n";
$r = $db->query("SELECT t.id, t.from_step_id, t.to_step_id, s1.name as from_name, s2.name as to_name
    FROM mantis_plugin_ProcessEngine_transition_table t
    LEFT JOIN mantis_plugin_ProcessEngine_step_table s1 ON t.from_step_id=s1.id
    LEFT JOIN mantis_plugin_ProcessEngine_step_table s2 ON t.to_step_id=s2.id
    WHERE t.flow_id=6 ORDER BY t.from_step_id");
if ($r) { while ($row = $r->fetch_assoc()) { echo implode(' | ', $row) . "\n"; } }

echo "\n=== STEPS (Flow 1) detail ===\n";
$r = $db->query("SELECT id, name, mantis_status, step_type, child_flow_id, child_project_id, sla_hours, handler_id FROM mantis_plugin_ProcessEngine_step_table WHERE flow_id=1 ORDER BY step_order");
if ($r) { while ($row = $r->fetch_assoc()) { echo implode(' | ', array_map(function($v){return $v===null?'NULL':$v;}, $row)) . "\n"; } }

echo "\n=== STEPS (Flow 6) detail ===\n";
$r = $db->query("SELECT id, name, mantis_status, step_type, child_flow_id, child_project_id, sla_hours, handler_id FROM mantis_plugin_ProcessEngine_step_table WHERE flow_id=6 ORDER BY step_order");
if ($r) { while ($row = $r->fetch_assoc()) { echo implode(' | ', array_map(function($v){return $v===null?'NULL':$v;}, $row)) . "\n"; } }

echo "\n=== MANTIS STATUS ENUM ===\n";
$r = $db->query("SELECT config_id, value FROM mantis_config_table WHERE config_id='status_enum_string' LIMIT 1");
if ($r) { $row = $r->fetch_assoc(); if ($row) echo $row['value'] . "\n"; }

echo "\n=== BUG_RESOLVED_STATUS_THRESHOLD ===\n";
$r = $db->query("SELECT config_id, value FROM mantis_config_table WHERE config_id='bug_resolved_status_threshold' LIMIT 1");
if ($r) { $row = $r->fetch_assoc(); if ($row) echo $row['value'] . "\n"; else echo "default (80)\n"; }

$db->close();
