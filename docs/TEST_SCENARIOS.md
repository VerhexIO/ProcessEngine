# ProcessEngine — Test Senaryoları

## 1. Kurulum ve Tablo Kontrolü

**Amaç:** Plugin kurulumunun doğru çalıştığını ve tüm tabloların oluştuğunu doğrulamak.

**Adımlar:**
1. MantisBT yönetim paneline giriş yap
2. **Yönet → Eklentileri Yönet** sayfasına git
3. "Süreç Motoru" eklentisini bul ve **Kur** butonuna tıkla
4. Docker üzerinden tablo kontrolü çalıştır:
   ```bash
   docker exec mantis_mysql mysql -u mantis -pmantis123 mantis -e "SHOW TABLES LIKE 'mantis_plugin_ProcessEngine_%';"
   ```

**Beklenen Sonuç:**
- 7 tablo oluşturulmuş olmalı: `flow_definition_table`, `step_table`, `transition_table`, `log_table`, `sla_tracking_table`, `process_instance_table`, `subprocess_target_table`
- `step_table`'da `step_type`, `child_flow_id`, `child_project_id`, `wait_mode`, `step_instructions` sütunları mevcut
- `transition_table`'da `condition_type`, `label` sütunları mevcut
- `process_instance_table`'da gerekli indeksler mevcut
- `subprocess_target_table`'da `step_id`, `child_flow_id`, `child_project_id`, `target_label` sütunları mevcut

**Otomatik Test:**
```bash
docker exec mantisbt php /var/www/html/scripts/test_technical.php
```

---

## 2. Lineer Akış Testi

**Amaç:** Basit (alt süreçsiz) bir akışın doğru çalıştığını doğrulamak.

**Ön Koşul:** Aktif lineer akış mevcut (seed data yüklenmiş)

**Adımlar:**
1. Akışa sahip projede yeni sorun oluştur
2. Sorun detay sayfasında "Süreç Bilgisi" panelini kontrol et
3. Sorunun durumunu akıştaki bir sonraki adıma uygun MantisBT durumuna değiştir
4. Süreç logunda geçişin kayıt edildiğini doğrula
5. Tüm adımları tamamlayana kadar tekrarla

**Beklenen Sonuç:**
- Sorun oluşturulduğunda süreç otomatik başlar
- Her durum değişikliğinde `log_table`'a kayıt düşer
- `process_instance_table`'da süreç kaydı oluşturulur
- İlerleme çubuğu doğru adımı gösterir
- Tüm adımlar tamamlandığında süreç COMPLETED olur

---

## 3. Alt Süreç (Subprocess) Testi

**Amaç:** Hiyerarşik alt süreç oluşturma ve ebeveyn bekleme mekanizmasını doğrulamak.

**Ön Koşul:** Subprocess adımı içeren aktif akış mevcut

