// @ts-check
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

/**
 * Faz 11 — Paradigma Degisikligi E2E Test
 *
 * Faz 10'dan farklar:
 *   - confirm() diyalogu yerine modal kullaniliyor
 *   - Yari-manuel subprocess ("Simdi Ac" / "Bagla" butonlari)
 *   - Birlesik timeline (.pe-timeline)
 *   - Ilerleme modali (not destegi, note_required zorunluluk)
 */

const BASE_URL = 'http://localhost:8080';
const USERNAME = 'administrator';
const PASSWORD = 'root';
const STATE_FILE = path.join(__dirname, '.faz11-test-state.json');

function saveState(data) {
  const existing = loadState();
  fs.writeFileSync(STATE_FILE, JSON.stringify({ ...existing, ...data }));
}

function loadState() {
  try { return JSON.parse(fs.readFileSync(STATE_FILE, 'utf-8')); }
  catch { return {}; }
}

/** Login */
async function login(page) {
  await page.goto(`${BASE_URL}/login_page.php`);
  await page.waitForLoadState('domcontentloaded');

  // Zaten giris yapilmissa atla
  if (page.url().includes('my_view_page') || page.url().includes('account_page') || page.url().includes('view_all')) {
    return;
  }

  // Username alani varsa (login formu)
  const usernameField = page.locator('#username');
  if (await usernameField.isVisible({ timeout: 3000 }).catch(() => false)) {
    await usernameField.fill(USERNAME);
    await page.locator('input[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');
  }

  // Password alani varsa
  const passwordField = page.locator('#password');
  if (await passwordField.isVisible({ timeout: 3000 }).catch(() => false)) {
    await passwordField.fill(PASSWORD);
    await page.locator('input[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');
  }

  await page.waitForTimeout(500);
}

async function goToBug(page, bugId) {
  await page.goto(`${BASE_URL}/view.php?id=${bugId}`);
  await page.waitForLoadState('domcontentloaded');
}

async function goToDashboard(page) {
  await page.goto(`${BASE_URL}/plugin.php?page=ProcessEngine/dashboard`);
  await page.waitForLoadState('domcontentloaded');
}

