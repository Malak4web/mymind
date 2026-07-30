import { chromium } from 'playwright';

(async () => {
  try {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    console.log('Edge launched successfully!');
    await browser.close();
  } catch (err) {
    console.error('Edge launch error:', err.message);
    try {
      const browser = await chromium.launch({ channel: 'chrome', headless: true });
      console.log('Chrome launched successfully!');
      await browser.close();
    } catch (err2) {
      console.error('Chrome launch error:', err2.message);
    }
  }
})();
