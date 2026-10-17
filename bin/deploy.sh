#!/bin/bash
# ---------------------------------------------------------------------
# Deploys the portal from the cPanel repository clone to the web root.
# Called by .cpanel.yml:  bin/deploy.sh <repo-path> <deploy-path>
# ---------------------------------------------------------------------
set -e

REPO="${1:-/home/sportsby/repositories/science_excibision_campuschamions}"
DEST="${2:-/home/sportsby/science_expo.sportsmis.com}"
LOG="$DEST/storage/logs/deploy.log"

echo "Deploying $REPO -> $DEST"
mkdir -p "$DEST"

# 1. Copy application files. Never touches: .env, uploads, logs, cache, vendor
#    (vendor is rebuilt by composer below) or anything else already in the web root.
EXCLUDES=(
  --exclude=.git/ --exclude=.github/ --exclude=.cpanel.yml --exclude=.gitignore
  --exclude=.env --exclude=node_modules/ --exclude=vendor/
  --exclude=storage/uploads/ --exclude=storage/logs/ --exclude=storage/cache/
  --exclude=router.php --exclude=assets/src/
)
if command -v rsync >/dev/null 2>&1; then
  rsync -rlt "${EXCLUDES[@]}" "$REPO/" "$DEST/"
else
  # Fallback without rsync: stream a git archive of HEAD (respects tracked files only)
  (cd "$REPO" && git archive --format=tar HEAD \
     -- . ':!.cpanel.yml' ':!.gitignore' ':!router.php' ':!assets/src' ':!storage/uploads' ':!storage/logs') \
   | tar -x -C "$DEST"
fi

# 2. Writable storage folders
mkdir -p "$DEST/storage/uploads/photos" "$DEST/storage/uploads/payments" "$DEST/storage/logs" "$DEST/storage/cache"
chmod -R u+rwX "$DEST/storage"
cp -n "$REPO/storage/.htaccess" "$DEST/storage/.htaccess" 2>/dev/null || true

# 3. PHP dependencies (PHPMailer, Dompdf). Prefer cPanel's PHP 8.3/8.2/8.1 CLI.
PHP_BIN=""
for p in /opt/cpanel/ea-php83/root/usr/bin/php /opt/cpanel/ea-php82/root/usr/bin/php /opt/cpanel/ea-php81/root/usr/bin/php "$(command -v php || true)"; do
  if [ -n "$p" ] && [ -x "$p" ]; then PHP_BIN="$p"; break; fi
done
COMPOSER=""
for c in "$(command -v composer || true)" /opt/cpanel/composer/bin/composer /usr/local/bin/composer "$HOME/bin/composer" "$HOME/composer.phar"; do
  if [ -n "$c" ] && [ -f "$c" ]; then COMPOSER="$c"; break; fi
done

if [ -n "$PHP_BIN" ] && [ -n "$COMPOSER" ]; then
  echo "Running composer with $PHP_BIN"
  (cd "$DEST" && COMPOSER_ALLOW_SUPERUSER=1 "$PHP_BIN" "$COMPOSER" install \
      --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress)
else
  echo "WARNING: composer or PHP CLI not found - run 'composer install --no-dev' in $DEST manually."
fi

# 4. .env reminder (never created automatically with real values)
if [ ! -f "$DEST/.env" ]; then
  cp "$REPO/.env.example" "$DEST/.env"
  chmod 600 "$DEST/.env"
  echo "WARNING: $DEST/.env was missing - copied from .env.example. Edit it with the real database / mail credentials."
fi

echo "$(date '+%Y-%m-%d %H:%M:%S') deployed $(cd "$REPO" && git rev-parse --short HEAD 2>/dev/null)" >> "$LOG"
echo "Deployment complete."
