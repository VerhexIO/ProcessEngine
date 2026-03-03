# Faz 11 — Test Dokümanı
## Süreç Motoru Paradigma Değişikliği

**Tarih:** 3 Mart 2026
**Ortam:** http://localhost:8080 (Docker)

---

## Bu Fazda Ne Yapıldı?

| Değişiklik | Açıklama |
|-----------|----------|
| **Birleşik Zaman Çizelgesi** | Süreç logları + MantisBT notları tek bir timeline'da, olay tipi bazlı ikon ve renklerle |
| **Yarı-Manuel Subprocess** | Otomatik çocuk oluşturma kaldırıldı — kullanıcı "Şimdi Aç" veya "Bağla" butonuyla kontrol eder |
| **Proje Bazlı Erişim Kontrolü** | Dashboard ve subprocess panelinde sadece erişilebilir projeler gösterilir |
| **İlerleme Modalı + Not Desteği** | `confirm()` yerine modal pencere, not alanı, zorunluluk kontrolü |
| **Adım Yaşam Döngüsü** | start_trigger (auto/manual/on_assign/on_create) + completion_criteria (manual/on_status/on_resolve) |
| **Akış Tasarımcısı Güncelleme** | Adım modalında 4 yeni alan: başlangıç tetikleyicisi, tamamlanma kriteri, hedef durum, not zorunlu |

---

## Ön Koşullar

### Veritabanı Schema Güncelleme
Plugin yüklenmişse MantisBT otomatik olarak yeni schema'yı uygular. Kontrol etmek için:

```bash
docker exec mantis_mysql mysql -u mantis -pmantis123 mantis -e "
  SHOW COLUMNS FROM mantis_plugin_ProcessEngine_log_table LIKE 'event_type';
  SHOW COLUMNS FROM mantis_plugin_ProcessEngine_log_table LIKE 'transition_label';
  SHOW COLUMNS FROM mantis_plugin_ProcessEngine_step_table LIKE 'note_required';
  SHOW COLUMNS FROM mantis_plugin_ProcessEngine_step_table LIKE 'start_trigger';
  SHOW COLUMNS FROM mantis_plugin_ProcessEngine_step_table LIKE 'completion_criteria';
  SHOW COLUMNS FROM mantis_plugin_ProcessEngine_step_table LIKE 'completion_status';
"
```

**Beklenen:** 6 yeni sütun listelenmeli. Sütunlar görünmüyorsa plugin'i Yönetim → Eklentileri Yönet sayfasından **Kaldır** ve tekrar **Yükle** yapın.

### Kullanıcılar

| Kullanıcı | Yetki | Projeler |
|-----------|-------|----------|
| `administrator` | Administrator (90) | Tüm projeler |
| `yonetici_user` | Manager (70) | Tüm projeler |
| `fiyat_user` | Updater (40) | Sadece kendi projesi |
| `satis_user` | Reporter (25) | Sadece kendi projesi |

### Akış Gereksinimleri
En az bir **AKTİF** akış olmalı. Mümkünse subprocess adımı içeren akış (örn: Fiyat Talebi akışı).

---

## Test Senaryoları

---

### TEST 1: Schema Doğrulama

**Amaç:** Faz 11 ile eklenen 6 yeni sütunun veritabanında mevcut olduğunu doğrulamak.

**Adımlar:**
1. Yukarıdaki SQL komutunu çalıştırın

**Beklenen Sonuç:**
| Tablo | Sütun | Tip | Varsayılan |
|-------|-------|-----|------------|
| log_table | event_type | varchar(32) | 'status_change' |
| log_table | transition_label | varchar(128) | '' |
| step_table | note_required | smallint | 0 |
| step_table | start_trigger | varchar(32) | 'auto' |
| step_table | completion_criteria | varchar(32) | 'manual' |
| step_table | completion_status | smallint | 0 |

---

### TEST 2: Birleşik Zaman Çizelgesi (Timeline)

**Amaç:** Süreç logları ve kullanıcı notlarının tek bir zaman çizelgesinde gösterildiğini doğrulamak.

