const puppeteer = require('puppeteer');

(async () => {
  const url = process.argv[2] || 'http://localhost/WRSOMS/pages/product.html';
  const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  page.setDefaultNavigationTimeout(20000);

  try {
    await page.goto(url, { waitUntil: 'networkidle2' });
    // Wait for product cards to appear (timeout 8s)
    await page.waitForSelector('.product-card', { timeout: 8000 });

    const result = await page.evaluate(() => {
      const cards = Array.from(document.querySelectorAll('.product-card'));
      return {
        count: cards.length,
        htmlSample: cards.slice(0,3).map(c => c.outerHTML)
      };
    });

    console.log(JSON.stringify({ ok: true, url, ...result }, null, 2));
  } catch (err) {
    console.error(JSON.stringify({ ok: false, url, error: err.message }, null, 2));
    process.exitCode = 2;
  } finally {
    await browser.close();
  }
})();
