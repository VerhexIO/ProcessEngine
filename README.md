# ProcessEngine — MantisBT Süreç Motoru Eklentisi

MantisBT 2.24+ üzerinde çalışan departmanlar arası iş akışı yönetim motoru. Fiyat talebi, ürün geliştirme gibi çok adımlı süreçleri otomatize eder.

## Özellikler

- **Görsel Akış Tasarımcısı**: Sürükle-bırak SVG editörü ile akış tanımlama
- **Koşullu Dallanma**: Alan eşittir, durum eşittir koşullarıyla otomatik yönlendirme
- **Hiyerarşik Alt Süreçler**: Adımları alt akışlara bağlama, otomatik çocuk sorun oluşturma
- **SLA Takibi**: İş saatlerine göre deadline hesaplama, uyarı ve eskalasyon
- **E-posta Bildirimleri**: SLA uyarı, aşım ve eskalasyon e-postaları
- **Süreç Paneli (Dashboard)**: Filtre, ilerleme çubuğu, adım ilerleme, SLA güncelleme
- **Süreç Ağacı**: Ebeveyn-çocuk ilişkisini görselleştirme
- **Yapılandırılabilir**: İş saatleri, çalışma günleri, departmanlar, yetki seviyeleri

## Gereksinimler

| Bileşen | Sürüm |
|---------|-------|
| MantisBT | 2.24.0+ |
| PHP | 7.x+ |
| Veritabanı | MySQL 5.7+ / MariaDB 10.2+ |

## Kurulum

1. `plugins/ProcessEngine/` klasörünü MantisBT kurulumunuzun `plugins/` dizinine kopyalayın
2. MantisBT yönetim paneline giriş yapın: **Yönet → Eklentileri Yönet**
3. "Süreç Motoru" eklentisini bulun ve **Kur** butonuna tıklayın
4. Gerekli veritabanı tabloları otomatik oluşturulur (6 tablo + indeksler)

## Yapılandırma

**Yönet → Süreç Motoru Ayarları** sayfasından:

| Ayar | Varsayılan | Açıklama |
|------|-----------|----------|
| Yönetim Erişim Seviyesi | MANAGER | Akış tasarımı ve yapılandırma |
| Görüntüleme Erişim Seviyesi | REPORTER | Süreç bilgilerini görme |
| İşlem Yetki Seviyesi | DEVELOPER | Dashboard'dan adım ilerleme |
| SLA Uyarı Yüzdesi | %80 | SLA uyarı tetikleme eşiği |
| İş Saatleri | 09:00 - 18:00 | SLA hesaplaması için |
| Çalışma Günleri | Pzt-Cum | SLA hesaplaması için |
| Departmanlar | (boş) | Virgülle ayrılmış liste |

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

### 3. Alt Süreçler
- Adım tipini "Alt Süreç" olarak ayarlayın
- Hedef akış ve proje seçin
- Ebeveyn bekleme modu: "Tümünü Bekle" veya "Herhangi Birini Bekle"

### 4. SLA Cron
Otomatik SLA kontrolü için cron görevi ekleyin:
```bash
*/5 * * * * php /path/to/mantisbt/scripts/sla_cron.php
```

## Veritabanı Tabloları

| Tablo | Açıklama |
|-------|----------|
| `mantis_plugin_ProcessEngine_flow_definition` | Akış tanımları |
| `mantis_plugin_ProcessEngine_step` | Akış adımları |
| `mantis_plugin_ProcessEngine_transition` | Adımlar arası geçişler |
| `mantis_plugin_ProcessEngine_log` | Süreç logları |
| `mantis_plugin_ProcessEngine_sla_tracking` | SLA takip kayıtları |
| `mantis_plugin_ProcessEngine_process_instance` | Süreç örnekleri |

## Bilinen Kısıtlamalar

- Proje başına yalnızca bir aktif akış desteklenir
- Alt süreç derinliği en fazla 10 seviye
- MantisBT native ilişki tablosu (`bug_relationship_table`) süreç bağlamında kullanılmaz
- Aktif (yayınlanmış) akışlar doğrudan düzenlenemez — önce taslak durumuna alınmalıdır

## Lisans

GPL v2 veya üstü — MantisBT lisansı ile uyumlu.

## Geliştirici

**VerhexIO** — [hello@verhex.io](mailto:hello@verhex.io)
