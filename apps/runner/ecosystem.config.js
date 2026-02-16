module.exports = {
  apps: [
    {
      name: 'fixpulse-runner',
      cwd: '/var/www/fixpulse/apps/runner',
      script: 'src/server.js',
      interpreter: 'node',
      env: {
        NODE_ENV: 'production',
        RUNNER_HOST: '127.0.0.1',
        RUNNER_PORT: '3333',
      },
    },
  ],
};
