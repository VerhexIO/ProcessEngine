<?php
/**
 * ProcessEngine - Alt Süreç Test Senaryosu Oluşturucu
 *
 * Bu betik mevcut sorunları subprocess adımına ilerletir ve
 * otomatik çocuk sorun oluşturulmasını tetikler.
 *
 * Kullanım: docker exec mantisbt bash -c 'cd /var/www/html && php scripts/create_subprocess_test.php'
 */

// MantisBT ortamını yükle
$t_mantis_dir = dirname( dirname( __FILE__ ) );
if( file_exists( $t_mantis_dir . '/core.php' ) ) {
    require_once( $t_mantis_dir . '/core.php' );
} else {
    require_once( '/var/www/html/core.php' );
}

// Plugin bağlamını zorla ayarla — plugin_table() fonksiyonu için gerekli
plugin_push_current( 'ProcessEngine' );

// CLI'de oturum açık olmadığından kimlik doğrulaması yap (argv ile kullanıcı adı verilebilir)
$t_login_user = isset( $argv[1] ) ? $argv[1] : 'administrator';
auth_attempt_script_login( $t_login_user );

// Plugin core dosyalarını yükle
$t_plugin_paths = array(
    dirname( __FILE__ ) . '/../plugins/ProcessEngine/core/',
    '/var/www/html/plugins/ProcessEngine/core/',
);

$t_core_path = '';
foreach( $t_plugin_paths as $t_path ) {
    if( file_exists( $t_path . 'process_api.php' ) ) {
        $t_core_path = $t_path;
        break;
    }
}

if( $t_core_path === '' ) {
    die( "HATA: Plugin core dosyaları bulunamadı.\n" );
}

require_once( $t_core_path . 'process_api.php' );
require_once( $t_core_path . 'subprocess_api.php' );
require_once( $t_core_path . 'flow_api.php' );
require_once( $t_core_path . 'sla_api.php' );

$t_is_cli = ( php_sapi_name() === 'cli' );
$t_nl = $t_is_cli ? "\n" : "<br>\n";

echo "=== ProcessEngine Alt Süreç Test Senaryosu ===" . $t_nl . $t_nl;

// Tablo adlarını doğrudan tanımla (plugin bağlamı sorunu önleme)
$t_pe_prefix = db_get_table( 'plugin_ProcessEngine_' );
// MantisBT db_get_table prefix mantığı
$t_step_table = 'mantis_plugin_ProcessEngine_step_table';
$t_flow_table = 'mantis_plugin_ProcessEngine_flow_definition_table';
$t_inst_table = 'mantis_plugin_ProcessEngine_process_instance_table';
$t_bug_table = db_get_table( 'bug' );

// 1. Subprocess adımı olan aktif akışları bul
$t_query = "SELECT s.*, f.name AS flow_name, f.id AS flow_id
    FROM $t_step_table s
    INNER JOIN $t_flow_table f ON s.flow_id = f.id
    WHERE s.step_type = 'subprocess'
    AND s.child_flow_id IS NOT NULL
    AND s.child_flow_id > 0
    AND f.status = 2
    ORDER BY f.id ASC, s.step_order ASC";
$t_result = db_query( $t_query );

$t_subprocess_steps = array();
while( $t_row = db_fetch_array( $t_result ) ) {
    $t_subprocess_steps[] = $t_row;
}

if( empty( $t_subprocess_steps ) ) {
    echo "HATA: Aktif akışlarda subprocess adımı bulunamadı." . $t_nl;
    echo "Önce seed data yüklediğinizden emin olun." . $t_nl;
    exit( 1 );
}

echo "Bulunan subprocess adımları:" . $t_nl;
foreach( $t_subprocess_steps as $t_ss ) {
    echo "  - Akış: " . $t_ss['flow_name'] . " (ID:" . $t_ss['flow_id'] . ")"
       . " | Adım: " . $t_ss['name'] . " (ID:" . $t_ss['id'] . ")"
       . " | MantisBT Durumu: " . $t_ss['mantis_status']
       . " | Alt Akış ID: " . $t_ss['child_flow_id']
       . $t_nl;
}
echo $t_nl;

// 2. Subprocess adımına karşılık gelen durumda olan sorunları bul
$t_created_count = 0;

