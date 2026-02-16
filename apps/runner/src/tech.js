function detectByRegex(source, tests) {
  for (const test of tests) {
    if (test.pattern.test(source)) {
      return true;
    }
  }

  return false;
}

function normalizeHeaders(headers) {
  const map = {};
  for (const [key, value] of headers.entries()) {
    map[key.toLowerCase()] = value;
  }

  return map;
}

function buildSignals({ html, headers, finalUrl }) {
  const signals = [];

  const defs = [
    { name: 'WordPress', category: 'CMS', tests: [/wp-content\//i, /wp-includes\//i, /wp-json\//i] },
    { name: 'Elementor', category: 'CMS Plugin', tests: [/elementor-frontend/i, /elementor-pro/i] },
    { name: 'WooCommerce', category: 'Ecommerce', tests: [/wp-content\/plugins\/woocommerce/i, /wc-ajax=/i] },
    { name: 'Shopify', category: 'Ecommerce', tests: [/cdn\.shopify\.com/i, /shopify-checkout-api-token/i] },
    { name: 'PrestaShop', category: 'Ecommerce', tests: [/\/modules\/ps_/i, /\/themes\/classic\/assets\//i] },
    { name: 'Joomla', category: 'CMS', tests: [/\/media\/system\/js\//i, /content=\"Joomla!/i] },
    { name: 'Drupal', category: 'CMS', tests: [/drupalSettings/i, /\/sites\/default\/files\//i] },
    { name: 'Cloudflare', category: 'CDN', tests: [/cloudflare/i], headerTest: (h) => Boolean(h['cf-ray'] || /cloudflare/i.test(h.server || '')) },
    { name: 'Nginx', category: 'Web Server', tests: [], headerTest: (h) => /nginx/i.test(h.server || '') },
    { name: 'Apache', category: 'Web Server', tests: [], headerTest: (h) => /apache/i.test(h.server || '') },
    { name: 'LiteSpeed', category: 'Web Server', tests: [], headerTest: (h) => /litespeed/i.test(h.server || '') },
    { name: 'jQuery', category: 'JavaScript Library', tests: [/jquery(\.min)?\.js|window\.jQuery/i] },
    { name: 'Bootstrap', category: 'CSS Framework', tests: [/bootstrap(\.min)?\.(css|js)|data-bs-/i] },
    { name: 'React', category: 'JavaScript Framework', tests: [/react-dom/i, /__REACT_DEVTOOLS_GLOBAL_HOOK__/i, /data-reactroot/i] },
    { name: 'Vue.js', category: 'JavaScript Framework', tests: [/vue(\.runtime)?(\.global)?\.js/i, /data-v-[a-f0-9]{4,}/i] },
    { name: 'Google Tag Manager', category: 'Analytics', tests: [/googletagmanager\.com|GTM-[A-Z0-9]+/i] },
    { name: 'Google Analytics', category: 'Analytics', tests: [/google-analytics\.com|gtag\(|ga\('/i] },
    { name: 'Hotjar', category: 'Analytics', tests: [/hotjar/i] },
    { name: 'Facebook Pixel', category: 'Analytics', tests: [/connect\.facebook\.net|fbq\(/i] },
  ];

  for (const def of defs) {
    const matchedHtml = def.tests.length > 0 && detectByRegex(html, def.tests.map((pattern) => ({ pattern })));
    const matchedHeaders = typeof def.headerTest === 'function' ? def.headerTest(headers) : false;

    if (matchedHtml || matchedHeaders) {
      signals.push({
        name: def.name,
        category: def.category,
        confidence: matchedHeaders ? 0.9 : 0.7,
      });
    }
  }

  if (/^https:\/\//i.test(finalUrl)) {
    signals.push({ name: 'HTTPS', category: 'Security', confidence: 1.0 });
  }

  return signals;
}

async function analyzeTechnology(url, timeoutMs = 20000) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), timeoutMs);

  try {
    const response = await fetch(url, {
      method: 'GET',
      redirect: 'follow',
      signal: controller.signal,
      headers: {
        'User-Agent': 'FixPulse-Tech-Analyzer/1.0',
      },
    });

    const html = await response.text();
    const headers = normalizeHeaders(response.headers);

    const technologies = buildSignals({ html, headers, finalUrl: response.url })
      .sort((a, b) => b.confidence - a.confidence || a.name.localeCompare(b.name));

    return {
      url,
      final_url: response.url,
      status_code: response.status,
      technologies,
      headers: {
        server: headers.server || null,
        powered_by: headers['x-powered-by'] || null,
      },
    };
  } finally {
    clearTimeout(timeout);
  }
}

module.exports = {
  analyzeTechnology,
};
