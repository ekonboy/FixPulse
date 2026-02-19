const lighthouseModule = require('lighthouse');
const chromeLauncher = require('chrome-launcher');
const fs = require('node:fs');

const lighthouse = lighthouseModule?.default ?? lighthouseModule?.lighthouse ?? lighthouseModule;
if (typeof lighthouse !== 'function') {
  throw new Error('Unable to resolve lighthouse function from module export.');
}

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
    const settings = {
      port: chrome.port,
      output: 'json',
      logLevel: 'error',
      throttlingMethod: 'simulate',
      maxWaitForLoad: timeoutMs,
      locale,
    };

    if (isDesktop) {
      settings.preset = 'desktop';
      settings.formFactor = 'desktop';
      settings.screenEmulation = {
        mobile: false,
        width: 1350,
        height: 940,
        deviceScaleFactor: 1,
        disabled: false,
      };
    }

    const result = await lighthouse(
      url,
      settings,
      undefined,
    );

    return result.lhr;
  } finally {
    try {
      await chrome.kill();
    } catch (_) {
      // Cleanup errors in temporary profile directories should not fail the scan.
    }
  }
}

module.exports = {
  runLighthouseAudit,
};
