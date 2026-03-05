# ProcessEngine Eklentisi - Kullanım Kılavuzu

> **Sürüm:** 1.0.0
> **Uyumluluk:** MantisBT 2.24.2 (Schema 210), PHP 7.x, MySQL 8.0
> **Geliştirici:** VerhexIO

---

## İçindekiler

1. [Genel Bakış](#1-genel-bakış)
2. [Kurulum](#2-kurulum)
3. [İlk Yapılandırma](#3-ilk-yapılandırma)
4. [Akış Tasarımcısı (Flow Designer)](#4-akış-tasarımcısı)
5. [Süreç Paneli (Dashboard)](#5-süreç-paneli)
6. [Sorun Detay Sayfası](#6-sorun-detay-sayfası)
7. [SLA Yönetimi](#7-sla-yönetimi)
8. [Eskalasyon Sistemi](#8-eskalasyon-sistemi)
9. [Cron Görevi](#9-cron-görevi)
10. [Yapılandırma](#10-yapılandırma)
11. [Hiyerarşik Süreç Yönetimi](#11-hiyerarşik-süreç-yönetimi)
12. [Rapor Sayfası](#12-rapor-sayfası)
13. [Mimari ve Kısıtlamalar](#13-mimari-ve-kısıtlamalar)
14. [Sorun Giderme](#14-sorun-giderme)

---

## 1. Genel Bakış

ProcessEngine, MantisBT üzerinde çalışan bir **süreç motoru** eklentisidir. Departmanlar arası iş akışlarını (fiyat talebi, ürün geliştirme vb.) görsel olarak tasarlamanıza, SLA sürelerini takip etmenize ve darboğazları tespit etmenize olanak tanır.

### Temel Yetenekler

| Özellik | Açıklama |
|---------|----------|
| **Görsel Akış Tasarımcısı** | Sürükle-bırak ile iş akışı tasarlama (SVG tabanlı) |
| **SLA Takibi** | Her adım için dakika hassasiyetinde SLA süresi tanımlama ve izleme |
| **Otomatik Eskalasyon** | SLA aşımında 4 seviyeli bildirim sistemi |
| **Otomatik Sorumlu Atama** | Her adıma sorumlu kişi tanımlama, durum değiştiğinde otomatik atama |
| **Süreç Paneli** | Canlı özet kartları, filtrelenebilir talep tablosu (durum, departman, yıl/ay) |
| **İlerleme Ağacı** | Sorun detay sayfasında dikey ağaç yapısıyla süreç ilerlemesi |
| **Birleşik Zaman Çizelgesi** | Süreç logları ve MantisBT notları tek kronolojik görünümde |
| **Rapor Sayfası** | Chart.js grafikleri ile departman performansı, SLA dağılımı, aylık trend |
| **Hiyerarşik Alt Süreçler** | Yarı-manuel çocuk sorun oluşturma, çoklu hedef desteği |
| **Koşullu Dallanma** | Alan eşittir / durum eşittir koşullarıyla otomatik yönlendirme |
| **Darboğaz Analizi** | En çok SLA aşımı yaşanan adımların raporlanması |
| **Dinamik Departman Yönetimi** | Yapılandırma sayfasından departman tanımlama |

### Çalışma Modeli

Plugin **"Tek Sorun, Çoklu Adım"** modeli ile çalışır: Bir MantisBT sorunu, tanımlı akış adımları boyunca ilerler. Her durum değişikliği bir adım geçişi olarak loglanır ve SLA takibi başlatılır. Ek olarak **hiyerarşik alt süreç** desteği ile farklı projelerdeki sorunlar ebeveyn-çocuk ilişkisiyle bağlanabilir.

---

## 2. Kurulum

### Ön Koşullar

- MantisBT 2.24.2 kurulu ve çalışıyor olmalı
- MySQL 8.0 veritabanı
- PHP 7.x

### Kurulum Adımları

1. `plugins/ProcessEngine/` klasörünü MantisBT kurulum dizinine kopyalayın.

2. MantisBT'ye **administrator** olarak giriş yapın.

3. **Yönetim > Eklentileri Yönet** sayfasına gidin.

4. "Available Plugins" bölümünde **Process Engine 1.0.0** satırındaki **Install** butonuna tıklayın.

5. Eklenti 7 veritabanı tablosu ve gerekli indeksleri oluşturur:
   - `mantis_plugin_ProcessEngine_flow_definition_table`
   - `mantis_plugin_ProcessEngine_step_table`
   - `mantis_plugin_ProcessEngine_transition_table`
   - `mantis_plugin_ProcessEngine_log_table`
   - `mantis_plugin_ProcessEngine_sla_tracking_table`
   - `mantis_plugin_ProcessEngine_process_instance_table`
   - `mantis_plugin_ProcessEngine_subprocess_target_table`

6. Kurulum tamamlandığında üst menüde **"Süreç Paneli"** bağlantısı görünür.

### Docker Ortamı

```bash
docker compose up -d          # Ortamı başlat
docker compose down            # Ortamı durdur
docker compose logs mantisbt   # Logları görüntüle
```

- **MantisBT:** http://localhost:8080
- **MailHog (e-posta testi):** http://localhost:8025

---

## 3. İlk Yapılandırma

Kurulumdan sonra **Yönetim > Süreç Motoru Ayarları** sayfasına gidin.

### Departman Tanımlama

**Departmanlar** alanına organizasyonunuzdaki departmanları virgülle ayırarak yazın:

```
Satış, Fiyatlandırma, Satış Operasyon, Satınalma, ArGe, Yönetim, Kalite
```

Bu liste, akış tasarımcısında ve dashboard'daki departman filtresinde kullanılır. Akış tasarımcısında "Diğer" seçeneği ile henüz tanımlı olmayan departmanlar da serbest metin olarak girilebilir.

### Varsayılan Değerler

| Ayar | Varsayılan | Açıklama |
|------|-----------|----------|
| Yönetim Erişim Seviyesi | MANAGER | Akış tasarımı ve yapılandırma erişimi |
| Görüntüleme Erişim Seviyesi | REPORTER | Süreç paneli ve zaman çizelgesi görüntüleme |
| İşlem Yetki Seviyesi | DEVELOPER | Dashboard'dan adım ilerleme/geri alma |
| SLA Uyarı Yüzdesi | %80 | Bu oran dolunca uyarı e-postası gönderilir |
| İş Saati Başlangıç | 09:00 | SLA hesabı bu saatten başlar (HH:MM formatı) |
| İş Saati Bitiş | 18:00 | SLA hesabı bu saatte durur (HH:MM formatı) |
| Çalışma Günleri | 1,2,3,4,5 | Pazartesi-Cuma (1=Pzt, 7=Paz) |
| Departmanlar | (boş) | Virgülle ayrılmış departman adları |
| Otomatik Süreçler | Kapalı | Global oto kilit — açıksa otomatik tetikleyiciler çalışır |

### Örnek Veri Yükleme

Yapılandırma sayfasının alt bölümündeki **"Örnek Veri Yükle"** butonuna tıklayarak 4 hazır akış şablonu yükleyebilirsiniz (2 lineer + 1 hiyerarşik + 1 alt akış). Bu, eklentiyi hızlıca denemek için kullanışlıdır.

---

## 4. Akış Tasarımcısı

**Erişim:** Süreç Paneli > **Akış Tasarımcısı** veya Yönetim > **Süreç Motoru Ayarları** > **Akış Tasarımcısı**
**Gerekli Yetki:** Yönetim Erişim Seviyesi (varsayılan: MANAGER)

### Akış Listesi

İlk açılışta tüm akışların listesi görüntülenir. **Yeni Akış** butonuna tıklayarak boş bir akış oluşturabilirsiniz.

### Akış Durumları

| Durum | Kod | Açıklama |
|-------|-----|----------|
| **TASLAK** | 0 | Yeni oluşturulmuş, serbestçe düzenlenebilir |
| **ONAY BEKLİYOR** | 1 | Yayın için onay aşamasında |
| **AKTİF** | 2 | Canlı ortamda çalışıyor, değişiklik yapılamaz |

### Görsel Tasarımcı Kullanımı

Bir akışa tıkladığınızda SVG tabanlı görsel tasarımcı açılır.

#### Araç Çubuğu

| Buton | İşlem |
|-------|-------|
| **Adım Ekle** | Kanvasa yeni bir adım düğümü ekler |
| **Kaydet** | Tüm adımları ve geçişleri sunucuya kaydeder (AJAX) |
| **Doğrula** | Akış grafiğini doğrulama kontrolünden geçirir |
| **Yayınla** | Doğrulama başarılıysa akışı AKTİF duruma geçirir |

#### Adım Özellikleri

Her adım düğümüne çift tıklayarak düzenleyebilirsiniz:

| Özellik | Açıklama |
|---------|----------|
| **Adım Adı** | Gösterim adı (ör: "Fiyat Talebi Girişi") |
| **Departman** | Sorumlu departman (yapılandırmadan dinamik liste + serbest giriş) |
| **SLA (Saat)** | Bu adım için tanımlanan maksimum süre |
| **Rol** | Gerekli MantisBT rolü (reporter, developer, manager, vb.) |
| **MantisBT Durumu** | Bu adıma karşılık gelen MantisBT issue durumu |
| **Sorumlu Kişi** | Bu adıma atanacak kullanıcı (otomatik atama için) |
| **Adım Tipi** | "Normal" veya "Alt Süreç" — alt süreç seçilirse hedef akış/proje tanımlanır |
| **Adım Talimatları** | Kullanıcıya gösterilecek bilgi metni / yönerge (isteğe bağlı) |
| **Başlatma Tetikleyicisi** | Otomatik veya Manuel adım başlatma |
| **Tamamlama Kriteri** | Manuel, durum değişikliği veya çözülme ile tamamlama |

#### Otomatik Sorumlu Atama

Bir adıma **Sorumlu Kişi** tanımlandığında:
- Yeni sorun oluşturulduğunda, başlangıç adımının sorumlusu otomatik olarak atanır.
- Sorun durumu değiştiğinde, yeni adımın sorumlusu otomatik olarak atanır.
- "Otomatik atama yok" seçilirse mevcut sorumlu değiştirilmez.

#### Geçişler (Transitions)

Bir adımın **çıkış portuna** (sağ taraf, mavi daire) tıklayıp hedef adımın üzerine bırakarak geçiş oluşturabilirsiniz. Bir geçişi silmek için üzerine çift tıklayın.

#### Sağ Tık Menüsü

Bir adım üzerine sağ tıklayarak:
- **Düzenle** — Adım özelliklerini düzenle
- **Sil** — Adımı ve bağlı geçişleri sil

### Akış Doğrulama Kuralları

**Doğrula** butonuna basıldığında şu kontroller yapılır:

1. **Başlangıç Düğümü:** Tam olarak 1 adet gelen geçişi olmayan adım olmalı
2. **Bitiş Düğümü:** En az 1 adet giden geçişi olmayan adım olmalı
3. **Döngü Kontrolü:** Akışta döngü (cycle) olmamalı
4. **Erişilebilirlik:** Tüm adımlar başlangıçtan erişilebilir olmalı

### Yayınlama

- Yayınlama öncesi doğrulama otomatik çalışır
- Aynı projedeki önceki AKTİF akış otomatik olarak TASLAK durumuna döner
- Her projede aynı anda yalnızca 1 AKTİF akış bulunabilir

---

## 5. Süreç Paneli

**Erişim:** Üst menü > **Süreç Paneli**
**Gerekli Yetki:** Görüntüleme Erişim Seviyesi (varsayılan: REPORTER)

### Özet Kartları

Sayfanın üst kısmında 6 özet kartı bulunur:

| Kart | Renk | Açıklama |
|------|------|----------|
| **Toplam Talepler** | Beyaz | Süreç logu olan toplam benzersiz talep sayısı |
| **Aktif Süreçler** | Mavi | SLA takibi devam eden talepler |
| **SLA Aşımı** | Kırmızı | SLA süresi aşılmış talepler |
| **Ort. Çözüm Süresi** | Mor | Tamamlanan SLA kayıtlarının ortalama süresi (saat) |
| **Bugün Güncellenen** | Yeşil | Bugün durum değişikliği olan talepler |
| **Onay Bekleyen** | Turuncu | Henüz çözülmemiş (status < 80) talepler |

### Filtreler

Talep tablosu dört tür filtre ile daraltılabilir:

**Durum Filtreleri:**
- **Tümü** — Tüm süreçli talepler
- **Aktif** — Devam eden talepler (status < 80)
- **SLA Aşımı** — SLA süresi aşılmış talepler
- **Tamamlanan** — Çözülmüş/kapatılmış talepler (status ≥ 80)

**Departman Filtresi:**
Dropdown menüden belirli bir departmanı seçerek o departmandaki talepleri filtreleyebilirsiniz.

**Yıl/Ay Filtresi:**
Sorunların oluşturulma tarihine göre yıl ve ay seçerek filtreleyebilirsiniz. Tüm filtreler birlikte çalışır — durum + departman + yıl + ay kombinasyonu uygulanabilir.

### Talep Tablosu

Her satırda şu bilgiler gösterilir:

| Sütun | Açıklama |
|-------|----------|
| Talep No | Tıklanabilir bağlantı (talep detay sayfasına yönlendirir) |
| Başlık | Talebin özeti |
| Mevcut Adım | Süreçte hangi adımda olduğu |
| Departman | Adımın ait olduğu departman |
| İlerleme | Yüzde olarak ilerleme çubuğu |
| Sorumlu | Talebe atanmış kişi |
| SLA Durumu | NORMAL / WARNING / EXCEEDED |
| Açılma Tarihi | Sorunun oluşturulma tarihi |
| Güncelleme | Son durum değişikliği tarihi |
| İşlemler | İlerlet / Geri Al butonları (yetki seviyesine göre) |

---

## 6. Sorun Detay Sayfası

Her sorunun detay sayfasında (view.php) süreç bilgileri otomatik olarak 3 bölüm halinde gösterilir:

### Süreç Bilgi Paneli

Sorun bir aktif akışla eşleşiyorsa, şu bilgiler bilgi panelinde görüntülenir:

| Alan | Açıklama |
|------|----------|
| **Mevcut Adım** | Sorunun bulunduğu akış adımı |
| **Departman** | Mevcut adımın departmanı |
| **Adım Durumu** | Başlangıç zamanı ve geçen süre |
| **İlerleme** | "Adım X / Y" formatında ilerleme bilgisi |
| **SLA Kalan** | Kalan SLA süresi veya "Süre aşıldı" |
| **Sorumlu** | Mevcut adıma tanımlı sorumlu kişi |
| **Adım Talimatları** | Tanımlıysa adım yönerge metni |

### İlerleme ve Geri Alma

- **İlerlet** butonu: Sorun sayfasından bir sonraki adıma ilerleme. Tıklandığında ilerleme modalı açılır — mevcut adım, sonraki adım bilgisi ve varsa adım talimatları gösterilir.
- **Geri Al** butonu: Bir önceki adıma geri alma.
- Her iki işlem de yetki seviyesine (action_threshold) tabidir.

### Dikey İlerleme Ağacı

Akışın tüm adımları dikey ağaç yapısında gösterilir. Subprocess adımları dallanarak alt süreç adımlarını da görüntüler:

- **Yeşil daire + onay işareti**: Tamamlanan adımlar
- **Mavi daire (vurgulu)**: Mevcut adım
- **Gri daire**: Bekleyen adımlar
- **Turuncu rozet**: Subprocess adımları (alt süreç dalları ile)

Her adımın yanında adım adı, departman ve varsa adım talimatları gösterilir. Subprocess adımında "Şimdi Aç" ve "Bağla" butonları da yer alır.

### Birleşik Zaman Çizelgesi

Süreç logları ve MantisBT notları tek bir kronolojik zaman çizelgesinde görüntülenir:

- **Süreç başlatma** (yeşil play ikonu): Süreç ilk başladığında
- **Adım ilerleme** (mavi ok ikonu): Adım geçişleri, önceki/yeni durum bilgisi
- **Geri alma** (turuncu geri al ikonu): Rollback işlemleri
- **Kullanıcı notları** (yorum ikonu): MantisBT bugnote'ları
- **Alt süreç olayları**: Çocuk sorun oluşturma ve tamamlanma

Her satırda tarih, kullanıcı ve olay detayı gösterilir.

---

## 7. SLA Yönetimi

### SLA Nasıl Çalışır?

1. Bir talep durum değiştirdiğinde, eklenti aktif akıştaki eşleşen adımı bulur.
2. Adımın `SLA (Saat)` değeri > 0 ise SLA takibi başlar.
3. SLA süresi **iş saatleri** üzerinden hesaplanır (hafta sonu ve mesai dışı saatler hariç).
4. Önceki adımdaki SLA takibi otomatik olarak tamamlanır.

### SLA Durumları

| Durum | Koşul | Renk |
|-------|-------|------|
| **NORMAL** | SLA süresinin <%80'i | Yeşil |
| **WARNING** | SLA süresinin %80'i doldu | Sarı |
| **EXCEEDED** | SLA süresi doldu | Kırmızı |

### SLA Kontrol Sayfası

**Erişim:** Yapılandırma > **SLA Kontrol**

Bu sayfa aktif SLA takiplerini tablo halinde gösterir ve **Darboğazları Göster** bağlantısı ile en çok SLA aşımı yaşanan adımları raporlar.

---

## 8. Eskalasyon Sistemi

SLA aşımında otomatik eskalasyon devreye girer. 4 seviyeli bildirim sistemi:

| Seviye | Koşul | Alıcı | E-posta Konusu |
|--------|-------|-------|----------------|
| **UYARI (Sarı)** | SLA süresinin %80'i doldu | Atanan kullanıcı | [MantisBT] SLA Uyarısı - Talep #X |
| **AŞIM (Kırmızı)** | SLA süresi doldu | Atanan + departman yöneticisi | [MantisBT] SLA Aşımı - Talep #X |
| **Eskalasyon Lv1** | SLA süresinin 1.5 katı | MANAGER rolü | [MantisBT] Eskalasyon Seviye 1 - Talep #X |
| **Eskalasyon Lv2** | SLA süresinin 2 katı | ADMINISTRATOR | [MantisBT] Eskalasyon Seviye 2 - Talep #X |

---

## 9. Cron Görevi

SLA kontrolünün otomatik çalışabilmesi için cron görevi ayarlanmalıdır.

### Manuel Çalıştırma

```bash
docker exec mantisbt php /var/www/html/scripts/sla_cron.php
```

### Crontab Ayarı (Üretim Ortamı)

Her 15 dakikada bir SLA kontrolü:

```cron
*/15 * * * * php /var/www/html/scripts/sla_cron.php > /dev/null 2>&1
```

### Cron Ne Yapar?

1. Tüm aktif (completed_at IS NULL) SLA takiplerini tarar
2. Geçen iş saatlerini hesaplar
3. Uyarı eşiğini geçenlere WARNING durumu atar
4. SLA süresi dolanlara EXCEEDED durumu atar
5. 1.5x aşımda Eskalasyon Lv1, 2x aşımda Lv2 tetikler
6. Gerekli e-posta bildirimlerini gönderir

---

## 10. Yapılandırma

**Erişim:** Yönetim > **Süreç Motoru Ayarları**
**Gerekli Yetki:** MANAGER

### Ayarlar

| Ayar | Tip | Aralık | Açıklama |
|------|-----|--------|----------|
| **Yönetim Erişim Seviyesi** | Seçim | MantisBT erişim seviyeleri | Akış tasarımı ve yapılandırma için minimum yetki |
| **Görüntüleme Erişim Seviyesi** | Seçim | MantisBT erişim seviyeleri | Süreç paneli ve zaman çizelgesini görme yetkisi |
| **İşlem Yetki Seviyesi** | Seçim | MantisBT erişim seviyeleri | Dashboard'dan adım ilerleme/geri alma yetkisi |
| **SLA Uyarı Yüzdesi** | Sayı | 50-99 | SLA uyarı e-postasının gönderildiği eşik değeri |
| **İş Saati Başlangıç** | Metin | HH:MM | SLA hesaplamasında günün başlangıç saati (ör: 09:00) |
| **İş Saati Bitiş** | Metin | HH:MM | SLA hesaplamasında günün bitiş saati (ör: 18:00) |
| **Çalışma Günleri** | Metin | 1-7 arası | Virgülle ayrılmış gün numaraları (1=Pzt, 7=Paz) |
| **Departmanlar** | Metin | Serbest | Virgülle ayrılmış departman adları |
| **Otomatik Süreçler** | Açık/Kapalı | ON/OFF | Global oto kilit — kapalıyken otomatik tetikleyiciler engellenir |

### Hızlı Erişim Butonları

Yapılandırma sayfasının alt bölümünde:
- **Akış Tasarımcısı** — Doğrudan tasarımcıya git
- **SLA Kontrol** — SLA izleme sayfasına git
- **Örnek Veri Yükle** — Hazır akış şablonları yükle

---

## 11. Hiyerarşik Süreç Yönetimi (Alt Süreçler)

### Genel Bakış

ProcessEngine artık **hiyerarşik süreç yönetimini** destekler. Bir ana süreç akışındaki belirli adımlar, farklı projelerde çalışan **alt süreçleri** (subprocess) tetikleyebilir. Ebeveyn süreç, alt süreçlerin tamamlanmasını bekler ve ardından otomatik olarak ilerler.

### Kavramlar

| Kavram | Açıklama |
|--------|----------|
| **Ana Süreç (Ebeveyn)** | Kendi akışında ilerleyen birincil talep |
| **Alt Süreç (Çocuk)** | Ebeveyn sürecin bir adımı tarafından tetiklenen bağımsız talep |
| **Subprocess Adımı** | Akış tasarımcısında "Alt Süreç" tipi olarak işaretlenen adım |
| **Bekleme Modu** | `Tümünü Bekle` veya `Herhangi Birini Bekle` — ebeveynin ne zaman ilerleyeceğini belirler |
| **Süreç Ağacı** | Ebeveyn-çocuk ilişkilerinin iç içe görünümü |
| **Süreç Instance** | Bir sorunun belirli bir akış üzerindeki çalışma kaydı |

### Alt Süreç Oluşturma

#### Akış Tasarımcısında Subprocess Adımı Tanımlama

1. Akış tasarımcısında bir adım oluşturun
2. Adım düzenleme penceresinde **Adım Tipi** = "Alt Süreç" seçin
3. **Çoklu Hedef** listesinden bir veya daha fazla hedef tanımlayın:
   - Her hedef için: **Hedef Akış**, **Hedef Proje** ve **Etiket** belirtin
4. **Bekleme Modu** seçin:
   - **Tümünü Bekle**: Tüm alt süreçler tamamlanınca ebeveyn ilerler
   - **Herhangi Birini Bekle**: İlk tamamlanan alt süreç sonrası ebeveyn ilerler

#### Yarı-Manuel Çocuk Sorun Oluşturma

Sorun subprocess adımına ulaştığında:
- Sorun detay sayfasında her hedef için **"Şimdi Aç"** butonu görünür
- Kullanıcı butona tıklayarak çocuk sorun oluşturur (hedef projede)
- Çocuk talep, seçilen alt süreç akışının başlangıç adımından başlar
- Ebeveyn süreç **BEKLEMEDE** durumuna geçer
- Çocuk tamamlandığında ebeveyn otomatik ilerler

#### Manuel Bağlama

Mevcut bir talebi ebeveyn sürecin alt süreci olarak bağlayabilirsiniz:
1. Sorun detay sayfasındaki **"Bağla"** alanına mevcut sorun ID'sini girin
2. **"Bağla"** butonuna tıklayın
3. Ebeveyn subprocess adımında bekliyorsa, çocuk tamamlandığında ebeveyn ilerler

### Süreç Ağacı Görünümü

Her sorunun detay sayfasında **"Süreç Ağacını Görüntüle"** butonu ile tüm hiyerarşik yapıyı inceleyebilirsiniz:

```
[Ana Talep #100 — Adım 4/5 — %80] ── AKTİF
  ├── [Alt Talep #101 — Tamamlandı — %100] ── TAMAMLANDI
  ├── [Alt Talep #102 — Adım 2/3 — %66] ── AKTİF
  │     └── [Torun Talep #105 — Beklemede] ── AKTİF
  └── [Alt Talep #103 — Adım 1/2] ── AKTİF
```

#### Görünürlük Kontrolü

Süreç ağacında farklı projelerdeki talepler görünürlük seviyelerine göre gösterilir:

| Seviye | Koşul | Gösterim |
|--------|-------|----------|
| **Tam Erişim** | Kullanıcı projeye erişim yetkisine sahip | Talep ID, özet, tüm detaylar — tıklanabilir link |
| **Meta Veri** | Kullanıcı projeyi görebilir ama yeterli yetkisi yok | Sadece adım adı, departman, ilerleme % |
| **Gizli** | Kullanıcının hiçbir erişimi yok | Gösterilmez |

### Sorun Detay Sayfası

Hiyerarşik süreç bilgileri sorun detay sayfasında otomatik gösterilir:

- **Ebeveyn Bağlantısı**: Bu sorun bir alt süreç ise → "Ebeveyn Süreç: #123" tıklanabilir link
- **Alt Süreç Listesi**: Bu sorunun alt süreçleri varsa → tablo halinde (ID, durum, mevcut adım)
- **"Süreç Ağacını Görüntüle" butonu**: Tüm ağacı gösteren sayfaya yönlendirir

### Süreç Paneli (Dashboard)

Dashboard tablosunda alt süreç bilgileri de gösterilir:
- **Açılma Tarihi** sütunu ile sorunun oluşturulma tarihi görüntülenir
- Alt süreçleri olan taleplerde ağaç ikonu ile süreç ağacına gidilir
- **Filtreler**: Durum (tümü/aktif/SLA aşımı/tamamlanan), departman, yıl ve ay filtreleri
- **İşlem butonları**: İlerlet ve Geri Al (DEVELOPER+ yetkisi ile)
- **Global SLA Kontrol** butonu (MANAGER+ yetkisi ile): Tüm aktif SLA'ları günceller

### Koşullu Geçişler (Dallanma)

Akış tasarımcısında geçişlere koşul tanımlayabilirsiniz:

1. Bir geçiş okuna çift tıklayın
2. **Koşul Tipi** seçin:
   - **Koşulsuz**: Her zaman geçerli (varsayılan)
   - **Alan Eşittir**: Bug alanı belirli bir değere eşit mi? (priority, severity, handler_id vb.)
   - **Durum Eşittir**: MantisBT durumu belirli bir değere eşit mi?
3. **Etiket** ekleyin (ok üzerinde gösterilir)

Bir adımdan birden fazla koşullu geçiş çıkabilir. İlk koşulu sağlayan geçiş kullanılır (öncelik: ID sırasına göre).

### Döngü Koruması

Akış doğrulamasında subprocess zincirlerinde döngü kontrolü yapılır:
- A akışı → B akışını alt süreç olarak çağırıyorsa → B akışı A'yı çağıramaz
- Döngü tespit edildiğinde doğrulama hatası verilir

---

## 12. Rapor Sayfası

**Erişim:** Süreç Paneli > **Rapor**
**Gerekli Yetki:** Görüntüleme Erişim Seviyesi (varsayılan: REPORTER)

### Filtreler

Rapor sayfasında aşağıdaki filtreler kullanılabilir:
- **Tarih Aralığı**: Başlangıç ve bitiş tarihi
- **Proje**: Belirli bir proje seçimi
- **Departman**: Departman bazlı filtreleme
- **Akış**: Belirli bir akış tanımı
- **Durum**: Aktif / Tamamlanan / Tümü

### Özet Kartları

Sayfanın üst kısmında 4 özet kartı bulunur: toplam süreç, aktif süreç, tamamlanan süreç, SLA aşımı sayısı.

### Grafikler (Chart.js)

| Grafik | Tip | Açıklama |
|--------|-----|----------|
| **Departman Performansı** | Bar | Departman bazında ortalama süreç süresi (saat) |
| **SLA Dağılımı** | Pie | Normal / Uyarı / Aşım oranları |
| **Adım Süre Dağılımı** | Yatay Bar | Her adımın ortalama süresi (saat) |
| **Aylık Trend** | Line | Aylık süreç sayısı ve SLA aşım trendi |

### Detay Tablosu

Filtrelenmiş süreç kayıtlarının tablo görünümü: sorun ID, özet, akış, adım, departman, sorumlu, durum, süre, SLA durumu.

---

## 13. Mimari ve Kısıtlamalar

### Tek Sorun, Çoklu Adım Modeli

ProcessEngine, **"Tek Sorun, Çoklu Adım"** modeli ile çalışır:

- Bir MantisBT sorunu, tek bir akışta adım adım ilerler.
- Her durum değişikliği (status change), akıştaki bir adıma eşlenir.
- SLA takibi adım bazında yapılır.

### Hiyerarşik Süreç Desteği (Alt Süreçler)

Aşağıdaki senaryolar **hiyerarşik süreç modeli** ile desteklenmektedir:

- **Sorun Zinciri (Subprocess):** A talebi → B talebi → C talebi şeklinde farklı sorunları ebeveyn-çocuk ilişkisiyle bağlama. Detaylar için bkz. [Bölüm 11: Hiyerarşik Süreç Yönetimi](#11-hiyerarşik-süreç-yönetimi).
- **Yarı-Manuel Alt Talep:** Subprocess tipindeki adımlarda kullanıcı "Şimdi Aç" butonuyla hedef projede çocuk sorun oluşturur. Çoklu hedef tanımlıysa her hedef için ayrı buton gösterilir.
- **Koşullu Dallanma:** Bir adımdan birden fazla geçiş tanımlanabilir, koşullar (field_equals, status_is) ile otomatik dallanma sağlanır.

Ebeveyn-çocuk ilişkileri `process_instance` tablosu üzerinden takip edilir. Subprocess oluştururken MantisBT native `bug_relationship_table` tablosuna `BUG_REL_PARENT_OF` kaydı da eklenir (mevcut kayıtlar değiştirilmez).

### Departman Yönetimi

Departmanlar artık yapılandırma sayfasından dinamik olarak yönetilir. Eski hardcode departman listesi kaldırılmıştır. Sistem departmanları iki kaynaktan toplar:
1. Yapılandırma sayfasındaki "Departmanlar" alanı
2. Mevcut akış adımlarındaki departman değerleri (step_table)

---

## 14. Sorun Giderme

### Sık Karşılaşılan Hatalar

#### "Table does not exist" hatası
MantisBT veritabanı tabloları oluşturulmamış. `admin/install.php` sayfasından veritabanı kurulumunu tamamlayın.

#### "BLOB/TEXT column can't have a default value"
MySQL 8.0 strict mode'da LONGTEXT alanlarına DEFAULT değer atanamaz. ProcessEngine 1.0.0'da bu sorun düzeltilmiştir.

#### "require_once failed to open stream"
Dosya yolu hatası. Eklentinin `core/` klasöründeki dosyaların mevcut olduğundan emin olun.

#### "APPLICATION ERROR #2800"
CSRF token doğrulama hatası. Sayfalara doğrudan URL ile değil, form butonları üzerinden erişmeye dikkat edin.

#### SLA bildirimleri gelmiyor
1. Cron görevinin ayarlı olduğunu doğrulayın
2. MantisBT e-posta ayarlarını kontrol edin
3. MailHog (http://localhost:8025) üzerinden test e-postalarını kontrol edin

#### Sorun detay sayfasında süreç bilgisi görünmüyor
1. Projeye ait AKTİF bir akış olduğunu doğrulayın
2. Sorunun süreç logunda kaydı olduğunu kontrol edin
3. Kullanıcının Görüntüleme Erişim Seviyesine sahip olduğunu doğrulayın

### Faydalı Komutlar

```bash
# Eklenti tablolarını kontrol et
docker exec mantis_mysql mysql -u mantis -pmantis123 mantis \
  -e "SHOW TABLES LIKE 'mantis_plugin_ProcessEngine_%';"

# Akış tanımlarını listele
docker exec mantis_mysql mysql -u mantis -pmantis123 mantis \
  -e "SELECT id, name, status FROM mantis_plugin_ProcessEngine_flow_definition_table;"

# Aktif SLA takiplerini gör
docker exec mantis_mysql mysql -u mantis -pmantis123 mantis \
  -e "SELECT * FROM mantis_plugin_ProcessEngine_sla_tracking_table WHERE completed_at IS NULL;"

# Manuel SLA kontrolü çalıştır
docker exec mantisbt php /var/www/html/scripts/sla_cron.php

# MantisBT loglarını izle
docker compose logs -f mantisbt
```

---

## Hızlı Başlangıç

1. Eklentiyi kurun ve etkinleştirin
2. **Yapılandırma** sayfasından departmanları, iş saatlerini ve SLA eşiğini ayarlayın
3. **Akış Tasarımcısı**'nda yeni bir akış oluşturun
4. Adımları ekleyin (departman, SLA süresi, MantisBT durumu, sorumlu kişi atayarak)
5. Adımlar arasına geçişler çizin
6. **Doğrula** butonuyla akışı kontrol edin
7. **Yayınla** butonuyla akışı aktif hale getirin
8. MantisBT'de talep durum değişikliklerini izleyin
9. Cron görevini ayarlayarak SLA kontrolünü otomatize edin
