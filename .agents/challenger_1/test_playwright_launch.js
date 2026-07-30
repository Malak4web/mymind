import { chromium } from 'playwright';

(async () => {
  try {
    const browser = await chromium.launch({ headless: true });
    console.log('Chromium launched successfully');
    await browser.close();
  } catch (err) {
    console.error('Launch failed:', err.message);
  }
})();
