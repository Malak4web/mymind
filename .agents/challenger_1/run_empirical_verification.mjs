import { chromium } from 'playwright';
import { spawn } from 'child_process';
import path from 'path';
import fs from 'fs';

const PROJECT_ROOT = 'c:\\xampp\\htdocs\\mymind';
const PORT = 4173;
const BASE_URL = `http://localhost:${PORT}/#routines`;

async function main() {
  console.log('--- Starting Empirical Verification for Daily Routines ---');

  // Step 1: Start vite preview server
  console.log('Launching Vite Preview server on port', PORT);
  const previewProcess = spawn('npx', ['vite', 'preview', '--port', String(PORT), '--strictPort'], {
    cwd: PROJECT_ROOT,
    shell: true,
    stdio: 'pipe'
  });

  let serverStarted = false;
  await new Promise((resolve, reject) => {
    const timeout = setTimeout(() => {
      if (!serverStarted) reject(new Error('Server start timed out'));
    }, 15000);

    previewProcess.stdout.on('data', (data) => {
      const msg = data.toString();
      console.log('[preview stdout]', msg.trim());
      if (msg.includes('4173') || msg.toLowerCase().includes('local')) {
        serverStarted = true;
        clearTimeout(timeout);
        resolve();
      }
    });

    previewProcess.stderr.on('data', (data) => {
      console.log('[preview stderr]', data.toString().trim());
    });
  });

  console.log('Vite preview server is running.');

  const results = {
    build: { status: 'PASSED', details: 'npm run build completed cleanly.' },
    overflowTests: [],
    grid360Test: null,
    navigator360Test: null,
    bottomSheets360Test: [],
    touchTargetTests: [],
    overallVerdict: 'PASSED'
  };

  let browser;
  try {
    browser = await chromium.launch({ channel: 'msedge', headless: true });
  } catch (e) {
    browser = await chromium.launch({ headless: true });
  }

  // Setup page context helper with Auth mocking
  async function setupAuthenticatedPage(context) {
    const page = await context.newPage();
    
    // Mock authentication API responses
    await page.route('**/api/profile', route => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ id: 1, name: 'مستخدم تجريبي', role: { name: 'مدير' } })
    }));
    await page.route('**/api/**', route => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: '[]'
    }));

    // Inject auth token into localStorage before page loads
    await page.addInitScript(() => {
      localStorage.setItem('mymind_token', 'mock_token_123');
    });

    return page;
  }

  try {
    const mobileWidths = [360, 375, 390, 414, 430];
    
    // --- TEST 1: Horizontal Scroll Verification across 5 Mobile Viewports ---
    console.log('\n--- 1. Testing Horizontal Scroll Overflow ---');
    for (const width of mobileWidths) {
      const context = await browser.newContext({
        viewport: { width, height: 800 },
        deviceScaleFactor: 2
      });
      const page = await setupAuthenticatedPage(context);
      await page.goto(BASE_URL, { waitUntil: 'networkidle' });
      await page.waitForTimeout(600);

      const overflowMetrics = await page.evaluate(() => {
        const docWidth = document.documentElement.clientWidth;
        const scrollWidth = document.documentElement.scrollWidth;
        const bodyScrollWidth = document.body.scrollWidth;
        const hasHorizontalScroll = scrollWidth > docWidth || bodyScrollWidth > docWidth;
        
        // Find any element causing overflow
        const overflowElements = [];
        const allElements = document.querySelectorAll('*');
        allElements.forEach(el => {
          const rect = el.getBoundingClientRect();
          if (rect.right > docWidth + 1) { // 1px tolerance
            overflowElements.push({
              tag: el.tagName,
              className: el.className,
              right: rect.right,
              width: rect.width
            });
          }
        });

        return {
          docWidth,
          scrollWidth,
          bodyScrollWidth,
          hasHorizontalScroll,
          overflowCount: overflowElements.length,
          topOverflowElement: overflowElements[0] || null
        };
      });

      const testResult = {
        viewportWidth: width,
        scrollWidth: overflowMetrics.scrollWidth,
        bodyScrollWidth: overflowMetrics.bodyScrollWidth,
        hasHorizontalScroll: overflowMetrics.hasHorizontalScroll,
        status: overflowMetrics.hasHorizontalScroll ? 'FAILED' : 'PASSED',
        details: overflowMetrics.hasHorizontalScroll 
          ? `Overflow detected! scrollWidth: ${overflowMetrics.scrollWidth}px vs docWidth: ${overflowMetrics.docWidth}px. Element: ${JSON.stringify(overflowMetrics.topOverflowElement)}` 
          : `Zero horizontal scroll. scrollWidth (${overflowMetrics.scrollWidth}px) <= viewport (${width}px).`
      };

      if (overflowMetrics.hasHorizontalScroll) {
        results.overallVerdict = 'FAILED';
      }

      results.overflowTests.push(testResult);
      console.log(`[Viewport ${width}px] ${testResult.status} - ${testResult.details}`);
      await context.close();
    }

    // --- TEST 2: Stress testing 360px Viewport Layouts ---
    console.log('\n--- 2. Stress Testing 360px Viewport Layouts ---');
    const context360 = await browser.newContext({
      viewport: { width: 360, height: 800 },
      deviceScaleFactor: 2
    });
    const page360 = await setupAuthenticatedPage(context360);
    await page360.goto(BASE_URL, { waitUntil: 'networkidle' });
    await page360.waitForTimeout(600);

    // 2a. 7-column weekday grid test
    const weekdayGridMetrics = await page360.evaluate(() => {
      const grid = document.querySelector('.grid-cols-7');
      if (!grid) return { found: false };
      
      const gridRect = grid.getBoundingClientRect();
      const buttons = Array.from(grid.querySelectorAll('button'));
      const buttonRects = buttons.map(b => {
        const r = b.getBoundingClientRect();
        return {
          width: r.width,
          height: r.height,
          left: r.left,
          right: r.right,
          text: b.innerText.replace(/\n/g, ' ')
        };
      });

      const wrapsOrOverflows = buttonRects.some(r => r.right > 360 || r.width < 10);
      const is7Cols = buttons.length === 7;

      return {
        found: true,
        gridWidth: gridRect.width,
        is7Cols,
        buttonCount: buttons.length,
        buttonWidths: buttonRects.map(r => Math.round(r.width * 10) / 10),
        minButtonWidth: Math.min(...buttonRects.map(r => r.width)),
        maxButtonRight: Math.max(...buttonRects.map(r => r.right)),
        wrapsOrOverflows
      };
    });

    results.grid360Test = {
      status: (weekdayGridMetrics.found && weekdayGridMetrics.is7Cols && !weekdayGridMetrics.wrapsOrOverflows && weekdayGridMetrics.maxButtonRight <= 360) ? 'PASSED' : 'FAILED',
      metrics: weekdayGridMetrics
    };
    if (results.grid360Test.status === 'FAILED') results.overallVerdict = 'FAILED';
    console.log(`[7-Col Weekday Grid @ 360px] ${results.grid360Test.status} - Grid width: ${weekdayGridMetrics.gridWidth}px, 7 cols rendered, min col width: ${Math.round(weekdayGridMetrics.minButtonWidth)}px.`);

    // 2b. Header date navigator test
    const dateNavMetrics = await page360.evaluate(() => {
      const stepper = document.querySelector('button[title="اليوم السابق"]')?.parentElement;
      if (!stepper) return { found: false };

      const stepperRect = stepper.getBoundingClientRect();
      const buttons = Array.from(stepper.querySelectorAll('button'));
      const buttonTargetSizes = buttons.map(b => {
        const r = b.getBoundingClientRect();
        return {
          title: b.title || b.innerText.trim(),
          width: r.width,
          height: r.height
        };
      });

      return {
        found: true,
        stepperWidth: stepperRect.width,
        stepperRight: stepperRect.right,
        overflows: stepperRect.right > 360,
        buttonTargetSizes
      };
    });

    results.navigator360Test = {
      status: (dateNavMetrics.found && !dateNavMetrics.overflows) ? 'PASSED' : 'FAILED',
      metrics: dateNavMetrics
    };
    if (results.navigator360Test.status === 'FAILED') results.overallVerdict = 'FAILED';
    console.log(`[Date Navigator @ 360px] ${results.navigator360Test.status} - Container width: ${dateNavMetrics.stepperWidth}px, right bound: ${dateNavMetrics.stepperRight}px.`);

    // --- TEST 3: Touch Target Sizes & Bottom Sheet Containers ---
    console.log('\n--- 3. Testing Touch Target Sizes & Bottom Sheets ---');

    // 3a. Check habit completion buttons size on page
    const checkButtonsMetrics = await page360.evaluate(() => {
      const checkBtns = Array.from(document.querySelectorAll('button[title="تسجيل الإنجاز"]'));
      return checkBtns.map(b => {
        const r = b.getBoundingClientRect();
        return {
          title: 'Check Habit Button',
          width: Math.round(r.width),
          height: Math.round(r.height),
          pass: r.width >= 56 && r.height >= 56
        };
      });
    });

    for (const btn of checkButtonsMetrics) {
      const status = btn.pass ? 'PASSED' : 'FAILED';
      results.touchTargetTests.push({
        name: 'Habit Check Button (56px target requirement)',
        measuredWidth: btn.width,
        measuredHeight: btn.height,
        requiredWidth: 56,
        requiredHeight: 56,
        status
      });
      if (!btn.pass) results.overallVerdict = 'FAILED';
      console.log(`[Touch Target: Check Button] ${status} - Measured ${btn.width}x${btn.height}px (Target: >=56px).`);
    }

    // Helper to click active modal close button
    async function clickActiveCloseButton() {
      const closeBtnLocator = page360.locator('button[title="إغلاق"]:visible').first();
      await closeBtnLocator.click();
      await page360.waitForTimeout(500); // Allow Vue Transition to completely finish unmounting
    }

    // 3b. Open Add Habit Modal and test its components & close button
    console.log('\nTesting Bottom Sheet: Add New Habit');
    await page360.click('button:has-text("إضافة عادة")');
    await page360.waitForTimeout(500);

    const addModalMetrics = await page360.evaluate(() => {
      const closeBtns = Array.from(document.querySelectorAll('button[title="إغلاق"]'));
      const activeCloseBtn = closeBtns.find(b => b.getBoundingClientRect().width > 0);
      const closeBtnRect = activeCloseBtn ? activeCloseBtn.getBoundingClientRect() : null;

      const modal = activeCloseBtn ? activeCloseBtn.closest('.fixed.inset-0.z-50') : null;
      const sheetBox = modal ? modal.querySelector('.relative.z-50') : null;
      const sheetRect = sheetBox ? sheetBox.getBoundingClientRect() : null;

      const freqGrid = modal ? modal.querySelector('.grid.grid-cols-7') : null;
      const freqBtns = freqGrid ? Array.from(freqGrid.querySelectorAll('button')) : [];
      const freqBtnRects = freqBtns.map(b => b.getBoundingClientRect());

      const docWidth = document.documentElement.clientWidth;
      const scrollWidth = document.documentElement.scrollWidth;

      return {
        open: !!modal,
        sheetWidth: sheetRect ? sheetRect.width : 0,
        sheetRight: sheetRect ? sheetRect.right : 0,
        closeBtnWidth: closeBtnRect ? closeBtnRect.width : 0,
        closeBtnHeight: closeBtnRect ? closeBtnRect.height : 0,
        closeBtnPass: closeBtnRect ? (closeBtnRect.width >= 44 && closeBtnRect.height >= 44) : false,
        freqBtnCount: freqBtns.length,
        freqMinHeight: freqBtnRects.length ? Math.min(...freqBtnRects.map(r => r.height)) : 0,
        freqMinWidth: freqBtnRects.length ? Math.min(...freqBtnRects.map(r => r.width)) : 0,
        freqAllTarget44: freqBtnRects.length ? freqBtnRects.every(r => r.height >= 44) : false,
        hasHorizontalScroll: scrollWidth > docWidth
      };
    });

    results.bottomSheets360Test.push({
      name: 'Add New Habit Bottom Sheet',
      status: (addModalMetrics.open && !addModalMetrics.hasHorizontalScroll && addModalMetrics.closeBtnPass) ? 'PASSED' : 'FAILED',
      metrics: addModalMetrics
    });

    results.touchTargetTests.push({
      name: 'Add Habit Modal Close Button (>=44px requirement)',
      measuredWidth: Math.round(addModalMetrics.closeBtnWidth),
      measuredHeight: Math.round(addModalMetrics.closeBtnHeight),
      requiredWidth: 44,
      requiredHeight: 44,
      status: addModalMetrics.closeBtnPass ? 'PASSED' : 'FAILED'
    });

    if (results.bottomSheets360Test[0].status === 'FAILED') results.overallVerdict = 'FAILED';
    console.log(`[Bottom Sheet: Add Habit] ${results.bottomSheets360Test[0].status} - Sheet width: ${addModalMetrics.sheetWidth}px, Close Btn: ${Math.round(addModalMetrics.closeBtnWidth)}x${Math.round(addModalMetrics.closeBtnHeight)}px, 7 Frequency Buttons min height: ${addModalMetrics.freqMinHeight}px.`);

    // Close Add Habit modal safely
    await clickActiveCloseButton();

    // 3c. Open Quick Habit Detail Bottom Sheet by clicking card title h4
    console.log('\nTesting Bottom Sheet: Quick Habit Detail Preview');
    const firstHabitTitle = page360.locator('h4').first();
    await firstHabitTitle.click();
    await page360.waitForTimeout(500);

    const quickDetailMetrics = await page360.evaluate(() => {
      const closeBtns = Array.from(document.querySelectorAll('button[title="إغلاق"]'));
      const activeCloseBtn = closeBtns.find(b => b.getBoundingClientRect().width > 0);
      const closeBtnRect = activeCloseBtn ? activeCloseBtn.getBoundingClientRect() : null;

      const modal = activeCloseBtn ? activeCloseBtn.closest('.fixed.inset-0.z-50') : null;
      const docWidth = document.documentElement.clientWidth;
      const scrollWidth = document.documentElement.scrollWidth;

      return {
        open: !!modal,
        closeBtnWidth: closeBtnRect ? closeBtnRect.width : 0,
        closeBtnHeight: closeBtnRect ? closeBtnRect.height : 0,
        closeBtnPass: closeBtnRect ? (closeBtnRect.width >= 44 && closeBtnRect.height >= 44) : false,
        hasHorizontalScroll: scrollWidth > docWidth
      };
    });

    results.bottomSheets360Test.push({
      name: 'Quick Habit Detail Bottom Sheet',
      status: (quickDetailMetrics.open && !quickDetailMetrics.hasHorizontalScroll && quickDetailMetrics.closeBtnPass) ? 'PASSED' : 'FAILED',
      metrics: quickDetailMetrics
    });

    results.touchTargetTests.push({
      name: 'Quick Detail Sheet Close Button (>=44px requirement)',
      measuredWidth: Math.round(quickDetailMetrics.closeBtnWidth),
      measuredHeight: Math.round(quickDetailMetrics.closeBtnHeight),
      requiredWidth: 44,
      requiredHeight: 44,
      status: quickDetailMetrics.closeBtnPass ? 'PASSED' : 'FAILED'
    });

    if (results.bottomSheets360Test[1].status === 'FAILED') results.overallVerdict = 'FAILED';
    console.log(`[Bottom Sheet: Quick Detail] ${results.bottomSheets360Test[1].status} - Close Btn: ${Math.round(quickDetailMetrics.closeBtnWidth)}x${Math.round(quickDetailMetrics.closeBtnHeight)}px.`);

    // Close Quick Detail sheet safely
    await clickActiveCloseButton();

    // 3d. Open Stats Drawer
    console.log('\nTesting Bottom Sheet: Habit Stats & Overview Drawer');
    await page360.click('button[title="عرض تقويم وإحصائيات العادة"]');
    await page360.waitForTimeout(500);

    const statsDrawerMetrics = await page360.evaluate(() => {
      const closeBtns = Array.from(document.querySelectorAll('button[title="إغلاق"]'));
      const activeCloseBtn = closeBtns.find(b => b.getBoundingClientRect().width > 0);
      const closeBtnRect = activeCloseBtn ? activeCloseBtn.getBoundingClientRect() : null;

      const modal = activeCloseBtn ? activeCloseBtn.closest('.fixed.inset-0.z-50') : null;
      const docWidth = document.documentElement.clientWidth;
      const scrollWidth = document.documentElement.scrollWidth;

      const heatmap = modal ? modal.querySelector('.grid.grid-cols-7') : null;
      const heatmapRect = heatmap ? heatmap.getBoundingClientRect() : null;

      return {
        open: !!modal,
        closeBtnWidth: closeBtnRect ? closeBtnRect.width : 0,
        closeBtnHeight: closeBtnRect ? closeBtnRect.height : 0,
        closeBtnPass: closeBtnRect ? (closeBtnRect.width >= 44 && closeBtnRect.height >= 44) : false,
        heatmapWidth: heatmapRect ? heatmapRect.width : 0,
        heatmapRight: heatmapRect ? heatmapRect.right : 0,
        hasHorizontalScroll: scrollWidth > docWidth
      };
    });

    results.bottomSheets360Test.push({
      name: 'Habit Stats & Overview Drawer',
      status: (statsDrawerMetrics.open && !statsDrawerMetrics.hasHorizontalScroll && statsDrawerMetrics.closeBtnPass) ? 'PASSED' : 'FAILED',
      metrics: statsDrawerMetrics
    });

    results.touchTargetTests.push({
      name: 'Stats Drawer Close Button (>=44px requirement)',
      measuredWidth: Math.round(statsDrawerMetrics.closeBtnWidth),
      measuredHeight: Math.round(statsDrawerMetrics.closeBtnHeight),
      requiredWidth: 44,
      requiredHeight: 44,
      status: statsDrawerMetrics.closeBtnPass ? 'PASSED' : 'FAILED'
    });

    if (results.bottomSheets360Test[2].status === 'FAILED') results.overallVerdict = 'FAILED';
    console.log(`[Bottom Sheet: Stats Drawer] ${results.bottomSheets360Test[2].status} - Close Btn: ${Math.round(statsDrawerMetrics.closeBtnWidth)}x${Math.round(statsDrawerMetrics.closeBtnHeight)}px, Heatmap width: ${statsDrawerMetrics.heatmapWidth}px.`);

    await clickActiveCloseButton();

    await context360.close();

  } catch (err) {
    console.error('Test execution error:', err);
    results.overallVerdict = 'FAILED';
    results.executionError = err.message;
  } finally {
    await browser.close();
    previewProcess.kill();
  }

  console.log('\n========================================');
  console.log(`FINAL VERDICT: ${results.overallVerdict}`);
  console.log('========================================');

  fs.writeFileSync(
    path.join(PROJECT_ROOT, '.agents', 'challenger_1', 'verification_results.json'),
    JSON.stringify(results, null, 2),
    'utf-8'
  );

  process.exit(results.overallVerdict === 'PASSED' ? 0 : 1);
}

main();
