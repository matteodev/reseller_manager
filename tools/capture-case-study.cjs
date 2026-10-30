// Run inside the documented Playwright container connected to the demo network.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
(async () => {
  const output = process.env.CAPTURE_OUTPUT || '/output';
  const base = 'http://wordpress';
  const browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
  try {
    const page = await browser.newPage({viewport: {width: 1440, height: 1000}, deviceScaleFactor: 1});
    await page.goto(base + '/?pagename=case-study-signup', {waitUntil:'networkidle'});
    await page.locator('form.mpwp-reseller-form-signup').waitFor();
    await page.locator('main').screenshot({path:path.join(output,'01-registrazione.png')});
    await page.locator('[name="mpwp-reseller-signup-name"]').fill('Studio Demo');
    await page.locator('[name="mpwp-reseller-signup-email"]').fill('studio.demo@example.test');
    await page.locator('[name="mpwp-reseller-signup-password"]').fill('Demo-local-456!');
    await Promise.all([page.waitForNavigation(), page.locator('form.mpwp-reseller-form-signup button[type=submit]').click()]);
    await page.locator('.mpwp-reseller-message-success').waitFor();
    await page.locator('main').screenshot({path:path.join(output,'02-in-attesa.png')});
    await page.goto(base+'/wp-login.php');
    await page.locator('#user_login').fill('demo_admin');
    await page.locator('#user_pass').fill('Local-test-only-123!');
    await Promise.all([page.waitForNavigation(), page.locator('#wp-submit').click()]);
    await page.goto(base+'/wp-admin/admin.php?page=reseller-manager', {waitUntil:'networkidle'});
    const row = page.locator('tr').filter({hasText:'Atelier Demo'});
    const link = row.locator('a[href*="mpwp-reseller-view"]').first();
    const profile = await link.getAttribute('href');
    if (!profile) throw new Error('Demo profile link missing');
    await page.goto(profile, {waitUntil:'networkidle'});
    await page.getByRole('heading',{name:'Profilo Rivenditore',exact:true}).waitFor();
    await page.locator('.wrap').filter({has:page.getByRole('heading',{name:'Profilo Rivenditore',exact:true})}).screenshot({path:path.join(output,'03-profilo-prezzi.png')});
    // This is a visual report of captured runner output, not a screenshot of GitHub CI.
    const log = fs.readFileSync('/app/docs/case-study/test-output.txt','utf8');
    const summary = log.split('\n').filter(line => /^(PASS: PHP syntax|Success: .*cases passed|PASS: (form field names|HTTP approved))/.test(line));
    if (!log.includes('Success: 29 integration cases passed.') || !log.includes('Success: 7 HTTP cases passed;')) throw new Error('Successful test output required');
    const escape = value => value.replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;');
    await page.setContent(`<!doctype html><html lang="it"><meta charset="utf-8"><title>Verifiche Docker locali</title><style>body{margin:0;background:#f3f5f8;color:#18212f;font:22px/1.6 system-ui}main{padding:64px;max-width:1200px}h1{font-size:44px;line-height:1.2}p{max-width:1000px}.metrics{display:flex;gap:28px;margin:36px 0}.metric{background:white;padding:24px 32px;border-radius:12px;flex:1}.metric strong{display:block;font-size:42px;color:#17634b}pre{background:#18212f;color:#d2f6df;font:18px/1.8 monospace;padding:24px;white-space:pre-wrap;border-radius:12px}.note{font-size:18px;color:#49576b}</style><main><p>RESELLER MANAGER · PROGETTO DIMOSTRATIVO</p><h1>Verifiche Docker locali</h1><p>Rapporto generato dall’output reale di <code>bash tests/run.sh</code>.</p><div class="metrics"><div class="metric"><strong>12</strong>assert del modulo</div><div class="metric"><strong>29</strong>casi di integrazione</div><div class="metric"><strong>7</strong>casi HTTP</div></div><pre>${escape(summary.join('\n'))}</pre><p class="note">WordPress 7.0 · WooCommerce 11.2.1 · PHP CLI 8.3.33 / web 8.2.31<br>Questa immagine documenta l’esecuzione locale; non attesta un’esecuzione su GitHub Actions.</p></main></html>`);
    await page.screenshot({path:path.join(output,'04-test-docker.png'),fullPage:true});
    console.log('Captured 4 case-study images from the local demo and test output.');
  } finally { await browser.close(); }
})().catch(error => {console.error(error); process.exit(1);});
