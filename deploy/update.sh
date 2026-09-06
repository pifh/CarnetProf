#!/usr/bin/env bash
#
# Pulls the latest commit from GitHub and redeploys it. Called by
# App\Services\SiteUpdater (from the "Mises à jour" admin page) as a
# subprocess, and can also be run by hand over SSH.
#
# Must stay non-interactive: no prompts, everything either succeeds or
# fails loudly. `set -e` means the first failing step aborts the script —
# on purpose, since "up" not running again leaves the site in maintenance
# mode rather than serving a half-updated app.
#
# The GitHub repo is private, so `git pull` here relies on the "origin"
# remote already being an SSH URL with a passphrase-less deploy key
# configured for this system user (set up once by deploy/install.sh — see
# DEPLOY.md).

set -euo pipefail

cd "$(dirname "$0")/.."

# Every line this script (and everything it runs) prints goes only to this
# log file from here on — not back to whatever invoked the script — so the
# last step reached always survives on disk even if the process is killed
# mid-way (e.g. by PHP's own max_execution_time) before App\Services\
# SiteUpdater's Process::run() ever gets a captured result to show on the
# "Mises à jour" page. A plain file redirect rather than `tee`/process
# substitution: piping this script's stdout through a subshell broke npm's
# own PATH resolution for the child shell it spawns to run "vite build"
# (reproduced directly: same command works stand-alone, fails only through
# that redirection) — not worth it just to also mirror output live to the
# caller, which SiteUpdater reads back from this same file instead.
mkdir -p storage/logs
exec >> storage/logs/deploy-update.log 2>&1
echo ""
echo "=== $(date '+%Y-%m-%d %H:%M:%S') — nouvelle tentative (utilisateur : $(id -un)) ==="

# When triggered from the "Mises à jour" admin page, this runs as a
# PHP-FPM child process — the pool doesn't set env[HOME], so without this,
# composer/npm (which rely on $HOME to find their cache/config dirs) get a
# missing or wrong one and can hang or silently redo work an interactive
# SSH shell never has to, since $HOME is already correct there. Recomputed
# from the actual running user's own passwd entry, so this is a no-op
# (and harmless) when $HOME was already right.
export HOME="$(getent passwd "$(id -un)" | cut -d: -f6)"
echo "==> HOME=${HOME} (utilisateur : $(id -un))"

PHP_BIN="${DEPLOY_PHP_BIN:-php}"
COMPOSER_BIN="${DEPLOY_COMPOSER_BIN:-composer}"
NPM_BIN="${DEPLOY_NPM_BIN:-npm}"
GIT_REMOTE="${DEPLOY_GIT_REMOTE:-origin}"
GIT_BRANCH="${DEPLOY_GIT_BRANCH:-main}"

on_error() {
    echo ""
    echo "!! Échec de la mise à jour. Le site reste en mode maintenance."
    echo "!! Corrigez le problème ci-dessus puis relancez la mise à jour,"
    echo "!! ou exécutez manuellement : ${PHP_BIN} artisan up"
}
trap on_error ERR

echo "==> Passage en mode maintenance"
"$PHP_BIN" artisan down --retry=60 --render="errors::503"

echo "==> Récupération de la dernière version (${GIT_REMOTE}/${GIT_BRANCH})"
git pull --ff-only "$GIT_REMOTE" "$GIT_BRANCH"

echo "==> Installation des dépendances PHP"
"$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction

echo "==> Installation des dépendances front-end et build"
"$NPM_BIN" ci
sync

# node_modules/.bin/vite has been observed missing to the very next command
# right after `npm ci` reports success — a filesystem consistency lag on
# this host (confirmed: the exact same "npm run build" works fine run by
# hand a few seconds later), not a real install failure. Wait for it
# rather than fail instantly on what's usually just a race.
for _ in 1 2 3 4 5 6 7 8 9 10; do
    [ -e node_modules/.bin/vite ] && break
    echo "==> En attente de node_modules/.bin/vite..."
    sleep 1
done

"$NPM_BIN" run build

echo "==> Migrations de base de données"
"$PHP_BIN" artisan migrate --force

echo "==> Recompilation des caches"
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

echo "==> Sortie du mode maintenance"
"$PHP_BIN" artisan up

echo "==> Mise à jour terminée."
