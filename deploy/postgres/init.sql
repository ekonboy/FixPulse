-- FixPulse PostgreSQL bootstrap
-- Ejecutar como superusuario (postgres):
-- psql -U postgres -f deploy/postgres/init.sql

DO
$$
BEGIN
   IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'fixpulse') THEN
      CREATE ROLE fixpulse LOGIN PASSWORD 'secret';
   END IF;
END
$$;

SELECT 'CREATE DATABASE fixpulse OWNER fixpulse'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'fixpulse')
\gexec

GRANT ALL PRIVILEGES ON DATABASE fixpulse TO fixpulse;
