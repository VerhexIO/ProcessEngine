<?php
/**
 * ProcessEngine — E2E Test Fixture (idempotent)
 *
 * `tests/full-e2e.spec.js` testlerinin önkoşullarını MEVCUT bir veritabanı üzerinde
 * idempotent olarak kurar (seed guard'ı dolu DB'de hiçbir şey yapmadığı için gerekli):
 *
 *   1. Proje 2'nin (IFS Destek) aktif akışını "Hiyerarşik Fiyat Talebi" yapar,
 *      linear "Fiyat Talebi"yi proje 2'den çözer.
 *      → B1/C1: süreç başlar, panel render olur, adım ilerletilir.
 *      → D1-D4: adım 3 subprocess'ine ulaşılır, çocuk oluşturma/tamamlama akışı.
 *   2. "Hiyerarşik Fiyat Talebi" adım 4'ünü (Yönetim Onayı) çoklu hedefli subprocess yapar.
 *      (Terminal olmayan adım: son adım subprocess olsa girişte süreç COMPLETED olur, panel çıkmaz.)
 *      → D5: manuel çocuk bağlama girişi (.pe-link-child-input) yalnızca >1 hedefte render olur.
 *   3. reporter_test kullanıcısını (REPORTER) oluşturur. → G2.
 *
 * Çalıştırma:
 *   docker exec mantisbt php /var/www/html/scripts/e2e_fixture.php
 */

$g_bypass_headers = true;
chdir( dirname( __FILE__ ) . '/..' );
require_once( 'core.php' );

plugin_push_current( 'ProcessEngine' );
$t_flow_table   = plugin_table( 'flow_definition' );
$t_step_table   = plugin_table( 'step' );
$t_target_table = plugin_table( 'subprocess_target' );

/** Verilen ada sahip aktif akışın satırını döndürür (yoksa null). */
function pe_fixture_get_active_flow( $t_flow_table, $p_name ) {
    db_param_push();
    $t_result = db_query(
        "SELECT id, project_id FROM $t_flow_table WHERE name = " . db_param()
        . " AND status = 2 ORDER BY id DESC LIMIT 1",
        array( $p_name )
    );
    $t_row = db_fetch_array( $t_result );
    return $t_row === false ? null : $t_row;
}

// --- 1. Proje 2'nin aktif akışını ayarla (idempotent) ---
$t_flow_bindings = array(
    'Hiyerarşik Fiyat Talebi' => 2, // proje 2'ye bağla (subprocess içerir)
    'Fiyat Talebi'            => 0, // proje 2'den çöz (global)
    'Satınalma İnceleme'      => 3, // proje 3'e bağla → D5 manuel bağlama çocuğunun (proje 3) aktif akışı olmalı
);
foreach( $t_flow_bindings as $t_name => $t_target_pid ) {
    $t_row = pe_fixture_get_active_flow( $t_flow_table, $t_name );
    if( $t_row === null ) {
        echo "FLOW: '" . $t_name . "' aktif akisi bulunamadi (atlandi)\n";
        continue;
    }
    if( (int) $t_row['project_id'] === (int) $t_target_pid ) {
        echo "FLOW: '" . $t_name . "' zaten proje " . (int) $t_target_pid . " (atlandi)\n";
        continue;
    }
    db_param_push();
    db_query(
        "UPDATE $t_flow_table SET project_id = " . db_param() . ", subproject_id = 0 WHERE id = " . db_param(),
        array( (int) $t_target_pid, (int) $t_row['id'] )
    );
    echo "FLOW: '" . $t_name . "' (#" . (int) $t_row['id'] . ") proje " . (int) $t_target_pid . " olarak ayarlandi\n";
}

// --- 2. Hiyerarşik akış adımlarını ayarla (idempotent) ---
//   Adım 4 (Yönetim Onayı, terminal değil) → çoklu hedefli subprocess (D5 manuel bağlama).
//   Adım 5 (Teklif Hazırlama, terminal) → normal'e geri al (önceki denemelerin temizliği).
$t_hier  = pe_fixture_get_active_flow( $t_flow_table, 'Hiyerarşik Fiyat Talebi' );
$t_child = pe_fixture_get_active_flow( $t_flow_table, 'Satınalma İnceleme' );

if( $t_hier !== null && $t_child !== null ) {
    $t_child_flow_id = (int) $t_child['id'];

    // Adım id'lerini step_order ile bul
    $t_s4_id = 0;
    $t_s5_id = 0;
    db_param_push();
    $t_r = db_query(
        "SELECT id, step_order FROM $t_step_table WHERE flow_id = " . db_param() . " AND step_order IN (4,5)",
        array( (int) $t_hier['id'] )
    );
    while( $t_row = db_fetch_array( $t_r ) ) {
        if( (int) $t_row['step_order'] === 4 ) { $t_s4_id = (int) $t_row['id']; }
        if( (int) $t_row['step_order'] === 5 ) { $t_s5_id = (int) $t_row['id']; }
    }

    // Adım 5'i normal terminale geri al + hedeflerini sil
    if( $t_s5_id > 0 ) {
        db_param_push();
        db_query( "UPDATE $t_step_table SET step_type = 'normal' WHERE id = " . db_param(), array( $t_s5_id ) );
        db_param_push();
        db_query( "DELETE FROM $t_target_table WHERE step_id = " . db_param(), array( $t_s5_id ) );
    }

    // Adım 4'ü subprocess yap + 2 hedef (idempotent: hedefleri sıfırla, 2 ekle)
    if( $t_s4_id > 0 ) {
        db_param_push();
        db_query( "UPDATE $t_step_table SET step_type = 'subprocess' WHERE id = " . db_param(), array( $t_s4_id ) );
        db_param_push();
        db_query( "DELETE FROM $t_target_table WHERE step_id = " . db_param(), array( $t_s4_id ) );
        foreach( array( array( 3, 'Satınalma Süreci' ), array( 4, 'Teknik Değerlendirme' ) ) as $t_tgt ) {
            db_param_push();
            db_query(
                "INSERT INTO $t_target_table (step_id, child_flow_id, child_project_id, target_label)
                 VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ")",
                array( $t_s4_id, $t_child_flow_id, (int) $t_tgt[0], $t_tgt[1] )
            );
        }
        echo "STEP: adim 4 (#" . $t_s4_id . ") subprocess + 2 hedef (child_flow=" . $t_child_flow_id . "); adim 5 normal\n";
    } else {
        echo "STEP: 'Hiyerarşik Fiyat Talebi' adim 4 bulunamadi (atlandi)\n";
    }
} else {
    echo "STEP: hiyerarşik veya child ('Satınalma İnceleme') akis bulunamadi (atlandi)\n";
}

plugin_pop_current();

// --- 3. reporter_test kullanicisini olustur (idempotent) ---
$t_username = 'reporter_test';
$t_existing = user_get_id_by_name( $t_username );

if( $t_existing === false ) {
    user_create( $t_username, $t_username, 'reporter.test@mantisbt.local', REPORTER, false, true, 'E2E Reporter' );
    echo "USER: '" . $t_username . "' olusturuldu (REPORTER, parola=" . $t_username . ")\n";
} else {
    echo "USER: '" . $t_username . "' zaten var (#" . (int) $t_existing . ", atlandi)\n";
}

echo "E2E FIXTURE TAMAM\n";
