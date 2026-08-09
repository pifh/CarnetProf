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

set -euo pipefail

cd "$(dirname "$0")/.."

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
