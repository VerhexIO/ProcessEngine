<?php
/**
 * ProcessEngine Teknik Test Betiği
 * Container içinde çalıştırılır: docker exec mantisbt php /var/www/html/scripts/test_technical.php [kullanıcı_adı]
 */
$g_bypass_headers = true;
require_once( dirname( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'core.php' );

$t_base_dir = dirname( dirname( __FILE__ ) );
$t_plugin_dir = $t_base_dir . '/plugins/ProcessEngine';

$pass = 0;
$fail = 0;
$warn = 0;

function test_ok($label, $detail = '') {
    global $pass;
    $pass++;
    echo "  [OK] " . $label . ($detail ? " — " . $detail : "") . PHP_EOL;
}
function test_fail($label, $detail = '') {
    global $fail;
    $fail++;
    echo "  [HATA] " . $label . ($detail ? " — " . $detail : "") . PHP_EOL;
}
function test_warn($label, $detail = '') {
    global $warn;
    $warn++;
    echo "  [UYARI] " . $label . ($detail ? " — " . $detail : "") . PHP_EOL;
}

// ============================================================
echo "=== 1. TABLO KONTROLU ===" . PHP_EOL;
$tables = array(
    "flow_definition_table",
    "step_table",
    "transition_table",
    "log_table",
    "sla_tracking_table",
    "process_instance_table"
);
foreach($tables as $t) {
    $full = "mantis_plugin_ProcessEngine_" . $t;
    try {
        $r = db_query("SELECT COUNT(*) AS cnt FROM " . $full);
        $row = db_fetch_array($r);
        test_ok(str_pad($t, 30), $row["cnt"] . " kayit");
    } catch(Exception $e) {
        test_fail($t, $e->getMessage());
    }
}

// ============================================================
echo PHP_EOL . "=== 2. STEP_TABLE YENI SUTUNLAR ===" . PHP_EOL;
$required_cols = array("step_type", "child_flow_id", "child_project_id", "wait_mode");
$r = db_query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mantis_plugin_ProcessEngine_step_table'");
$existing = array();
while($row = db_fetch_array($r)) {
    $existing[] = $row["COLUMN_NAME"];
}
foreach($required_cols as $col) {
    if(in_array($col, $existing)) {
        test_ok("step_table." . $col);
    } else {
        test_fail("step_table." . $col, "sutun bulunamadi");
    }
}

// ============================================================
echo PHP_EOL . "=== 3. TRANSITION_TABLE YENI SUTUNLAR ===" . PHP_EOL;
$required_cols2 = array("condition_type", "label");
$r = db_query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mantis_plugin_ProcessEngine_transition_table'");
$existing2 = array();
while($row = db_fetch_array($r)) {
    $existing2[] = $row["COLUMN_NAME"];
}
foreach($required_cols2 as $col) {
    if(in_array($col, $existing2)) {
        test_ok("transition_table." . $col);
    } else {
        test_fail("transition_table." . $col, "sutun bulunamadi");
    }
}

// ============================================================
echo PHP_EOL . "=== 4. PROCESS_INSTANCE TABLO YAPISI ===" . PHP_EOL;
$required_inst = array("id","bug_id","flow_id","current_step_id","parent_instance_id","parent_step_id","status","created_at","completed_at");
$r = db_query("SELECT COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mantis_plugin_ProcessEngine_process_instance_table'");
$existing3 = array();
while($row = db_fetch_array($r)) {
    $existing3[$row["COLUMN_NAME"]] = $row["COLUMN_TYPE"];
}
foreach($required_inst as $col) {
    if(isset($existing3[$col])) {
        test_ok("process_instance." . $col, $existing3[$col]);
    } else {
        test_fail("process_instance." . $col, "sutun bulunamadi");
    }
}

// ============================================================
echo PHP_EOL . "=== 5. INDEX KONTROLU ===" . PHP_EOL;
$r = db_query("SELECT INDEX_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mantis_plugin_ProcessEngine_process_instance_table'");
$indexes = array();
while($row = db_fetch_array($r)) {
    $indexes[$row["INDEX_NAME"]][] = $row["COLUMN_NAME"];
}
foreach($indexes as $name => $cols) {
    test_ok($name, implode(", ", $cols));
}
$expected_idx = array("bug_id", "parent_instance_id", "status");
foreach($expected_idx as $col) {
    $found = false;
    foreach($indexes as $name => $cols) {
        if(in_array($col, $cols)) { $found = true; break; }
    }
    if(!$found) test_warn("Index eksik: " . $col);
}

// ============================================================
echo PHP_EOL . "=== 6. SUBPROCESS_API FONKSIYON TESTI ===" . PHP_EOL;
$api_file = $t_plugin_dir . '/core/subprocess_api.php';
if(file_exists($api_file)) {
    require_once($api_file);
    $funcs = array(
        "subprocess_create_instance",
        "subprocess_get_instance",
        "subprocess_get_all_instances",
        "subprocess_get_tree",
        "subprocess_get_ancestry",
        "subprocess_get_children",
        "subprocess_create_child_issue",
        "subprocess_on_child_completed",
        "subprocess_check_wait_condition",
        "subprocess_advance_parent",
        "subprocess_validate_no_cycle",
        "subprocess_is_completed"
    );
    foreach($funcs as $f) {
        if(function_exists($f)) {
            test_ok($f . "()");
        } else {
            test_fail($f . "()", "fonksiyon tanimli degil");
        }
    }
} else {
    test_fail("subprocess_api.php bulunamadi!");
}

// ============================================================
echo PHP_EOL . "=== 7. PROCESS_API FONKSIYON TESTI ===" . PHP_EOL;
require_once($t_plugin_dir . '/core/process_api.php');
$funcs2 = array(
    "process_evaluate_condition",
    "process_get_valid_transitions",
    "process_get_departments",
    "process_get_dashboard_stats",
    "process_get_dashboard_bugs",
    "process_log_status_change",
    "process_get_flow_progress"
);
foreach($funcs2 as $f) {
    if(function_exists($f)) {
        test_ok($f . "()");
    } else {
        test_fail($f . "()", "fonksiyon tanimli degil");
    }
}

// ============================================================
echo PHP_EOL . "=== 8. SLA_API AGAC FONKSIYONLARI ===" . PHP_EOL;
require_once($t_plugin_dir . '/core/sla_api.php');
$funcs3 = array("sla_get_tree_summary", "sla_aggregate_tree_node");
foreach($funcs3 as $f) {
    if(function_exists($f)) {
        test_ok($f . "()");
    } else {
        test_fail($f . "()", "fonksiyon tanimli degil");
    }
}

// ============================================================
echo PHP_EOL . "=== 9. FLOW_API FONKSIYON TESTI ===" . PHP_EOL;
require_once($t_plugin_dir . '/core/flow_api.php');
$funcs4 = array("flow_validate", "flow_get_steps", "flow_get_transitions");
foreach($funcs4 as $f) {
    if(function_exists($f)) {
        test_ok($f . "()");
    } else {
        test_fail($f . "()", "fonksiyon tanimli degil");
    }
}

// ============================================================
echo PHP_EOL . "=== 10. PLUGIN EVENT KAYITLARI ===" . PHP_EOL;
$plugin_file = $t_plugin_dir . '/ProcessEngine.php';
$content = file_get_contents($plugin_file);
$events = array(
    "EVENT_REPORT_BUG",
    "EVENT_UPDATE_BUG",
    "EVENT_VIEW_BUG_EXTRA",
    "EVENT_BUG_DELETED",
    "EVENT_PROCESSENGINE_CHILD_CREATED",
    "EVENT_PROCESSENGINE_CHILD_COMPLETED"
);
foreach($events as $ev) {
    if(strpos($content, $ev) !== false) {
        test_ok($ev);
    } else {
        test_fail($ev, "event bulunamadi");
    }
}

// ============================================================
echo PHP_EOL . "=== 11. DIL DOSYALARI ===" . PHP_EOL;
$tr = file_get_contents($t_plugin_dir . '/lang/strings_turkish.txt');
$en = file_get_contents($t_plugin_dir . '/lang/strings_english.txt');
$required_strings = array(
    "subprocess", "step_type", "step_type_normal", "step_type_subprocess",
    "child_flow", "child_project", "wait_mode", "wait_mode_all", "wait_mode_any",
    "process_tree", "view_process_tree", "parent_process", "child_processes",
    "instance_status", "instance_active", "instance_waiting", "instance_completed", "instance_cancelled",
    "migrate_data", "migrate_success",
    "condition_field", "condition_value", "condition_type", "condition_none",
    "branching_auto", "sla_tree_summary", "sla_tree_total", "sla_tree_worst",
    "parent_link", "child_summary", "subprocess_of",
    "tree_restricted_access", "tree_no_tree", "tree_root"
);
$tr_ok = 0; $en_ok = 0; $missing = 0;
foreach($required_strings as $s) {
    $key = 'plugin_ProcessEngine_' . $s;
    $tr_found = strpos($tr, $key) !== false;
    $en_found = strpos($en, $key) !== false;
    if($tr_found && $en_found) {
        $tr_ok++; $en_ok++;
    } elseif($tr_found) {
        test_warn($s, "sadece TR");
    } elseif($en_found) {
        test_warn($s, "sadece EN");
    } else {
        test_fail($s, "dil stringi bulunamadi");
        $missing++;
    }
}
test_ok("TR: " . $tr_ok . "/" . count($required_strings) . ", EN: " . $en_ok . "/" . count($required_strings) . " dil stringi");

// ============================================================
echo PHP_EOL . "=== 12. DOSYA VARLIK KONTROLU ===" . PHP_EOL;
$files = array(
    $t_plugin_dir . '/core/subprocess_api.php',
    $t_plugin_dir . '/pages/process_tree.php',
    $t_plugin_dir . '/files/process_tree.css',
    $t_plugin_dir . '/files/process_tree.js',
    $t_plugin_dir . '/db/seed_data.php',
    $t_plugin_dir . '/ProcessEngine.php',
    $t_plugin_dir . '/pages/dashboard.php',
    $t_plugin_dir . '/pages/flow_designer.php',
    $t_plugin_dir . '/pages/config_page.php'
);
foreach($files as $f) {
    if(file_exists($f)) {
        test_ok(basename($f), filesize($f) . " bytes");
    } else {
        test_fail(basename($f), "dosya bulunamadi");
    }
}

// ============================================================
echo PHP_EOL . "=== 13. MEVCUT VERI BUTUNLUGU ===" . PHP_EOL;
$r = db_query("SELECT COUNT(*) as c FROM mantis_bug_table");
$row = db_fetch_array($r);
test_ok("Toplam sorun: " . $row["c"]);

$r = db_query("SELECT COUNT(DISTINCT bug_id) as c FROM mantis_plugin_ProcessEngine_log_table");
$row = db_fetch_array($r);
test_ok("Surec logu olan sorun: " . $row["c"]);

$r = db_query("SELECT COUNT(*) as c FROM mantis_plugin_ProcessEngine_sla_tracking_table");
$row = db_fetch_array($r);
test_ok("SLA takip kaydi: " . $row["c"]);

// Varsayilan degerler kontrolu
$r = db_query("SELECT COUNT(*) as c FROM mantis_plugin_ProcessEngine_step_table WHERE step_type='normal'");
$row = db_fetch_array($r);
$r2 = db_query("SELECT COUNT(*) as c FROM mantis_plugin_ProcessEngine_step_table");
$row2 = db_fetch_array($r2);
if((int)$row["c"] === (int)$row2["c"]) {
    test_ok("Tum mevcut adimlar step_type=normal (geriye uyumlu)");
} else {
    test_ok("Mevcut adimlar: " . $row["c"] . " normal, " . ((int)$row2["c"] - (int)$row["c"]) . " subprocess");
}

// ============================================================
echo PHP_EOL . "=== 14. SUBPROCESS DONGU KONTROLU ===" . PHP_EOL;
$t_step_table = "mantis_plugin_ProcessEngine_step_table";
$r = db_query("SELECT id FROM mantis_plugin_ProcessEngine_flow_definition_table LIMIT 1");
$row = db_fetch_array($r);
if($row) {
    $flow_id = (int)$row["id"];
    db_param_push();
    $r2 = db_query("SELECT child_flow_id FROM $t_step_table WHERE flow_id=" . db_param() . " AND step_type='subprocess' AND child_flow_id IS NOT NULL", array($flow_id));
    $has_cycle = false;
    while($row2 = db_fetch_array($r2)) {
        if((int)$row2["child_flow_id"] === $flow_id) {
            $has_cycle = true;
        }
    }
    if(!$has_cycle) {
        test_ok("Flow #" . $flow_id . " dongu yok");
    } else {
        test_fail("Flow #" . $flow_id . " kendi kendine dongu tespit edildi");
    }
} else {
    test_warn("Hic akis bulunamadi");
}

// ============================================================
echo PHP_EOL . "=== 15. DASHBOARD STATS FONKSIYON TESTI ===" . PHP_EOL;
try {
    $t_login_user = isset($argv[1]) ? $argv[1] : 'administrator';
    auth_attempt_script_login($t_login_user);
    $stats = process_get_dashboard_stats();
    test_ok("process_get_dashboard_stats()", "total=" . $stats["total"] . ", active=" . $stats["active"] . ", sla_exceeded=" . $stats["sla_exceeded"]);
    if(isset($stats["waiting_subprocesses"])) {
        test_ok("waiting_subprocesses alani mevcut", "deger=" . $stats["waiting_subprocesses"]);
    } else {
        test_fail("waiting_subprocesses alani eksik");
    }
} catch(Exception $e) {
    test_warn("Dashboard stats", $e->getMessage());
}

// ============================================================
echo PHP_EOL . "========================================" . PHP_EOL;
echo "SONUC: " . $pass . " basarili, " . $fail . " hata, " . $warn . " uyari" . PHP_EOL;
echo "========================================" . PHP_EOL;

if($fail > 0) {
    exit(1);
}
