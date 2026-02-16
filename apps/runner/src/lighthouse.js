const lighthouseModule = require('lighthouse');
const chromeLauncher = require('chrome-launcher');
const fs = require('node:fs');
const lighthouse = lighthouseModule.default ?? lighthouseModule;

function resolveChromePath(chromePath) {
  if (!chromePath) {
    return undefined;
  }

  const trimmed = String(chromePath).trim();
  if (trimmed.length === 0) {
    return undefined;
  }

  // If an invalid path is provided (e.g. Linux path on Windows), ignore it
  // so chrome-launcher can auto-detect an installed browser.
  return fs.existsSync(trimmed) ? trimmed : undefined;
}

async function runLighthouseAudit({ url, device, locale, timeoutMs, chromePath }) {
  const chrome = await chromeLauncher.launch({
    chromePath: resolveChromePath(chromePath),
    chromeFlags: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'],
  });

  try {
    const isDesktop = device === 'desktop';
    const result = await lighthouse(
      url,
      {
        port: chrome.port,
        output: 'json',
        logLevel: 'error',
        preset: isDesktop ? 'desktop' : undefined,
        formFactor: isDesktop ? 'desktop' : 'mobile',
        throttlingMethod: 'simulate',
        screenEmulation: isDesktop
          ? { mobile: false, width: 1350, height: 940, deviceScaleFactor: 1, disabled: false }
          : { mobile: true, width: 390, height: 844, deviceScaleFactor: 2, disabled: false },
        maxWaitForLoad: timeoutMs,
        locale,
      },
      undefined,
    );

    return result.lhr;
  } finally {
    await chrome.kill();
  }
}

module.exports = {
  runLighthouseAudit,
};
