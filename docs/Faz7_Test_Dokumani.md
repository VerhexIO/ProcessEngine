# Faz 7 — CEO Test Dokümanı
## Dashboard Etkileşimi, Ağaç Görünümü ve Yetkilendirme

**Tarih:** 2 Mart 2026
**Ortam:** http://localhost:8080 (Docker)

---

## Bu Fazda Ne Yapıldı?

Önceki fazlarda dashboard salt okunur bir paneldi — sadece tabloyu ve istatistik kartlarını gösteriyordu. Artık:

| Yetenek | Açıklama |
|---------|----------|
| **Adım İlerleme Butonu** | Dashboard'dan tek tıkla bir sorunu sonraki adıma ilerletebilirsiniz |
| **SLA Güncelleme Butonu** | SLA durumunu anında yeniden hesaplayabilirsiniz |
| **Yetki Kontrolü** | Bu butonları sadece yeterli yetkiye sahip kullanıcılar görebilir |
| **Ağaç İkonu (Tüm Sorunlar)** | Artık her sorunun yanında süreç ağacı ikonu var (sadece alt süreç ebeveynleri değil) |
| **Göç Hatası Düzeltmesi** | Veri göç fonksiyonu artık doğru adım eşleşmesi yapıyor |
| **action_threshold Ayarı** | Yapılandırma sayfasından işlem yetki seviyesi ayarlanabiliyor |

---

## Kullanıcılar ve Yetkileri

| Kullanıcı | Şifre | Yetki Seviyesi | Dashboard Butonları |
|-----------|-------|----------------|---------------------|
| `administrator` | *(mevcut şifre)* | Administrator (90) | Görür ve kullanabilir |
| `yonetici_user` | *(mevcut şifre)* | Manager (70) | Görür ve kullanabilir |
| `fiyat_user` | *(mevcut şifre)* | Updater (40) | **GÖREMEZ** (varsayılan eşik: Developer=55) |
| `satis_user` | *(mevcut şifre)* | Reporter (25) | **GÖREMEZ** |

> **Not:** Varsayılan `action_threshold = DEVELOPER (55)`. Updater (40) ve Reporter (25) bu eşiğin altında olduğu için butonları göremez.

---

## Test Senaryoları

### Test 1: Yapılandırma Sayfasında Yeni Ayar

**Amaç:** `action_threshold` ayarının göründüğünü ve çalıştığını doğrulamak.

**Adımlar:**
1. `administrator` olarak giriş yapın → http://localhost:8080
2. **Yönetim** → **Eklentileri Yönet** → **Süreç Motoru Ayarları** (veya doğrudan: Yönetim menüsünde "Süreç Motoru Ayarları" linkine tıklayın)
3. Sayfada şu ayarları göreceksiniz:
   - Yönetim Erişim Seviyesi
   - Görüntüleme Erişim Seviyesi
   - **İşlem Yetki Seviyesi** ← YENİ
   - SLA Uyarı Yüzdesi
   - ...diğer ayarlar

**Beklenen Sonuç:**
- "İşlem Yetki Seviyesi" dropdown'ı görünür
- Altında açıklama metni var: "Dashboard üzerinden adım ilerleme ve SLA güncelleme işlemlerini yapabilecek minimum yetki seviyesi"
- Varsayılan değer: **developer** seçili
- Kaydet butonuna basınca ayar kaydedilir

**Ekstra Test:** Dropdown'u "updater" olarak değiştirip kaydedin → Test 5'te `fiyat_user` da butonları görebilmeli.

---

### Test 2: Dashboard'da Ağaç İkonu (Tüm Sorunlar)

**Amaç:** Süreç kaydı olan her sorunun yanında ağaç (sitemap) ikonunun göründüğünü doğrulamak.

**Adımlar:**
1. `administrator` olarak giriş yapın
2. Üst menüden **Süreç Paneli**'ne tıklayın
3. Tablodaki "Alt Süreçler" sütununa bakın

**Beklenen Sonuç:**
- **Her aktif sorunun** yanında mavi sitemap ikonu (fa-sitemap) görünür
- İkona tıklayınca **Süreç Ağacı** sayfası açılır (o sorunun ağaç yapısı)
- Alt süreç çocukları olan sorunlarda ikon yanında "X/Y tamamlandı" metni de görünür

**Önceki Davranış:** Ağaç ikonu sadece alt süreç ebeveynlerinde görünüyordu, artık tüm sorunlarda görünüyor.