**Adımlar:**
1. Ebeveyn akışa sahip projede sorun oluştur
2. Sorunun durumunu subprocess adımına kadar ilerlet (dashboard veya bug view'dan)
3. Subprocess adımında "Şimdi Aç" butonuna tıklayarak çocuk sorun oluştur (yarı-manuel)
4. Ebeveyn sürecin WAITING durumuna geçtiğini kontrol et
5. Çocuk sorunu tamamla (tüm adımları ilerlet)
6. Ebeveynin otomatik ilerlediğini doğrula

**Beklenen Sonuç:**
- Subprocess adımına gelindiğinde "Şimdi Aç" butonu görünür (yarı-manuel oluşturma)
- Çoklu hedef tanımlıysa her hedef için ayrı "Şimdi Aç" butonu gösterilir
- Kullanıcı butona tıklayınca çocuk sorun hedef projede oluşturulur
- Mevcut sorunlar "Bağla" butonuyla da bağlanabilir
- Ebeveyn instance durumu ACTIVE → WAITING olur
- Çocuk tamamlandığında ebeveyn otomatik bir sonraki adıma ilerler
- Süreç ağacında ebeveyn-çocuk ilişkisi görüntülenir
- `wait_mode='all'` ise tüm çocuklar tamamlanmalı
- `wait_mode='any'` ise herhangi bir çocuk yeterli

**Otomatik Test:**
```bash
docker exec mantisbt php /var/www/html/scripts/create_subprocess_test.php
```

---

## 4. SLA Takibi Testi

**Amaç:** SLA hesaplama, uyarı ve eskalasyon mekanizmalarını doğrulamak.

**Ön Koşul:** SLA saati tanımlı adımlar içeren aktif akış

**Adımlar:**
1. SLA tanımlı bir adımda sorun oluştur
2. SLA cron betiğini çalıştır:
   ```bash
   docker exec mantisbt php /var/www/html/scripts/sla_cron.php
   ```
3. SLA Dashboard'unu kontrol et (**Süreç Paneli → SLA**)
4. Uyarı eşiğini (%80) geçen sorunları kontrol et
5. MailHog'da e-posta bildirimlerini doğrula (http://localhost:8025)

**Beklenen Sonuç:**
- `sla_tracking_table`'da deadline doğru hesaplanır (iş saatlerine göre)
- Uyarı eşiği geçildiğinde UYARI durumu set edilir
- SLA aşıldığında AŞIM durumu set edilir
- Eskalasyon seviyeleri (1.5x, 2x) tetiklenir
- E-posta bildirimleri gönderilir
- Dashboard'da SLA durumu renk kodları ile gösterilir

---

## 5. Koşullu Dallanma Testi

**Amaç:** Koşullu geçişlerin (field_equals, status_is) doğru çalıştığını doğrulamak.

**Ön Koşul:** Koşullu geçişler tanımlı akış (birden fazla çıkış yolu olan adım)

**Adımlar:**
1. Akış Tasarımcısı'nda koşullu geçişler oluştur:
   - Geçiş A: `condition_type=field_equals`, alan=priority, değer=40 (acil)
   - Geçiş B: `condition_type=none` (varsayılan)
2. Sorun oluştur, önceliği "acil" olarak ayarla
3. Durum değiştirdiğinde Geçiş A'nın seçildiğini doğrula
4. Farklı öncelikli sorunla tekrarla, Geçiş B'nin seçildiğini doğrula

**Beklenen Sonuç:**
- Koşul sağlandığında ilgili geçiş seçilir
- Koşul sağlanmadığında koşulsuz (fallback) geçiş kullanılır
- Süreç logunda "Otomatik dallanma: koşula göre X adımına yönlendirildi" kaydedilir

---

## 6. Dashboard İşlemleri Testi

**Amaç:** Dashboard'dan adım ilerleme ve SLA güncelleme işlemlerini doğrulamak.

**Ön Koşul:** Aktif süreçlere sahip sorunlar mevcut, DEVELOPER veya üstü yetki

**Adımlar:**
1. **Süreç Paneli** sayfasına git
2. Aktif süreçlerin listesini kontrol et
3. Bir sorunun yanındaki "İlerlet" butonuna tıkla
4. Onay diyaloğunu kabul et
5. Sorunun bir sonraki adıma ilerlediğini doğrula
6. "SLA Güncelle" butonuna tıkla ve SLA durumunun güncellendiğini kontrol et

**Beklenen Sonuç:**
- İlerletme sonrası MantisBT durum geçmişinde kayıt görünür (`history_log_event`)
- Sorunun bug_status değeri güncellenir
- SLA güncelleme sonrası güncel zamana göre SLA durumu yenilenir
- DEVELOPER altı kullanıcılar işlem butonlarını göremez
- Farklı projeden sorunlara erişim engellenir (`access_has_bug_level`)

---

## 7. Erişim Kontrolü Testi

**Amaç:** Yetki seviyelerine göre erişim kısıtlamalarını doğrulamak.

**Adımlar:**
1. **REPORTER** kullanıcısıyla giriş yap:
   - Süreç Paneli görüntülenebilmeli
   - Akış Tasarımcısı'na erişim engellenmeli
   - Dashboard'dan işlem butonları görünmemeli
2. **DEVELOPER** kullanıcısıyla giriş yap:
   - Süreç Paneli ve işlem butonları görünmeli
   - Akış Tasarımcısı'na erişim engellenmeli
3. **MANAGER** kullanıcısıyla giriş yap:
   - Tüm sayfalara erişim mümkün olmalı
   - Akış oluşturma ve yönetimi yapılabilmeli
4. Alt süreç ağacında farklı projedeki çocuk sorunlara erişim:
   - Proje erişimi olan kullanıcı detay görebilmeli
   - Erişimi olmayan kullanıcı sadece metadata görmeli

**Beklenen Sonuç:**
- `manage_threshold` (MANAGER): Akış tasarımı ve yapılandırma
- `view_threshold` (REPORTER): Süreç bilgilerini görme
- `action_threshold` (DEVELOPER): Dashboard'dan işlem yapabilme
- CSRF token koruması AJAX endpoint'lerinde çalışır

---

## 8. Silme Koruması ve Veri Bütünlüğü Testi

**Amaç:** Veri bütünlüğü koruma mekanizmalarını doğrulamak.

**Adımlar:**
1. **Aktif akış silme koruması:**
   - Aktif süreçlere bağlı bir akışı silmeyi dene
   - Hata mesajı: "Bu akış silinemiyor çünkü bağlı aktif süreçler var"
2. **Aktif akış düzenleme koruması:**
   - AKTİF durumdaki akışı kaydetmeyi dene
   - Hata mesajı: "Aktif akışlar düzenlenemez"
3. **Sorun silme — orphan temizliği:**
   - Aktif süreç kaydı olan bir sorunu sil
   - `process_instance_table`'da durumun CANCELLED olduğunu doğrula
   - `sla_tracking_table`'da açık kayıtların kapatıldığını doğrula
   - Ebeveyn sorun silindiğinde çocuk instance'ların da iptal edildiğini doğrula
4. **Subprocess derinlik limiti:**
   - 10'dan fazla iç içe alt süreç tetiklenmeye çalışıldığında
   - `error_log`'da uyarı mesajı görünmeli ve ilerleme durmalı
5. **Döngü koruması:**
   - Akış Tasarımcısı'nda döngüsel alt süreç referansı oluşturmayı dene
   - Doğrulama hatası: "Alt süreç zincirinde döngü tespit edildi"

**Beklenen Sonuç:**
- Aktif akışlar silinemez ve düzenlenemez
- Silinen sorunların süreç kayıtları temizlenir
- Alt süreç derinlik limiti (10 seviye) uygulanır
- Döngüsel referanslar akış doğrulamada engellenir
- `bug_exists()` kontrolleri veri bütünlüğünü korur