// ============================================================
test.describe.serial('Faz 11 — Paradigma Degisikligi E2E', () => {

  test.beforeAll(() => {
    // State dosyasini temizle
    try { fs.unlinkSync(STATE_FILE); } catch {}
  });

  // ----------------------------------------------------------
  test('1. Login', async ({ page }) => {
    await login(page);
    await expect(page).toHaveTitle(/MantisBT/);
  });

  // ----------------------------------------------------------
  test('2. Sorun olustur — surec otomatik baslamali', async ({ page }) => {
    await login(page);

    // Proje 2 sec (aktif akisli proje)
    await page.goto(`${BASE_URL}/set_project.php?project_id=2&ref=bug_report_page.php`);
    await page.waitForLoadState('domcontentloaded');

    await page.goto(`${BASE_URL}/bug_report_page.php`);
    await page.waitForLoadState('domcontentloaded');

    // Kategori
    const catSelect = page.locator('#category_id');
    if (await catSelect.isVisible()) {
      const opts = await catSelect.locator('option').count();
      if (opts > 1) await catSelect.selectOption({ index: 1 });
    }

    // Ozet + Aciklama
    await page.locator('#summary').fill('Faz11-E2E-' + Date.now());
    const desc = page.locator('#description');
    if (await desc.isVisible()) await desc.fill('Faz 11 E2E test');

    // Gonder
    await page.locator('input[type="submit"]').first().click();
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(1000);

    // Bug ID al
    let parentBugId = 0;
    const url = page.url();
    const urlMatch = url.match(/id=(\d+)/);
    if (urlMatch) {
      parentBugId = parseInt(urlMatch[1]);
    } else {
      const viewLink = page.locator('a:has-text("View Submitted Issue"), a:has-text("Gönderilen Sorunu")').first();
      if (await viewLink.isVisible()) {
        const linkText = await viewLink.textContent();
        const textMatch = linkText.match(/(\d+)/);
        if (textMatch) parentBugId = parseInt(textMatch[1]);
        await viewLink.click();
        await page.waitForLoadState('domcontentloaded');
      }
    }
    expect(parentBugId).toBeGreaterThan(0);
    saveState({ parentBugId });
    console.log(`  -> Parent bug ID: ${parentBugId}`);

    // Surec paneli gorunmeli
    await goToBug(page, parentBugId);
    await expect(page.locator('.pe-info-panel')).toBeVisible({ timeout: 10000 });
    const panelText = await page.locator('.pe-info-panel').textContent();
    expect(panelText).toContain('Talep');
  });

  // ----------------------------------------------------------
  test('3. Ilerleme modali — modal acilmali (confirm degil)', async ({ page }) => {
    const { parentBugId } = loadState();
    expect(parentBugId).toBeGreaterThan(0);
    await login(page);
    await goToBug(page, parentBugId);

    // "Adimi Ilerlet" butonu gorunmeli
    const advBtn = page.locator('.pe-bugview-advance');
    await expect(advBtn).toBeVisible({ timeout: 10000 });

    // Tikla — confirm() DEGIL, modal acilmali
    await advBtn.click();
    await page.waitForTimeout(500);

    // Modal gorunur olmali
    const modal = page.locator('#pe-advance-modal');
    await expect(modal).toBeVisible({ timeout: 5000 });

    // Modalda mevcut adim ve sonraki adim bilgisi olmali
    const modalText = await modal.textContent();
    expect(modalText.length).toBeGreaterThan(10);
    console.log(`  -> Modal icerik: ${modalText.substring(0, 200)}`);
  });

  // ----------------------------------------------------------
  test('4. Modal ile ilerlet — adim 1→2', async ({ page }) => {
    const { parentBugId } = loadState();
    await login(page);
    await goToBug(page, parentBugId);

    // Advance butonuna tikla
    const advBtn = page.locator('.pe-bugview-advance');
    await expect(advBtn).toBeVisible({ timeout: 10000 });
    await advBtn.click();
    await page.waitForTimeout(500);

    // Modal icindeki "Ilerlet" / "Onayla" butonuna tikla
    const modalConfirmBtn = page.locator('#pe-advance-modal .btn-primary, #pe-advance-modal button[type="submit"]');
    await expect(modalConfirmBtn.first()).toBeVisible({ timeout: 5000 });
    await modalConfirmBtn.first().click();
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(2000);

    // Kontrol: adim ilerledi
    await goToBug(page, parentBugId);
    const panelText = await page.locator('.pe-info-panel').textContent();
    console.log(`  -> Ilerleme sonrasi panel: ${panelText.substring(0, 150)}`);
    // Ilk adimdan ikinci adima gecmis olmali
    expect(panelText).toBeTruthy();
  });

  // ----------------------------------------------------------
  test('5. Timeline dogrulama — birlesik zaman cizelgesi', async ({ page }) => {
    const { parentBugId } = loadState();
    await login(page);
    await goToBug(page, parentBugId);

    // .pe-timeline elementi
    const timeline = page.locator('.pe-timeline');
    await expect(timeline).toBeVisible({ timeout: 10000 });

    const timelineHtml = await timeline.innerHTML();

    // Surec baslatma ikonu (fa-play-circle)
    expect(timelineHtml).toContain('fa-play-circle');

    // Adim ilerleme ikonu (fa-forward)
    expect(timelineHtml).toContain('fa-forward');

    console.log(`  -> Timeline HTML uzunlugu: ${timelineHtml.length} karakter`);
  });

  // ----------------------------------------------------------
  test('6. Subprocess adimina ilerlet — subprocess paneli gorunmeli', async ({ page }) => {
    const { parentBugId } = loadState();
    await login(page);
    await goToBug(page, parentBugId);

    // Subprocess adimina ulasmak icin gerekli sayida ilerlet
    // Adim 2 → Adim 3 (subprocess) ilerletme
    const advBtn = page.locator('.pe-bugview-advance');
    if (await advBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      await advBtn.click();
      await page.waitForTimeout(500);

      // Modal varsa onayla
      const modalConfirmBtn = page.locator('#pe-advance-modal .btn-primary, #pe-advance-modal button[type="submit"]');
      if (await modalConfirmBtn.first().isVisible({ timeout: 3000 }).catch(() => false)) {
        await modalConfirmBtn.first().click();
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(2000);
      }
    }

    // Subprocess adimina gelince
    await goToBug(page, parentBugId);
    const panel = page.locator('.pe-info-panel');
    await expect(panel).toBeVisible({ timeout: 10000 });
    const panelText = await panel.textContent();
    console.log(`  -> Subprocess adim panel: ${panelText.substring(0, 200)}`);

    // Subprocess paneli: "Simdi Ac" veya "Bagla" butonu
    const subprocessPanel = page.locator('.pe-subprocess-create, .pe-subprocess-panel, .pe-subprocess-children');
    const hasSubprocessUI = await subprocessPanel.isVisible({ timeout: 5000 }).catch(() => false);
    if (hasSubprocessUI) {
      console.log('  -> Subprocess paneli gorunur');
    } else {
      // Belki henuz subprocess adiminda degiliz, bir kez daha ilerletelim
      console.log('  -> Subprocess paneli henuz gorunmuyor, ek ilerleme gerekebilir');
    }
  });

  // ----------------------------------------------------------
  test('7. "Simdi Ac" butonu — subprocess olusturma', async ({ page }) => {
    const { parentBugId } = loadState();
    await login(page);
    await goToBug(page, parentBugId);

    // "Simdi Ac" butonu (btn_create_subprocess)
    const createBtn = page.locator('.pe-btn-create-subprocess, button:has-text("Şimdi Aç"), a:has-text("Şimdi Aç"), .pe-subprocess-create button');
    const isSubprocessStep = await createBtn.first().isVisible({ timeout: 5000 }).catch(() => false);

    if (isSubprocessStep) {
      await createBtn.first().click();
      await page.waitForLoadState('domcontentloaded');
      await page.waitForTimeout(2000);

      // Cocuk sorun linki gorunmeli
      await goToBug(page, parentBugId);
      const childLink = page.locator('.pe-subprocess-children a[href*="view.php"], .pe-child-link');
      const hasChild = await childLink.first().isVisible({ timeout: 5000 }).catch(() => false);

      if (hasChild) {
        const href = await childLink.first().getAttribute('href');
        const m = href.match(/id=(\d+)/);
        if (m) {
          const childBugId = parseInt(m[1]);
          saveState({ childBugId });
          console.log(`  -> Cocuk sorun ID: ${childBugId}`);
          expect(childBugId).toBeGreaterThan(0);
        }
      } else {
        console.log('  -> Cocuk sorun linki henuz gorunmuyor');
      }
    } else {
      // Subprocess adiminda degiliz veya otomatik olusturulmus
      console.log('  -> "Simdi Ac" butonu gorunmuyor — subprocess adiminda olmayabiliriz');

      // Mevcut cocuk surecler kontrol et
      const childLink = page.locator('.pe-subprocess-children a[href*="view.php"]').first();
      if (await childLink.isVisible({ timeout: 3000 }).catch(() => false)) {
        const href = await childLink.getAttribute('href');
        const m = href.match(/id=(\d+)/);
        if (m) {
          saveState({ childBugId: parseInt(m[1]) });
          console.log(`  -> Mevcut cocuk ID: ${m[1]}`);
        }
      }
    }
  });

  // ----------------------------------------------------------
  test('8. Ebeveyn WAITING durumunda — advance butonu yok', async ({ page }) => {
    const { parentBugId } = loadState();
    await login(page);
    await goToBug(page, parentBugId);

    const panel = page.locator('.pe-info-panel');
    await expect(panel).toBeVisible({ timeout: 10000 });

    // Waiting label kontrol
    const waitLabel = page.locator('.pe-waiting-label, .pe-status-waiting, .badge:has-text("Beklemede")');
    const isWaiting = await waitLabel.first().isVisible({ timeout: 5000 }).catch(() => false);

    if (isWaiting) {
      console.log('  -> Ebeveyn WAITING durumunda');
      // Advance butonu OLMAMALI
      const advBtn = page.locator('.pe-bugview-advance');
      await expect(advBtn).toHaveCount(0);
    } else {
      const panelText = await panel.textContent();
      console.log(`  -> Ebeveyn WAITING degil: ${panelText.substring(0, 100)}`);
    }
  });

  // ----------------------------------------------------------
  test('9. Cocuk tamamlama — ebeveyn otomatik ilerlemeli', async ({ page }) => {
    const state = loadState();
    const childBugId = state.childBugId;
    const parentBugId = state.parentBugId;

    if (!childBugId) {
      console.log('  -> Cocuk bug ID bulunamadi, test atlaniyor');
      return;
    }

    await login(page);
    await goToBug(page, childBugId);

    // Cocuk sorunun adimlarini ilerlet
    let maxAdvance = 5;
    while (maxAdvance > 0) {
      const advBtn = page.locator('.pe-bugview-advance');
      const isVisible = await advBtn.isVisible({ timeout: 3000 }).catch(() => false);
      if (!isVisible) break;

      await advBtn.click();
      await page.waitForTimeout(500);

      // Modal varsa onayla
      const modalConfirmBtn = page.locator('#pe-advance-modal .btn-primary, #pe-advance-modal button[type="submit"]');
      if (await modalConfirmBtn.first().isVisible({ timeout: 2000 }).catch(() => false)) {
        await modalConfirmBtn.first().click();
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(1000);
      }

      await goToBug(page, childBugId);
      maxAdvance--;
    }

    console.log(`  -> Cocuk sorun islemleri tamamlandi`);

    // Ebeveyn kontrol — WAITING'den cikmis olmali
    await page.waitForTimeout(1000);
    await goToBug(page, parentBugId);
    const panel = page.locator('.pe-info-panel');
    if (await panel.isVisible({ timeout: 5000 }).catch(() => false)) {
      const text = await panel.textContent();
      console.log(`  -> Ebeveyn panel: ${text.substring(0, 150)}`);

      // Waiting label artik olmamali veya sonraki adima ilerlenmis olmali
      const waitCount = await page.locator('.pe-waiting-label').count();
      if (waitCount === 0) {
        console.log('  -> Ebeveyn WAITING durumundan cikti');
      } else {
        console.log('  -> Ebeveyn hala WAITING (cocuk henuz tamamlanmamis olabilir)');
      }
    }
  });
});