---

### Test 3: Dashboard İşlemler Sütunu — Adım İlerleme

**Amaç:** "İlerlet" butonuyla bir sorunu sonraki adıma ilerletmek.

**Ön Koşul:** `administrator` ile giriş yapılmış olmalı.

**Adımlar:**
1. **Süreç Paneli**'ne gidin
2. Tablonun en sağında **"İşlemler"** sütununu görün
3. Aktif bir sorun bulun (örn: Bug #15, "Fiyat Analizi" adımında)
4. O satırdaki **mavi ileri butonu** (▶ ikonu) na tıklayın
5. Onay diyaloğu çıkacak: "Bu sorunu sonraki adıma ilerletmek istediğinize emin misiniz?"
6. **Tamam** / **OK** tıklayın

**Beklenen Sonuç:**
- Sayfa yenilenir
- Bug #15 artık **"Satınalma İnceleme"** adımında (bir sonraki adım)
- İlerleme çubuğu güncellenir (%40 → %60)
- SLA yeni adım için sıfırdan başlar (varsa)
- Süreç logu'na kayıt eklenir

**Doğrulama:** Bug #15'e tıklayıp sorun detay sayfasına gidin → "Süreç Zaman Çizelgesi" tablosunda yeni bir satır olmalı: "Sorun başarıyla sonraki adıma ilerletildi."

**Akış Adımları Referansı (Fiyat Talebi akışı):**
```
1. Talep Doğrulama    (MantisBT durum: 30 - onaylandı)
2. Fiyat Analizi       (MantisBT durum: 40 - teyit edildi)
3. Satınalma İnceleme  (MantisBT durum: 50 - atanmış)
4. Yönetim Onayı       (MantisBT durum: 80 - çözülmüş)
5. Teklif Teslim       (MantisBT durum: 90 - kapatılmış)
```

---

### Test 4: Dashboard İşlemler Sütunu — SLA Güncelle

**Amaç:** SLA durumu normal olmayan sorunlarda SLA güncelleme butonunun çalıştığını doğrulamak.

**Adımlar:**
1. **Süreç Paneli**'ne gidin
2. SLA durumu "WARNING" veya "EXCEEDED" olan bir sorun bulun
3. O satırdaki **sarı saat butonu** (🕐 ikonu) na tıklayın

**Beklenen Sonuç:**
- Sayfa yenilenir
- SLA durumu yeniden hesaplanır
- Durum doğruysa aynı kalır; zaman geçtikçe WARNING → EXCEEDED olabilir

> **Not:** SLA durumu "NORMAL" olan sorunlarda bu buton **görünmez** — bu tasarım gereğidir.

---

### Test 5: Yetki Kontrolü — Düşük Yetkili Kullanıcı

**Amaç:** `action_threshold` altındaki kullanıcıların butonları göremediğini doğrulamak.

**Adımlar:**
1. Oturumu kapatın
2. `fiyat_user` olarak giriş yapın (Updater, seviye 40)
3. **Süreç Paneli**'ne gidin
4. Tabloya bakın

**Beklenen Sonuç:**
- **"İşlemler" sütunu GÖRÜNMEZ** — tablo 9 sütunlu (10 değil)
- İleri ve saat butonları yok
- Diğer her şey (ağaç ikonu, filtreler, istatistikler) normal çalışır

**Karşılaştırma:** `administrator` ile giriş yapınca İşlemler sütunu görünür.

---

### Test 6: Yetki Eşiğini Değiştirme

**Amaç:** `action_threshold` ayarını değiştirerek düşük yetkili kullanıcılara da izin vermek.

**Adımlar:**
1. `administrator` olarak giriş yapın
2. **Yapılandırma** sayfasına gidin
3. "İşlem Yetki Seviyesi" → **updater** olarak değiştirin
4. **Kaydet**
5. Oturumu kapatın
6. `fiyat_user` ile giriş yapın (Updater, seviye 40)
7. **Süreç Paneli**'ne gidin

**Beklenen Sonuç:**
- Artık `fiyat_user` da **İşlemler sütununu** ve butonları görebilir
- İleri butonu çalışır

**Temizlik:** Testi bitirdikten sonra ayarı tekrar "developer"a döndürün.

---

### Test 7: Göç (Migration) Fonksiyonu Düzeltmesi

**Amaç:** Veri göç fonksiyonunun doğru step_id ataması yaptığını doğrulamak.

**Adımlar:**
1. `administrator` olarak giriş yapın
2. **Yapılandırma** sayfasına gidin
3. **"Verileri Göç Et"** butonuna tıklayın
4. Sonucu gözlemleyin

**Beklenen Sonuç:**
- "Göç ettirilecek veri bulunamadı" mesajı görünür (çünkü tüm sorunlar zaten göç ettirilmiş)
- **Hata almadan** tamamlanır

**Arka Plan:** Önceki sürümde göç fonksiyonu log tablosundaki eski step_id'leri kullanıyordu. Akış silinip yeniden oluşturulduğunda bu ID'ler geçersiz oluyordu. Artık sorunun MantisBT durumuna göre eşleşen adım bulunuyor.

---

### Test 8: Bitiş Adımına İlerleme

**Amaç:** Bir sorunu son adıma kadar ilerlettiğimizde instance'ın COMPLETED olduğunu doğrulamak.

**Adımlar:**
1. `administrator` olarak **Süreç Paneli**'ne gidin
2. "Satınalma İnceleme" adımında olan bir sorun bulun (örn: Bug #14 veya #16)
3. İlerlet butonuna tıklayın → "Yönetim Onayı" adımına geçer
4. Tekrar ilerlet → "Teklif Teslim & Kapanış" adımına geçer
5. Bu son adım — bitiş adımı olduğu için instance tamamlanır

**Beklenen Sonuç:**
- Sorun "tamamlanan" filtresi altında görünür
- Süreç ağacında durum "Tamamlandı" olarak gösterilir
- İlerlet butonu artık görünmez (bug_status >= 80)

---

### Test 9: Süreç Ağacı Sayfası

**Amaç:** Ağaç ikonuna tıklayınca süreç ağacının doğru göründüğünü doğrulamak.

**Adımlar:**
1. **Süreç Paneli**'nde herhangi bir sorunun ağaç ikonuna (🔗 sitemap) tıklayın
2. Süreç Ağacı sayfası açılır

**Beklenen Sonuç:**
- Sorunun ağaç yapısı gösterilir
- Kök süreç en üstte, alt süreçler altında (varsa)
- Her düğümde: Bug ID, durum, mevcut adım, ilerleme
- Erişim kısıtlı sorunlar "metadata" olarak görünür

---

## Hızlı Kontrol Listesi

| # | Kontrol | Durum |
|---|---------|-------|
| 1 | Config sayfasında "İşlem Yetki Seviyesi" dropdown görünüyor | ☐ |
| 2 | Dashboard'da her sorun satırında ağaç (sitemap) ikonu var | ☐ |
| 3 | Ağaç ikonuna tıklayınca Süreç Ağacı sayfası açılıyor | ☐ |
| 4 | Administrator ile İşlemler sütunu ve butonlar görünüyor | ☐ |
| 5 | İleri butonuna tıklayınca sorun sonraki adıma geçiyor | ☐ |
| 6 | İlerleme sonrası süreç logu kaydı oluşuyor | ☐ |
| 7 | SLA güncelle butonu çalışıyor | ☐ |
| 8 | fiyat_user ile İşlemler sütunu **görünmüyor** | ☐ |
| 9 | action_threshold ayarı değiştirilince yetki güncelleniyor | ☐ |
| 10 | Göç butonu hata vermeden çalışıyor | ☐ |
| 11 | Son adıma ilerletince instance COMPLETED oluyor | ☐ |
| 12 | Tamamlanan sorunlarda ileri butonu görünmüyor | ☐ |
| 13 | Normal MantisBT işlemleri (sorun düzenle, not ekle, atama) bozulmamış | ☐ |

---

## Bilinen Sınırlamalar

1. **Birden fazla geçerli geçiş:** Koşullu dallanmada birden fazla geçiş geçerliyse, ileri butonu ilk geçerli geçişi seçer. Kullanıcıya seçim sunulmaz.
2. **AJAX güvenlik token:** Her ilerleme işleminden sonra sayfa yenilenir. Arka arkaya çok hızlı tıklama token hatası verebilir.
3. **SLA butonu:** Sadece NORMAL olmayan (WARNING/EXCEEDED) SLA durumlarında görünür.

---

## Erişim Bilgileri

| Hizmet | URL |
|--------|-----|
| MantisBT | http://localhost:8080 |
| MailHog (e-posta) | http://localhost:8025 |