foreach( $t_subprocess_steps as $t_ss ) {
    $t_flow_id = (int) $t_ss['flow_id'];
    $t_step_id = (int) $t_ss['id'];
    $t_mantis_status = (int) $t_ss['mantis_status'];
    $t_child_flow_id = (int) $t_ss['child_flow_id'];

    echo "--- Akış: " . $t_ss['flow_name'] . " | Adım: " . $t_ss['name'] . " ---" . $t_nl;

    // Bu durumda olan sorunları bul
    db_param_push();
    $t_bug_q = "SELECT b.id, b.summary, b.project_id, b.status
        FROM $t_bug_table b
        WHERE b.status = " . db_param() . "
        ORDER BY b.id ASC
        LIMIT 5";
    $t_bug_r = db_query( $t_bug_q, array( $t_mantis_status ) );

    $t_matching_bugs = array();
    while( $t_bug_row = db_fetch_array( $t_bug_r ) ) {
        $t_matching_bugs[] = $t_bug_row;
    }

    if( empty( $t_matching_bugs ) ) {
        echo "  Bu durumda (status=" . $t_mantis_status . ") sorun yok." . $t_nl;

        // Önceki durumda olan sorunları da kontrol et
        db_param_push();
        $t_prev_q = "SELECT b.id, b.summary, b.status
            FROM $t_bug_table b
            WHERE b.status < " . db_param() . " AND b.status >= 10
            ORDER BY b.id ASC
            LIMIT 3";
        $t_prev_r = db_query( $t_prev_q, array( $t_mantis_status ) );
        while( $t_prev_row = db_fetch_array( $t_prev_r ) ) {
            echo "  Aday sorun: #" . $t_prev_row['id'] . " (" . $t_prev_row['summary'] . ") - mevcut durum: " . $t_prev_row['status'] . $t_nl;
        }
        continue;
    }

    echo "  " . count( $t_matching_bugs ) . " sorun bulundu (status=" . $t_mantis_status . ")." . $t_nl;

    foreach( $t_matching_bugs as $t_mb ) {
        $t_bug_id = (int) $t_mb['id'];

        // Zaten instance'ı var mı?
        $t_existing = subprocess_get_instance( $t_bug_id );
        if( $t_existing !== null && $t_existing['status'] === 'WAITING' ) {
            echo "  #" . $t_bug_id . " zaten WAITING durumunda, atlanıyor." . $t_nl;
            continue;
        }

        // Zaten bu adımda çocuk oluşturulmuş mu?
        if( $t_existing !== null ) {
            $t_children = subprocess_get_children( (int) $t_existing['id'], $t_step_id );
            if( !empty( $t_children ) ) {
                echo "  #" . $t_bug_id . " için bu adımda zaten çocuk var, atlanıyor." . $t_nl;
                continue;
            }
        }

        // Process instance yoksa oluştur
        if( $t_existing === null ) {
            $t_inst_id = subprocess_create_instance( $t_bug_id, $t_flow_id, $t_step_id );
            echo "  #" . $t_bug_id . " için yeni process instance oluşturuldu (ID: " . $t_inst_id . ")" . $t_nl;
        } else {
            $t_inst_id = (int) $t_existing['id'];
            // Instance'ı bu adıma güncelle
            subprocess_update_current_step( $t_inst_id, $t_step_id );
            echo "  #" . $t_bug_id . " instance mevcut adıma güncellendi (Inst: " . $t_inst_id . ")" . $t_nl;
        }

        // Alt süreç oluştur
        $t_child_project_id = isset( $t_ss['child_project_id'] ) && (int) $t_ss['child_project_id'] > 0
            ? (int) $t_ss['child_project_id']
            : (int) $t_mb['project_id'];

        $t_child_bug_id = subprocess_create_child_issue(
            $t_bug_id,
            $t_child_flow_id,
            $t_child_project_id,
            $t_inst_id,
            $t_step_id
        );

        if( $t_child_bug_id !== null ) {
            echo "  BASARILI: #" . $t_bug_id . " -> Cocuk #" . $t_child_bug_id . " olusturuldu (Alt akis: " . $t_child_flow_id . ")" . $t_nl;
            $t_created_count++;
        } else {
            echo "  HATA: #" . $t_bug_id . " icin cocuk sorun olusturulamadi." . $t_nl;
        }

        // Maksimum 3 çocuk sorun oluştur
        if( $t_created_count >= 3 ) {
            break 2;
        }
    }
}

echo $t_nl . "=== Sonuc ===" . $t_nl;
echo "Toplam " . $t_created_count . " alt surec olusturuldu." . $t_nl;

// 3. Mevcut alt süreç durumunu göster
echo $t_nl . "=== Mevcut Alt Surec Durumlari ===" . $t_nl;
$t_inst_q = "SELECT pi.*, b.summary
    FROM $t_inst_table pi
    LEFT JOIN $t_bug_table b ON pi.bug_id = b.id
    WHERE pi.parent_instance_id IS NOT NULL
    ORDER BY pi.id DESC
    LIMIT 10";
$t_inst_r = db_query( $t_inst_q );
while( $t_inst_row = db_fetch_array( $t_inst_r ) ) {
    echo "  Inst #" . $t_inst_row['id']
       . " | Bug #" . $t_inst_row['bug_id']
       . " | Durum: " . $t_inst_row['status']
       . " | Ebeveyn Inst: " . $t_inst_row['parent_instance_id']
       . " | " . ( $t_inst_row['summary'] ? $t_inst_row['summary'] : '(silindi)' )
       . $t_nl;
}

echo $t_nl . "=== Ebeveyn Surec Durumlari ===" . $t_nl;
$t_parent_q = "SELECT pi.*, b.summary
    FROM $t_inst_table pi
    LEFT JOIN $t_bug_table b ON pi.bug_id = b.id
    WHERE pi.status = 'WAITING'
    ORDER BY pi.id DESC
    LIMIT 10";
$t_parent_r = db_query( $t_parent_q );
while( $t_parent_row = db_fetch_array( $t_parent_r ) ) {
    echo "  BEKLEYEN Inst #" . $t_parent_row['id']
       . " | Bug #" . $t_parent_row['bug_id']
       . " | " . ( $t_parent_row['summary'] ? $t_parent_row['summary'] : '(silindi)' )
       . $t_nl;
}

echo $t_nl . "Tamamlandi." . $t_nl;
