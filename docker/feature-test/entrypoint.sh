#!/bin/sh
# Feature-test image entrypoint.
#
# Default (no args): start Mailpit (SMTP sink + web UI) in the background, then run the
# FrankenPHP web server in the foreground — a single self-contained shop you can click
# through, with every outgoing email captured by Mailpit.
#
# Any args: exec them instead of the server. The image ships the full vendor/ toolchain,
# so CI can run e.g. `docker run --rm <image> vendor/bin/phpunit` or `... vendor/bin/behat`
# against the already-provisioned SQLite database.
set -e

if [ "$#" -gt 0 ]; then
	exec "$@"
fi

# Mailpit: SMTP on :1025 (matches MAILER_DSN), web UI on :8025.
mailpit --listen 0.0.0.0:8025 --smtp 0.0.0.0:1025 >/var/log/mailpit.log 2>&1 &

echo "▶ Shop:    http://localhost:<published 80>"
echo "▶ Mailpit: http://localhost:<published 8025>"

exec frankenphp run --config /etc/caddy/Caddyfile
