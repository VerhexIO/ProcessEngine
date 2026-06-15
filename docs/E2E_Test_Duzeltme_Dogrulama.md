# E2E Test Düzeltmesi — Kök Neden ve Doğrulama

> `tests/full-e2e.spec.js` paketinde başarısız olan testlerin kök-neden düzeltmesi.
> Plugin kodu sağlamdı (teknik doğrulama **151 OK / 0 hata**); sorunlar **seed/fixture**
> katmanındaydı. **Sonuç: 25/25 E2E testi geçiyor.**

## Kök Nedenler ve Düzeltmeler

| Test | Belirti | Kök Neden | Düzeltme |
|------|---------|-----------|----------|
| **B1, C1** | Proje 2'de süreç başlamıyor, panel yok | `process_get_active_flow_for_project` tam eşleşme yapar; seed akışları `project_id=0` ("Global") ile kuruluyordu → proje 2 eşleşmiyor | Aktif akış proje 2'ye bağlandı |
| **D1–D4** | Subprocess adımına ulaşılamıyor | Proje 2'ye **subprocess'siz** linear akış bağlıydı | Proje 2'ye **"Hiyerarşik Fiyat Talebi"** (subprocess'li) bağlandı |
| **D5** | Manuel çocuk bağlama paneli/linki yok | (1) Hiyerarşik akışta tek subprocess adımı vardı; (2) son adım subprocess olunca girişte süreç COMPLETED oluyordu; (3) `subprocess_link_manual_child` çocuğun projesinde aktif akış yoksa `false` döner — proje 3'ün akışı yoktu | Adım 4 (terminal değil) çoklu hedefli subprocess yapıldı; **"Satınalma İnceleme"** proje 3'e bağlandı |
| **G2** | `reporter_test` girişi başarısız | Kullanıcı DB'de yok | `reporter_test` (REPORTER) idempotent oluşturuldu |

## Değiştirilen Dosyalar

| Dosya | Değişiklik |
|-------|-----------|
| `plugins/ProcessEngine/db/seed_data.php` | "Hiyerarşik Fiyat Talebi" → proje 2; adım 4 → çoklu hedefli subprocess; "Satınalma İnceleme" → proje 3 (taze kurulumlar) |
| `scripts/e2e_fixture.php` (yeni) | **İdempotent** fixture: yukarıdaki bağlamaları mevcut DB'ye uygular + `reporter_test` kullanıcısını oluşturur |

> **Neden iki katman?** `process_seed_load()` idempotency guard'ı (`seed_data.php:24-28`),
> `flow_definition` doluysa hiçbir şey yapmadan döner. Mevcut DB zaten dolu olduğundan
> seed düzenlemesi yalnızca taze kurulumları etkiler; `e2e_fixture.php` mevcut DB'yi düzeltir.

## Doğrulama (MantisBT/Docker ayaktayken)

```bash
docker exec mantisbt php /var/www/html/scripts/e2e_fixture.php   # idempotent fixture
npm run test:tech                                                # 151 OK / 0 hata beklenir
npm test                                                         # 25/25 yeşil beklenir
```

## Tasarım Notu (bilgi)
`subprocess_link_manual_child` (`core/subprocess_api.php:896`), manuel bağlanan çocuğun
**projesinde aktif akış yoksa** bağlamayı reddeder. Bu, manuel bağlamanın yalnızca aktif
akışı olan projelerdeki sorunlarla yapılabileceği anlamına gelir. Fixture bunu proje 3'e
akış bağlayarak karşılar. İleride, manuel bağlamanın subprocess **hedefinin** child_flow'unu
kullanması (çocuğun proje akışı yerine) daha esnek olurdu — ayrı bir iyileştirme.

## Notlar
- Düzeltme MantisBT çekirdeğini/native tablolarını **bozmaz**; yalnızca plugin akış
  kayıtlarını günceller ve native `user_create()` ile bir test kullanıcısı ekler.
- `php -l` sözdizim kontrolü container'da yapılır (host'ta PHP yok).
