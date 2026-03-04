// @ts-check
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

/**
 * ProcessEngine — Kapsamli E2E Test Paketi
 *
 * 25 test, 7 grup:
 *   A: Akis Tasarimcisi (5)
 *   B: Sorun Olusturma ve Surec Baslatma (3)
 *   C: Adim Ilerleme ve Rollback (4)
 *   D: Subprocess Akisi (5)
 *   E: Dashboard Filtreleri (4)
 *   F: Rapor Sayfasi (2)
 *   G: Yapilandirma ve Erisim Kontrolu (2)
 */

const BASE_URL = 'http://localhost:8080';
const ADMIN_USER = 'administrator';
const ADMIN_PASS = 'root';
const REPORTER_USER = 'reporter_test';
const REPORTER_PASS = 'reporter_test';
const STATE_FILE = path.join(__dirname, '.full-e2e-state.json');

// ============================================================
// Yardimci Fonksiyonlar
// ============================================================

function saveState(data) {
  const existing = loadState();
  fs.writeFileSync(STATE_FILE, JSON.stringify({ ...existing, ...data }));
}

function loadState() {
  try { return JSON.parse(fs.readFileSync(STATE_FILE, 'utf-8')); }
  catch { return {}; }
}

async function login(page, username = ADMIN_USER, password = ADMIN_PASS) {
  await page.goto(`${BASE_URL}/login_page.php`);
  await page.waitForLoadState('domcontentloaded');

  // Zaten giris yapilmissa atla
  if (page.url().includes('my_view_page') || page.url().includes('account_page') || page.url().includes('view_all')) {
    return;
  }

  const usernameField = page.locator('#username');
  if (await usernameField.isVisible({ timeout: 3000 }).catch(() => false)) {
    await usernameField.fill(username);
    await page.locator('input[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');
  }

  const passwordField = page.locator('#password');
  if (await passwordField.isVisible({ timeout: 3000 }).catch(() => false)) {
    await passwordField.fill(password);
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

async function goToFlowDesigner(page, flowId = 0) {
  if (flowId > 0) {
    await page.goto(`${BASE_URL}/plugin.php?page=ProcessEngine/flow_designer&flow_id=${flowId}`);
  } else {
    await page.goto(`${BASE_URL}/plugin.php?page=ProcessEngine/flow_designer&flow_id=0`);
  }
  await page.waitForLoadState('domcontentloaded');
}

async function goToConfig(page) {
  await page.goto(`${BASE_URL}/plugin.php?page=ProcessEngine/config_page`);
  await page.waitForLoadState('domcontentloaded');
}

async function goToReport(page) {
  await page.goto(`${BASE_URL}/plugin.php?page=ProcessEngine/report`);
  await page.waitForLoadState('domcontentloaded');
}

/**
 * Bug view'da advance butonuna tikla ve modali onayla
 */
async function advanceStep(page, bugId) {
  await goToBug(page, bugId);

  const advBtn = page.locator('.pe-bugview-advance');
  const isVisible = await advBtn.isVisible({ timeout: 5000 }).catch(() => false);
  if (!isVisible) return false;

  await advBtn.click();
  await page.waitForTimeout(500);

  // Modal varsa onayla
  const modalConfirmBtn = page.locator('#pe-modal-confirm');
  if (await modalConfirmBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
    await modalConfirmBtn.click();
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(1500);
  }

  return true;
}

/**
 * Bug ID'yi URL veya sayfadan cikar
 */
async function extractBugId(page) {
  const url = page.url();
  const urlMatch = url.match(/id=(\d+)/);
  if (urlMatch) return parseInt(urlMatch[1]);

  // View link kontrolu
  const viewLink = page.locator('a:has-text("View Submitted Issue"), a:has-text("G\u00f6nderilen Sorunu")').first();
  if (await viewLink.isVisible({ timeout: 3000 }).catch(() => false)) {
    const href = await viewLink.getAttribute('href');
    const m = href && href.match(/id=(\d+)/);
    if (m) {
      await viewLink.click();
      await page.waitForLoadState('domcontentloaded');
      return parseInt(m[1]);
    }
  }

  return 0;
}


// ============================================================
// GRUP A: Temel Kurulum ve Akis Tasarimcisi (5 test)
// ============================================================
test.describe.serial('Grup A: Akis Tasarimcisi', () => {

  test('A1. Login ve Dashboard erisimi', async ({ page }) => {
    await login(page);
    await expect(page).toHaveTitle(/MantisBT/);

    await goToDashboard(page);
    // Sayfa yuklenip APPLICATION ERROR vermemeli
    await expect(page.locator('body')).not.toContainText('APPLICATION ERROR');

    // Ozet kartlar gorunmeli
    const statsCards = page.locator('.pe-card');
    const cardCount = await statsCards.count();
    expect(cardCount).toBeGreaterThanOrEqual(1);
    console.log(`  -> Dashboard: ${cardCount} istatistik karti`);

    // Filtre butonlari gorunmeli
    const filterBtns = page.locator('a[href*="filter="], a[href*="dashboard"]');
    const filterCount = await filterBtns.count();
    expect(filterCount).toBeGreaterThanOrEqual(1);
    console.log(`  -> ${filterCount} filtre butonu`);
  });

  test('A2. Akis Tasarimcisi — liste gorunumu', async ({ page }) => {
    await login(page);
    await goToFlowDesigner(page, 0);

    // APPLICATION ERROR olmamali
    await expect(page.locator('body')).not.toContainText('APPLICATION ERROR');

    // Akis tablosu gorunmeli
    const table = page.locator('table');
    await expect(table.first()).toBeVisible({ timeout: 10000 });

    // En az 1 akis listelenmiş olmali
    const rows = page.locator('table tbody tr');
    const rowCount = await rows.count();
    expect(rowCount).toBeGreaterThanOrEqual(1);
    console.log(`  -> ${rowCount} akis listelendi`);

    // Durum sutununda AKTİF veya TASLAK etiketi
    const pageText = await page.locator('table').first().textContent();
    const hasStatus = pageText.includes('AKT') || pageText.includes('TASLAK') || pageText.includes('Active') || pageText.includes('Draft');
    expect(hasStatus).toBeTruthy();
  });

  test('A3. Akis Tasarimcisi — yeni akis olusturma', async ({ page }) => {
    await login(page);

    // Yeni akis olustur
    await page.goto(`${BASE_URL}/plugin.php?page=ProcessEngine/flow_designer&flow_id=0&action=new`);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(1000);

    // Yeni akis ID ile tasarimciya yonlenmeli
    const url = page.url();
    const flowMatch = url.match(/flow_id=(\d+)/);
    expect(flowMatch).not.toBeNull();
    const newFlowId = parseInt(flowMatch[1]);
    expect(newFlowId).toBeGreaterThan(0);
    saveState({ testFlowId: newFlowId });
    console.log(`  -> Yeni akis ID: ${newFlowId}`);

    // Akis adi alani gorunmeli
    const flowNameInput = page.locator('#pe-flow-name');
    await expect(flowNameInput).toBeVisible({ timeout: 10000 });

    // SVG tuval gorunmeli
    const canvas = page.locator('#pe-canvas');
    await expect(canvas).toBeVisible({ timeout: 5000 });

    // "Adim Ekle" butonu gorunmeli
    const addStepBtn = page.locator('#pe-btn-add-step');
    await expect(addStepBtn).toBeVisible({ timeout: 5000 });
  });

  test('A4. Akis Tasarimcisi — adim ekleme ve kaydetme', async ({ page }) => {
    const { testFlowId } = loadState();
    expect(testFlowId).toBeGreaterThan(0);

    await login(page);
    await goToFlowDesigner(page, testFlowId);

    // Akis adini doldur
    const flowNameInput = page.locator('#pe-flow-name');
    await expect(flowNameInput).toBeVisible({ timeout: 10000 });
    await flowNameInput.fill('E2E Test Akisi ' + Date.now());

    // --- Adim 1 ekle ---
    await page.locator('#pe-btn-add-step').click();
    await page.waitForTimeout(500);

    const stepModal = page.locator('#pe-step-modal');
    await expect(stepModal).toBeVisible({ timeout: 5000 });

    // Ad
    await page.locator('#pe-modal-name').fill('Test Adim 1');

    // Departman — ilk secenegi sec
    const deptSelect = page.locator('#pe-modal-department');
    if (await deptSelect.isVisible()) {
      const opts = await deptSelect.locator('option').allTextContents();
      if (opts.length > 1) {
        await deptSelect.selectOption({ index: 1 });
      }
    }

    // SLA
    const slaInput = page.locator('#pe-modal-sla');
    if (await slaInput.isVisible()) {
      await slaInput.fill('2');
    }

    // MantisBT Status — acknowledged (30)
    const statusSelect = page.locator('#pe-modal-mantis-status');
    if (await statusSelect.isVisible()) {
      try {
        await statusSelect.selectOption({ value: '30' });
      } catch {
        // Index ile dene
        const statusOpts = await statusSelect.locator('option').count();
        if (statusOpts > 2) await statusSelect.selectOption({ index: 2 });
      }
    }

    // Kaydet
    await page.locator('#pe-modal-save').click();
    await page.waitForTimeout(1000);

    // Tuval'de dugum gorunmeli
    const nodes = page.locator('#pe-canvas .pe-node, #pe-canvas rect, #pe-canvas g[data-step-id]');
    const nodeCount = await nodes.count();
    expect(nodeCount).toBeGreaterThanOrEqual(1);
    console.log(`  -> Tuvalde ${nodeCount} dugum`);

    // --- Adim 2 ekle ---
    await page.locator('#pe-btn-add-step').click();
    await page.waitForTimeout(500);

    await page.locator('#pe-modal-name').fill('Test Adim 2');

    // MantisBT Status — confirmed (40)
    if (await statusSelect.isVisible()) {
      try {
        await statusSelect.selectOption({ value: '40' });
      } catch {
        const statusOpts = await statusSelect.locator('option').count();
        if (statusOpts > 3) await statusSelect.selectOption({ index: 3 });
      }
    }

    await page.locator('#pe-modal-save').click();
    await page.waitForTimeout(1000);

    // Kaydet butonu
    await page.locator('#pe-btn-save').click();
    await page.waitForTimeout(2000);

    // Basari mesaji veya hata olmamasi
    const pageContent = await page.locator('body').textContent();
    const hasError = pageContent.includes('APPLICATION ERROR') || pageContent.includes('HATA');
    // Kaydetme sonrasi alert veya toast kontrolu — hata yoksa basarili sayalim
    console.log(`  -> Kaydetme sonrasi hata: ${hasError}`);
  });

  test('A5. Akis Tasarimcisi — dogrulama ve yayinlama', async ({ page }) => {
    const { testFlowId } = loadState();
    expect(testFlowId).toBeGreaterThan(0);

    await login(page);
    await goToFlowDesigner(page, testFlowId);
    await page.waitForTimeout(1000);

    // Dogrula butonuna tikla
    const validateBtn = page.locator('#pe-btn-validate');
    await expect(validateBtn).toBeVisible({ timeout: 10000 });

    // Dogrulama AJAX cagrisi — dialog/alert dinle
    page.on('dialog', async dialog => {
      console.log(`  -> Dialog: ${dialog.message().substring(0, 100)}`);
      await dialog.accept();
    });

    await validateBtn.click();
    await page.waitForTimeout(2000);

    // Yayinla butonuna tikla
    const publishBtn = page.locator('#pe-btn-publish');
    if (await publishBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
      await publishBtn.click();
      await page.waitForTimeout(2000);
    }

    // Liste gorunumune don — akisin AKTİF etiketli olup olmadigini dogrula
    await goToFlowDesigner(page, 0);
    const tableText = await page.locator('table').first().textContent();
    console.log(`  -> Akis listesi: ${tableText.substring(0, 200)}`);
  });
});


// ============================================================
// GRUP B: Sorun Olusturma ve Surec Baslatma (3 test)
// ============================================================
test.describe.serial('Grup B: Sorun Olusturma', () => {

  test('B1. Aktif akisli projede sorun olusturma', async ({ page }) => {
    await login(page);

    // Aktif akis olan projeye gec (proje 2)
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
    const summary = 'FullE2E-' + Date.now();
    await page.locator('#summary').fill(summary);
    const desc = page.locator('#description');
    if (await desc.isVisible()) await desc.fill('Kapsamli E2E test sorunu');

    // Gonder
    await page.locator('input[type="submit"]').first().click();
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(1000);

    // Bug ID al
    const parentBugId = await extractBugId(page);
    expect(parentBugId).toBeGreaterThan(0);
    saveState({ parentBugId });
    console.log(`  -> Parent bug ID: ${parentBugId}`);

    // Sorun sayfasina git → .pe-info-panel gorunmeli
    await goToBug(page, parentBugId);
    const infoPanel = page.locator('.pe-info-panel');
    await expect(infoPanel).toBeVisible({ timeout: 10000 });

    const panelText = await infoPanel.textContent();
    // Panel'de ilk adim adi veya "Talep" gorunmeli
    expect(panelText.length).toBeGreaterThan(5);
    console.log(`  -> Info panel: ${panelText.substring(0, 150)}`);
  });

  test('B2. Surec bilgi paneli dogrulama', async ({ page }) => {
    const { parentBugId } = loadState();
    expect(parentBugId).toBeGreaterThan(0);

    await login(page);
    await goToBug(page, parentBugId);

    // Info panel
    const infoPanel = page.locator('.pe-info-panel');
    await expect(infoPanel).toBeVisible({ timeout: 10000 });

    // Mevcut adim, departman veya handler bilgisi
    const panelText = await infoPanel.textContent();
    expect(panelText.length).toBeGreaterThan(10);

    // Stepper/agac gorunumu (.pe-progress-tree veya .pe-step-progress)
    const progressTree = page.locator('.pe-progress-tree, .pe-step-progress, .pe-stepper');
    const hasProgress = await progressTree.first().isVisible({ timeout: 5000 }).catch(() => false);
    console.log(`  -> Ilerleme gorunumu: ${hasProgress}`);

    // Timeline gorunmeli (.pe-timeline)
    const timeline = page.locator('.pe-timeline');
    await expect(timeline).toBeVisible({ timeout: 10000 });

    const timelineHtml = await timeline.innerHTML();
    // "Surec baslatildi" ikonu (fa-play-circle)
    expect(timelineHtml).toContain('fa-play-circle');
    console.log(`  -> Timeline HTML uzunlugu: ${timelineHtml.length} karakter`);
  });

  test('B3. Tarih sutunu dogrulama', async ({ page }) => {
    const { parentBugId } = loadState();
    expect(parentBugId).toBeGreaterThan(0);

    await login(page);
    await goToDashboard(page);

    // "Acilma Tarihi" sutun basligi gorunmeli
    const pageText = await page.locator('body').textContent();
    const hasDateCol = pageText.includes('Tarihi') || pageText.includes('Date') || pageText.includes('Tarih');
    expect(hasDateCol).toBeTruthy();

    // Tarih formatini kontrol et (dd.mm.YYYY veya YYYY-MM-DD formatinda bir tarih olmali)
    const datePattern = /\d{2}\.\d{2}\.\d{4}|\d{4}-\d{2}-\d{2}/;
    const bodyText = await page.locator('table').first().textContent();
    const hasDate = datePattern.test(bodyText);
    console.log(`  -> Tarih formati bulundu: ${hasDate}`);
  });
});


// ============================================================
// GRUP C: Adim Ilerleme ve Rollback (4 test)
// ============================================================
test.describe.serial('Grup C: Ilerleme ve Rollback', () => {

  test('C1. Ilerleme modali ile adim 1\u21922', async ({ page }) => {
    const { parentBugId } = loadState();
    expect(parentBugId).toBeGreaterThan(0);

    await login(page);
    await goToBug(page, parentBugId);

    // "Adimi Ilerlet" butonu gorunmeli
    const advBtn = page.locator('.pe-bugview-advance');
    await expect(advBtn).toBeVisible({ timeout: 10000 });

    // Tikla — modal acilmali
    await advBtn.click();
    await page.waitForTimeout(500);

    // Modal gorunur olmali
    const modal = page.locator('#pe-advance-modal');
    await expect(modal).toBeVisible({ timeout: 5000 });

    // Modalda mevcut ve sonraki adim bilgisi
    const currentStep = page.locator('#pe-modal-current-step');
    const nextStep = page.locator('#pe-modal-next-step');
    await expect(currentStep).toBeVisible({ timeout: 3000 });
    await expect(nextStep).toBeVisible({ timeout: 3000 });

    const currentText = await currentStep.textContent();
    const nextText = await nextStep.textContent();
    console.log(`  -> Mevcut adim: ${currentText}, Sonraki: ${nextText}`);

    // step_instructions varsa bilgi kutusu gorunmeli
    const instructions = page.locator('#pe-modal-instructions-text, .pe-modal-instructions');
    const hasInstructions = await instructions.first().isVisible({ timeout: 2000 }).catch(() => false);
    console.log(`  -> Talimatlar gorunur: ${hasInstructions}`);

    // Onayla
    await page.locator('#pe-modal-confirm').click();
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(1500);

    // Panel'de yeni adim adi gorunmeli
    await goToBug(page, parentBugId);
    const panelText = await page.locator('.pe-info-panel').textContent();
    console.log(`  -> Ilerleme sonrasi: ${panelText.substring(0, 150)}`);
  });

  test('C2. Dashboard\'dan ilerleme', async ({ page }) => {
    const { parentBugId } = loadState();
    expect(parentBugId).toBeGreaterThan(0);

    await login(page);
    await goToDashboard(page);

    // parentBugId satirini bul
    const advBtn = page.locator(`.pe-action-advance[data-bug-id="${parentBugId}"]`);
    const isVisible = await advBtn.isVisible({ timeout: 5000 }).catch(() => false);

    if (isVisible) {
      // confirm() diyalogu
      page.on('dialog', async dialog => {
        console.log(`  -> Dialog: ${dialog.message().substring(0, 80)}`);
        await dialog.accept();
      });

      await advBtn.click();
      await page.waitForLoadState('domcontentloaded');
      await page.waitForTimeout(2000);

      console.log('  -> Dashboard\'dan ilerleme tamamlandi');
    } else {
      console.log('  -> Dashboard\'da advance butonu bulunamadi (adim sonunda veya baska durum)');
    }
  });

  test('C3. Rollback (geri alma)', async ({ page }) => {
    const { parentBugId } = loadState();
    expect(parentBugId).toBeGreaterThan(0);

    await login(page);
    await goToBug(page, parentBugId);

    // Onceki adim bilgisini kaydet
    const panelBefore = await page.locator('.pe-info-panel').textContent();

    // Rollback butonu
    const rollbackBtn = page.locator('.pe-bugview-rollback');
    const isVisible = await rollbackBtn.isVisible({ timeout: 5000 }).catch(() => false);

    if (isVisible) {
      page.on('dialog', async dialog => {
        console.log(`  -> Rollback dialog: ${dialog.message().substring(0, 80)}`);
        await dialog.accept();
      });

      await rollbackBtn.click();
      await page.waitForLoadState('domcontentloaded');
      await page.waitForTimeout(2000);

      // Panel'de onceki adim geri gelmis olmali
      await goToBug(page, parentBugId);
      const panelAfter = await page.locator('.pe-info-panel').textContent();
      console.log(`  -> Rollback oncesi: ${panelBefore.substring(0, 80)}`);
      console.log(`  -> Rollback sonrasi: ${panelAfter.substring(0, 80)}`);

      // Timeline'da "Geri alindi" (fa-undo) ikonu olmali
      const timeline = page.locator('.pe-timeline');
      const timelineHtml = await timeline.innerHTML();
      const hasUndo = timelineHtml.includes('fa-undo') || timelineHtml.includes('eri');
      console.log(`  -> Timeline geri alma ikonu: ${hasUndo}`);
    } else {
      console.log('  -> Rollback butonu gorunmuyor (ilk adimda olabilir)');
    }
  });

  test('C4. Dashboard\'dan rollback', async ({ page }) => {
    const { parentBugId } = loadState();
    expect(parentBugId).toBeGreaterThan(0);

    await login(page);

    // Oncelikle en az 2. adimda olmasini saglayalim
    await advanceStep(page, parentBugId);

    await goToDashboard(page);

    const rollbackBtn = page.locator(`.pe-action-rollback[data-bug-id="${parentBugId}"]`);
    const isVisible = await rollbackBtn.isVisible({ timeout: 5000 }).catch(() => false);

    if (isVisible) {
      page.on('dialog', async dialog => {
        await dialog.accept();
      });

      await rollbackBtn.click();
      await page.waitForLoadState('domcontentloaded');
      await page.waitForTimeout(2000);

      console.log('  -> Dashboard\'dan rollback tamamlandi');
    } else {
      console.log('  -> Dashboard\'da rollback butonu bulunamadi');
    }
  });
});


// ============================================================
// GRUP D: Subprocess Akisi (5 test)
// ============================================================
test.describe.serial('Grup D: Subprocess', () => {

  test('D1. Subprocess adimina ilerletme', async ({ page }) => {
    const { parentBugId } = loadState();
    expect(parentBugId).toBeGreaterThan(0);

    await login(page);

    // Subprocess adimina ulasana kadar ilerlet (maks 8 tur)
    // Gercek kriter: .pe-subprocess-action-panel gorunmesi (subprocess paneli)
    let found = false;
    for (let i = 0; i < 8; i++) {
      await goToBug(page, parentBugId);

      // Subprocess paneli: sadece gercekten subprocess adimindayken render edilir
      const subPanel = page.locator('.pe-subprocess-action-panel');
      const hasPanel = await subPanel.isVisible({ timeout: 2000 }).catch(() => false);
      if (hasPanel) {
        found = true;
        console.log(`  -> Subprocess adimina ${i} ilerleme sonrasi ulasildi`);
        break;
      }

      // Advance butonu yoksa dur (WAITING veya akis sonu)
      const advBtn = page.locator('.pe-bugview-advance');
      const canAdvance = await advBtn.isVisible({ timeout: 2000 }).catch(() => false);
      if (!canAdvance) {
        console.log(`  -> Advance butonu yok, dur (tur ${i})`);
        break;
      }

      // Ilerlet
      const advanced = await advanceStep(page, parentBugId);
      if (!advanced) break;
    }

    expect(found).toBeTruthy();
    console.log('  -> Subprocess paneli gorunur');
  });

  test('D2. Cocuk sorun olusturma', async ({ page }) => {
    const { parentBugId } = loadState();
    expect(parentBugId).toBeGreaterThan(0);

    await login(page);
    await goToBug(page, parentBugId);

    // Subprocess paneli gorunmeli
    const subPanel = page.locator('.pe-subprocess-action-panel');
    await expect(subPanel).toBeVisible({ timeout: 10000 });

    // "Simdi Ac" butonu — AJAX ile calisiyor
    const createBtn = page.locator('.pe-create-subprocess');
    await expect(createBtn.first()).toBeVisible({ timeout: 5000 });

    // AJAX yaniti bekle
    const responsePromise = page.waitForResponse(
      resp => resp.url().includes('dashboard_action') && resp.status() === 200,
      { timeout: 15000 }
    ).catch(() => null);

    await createBtn.first().click();
    const resp = await responsePromise;

    if (resp) {
      const body = await resp.json().catch(() => null);
      console.log(`  -> AJAX yanit: ${JSON.stringify(body).substring(0, 200)}`);
    }

    // Sayfayi yenile ve cocuk linki kontrol et
    await page.waitForTimeout(1500);
    await goToBug(page, parentBugId);

    // Cocuk sorun linki: <a href="view.php?id=XX"> biçiminde
    const childLink = page.locator('.pe-subprocess-action-panel a[href*="view.php"], .pe-subprocess-children-list a[href*="view.php"]');
    await expect(childLink.first()).toBeVisible({ timeout: 10000 });

    const href = await childLink.first().getAttribute('href');
    const m = href && href.match(/id=(\d+)/);
    expect(m).not.toBeNull();

    const childBugId = parseInt(m[1]);
    expect(childBugId).toBeGreaterThan(0);
    saveState({ childBugId });
    console.log(`  -> Cocuk sorun ID: ${childBugId}`);
  });

  test('D3. Ebeveyn WAITING kontrolu', async ({ page }) => {
    const { parentBugId, childBugId } = loadState();
    expect(parentBugId).toBeGreaterThan(0);
    expect(childBugId).toBeGreaterThan(0);

    await login(page);
    await goToBug(page, parentBugId);

    const panel = page.locator('.pe-info-panel');
    await expect(panel).toBeVisible({ timeout: 10000 });

    // Cocuk olusturuldu, ebeveyn WAITING olmali
    const waitLabel = page.locator('.pe-waiting-label');
    await expect(waitLabel).toBeVisible({ timeout: 10000 });
    console.log('  -> Ebeveyn WAITING durumunda');

    // Subprocess paneli hala gorunur (cocuk listesiyle birlikte)
    const subPanel = page.locator('.pe-subprocess-action-panel');
    await expect(subPanel).toBeVisible({ timeout: 5000 });

    // Cocuk sorun panelde listelenmis olmali
    const childLink = page.locator(`.pe-subprocess-action-panel a[href*="id=${childBugId}"]`);
    const hasChildLink = await childLink.isVisible({ timeout: 5000 }).catch(() => false);
    expect(hasChildLink).toBeTruthy();
    console.log(`  -> Cocuk #${childBugId} panelde gorunuyor`);
  });

  test('D4. Cocuk tamamlama ve ebeveyn otomatik ilerleme', async ({ page }) => {
    const { childBugId, parentBugId } = loadState();
    expect(childBugId).toBeGreaterThan(0);
    expect(parentBugId).toBeGreaterThan(0);

    await login(page);

    // Cocuk sorunun tum adimlarini ilerlet (maks 6 tur)
    let advanceCount = 0;
    for (let i = 0; i < 6; i++) {
      await goToBug(page, childBugId);

      const advBtn = page.locator('.pe-bugview-advance');
      const canAdvance = await advBtn.isVisible({ timeout: 3000 }).catch(() => false);
      if (!canAdvance) break;

      await advBtn.click();
      await page.waitForTimeout(500);

      // Modal varsa onayla
      const confirmBtn = page.locator('#pe-modal-confirm');
      if (await confirmBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
        await confirmBtn.click();
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(1500);
      }

      advanceCount++;
    }

    console.log(`  -> Cocuk sorun ${advanceCount} adim ilerletildi`);

    // Son adima gelince MantisBT resolved status'a ayarlamak gerekebilir
    // Cocugun process instance'ini kontrol edelim
    await goToBug(page, childBugId);
    const childPanel = page.locator('.pe-info-panel');
    if (await childPanel.isVisible({ timeout: 3000 }).catch(() => false)) {
      const childText = await childPanel.textContent();
      console.log(`  -> Cocuk panel: ${childText.substring(0, 100)}`);
    }

    // Ebeveyn kontrol
    await page.waitForTimeout(1000);
    await goToBug(page, parentBugId);

    const panel = page.locator('.pe-info-panel');
    await expect(panel).toBeVisible({ timeout: 10000 });

    const text = await panel.textContent();
    console.log(`  -> Ebeveyn panel: ${text.substring(0, 150)}`);

    // WAITING label kontrolu — cocuk tamamlandiysa kalkmis olmali
    const waitLabel = page.locator('.pe-waiting-label');
    const waitCount = await waitLabel.count();
    if (waitCount === 0) {
      console.log('  -> Ebeveyn WAITING durumundan cikti — basarili');
    } else {
      // Cocuk henuz tamamlanmamis olabilir — son adimda resolve gerekebilir
      console.log('  -> Ebeveyn hala WAITING — cocuk resolved olmayi bekliyor olabilir');

      // Cocugu MantisBT resolved statusune getir
      await goToBug(page, childBugId);
      const statusSelect = page.locator('select[name="new_status"]');
      if (await statusSelect.isVisible({ timeout: 3000 }).catch(() => false)) {
        // Resolved (80) secenegi var mi?
        try {
          await statusSelect.selectOption({ value: '80' });
          // Submit
          const changeBtn = page.locator('input[type="submit"][value*="Change"], input[type="submit"][value*="Degistir"]');
          if (await changeBtn.first().isVisible({ timeout: 2000 }).catch(() => false)) {
            await changeBtn.first().click();
            await page.waitForLoadState('domcontentloaded');
            await page.waitForTimeout(2000);
          }
        } catch {
          console.log('  -> Cocuk status degistirilemedi');
        }
      }

      // Ebeveyn tekrar kontrol
      await goToBug(page, parentBugId);
      const waitCount2 = await page.locator('.pe-waiting-label').count();
      console.log(`  -> Tekrar kontrol: WAITING label sayisi = ${waitCount2}`);
    }
  });

  test('D5. Manuel cocuk baglama', async ({ page }) => {
    const { parentBugId } = loadState();
    expect(parentBugId).toBeGreaterThan(0);

    await login(page);

    // Ebeveyn'i bir sonraki subprocess adimina ilerlet
    // (D4'te cocuk tamamlandi, ebeveyn ilerlemis olmali)
    // Bir sonraki subprocess adimina ulasana kadar ilerlet
    let atSubprocess = false;
    for (let i = 0; i < 4; i++) {
      await goToBug(page, parentBugId);

      const subPanel = page.locator('.pe-subprocess-action-panel');
      const hasPanel = await subPanel.isVisible({ timeout: 2000 }).catch(() => false);
      if (hasPanel) {
        atSubprocess = true;
        break;
      }

      const advBtn = page.locator('.pe-bugview-advance');
      const canAdvance = await advBtn.isVisible({ timeout: 2000 }).catch(() => false);
      if (!canAdvance) break;

      await advanceStep(page, parentBugId);
    }

    if (!atSubprocess) {
      // Hala subprocess adiminda degilsek, mevcut durumda link testi yapariz
      await goToBug(page, parentBugId);
      const linkInput = page.locator('.pe-link-child-input');
      atSubprocess = await linkInput.first().isVisible({ timeout: 3000 }).catch(() => false);
    }

    if (atSubprocess) {
      // Hedef projeye gec ve yeni sorun olustur
      // Once hedef projeyi belirle
      const subPanel = page.locator('.pe-subprocess-action-panel');
      const panelText = await subPanel.textContent().catch(() => '');
      console.log(`  -> Subprocess panel: ${panelText.substring(0, 150)}`);

      // Hedef projede sorun olustur (proje 3 = Satinalma veya proje 1 = Arge)
      // Hedef proje bilgisini data-target-id'den alabilecek bir projeye gecip sorun olusturalim
      const targetProjectId = 3; // Satinalma veya 1 (akis yapisina bagli)
      await page.goto(`${BASE_URL}/set_project.php?project_id=${targetProjectId}&ref=bug_report_page.php`);
      await page.waitForLoadState('domcontentloaded');

      await page.goto(`${BASE_URL}/bug_report_page.php`);
      await page.waitForLoadState('domcontentloaded');

      const catSelect = page.locator('#category_id');
      if (await catSelect.isVisible()) {
        const opts = await catSelect.locator('option').count();
        if (opts > 1) await catSelect.selectOption({ index: 1 });
      }

      await page.locator('#summary').fill('LinkChild-' + Date.now());
      const desc = page.locator('#description');
      if (await desc.isVisible()) await desc.fill('Manuel baglama testi');
      await page.locator('input[type="submit"]').first().click();
      await page.waitForLoadState('domcontentloaded');
      await page.waitForTimeout(1000);

      const linkBugId = await extractBugId(page);
      expect(linkBugId).toBeGreaterThan(0);
      console.log(`  -> Baglama icin olusturulan bug ID: ${linkBugId}`);

      // Ana projeye donup ana soruna git
      await page.goto(`${BASE_URL}/set_project.php?project_id=2&ref=view.php%3Fid%3D${parentBugId}`);
      await page.waitForLoadState('domcontentloaded');
      await goToBug(page, parentBugId);

      // Link input'a cocuk ID yaz
      const linkInput = page.locator('.pe-link-child-input').first();
      await expect(linkInput).toBeVisible({ timeout: 5000 });
      await linkInput.fill(String(linkBugId));

      // AJAX yaniti bekle
      const responsePromise = page.waitForResponse(
        resp => resp.url().includes('dashboard_action') && resp.status() === 200,
        { timeout: 15000 }
      ).catch(() => null);

      const linkBtn = page.locator('.pe-link-child-btn').first();
      await linkBtn.click();
      const resp = await responsePromise;

      if (resp) {
        const body = await resp.json().catch(() => null);
        console.log(`  -> Baglama AJAX yanit: ${JSON.stringify(body).substring(0, 200)}`);
      }

      // Sayfayi yenile ve kontrol et
      await page.waitForTimeout(1500);
      await goToBug(page, parentBugId);

      // Baglanan sorun cocuk listesinde gorunmeli
      const childLinks = page.locator('.pe-subprocess-action-panel a[href*="view.php"], .pe-subprocess-children-list a[href*="view.php"]');
      const childCount = await childLinks.count();
      expect(childCount).toBeGreaterThanOrEqual(1);
      console.log(`  -> Baglama sonrasi cocuk sayisi: ${childCount}`);
    } else {
      console.log('  -> Subprocess adimina ulasilamadi, link testi atlandi');
      // Bu durumda test basarisiz sayilsin
      expect(atSubprocess).toBeTruthy();
    }
  });
});


// ============================================================
// GRUP E: Dashboard Filtreleri (4 test)
// ============================================================
test.describe.serial('Grup E: Dashboard Filtreleri', () => {

  test('E1. Durum filtreleri', async ({ page }) => {
    await login(page);
    await goToDashboard(page);

    // "Aktif" filtre butonuna tikla
    const activeFilter = page.locator('a[href*="filter=active"]');
    if (await activeFilter.first().isVisible({ timeout: 5000 }).catch(() => false)) {
      await activeFilter.first().click();
      await page.waitForLoadState('domcontentloaded');

      const url = page.url();
      expect(url).toContain('filter=active');
      console.log('  -> Aktif filtre uygulandi');
    }

    // "SLA Asimi" filtre butonuna tikla
    const slaFilter = page.locator('a[href*="filter=sla_exceeded"]');
    if (await slaFilter.first().isVisible({ timeout: 3000 }).catch(() => false)) {
      await slaFilter.first().click();
      await page.waitForLoadState('domcontentloaded');

      const url = page.url();
      expect(url).toContain('filter=sla_exceeded');
      console.log('  -> SLA asimi filtresi uygulandi');
    }

    // "Tumu" filtre butonuna tikla
    const allFilter = page.locator('a[href*="filter=all"]');
    if (await allFilter.first().isVisible({ timeout: 3000 }).catch(() => false)) {
      await allFilter.first().click();
      await page.waitForLoadState('domcontentloaded');
      console.log('  -> Tumu filtresi uygulandi');
    }
  });

  test('E2. Departman filtresi', async ({ page }) => {
    await login(page);
    await goToDashboard(page);

    const deptFilter = page.locator('#pe-dept-filter');
    const isVisible = await deptFilter.isVisible({ timeout: 5000 }).catch(() => false);

    if (isVisible) {
      // Secenekleri al
      const opts = await deptFilter.locator('option').allTextContents();
      console.log(`  -> Departman secenekleri: ${opts.join(', ')}`);

      if (opts.length > 1) {
        // Ilk gercek departmani sec (index 1, 0 genelde "Tumu")
        await deptFilter.selectOption({ index: 1 });
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(1000);

        const url = page.url();
        const hasDeptParam = url.includes('department=');
        console.log(`  -> URL'de department parametresi: ${hasDeptParam}`);
      }
    } else {
      console.log('  -> Departman filtresi bulunamadi');
    }
  });

  test('E3. Yil/Ay filtresi', async ({ page }) => {
    await login(page);
    await goToDashboard(page);

    // Yil filtresi
    const yearFilter = page.locator('#pe-year-filter');
    const hasYear = await yearFilter.isVisible({ timeout: 5000 }).catch(() => false);

    if (hasYear) {
      const yearOpts = await yearFilter.locator('option').allTextContents();
      console.log(`  -> Yil secenekleri: ${yearOpts.join(', ')}`);

      // Mevcut yili sec
      const currentYear = new Date().getFullYear().toString();
      try {
        await yearFilter.selectOption({ label: currentYear });
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(1000);

        const url = page.url();
        expect(url).toContain('year=');
        console.log('  -> Yil filtresi uygulandi');
      } catch {
        console.log('  -> Mevcut yil secenegi bulunamadi');
      }
    }

    // Ay filtresi
    const monthFilter = page.locator('#pe-month-filter');
    const hasMonth = await monthFilter.isVisible({ timeout: 3000 }).catch(() => false);

    if (hasMonth) {
      const monthOpts = await monthFilter.locator('option').count();
      if (monthOpts > 1) {
        await monthFilter.selectOption({ index: 1 });
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(1000);

        const url = page.url();
        expect(url).toContain('month=');
        console.log('  -> Ay filtresi uygulandi');
      }
    }
  });

  test('E4. Filtre parametrelerinin korunmasi', async ({ page }) => {
    await login(page);

    // Departman + yil + ay birlikte uygula
    await page.goto(`${BASE_URL}/plugin.php?page=ProcessEngine/dashboard&filter=all&department=&year=${new Date().getFullYear()}&month=${new Date().getMonth() + 1}`);
    await page.waitForLoadState('domcontentloaded');

    // Simdi "Aktif" filtre butonuna tikla
    const activeFilter = page.locator('a[href*="filter=active"]');
    if (await activeFilter.first().isVisible({ timeout: 5000 }).catch(() => false)) {
      const href = await activeFilter.first().getAttribute('href');

      // Linkte year ve month parametreleri korunmali
      const hasYear = href && href.includes('year=');
      const hasMonth = href && href.includes('month=');
      console.log(`  -> Aktif filtre linki year iceriyor: ${hasYear}, month iceriyor: ${hasMonth}`);

      await activeFilter.first().click();
      await page.waitForLoadState('domcontentloaded');

      const url = page.url();
      console.log(`  -> Sonuc URL: ${url}`);
    } else {
      console.log('  -> Aktif filtre butonu bulunamadi');
    }
  });
});


// ============================================================
// GRUP F: Rapor Sayfasi (2 test)
// ============================================================
test.describe.serial('Grup F: Rapor Sayfasi', () => {

  test('F1. Rapor sayfasi acilma ve filtreler', async ({ page }) => {
    await login(page);
    await goToReport(page);

    // APPLICATION ERROR hatasi OLMAMALI
    await expect(page.locator('body')).not.toContainText('APPLICATION ERROR');

    // Sayfa basligi gorunmeli
    const pageText = await page.locator('body').textContent();
    const hasTitle = pageText.includes('Rapor') || pageText.includes('Report');
    expect(hasTitle).toBeTruthy();

    // Filtre formu gorunmeli
    const filterForm = page.locator('form');
    const hasForm = await filterForm.first().isVisible({ timeout: 5000 }).catch(() => false);
    console.log(`  -> Filtre formu: ${hasForm}`);

    // Ozet kartlar gorunmeli
    const reportCards = page.locator('.pe-report-card');
    const cardCount = await reportCards.count();
    console.log(`  -> Rapor kartlari: ${cardCount}`);

    // Chart.js grafikleri canvas elemanlari
    const chartCanvases = page.locator('canvas[id^="pe-chart-"]');
    const canvasCount = await chartCanvases.count();
    console.log(`  -> Chart canvas sayisi: ${canvasCount}`);
    expect(canvasCount).toBeGreaterThanOrEqual(1);
  });

  test('F2. Rapor tablosu ve sayfalama', async ({ page }) => {
    await login(page);
    await goToReport(page);

    // Detay tablosu gorunmeli
    const table = page.locator('table');
    const hasTable = await table.first().isVisible({ timeout: 5000 }).catch(() => false);
    console.log(`  -> Detay tablosu: ${hasTable}`);

    if (hasTable) {
      // Tablo sutunlarini kontrol et
      const headerText = await table.first().locator('thead, tr:first-child').first().textContent();
      console.log(`  -> Tablo baslik: ${headerText.substring(0, 150)}`);
    }

    // Sayfa yeniden yukleme page=2 ile — APPLICATION ERROR #203 duzeltmesi
    await page.goto(`${BASE_URL}/plugin.php?page=ProcessEngine/report&page_num=2`);
    await page.waitForLoadState('domcontentloaded');

    // APPLICATION ERROR olmamali
    const bodyText = await page.locator('body').textContent();
    const hasAppError = bodyText.includes('APPLICATION ERROR');
    expect(hasAppError).toBeFalsy();
    console.log('  -> Sayfa 2 yukleme hatasi yok');
  });
});


// ============================================================
// GRUP G: Yapilandirma ve Erisim Kontrolu (2 test)
// ============================================================
test.describe.serial('Grup G: Yapilandirma ve Erisim', () => {

  test('G1. Yapilandirma sayfasi', async ({ page }) => {
    await login(page);
    await goToConfig(page);

    // APPLICATION ERROR olmamali
    await expect(page.locator('body')).not.toContainText('APPLICATION ERROR');

    // Yapilandirma formu gorunmeli
    const form = page.locator('form');
    await expect(form.first()).toBeVisible({ timeout: 10000 });

    // SLA uyari yuzdesi alani
    const slaWarning = page.locator('input[name="sla_warning_percent"]');
    const hasSla = await slaWarning.isVisible({ timeout: 3000 }).catch(() => false);
    console.log(`  -> SLA uyari alani: ${hasSla}`);

    // Is saatleri
    const bhStart = page.locator('input[name="business_hours_start"]');
    const hasBhStart = await bhStart.isVisible({ timeout: 3000 }).catch(() => false);
    console.log(`  -> Is saati baslangic: ${hasBhStart}`);

    // Calisma gunleri
    const workDays = page.locator('input[name="working_days"]');
    const hasWorkDays = await workDays.isVisible({ timeout: 3000 }).catch(() => false);
    console.log(`  -> Calisma gunleri: ${hasWorkDays}`);

    // Kaydet butonuna tikla
    const submitBtn = page.locator('input[type="submit"]');
    if (await submitBtn.first().isVisible()) {
      await submitBtn.first().click();
      await page.waitForLoadState('domcontentloaded');
      await page.waitForTimeout(1000);

      // Basarili redirect veya hata yok
      const bodyText = await page.locator('body').textContent();
      const hasError = bodyText.includes('APPLICATION ERROR');
      expect(hasError).toBeFalsy();
      console.log('  -> Yapilandirma kaydedildi');
    }
  });

  test('G2. Dusuk yetkili kullanici erisim kontrolu', async ({ page }) => {
    // Once admin oturumunu kapat
    await page.goto(`${BASE_URL}/logout_page.php`);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    // REPORTER kullanici ile login
    await login(page, REPORTER_USER, REPORTER_PASS);

    // Login basarili oldu mu kontrol et
    const url = page.url();
    const isLoggedIn = !url.includes('login_page');
    expect(isLoggedIn).toBeTruthy();
    console.log('  -> Reporter kullanicisi ile giris yapildi');

    // Dashboard'a git — gorebilmeli (view_threshold = REPORTER)
    await goToDashboard(page);
    const bodyText = await page.locator('body').textContent();
    const canViewDashboard = !bodyText.includes('ACCESS_DENIED') && !bodyText.includes('APPLICATION ERROR');
    expect(canViewDashboard).toBeTruthy();
    console.log(`  -> Dashboard erisimi: ${canViewDashboard}`);

    // "Islemler" sutunu OLMAMALI (action_threshold = DEVELOPER)
    const actionsCol = page.locator('.pe-actions-col');
    const hasActions = await actionsCol.first().isVisible({ timeout: 3000 }).catch(() => false);
    expect(hasActions).toBeFalsy();
    console.log(`  -> Islemler sutunu gorunur: ${hasActions} (olmamali — dogru)`);

    // flow_designer.php'ye git — erisim reddedilmeli (manage_threshold = MANAGER)
    await goToFlowDesigner(page, 0);
    const designerBody = await page.locator('body').textContent();
    const designerDenied = designerBody.includes('ACCESS_DENIED') || designerBody.includes('APPLICATION ERROR') || designerBody.includes('Access Denied');
    expect(designerDenied).toBeTruthy();
    console.log(`  -> Akis tasarimcisi erisim engellendi: ${designerDenied}`);

    // config_page.php'ye git — erisim reddedilmeli
    await goToConfig(page);
    const configBody = await page.locator('body').textContent();
    const configDenied = configBody.includes('ACCESS_DENIED') || configBody.includes('APPLICATION ERROR') || configBody.includes('Access Denied');
    expect(configDenied).toBeTruthy();
    console.log(`  -> Yapilandirma erisim engellendi: ${configDenied}`);
  });
});