**Adımlar:**
1. `administrator` olarak giriş yapın
2. Aktif bir akışa bağlı bir sorun açın (veya mevcut bir sorunu görüntüleyin)
3. Sorunun detay sayfasında "Süreç Bilgisi" bölümüne inin
4. "Süreç Zaman Çizelgesi" bölümünü inceleyin
5. Soruna bir **bugnote** (yorum) ekleyin
6. Sayfayı yenileyin ve timeline'ı tekrar kontrol edin

**Beklenen Sonuç:**
- Timeline kart tabanlı görünümde olmalı (eski tablo yerine)
- Her olay için farklı ikon ve renk:
  - 🔵 `fa-play-circle` — Süreç Başlatıldı
  - 🟢 `fa-forward` — Adım İlerletildi
  - 🔄 `fa-exchange` — Durum Değişikliği
  - 🟣 `fa-sitemap` — Alt Süreç Oluşturuldu
  - ⚪ `fa-comment` — Not Eklendi (bugnote)
  - 🟠 `fa-exclamation-triangle` — Akış Dışı Geçiş
- Bugnote eklendikten sonra timeline'da "Not Eklendi" kartı olarak görünmeli
- Tüm olaylar tarih sırasına göre (eskiden yeniye) sıralı olmalı
- Private (özel) bugnote'lar sadece yeterli yetkiye sahip kullanıcılara gösterilmeli

**Not:** `satis_user` ile giriş yapıp aynı sorunu görüntülerseniz, private bugnote'ları görmemelisi.

---

### TEST 3: İlerleme Modalı — Temel Akış

**Amaç:** Adım ilerletme butonuna basıldığında confirm() yerine modal açıldığını doğrulamak.

**Adımlar:**
1. `administrator` olarak giriş yapın
2. Aktif süreçte olan bir sorunun detay sayfasını açın
3. "Süreç Bilgisi" panelinde **"Adımı İlerlet"** butonunu bulun
4. Butona tıklayın

**Beklenen Sonuç:**
- Tarayıcı `confirm()` diyaloğu **AÇILMAMALI**
- Bunun yerine özel bir modal pencere açılmalı:
  - Başlık: "Adımı İlerlet"
  - Mevcut adım adı gösterilmeli (sol tarafta)
  - Sonraki adım adı gösterilmeli (sağ tarafta, ok ile)
  - Not alanı (textarea) bulunmalı
  - "İptal" ve "İlerlet" butonları olmalı
- "İptal" butonuna basılırsa modal kapanmalı, hiçbir işlem yapılmamalı
- "İlerlet" butonuna basılırsa sorun sonraki adıma geçmeli ve sayfa yenilenmeli
- Timeline'da "Adım İlerletildi" olayı görünmeli

---

### TEST 4: İlerleme Modalı — Not Zorunluluğu

**Amaç:** `note_required=1` olan adımlarda boş not ile ilerletmenin engellendiğini doğrulamak.

