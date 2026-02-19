require('dotenv').config();
const fastify = require('fastify')({ logger: true });
const { z } = require('zod');
const { runLighthouseAudit } = require('./lighthouse');
const { analyzeTechnology } = require('./tech');

const HOST = process.env.RUNNER_HOST || '127.0.0.1';
const PORT = Number(process.env.RUNNER_PORT || 3333);
const RUNNER_KEY = process.env.RUNNER_KEY;
const CHROME_PATH = process.env.CHROME_PATH || '';
const TIMEOUT_MS = Number(process.env.RUNNER_TIMEOUT_MS || 90000);

if (!RUNNER_KEY) {
  throw new Error('RUNNER_KEY is required');
}

const payloadSchema = z.object({
  url: z.string().url(),
  device: z.enum(['mobile', 'desktop']).default('mobile'),
  locale: z.string().default('es-ES'),
});

const techPayloadSchema = z.object({
  url: z.string().url(),
});

fastify.addHook('onRequest', async (request, reply) => {
  if (request.raw.url && request.raw.url.startsWith('/health')) {
    return;
  }

  const apiKey = request.headers['x-runner-key'];
  if (apiKey !== RUNNER_KEY) {
    return reply.code(401).send({
      ok: false,
      error: {
        code: 'UNAUTHORIZED',
        message: 'Invalid runner API key',
      },
    });
  }
});

fastify.get('/health', async () => ({ ok: true }));

fastify.post('/audit/lighthouse', async (request, reply) => {
  const parsed = payloadSchema.safeParse(request.body);

  if (!parsed.success) {
    return reply.code(422).send({
      ok: false,
      error: {
        code: 'VALIDATION_ERROR',
        message: 'Invalid payload',
        details: parsed.error.flatten(),
      },
    });
  }

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), TIMEOUT_MS);

  try {
    const lhr = await Promise.race([
      runLighthouseAudit({
        url: parsed.data.url,
        device: parsed.data.device,
        locale: parsed.data.locale,
        timeoutMs: TIMEOUT_MS,
        chromePath: CHROME_PATH,
      }),
      new Promise((_, reject) => {
        controller.signal.addEventListener('abort', () => reject(new Error('Audit timeout exceeded')));
      }),
    ]);

    return { ok: true, lhr };
  } catch (error) {
    request.log.error({ err: error }, 'Lighthouse audit failed');

    return reply.code(500).send({
      ok: false,
      error: {
        code: 'AUDIT_FAILED',
        message: error.message || 'Lighthouse execution failed',
      },
    });
  } finally {
    clearTimeout(timeout);
  }
});

fastify.post('/analyze/tech', async (request, reply) => {
  const parsed = techPayloadSchema.safeParse(request.body);

  if (!parsed.success) {
    return reply.code(422).send({
      ok: false,
      error: {
        code: 'VALIDATION_ERROR',
        message: 'Invalid payload',
        details: parsed.error.flatten(),
      },
    });
  }

  try {
    const analysis = await analyzeTechnology(parsed.data.url);

    return { ok: true, analysis };
  } catch (error) {
    request.log.error({ err: error }, 'Technology analysis failed');

    return reply.code(500).send({
      ok: false,
      error: {
        code: 'TECH_ANALYSIS_FAILED',
        message: error.message || 'Technology analysis failed',
      },
    });
  }
});

async function start() {
  try {
    await fastify.listen({ host: HOST, port: PORT });
    fastify.log.info(`Runner listening on http://${HOST}:${PORT}`);
  } catch (error) {
    fastify.log.error(error);
    process.exit(1);
  }
}

start();
