# ProcessEngine — MantisBT Süreç Motoru Eklentisi

MantisBT 2.24+ üzerinde çalışan departmanlar arası iş akışı yönetim motoru. Fiyat talebi, ürün geliştirme gibi çok adımlı süreçleri otomatize eder.

## Özellikler

- **Görsel Akış Tasarımcısı**: Sürükle-bırak SVG editörü ile akış tanımlama
- **Koşullu Dallanma**: Alan eşittir, durum eşittir koşullarıyla otomatik yönlendirme
- **Hiyerarşik Alt Süreçler**: Adımları alt akışlara bağlama, yarı-manuel çocuk sorun oluşturma
- **Çoklu Subprocess Hedef**: Tek subprocess adımından birden fazla alt akış/proje hedefi tanımlama
- **Topolojik Sıralama**: Kahn algoritması ile otomatik adım sıralaması
- **SLA Takibi**: İş saatlerine göre dakika hassasiyetinde deadline hesaplama, uyarı ve eskalasyon
- **E-posta Bildirimleri**: SLA uyarı, aşım ve eskalasyon e-postaları
- **Süreç Paneli (Dashboard)**: Filtre (durum, departman, yıl/ay), ilerleme çubuğu, adım ilerleme modalı
- **Rapor Sayfası**: Chart.js grafikleri (departman performansı, SLA dağılımı, adım süresi, aylık trend)
- **Süreç Ağacı**: Ebeveyn-çocuk ilişkisini dikey ağaç yapısında görselleştirme
- **Adım Talimatları**: Her adıma bilgi metni/yönerge tanımlama
- **Yapılandırılabilir**: İş saatleri, çalışma günleri, departmanlar, yetki seviyeleri, global oto kilit

## Gereksinimler

| Bileşen | Sürüm |
|---------|-------|
| MantisBT | 2.24.0+ |
| PHP | 7.x+ |
| Veritabanı | MySQL 8.0 |

## Kurulum

1. `plugins/ProcessEngine/` klasörünü MantisBT kurulumunuzun `plugins/` dizinine kopyalayın
2. MantisBT yönetim paneline giriş yapın: **Yönet → Eklentileri Yönet**
3. "Süreç Motoru" eklentisini bulun ve **Kur** butonuna tıklayın
4. Gerekli veritabanı tabloları otomatik oluşturulur (7 tablo + indeksler)

## Yapılandırma

**Yönet → Süreç Motoru Ayarları** sayfasından:

| Ayar | Varsayılan | Açıklama |
|------|-----------|----------|
| Yönetim Erişim Seviyesi | MANAGER | Akış tasarımı ve yapılandırma |
| Görüntüleme Erişim Seviyesi | REPORTER | Süreç bilgilerini görme |
| İşlem Yetki Seviyesi | DEVELOPER | Dashboard'dan adım ilerleme |
| SLA Uyarı Yüzdesi | %80 | SLA uyarı tetikleme eşiği |
| İş Saatleri | 09:00 - 18:00 | SLA hesaplaması için (HH:MM formatı) |
| Çalışma Günleri | Pzt-Cum | SLA hesaplaması için |
| Departmanlar | (boş) | Virgülle ayrılmış liste |
| Otomatik Süreçler | Kapalı | Global oto kilit (açıksa auto tetikleyiciler çalışır) |

## Kullanım

### 1. Akış Oluşturma
- **Süreç Paneli → Akış Tasarımcısı** menüsünden yeni akış oluşturun
- Adımları sürükle-bırak ile konumlandırın
- Geçişleri çıkış portlarından çizerek bağlayın
- Doğrula → Yayınla

### 2. Süreç İzleme
- Sorun oluşturulduğunda aktif akış otomatik başlar
- Durum değişiklikleri süreç loguna kaydedilir
- SLA takibi otomatik başlar/biter
- Sorun detay sayfasında dikey ilerleme ağacı ve birleşik zaman çizelgesi görüntülenir

### 3. Alt Süreçler (Yarı-Manuel)
- Adım tipini "Alt Süreç" olarak ayarlayın
- Hedef akış ve proje seçin (çoklu hedef desteklenir)
- Sorun subprocess adımına geldiğinde kullanıcı "Şimdi Aç" butonuyla çocuk sorunu oluşturur
- Mevcut sorunlar "Bağla" butonuyla da bağlanabilir
- Ebeveyn bekleme modu: "Tümünü Bekle" veya "Herhangi Birini Bekle"

### 4. Rapor Sayfası
- **Süreç Paneli → Rapor** menüsünden erişilir
- Filtreler: tarih aralığı, proje, departman, akış, durum
- 4 grafik: departman performansı, SLA dağılımı, adım süresi, aylık trend
- Detay tablosu ve özet kartlar

### 5. SLA Cron
Otomatik SLA kontrolü için cron görevi ekleyin:
```bash
*/5 * * * * php /path/to/mantisbt/scripts/sla_cron.php
```

## Veritabanı Tabloları

| Tablo | Açıklama |
|-------|----------|
| `flow_definition_table` | Akış tanımları |
| `step_table` | Akış adımları |
| `transition_table` | Adımlar arası geçişler |
| `log_table` | Süreç logları |
| `sla_tracking_table` | SLA takip kayıtları |
| `process_instance_table` | Süreç örnekleri (ebeveyn-çocuk ilişkileri) |
| `subprocess_target_table` | Çoklu subprocess hedefleri |

## Bilinen Kısıtlamalar

- Proje başına yalnızca bir aktif akış desteklenir
- Alt süreç derinliği en fazla 10 seviye
- Aktif (yayınlanmış) akışlar doğrudan düzenlenemez — önce taslak durumuna alınmalıdır
- Aktif süreçlere bağlı akışlar silinemez
- MantisBT native `bug_relationship_table` tablosuna subprocess oluştururken `BUG_REL_PARENT_OF` kaydı eklenir (mevcut kayıtlar değiştirilmez)

## Lisans

GPL v2 veya üstü — MantisBT lisansı ile uyumlu.

## Geliştirici

**VerhexIO** — [hello@verhex.io](mailto:hello@verhex.io)