**Ön Koşul:** Bu testi yapmak için önce bir adımı `note_required=1` yapmanız gerekir (Test 10'da anlatılıyor).

**Adımlar:**
1. Akış Tasarımcısı'ndan bir adımın "Not Zorunlu" kutusunu işaretleyin ve kaydedin
2. Bu akışa bağlı bir sorun oluşturun
3. Sorunun detay sayfasında "Adımı İlerlet" butonuna tıklayın
4. Modalda not alanını **BOŞ** bırakın
5. "İlerlet" butonuna tıklayın

**Beklenen Sonuç:**
- Modal içinde **"Zorunlu"** rozeti gösterilmeli (not alanı yanında)
- Not boş bırakıldığında hata mesajı gösterilmeli: "Not alanı zorunludur."
- İlerletme işlemi **GERÇEKLEŞMEMELİ**
- Not alanına bir açıklama yazıp "İlerlet"e basıldığında normal şekilde ilerletme yapılmalı

---

### TEST 5: Yarı-Manuel Subprocess — "Şimdi Aç" Butonu

**Amaç:** Subprocess adımına gelindiğinde otomatik çocuk oluşturulmadığını ve kullanıcıya buton sunulduğunu doğrulamak.

**Ön Koşul:** Subprocess adımı içeren bir akış (örn: "Fiyat Talebi" akışında "Satınalma Onayı" adımı subprocess olabilir).

**Adımlar:**
1. `administrator` olarak giriş yapın
2. Subprocess adımı içeren akışa bağlı projede yeni bir sorun oluşturun
3. Sorunu, subprocess adımına gelene kadar ilerletin
4. Sorunun detay sayfasını açın

**Beklenen Sonuç:**
- Subprocess adımına gelindiğinde **OTOMATİK** çocuk sorun oluşturulmamalı
- "Süreç Bilgisi" panelinde uyarı kutusu görünmeli:
  ```
  ⚠ Bu adım için alt süreç oluşturulması gerekiyor
  Hedef Proje: [proje adı]
  [Şimdi Aç]  veya  [Talep No: ___] [Bağla]
  ```
- **"Adımı İlerlet"** butonu bu durumda **GİZLENMELİ**
- **"Şimdi Aç"** butonuna tıklayın

**"Şimdi Aç" Sonrası Beklenen:**
- Hedef projede yeni bir sorun oluşturulmalı
- Uyarı mesajıyla çocuk sorun numarası gösterilmeli
- Sayfa yenilendiğinde:
  - Ebeveyn süreç WAITING durumuna geçmeli
  - Subprocess panelinde çocuk sorun linki görünmeli
  - "Alt Süreç Bekleniyor" göstergesi aktif olmalı

---

### TEST 6: Yarı-Manuel Subprocess — "Bağla" Butonu

**Amaç:** Mevcut bir sorunu alt süreç olarak bağlama işlevinin çalıştığını doğrulamak.

**Ön Koşul:** Hedef projede zaten var olan bir sorun.

**Adımlar:**
1. Test 5'teki gibi subprocess adımına gelin (ama "Şimdi Aç" butonuna basmayın)
2. Hedef projede mevcut bir sorunun numarasını not edin (örn: #42)
3. Subprocess panelindeki metin kutusuna **42** yazın (# işareti ile veya olmadan)
4. **"Bağla"** butonuna tıklayın

**Beklenen Sonuç:**
- Mevcut sorun alt süreç olarak bağlanmalı
- Ebeveyn WAITING durumuna geçmeli
- Başarı mesajı gösterilmeli
- Geçersiz numara girilirse (örn: "abc") → "Geçerli bir talep numarası girin." uyarısı

---

### TEST 7: Subprocess — Zaten Çocuk Var Kontrolü

**Amaç:** Aynı adım için birden fazla alt süreç oluşturulmasının engellendiğini doğrulamak.

**Adımlar:**
1. Test 5'i tamamlayın (subprocess oluşturulmuş olsun)
2. Tarayıcı geliştirici konsolundan veya farklı bir yoldan tekrar "Şimdi Aç" aksiyonu tetiklemeye çalışın

**Beklenen Sonuç:**
- "Bu adımda zaten aktif bir alt süreç bulunuyor." hata mesajı döndürülmeli
- Yeni çocuk oluşturulmamalı

---

### TEST 8: Proje Bazlı Erişim Kontrolü — Dashboard

**Amaç:** Kullanıcıların dashboard'da sadece erişebildikleri projelerin sorunlarını gördüğünü doğrulamak.

**Ön Koşul:** En az 2 farklı proje, her birinde aktif süreçte olan sorunlar. Kullanıcıların proje erişimleri farklı olmalı.

**Adımlar:**
1. `administrator` olarak giriş yapın → Süreç Paneli'ni açın
2. Tüm projelerden sorunların listelendiğini doğrulayın
3. İstatistik kartlarındaki sayıları not edin
4. Çıkış yapın, `fiyat_user` olarak giriş yapın (sadece bir projeye erişimi var)
5. Süreç Paneli'ni açın

**Beklenen Sonuç:**
- `administrator`: Tüm projelerden sorunlar görünmeli, istatistikler tüm projeleri kapsamalı
- `fiyat_user`: **SADECE** erişebildiği projedeki sorunlar görünmeli
  - İstatistik kartları sadece bu projedeki sayıları göstermeli
  - Diğer projelerin sorunları **HİÇ** listelenmemeli
- `satis_user` ile test edilirse: Yine sadece kendi projesi görünmeli

---

### TEST 9: Proje Bazlı Erişim Kontrolü — Subprocess Paneli

**Amaç:** Subprocess panelinde ebeveyn/çocuk sorunların proje erişimine göre filtrelendiğini doğrulamak.

**Ön Koşul:** Farklı projelerdeki ebeveyn-çocuk süreç ilişkisi.

**Adımlar:**
1. `administrator` ile bir ebeveyn-çocuk ilişkisi olan sorunu görüntüleyin
2. Alt Süreçler panelinde ebeveyn ve çocuk linklerinin göründüğünü doğrulayın
3. Çıkış yapın, **çocuk projesine erişimi olmayan** bir kullanıcıyla giriş yapın
4. Aynı ebeveyn sorunu görüntüleyin

**Beklenen Sonuç:**
- `administrator`: Ebeveyn linki (tıklanabilir), çocuk listesi (tıklanabilir linkler)
- Erişimi olmayan kullanıcı:
  - **full** erişim → link ve detay tam görünür
  - **metadata** erişim → sadece sorun numarası (link yok)
  - **hidden** erişim → hiç gösterilmez

---

### TEST 10: Akış Tasarımcısı — Yeni Adım Alanları

**Amaç:** Akış tasarımcısında adım yaşam döngüsü alanlarının doğru çalıştığını doğrulamak.

**Adımlar:**
1. `administrator` olarak giriş yapın
2. Akış Tasarımcısı'nı açın → bir TASLAK akış seçin (veya yeni oluşturun)
3. Bir adıma çift tıklayarak düzenleme modalını açın
4. Aşağıdaki yeni alanları kontrol edin:
   - **Başlangıç Tetikleyicisi** (select): Otomatik / Atamada / Manuel / Oluşturmada
   - **Tamamlanma Kriteri** (select): Manuel / Duruma Göre / Çözümde
   - **Hedef Durum** (select): MantisBT durum listesi — **sadece "Duruma Göre" seçiliyse görünmeli**
   - **Not Zorunlu** (checkbox)
5. Şu değerleri ayarlayın:
   - Başlangıç Tetikleyicisi: `Atamada`
   - Tamamlanma Kriteri: `Duruma Göre`
   - Hedef Durum: `kapalı` (closed)
   - Not Zorunlu: ✓ (işaretli)
6. "Kaydet" butonuna tıklayın (modal içindeki)
7. Araç çubuğundaki "Kaydet" butonuna tıklayın (akışı kaydet)
8. Sayfayı yenileyin
9. Aynı adıma tekrar çift tıklayın

**Beklenen Sonuç:**
- Adım 4: Tüm alanlar görünmeli, varsayılan değerler doğru olmalı
- Adım 5: "Duruma Göre" seçildiğinde "Hedef Durum" alanı görünmeli; "Manuel" seçildiğinde gizlenmeli
- Adım 8-9: Kayıt sonrası sayfayı yenilediğinizde, aynı adımı açtığınızda:
  - Başlangıç Tetikleyicisi: `Atamada`
  - Tamamlanma Kriteri: `Duruma Göre`
  - Hedef Durum: `kapalı`
  - Not Zorunlu: ✓
  - **Değerler kaybolmamış olmalı**

---

### TEST 11: Çıkış Koşulu — on_status Kontrolü

**Amaç:** `completion_criteria = on_status` ayarlandığında, sorunun durumu hedef duruma ulaşmadan ilerletmenin engellendiğini doğrulamak.

**Ön Koşul:** Test 10'da bir adımı `completion_criteria = on_status`, `completion_status = 80` (çözümlenmiş) olarak ayarlayın ve akışı yayınlayın.

**Adımlar:**
1. Bu akışa bağlı projede yeni bir sorun oluşturun
2. Sorun ilgili adıma geldiğinde, **durumu henüz "çözümlenmiş" değilken** "Adımı İlerlet" butonuna tıklayın
3. Modal açılacak, "İlerlet" butonuna tıklayın

**Beklenen Sonuç:**
- Hata mesajı gösterilmeli: `İlerletmek için sorunun durumu "çözümlenmiş" olmalıdır.`
- Adım ilerletme **GERÇEKLEŞMEMELİ**
- Sorunun durumunu "çözümlenmiş" yapıp tekrar deneyin → normal şekilde ilerlemeli

---

### TEST 12: Çıkış Koşulu — on_resolve Kontrolü

**Amaç:** `completion_criteria = on_resolve` ayarındaki davranışı doğrulamak.

**Adımlar:**
1. Akış tasarımcısında bir adımın tamamlanma kriterini "Çözümde" olarak ayarlayın
2. Akışı kaydedin ve yayınlayın
3. Bu akışa bağlı projede sorun oluşturun
4. Sorun bu adıma geldiğinde, durum "çözümlenmiş"in altındayken ilerletmeyi deneyin

**Beklenen Sonuç:**
- Hata mesajı: `İlerletmek için sorunun çözülmüş durumda olması gerekir.`
- Sorun çözümlendikten sonra ilerletme başarılı olmalı

---

### TEST 13: Dil Stringleri

**Amaç:** Tüm yeni dil stringlerinin doğru yüklendiğini doğrulamak.

**Adımlar:**
1. MantisBT dil ayarını **Türkçe** yapın
2. Aşağıdaki sayfalarda Türkçe metinleri kontrol edin:
   - Sorun detay sayfası → Süreç Bilgisi paneli → Timeline
   - Sorun detay sayfası → "Adımı İlerlet" butonu → Modal
   - Subprocess adımında → Subprocess oluşturma paneli
   - Akış Tasarımcısı → Adım düzenleme modalı
3. Dil ayarını **English** yapın
4. Aynı sayfaları kontrol edin

**Beklenen Sonuç:**
- Türkçe'de: "Süreç Başlatıldı", "Adım İlerletildi", "Not Eklendi", "Şimdi Aç", "Bağla", "Başlangıç Tetikleyicisi", "Tamamlanma Kriteri", "Not Zorunlu" vb.
- İngilizce'de: "Process Started", "Step Advanced", "Note Added", "Create Now", "Link", "Start Trigger", "Completion Criteria", "Note Required" vb.
- Hiçbir yerde `MANTIS_ERROR` veya tanımsız string hatası **GÖRÜNMEMELI**

---

### TEST 14: Mevcut İşlevsellik Regresyon Testi

**Amaç:** Faz 11 değişikliklerinin mevcut işlevselliği bozmadığını doğrulamak.

**Kontrol Listesi:**

| # | Test | Beklenen |
|---|------|----------|
| 14.1 | Yeni sorun oluşturma (akışlı projede) | Sorun oluşur, süreç başlar, ilk adıma atanır |
| 14.2 | Sorun durumu değiştirme (standart MantisBT) | Durum değişir, süreç logu oluşur |
| 14.3 | Sorun güncelleme (açıklama, öncelik vb.) | Normal güncelleme çalışır, hata yok |
| 14.4 | Bugnote ekleme | Not eklenir, hata yok |
| 14.5 | Dashboard istatistik kartları | Sayılar doğru, erişim kontrolüne uygun |
| 14.6 | SLA güncelleme butonu (dashboard) | SLA yeniden hesaplanır |
| 14.7 | Akış doğrulama (validate) | Başlangıç/bitiş, döngü, subprocess kontrolleri çalışır |
| 14.8 | Akış yayınlama/pasife alma | Durum geçişleri çalışır |
| 14.9 | Süreç ağacı görüntüleyici | Ağaç yapısı doğru render edilir |
| 14.10 | Sorun silme | Orphan temizliği çalışır (instance iptal, SLA kapat) |

---

### TEST 15: Uçtan Uca Senaryo — Tam Süreç Döngüsü

**Amaç:** Tüm yeni özelliklerin birlikte çalıştığını uçtan uca doğrulamak.

**Senaryo:** Subprocess içeren bir fiyat talebi akışının tüm yaşam döngüsü.

**Adımlar:**
1. **Akış Hazırlığı** (Test 10 tamamlanmışsa atlayabilirsiniz)
   - Akış Tasarımcısı'nda 3 adımlı akış oluşturun:
     - Adım 1: "Talep Girişi" (normal, note_required=0, completion=manual)
     - Adım 2: "Satınalma Onayı" (subprocess, note_required=1)
     - Adım 3: "Kapanış" (normal, completion=on_resolve)
   - Akışı doğrulayın ve yayınlayın

2. **Sorun Oluşturma**
   - Akışa bağlı projede yeni sorun oluşturun
   - ✅ Süreç başlamalı, Adım 1'e atanmalı
   - ✅ Timeline'da "Süreç Başlatıldı" olayı görünmeli

3. **Adım 1 → Adım 2 İlerletme**
   - "Adımı İlerlet" butonuna tıklayın
   - Modal açılmalı, not **isteğe bağlı** (note_required=0)
   - "İlerlet" butonuna tıklayın
   - ✅ Adım 2'ye geçmeli
   - ✅ Timeline'da "Adım İlerletildi" olayı

4. **Subprocess Oluşturma (Adım 2)**
   - "Adımı İlerlet" butonu **GİZLİ** olmalı
   - Subprocess uyarı paneli görünmeli
   - "Şimdi Aç" butonuna tıklayın
   - ✅ Çocuk sorun oluşturulmalı
   - ✅ Ebeveyn WAITING durumuna geçmeli
   - ✅ Timeline'da "Alt Süreç Oluşturuldu" olayı

5. **Çocuk Sorunun Tamamlanması**
   - Çocuk sorunu açın, durumunu "çözümlenmiş" → "kapalı" yapın
   - ✅ Ebeveyn otomatik olarak Adım 3'e geçmeli
   - ✅ Timeline'da "Ebeveyn Süreç İlerletildi" olayı

6. **Adım 3 — Çıkış Koşulu Testi**
   - Sorun henüz çözülmemişken "Adımı İlerlet" deneyin
   - Modal açılacak, note_required=1 olduğu için not yazın
   - ✅ Hata: "İlerletmek için sorunun çözülmüş durumda olması gerekir."
   - Sorunu çözümleyin, tekrar "İlerlet" deneyin
   - ✅ Not alanına yazın (zorunlu) → İlerletme başarılı

7. **Süreç Tamamlanması**
   - ✅ Son adımdan sonra süreç tamamlanmalı
   - ✅ Timeline tüm olayları kronolojik sırada göstermeli

---

## Bilinen Sınırlamalar

- `start_trigger` alanları şu anda tanımlı ama henüz iş mantığında **aktif olarak zorlanmıyor** — ileride (Faz 12+) adım başlatma mantığı eklenecek. Şu an sadece akış tasarımcısında kaydedilir.
- `completion_criteria` ise `process_check_step_exit_conditions()` fonksiyonuyla aktif olarak çalışır.
- Zaman çizelgesinde çok fazla olay varsa scroll gerekebilir — pagination henüz yok.

---

## Sorun Giderme

| Belirti | Olası Neden | Çözüm |
|---------|-------------|-------|
| Yeni sütunlar görünmüyor | Schema güncellenmedi | Plugin'i kaldırıp tekrar yükleyin |
| "Adımı İlerlet" butonu yok | Yetki seviyesi yetersiz | `action_threshold` ayarını kontrol edin |
| Modal açılmıyor | JS hatası | Tarayıcı konsolunu (F12) kontrol edin |
| Subprocess paneli boş | Akışta subprocess adımı yok | Akış tasarımcısında adım tipini kontrol edin |
| Timeline'da bugnote yok | Yetki veya private bugnote | View state ve yetki seviyesini kontrol edin |
| "Şimdi Aç" butonu yok | Mevcut adım subprocess değil | Sorunu doğru adıma ilerletin |
| Proje filtreleme çalışmıyor | Kullanıcı tüm projelere erişebilir | Farklı proje erişimleri olan kullanıcılarla test edin |
