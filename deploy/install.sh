#!/usr/bin/env bash
#
# First-time application setup on a CloudPanel site. Run once over SSH as
# the site's system user, AFTER creating the site in the CloudPanel UI
# (PHP site, domain, SSL) — see DEPLOY.md for the full walkthrough,
# including pointing the vhost document root at "<path>/public".
#
# This script only handles the application layer (clone, .env, composer,
# npm, migrate) — it does not touch nginx/PHP-FPM/MySQL, which CloudPanel
# already manages. For subsequent updates, use the "Mises à jour" page in
# the admin panel (deploy/update.sh), not this script.
#
# Usage:
#   ./install.sh --path /home/carnetprof/htdocs/carnetprof.example.com \
#                --url https://carnetprof.example.com \
#                --db-database carnetprof --db-username carnetprof
#
# Prompts interactively for anything not passed as a flag (DB password,
# mail settings are left for you to fill in .env by hand afterwards).

set -euo pipefail

REPO="https://github.com/pifh/CarnetProf.git"
BRANCH="main"
TARGET_PATH="$(pwd)"
APP_URL=""
DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_DATABASE=""
DB_USERNAME=""
DB_PASSWORD=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --path) TARGET_PATH="$2"; shift 2 ;;
        --repo) REPO="$2"; shift 2 ;;
        --branch) BRANCH="$2"; shift 2 ;;
        --url) APP_URL="$2"; shift 2 ;;
        --db-host) DB_HOST="$2"; shift 2 ;;
        --db-port) DB_PORT="$2"; shift 2 ;;
        --db-database) DB_DATABASE="$2"; shift 2 ;;
        --db-username) DB_USERNAME="$2"; shift 2 ;;
        --db-password) DB_PASSWORD="$2"; shift 2 ;;
        *) echo "Option inconnue : $1"; exit 1 ;;
    esac
done

for bin in git composer npm php; do
    command -v "$bin" >/dev/null 2>&1 || { echo "Erreur : '$bin' est introuvable dans le PATH."; exit 1; }
done

[[ -n "$APP_URL" ]] || { read -rp "URL du site (ex. https://carnetprof.example.com) : " APP_URL; }
[[ -n "$DB_DATABASE" ]] || { read -rp "Nom de la base de données : " DB_DATABASE; }
[[ -n "$DB_USERNAME" ]] || { read -rp "Utilisateur MySQL : " DB_USERNAME; }
[[ -n "$DB_PASSWORD" ]] || { read -rsp "Mot de passe MySQL : " DB_PASSWORD; echo; }

if [[ -d "$TARGET_PATH/.git" ]]; then
    echo "==> Dépôt déjà présent dans $TARGET_PATH, pull plutôt que clone"
    git -C "$TARGET_PATH" pull --ff-only "$REPO" "$BRANCH"
else
    echo "==> Clonage de $REPO dans $TARGET_PATH"
    git clone --branch "$BRANCH" "$REPO" "$TARGET_PATH"
fi

cd "$TARGET_PATH"

if [[ ! -f .env ]]; then
    echo "==> Création du .env"
    cp .env.example .env
fi

set_env() {
    local key="$1" value="$2"
    if grep -q "^${key}=" .env; then
        sed -i.bak "s#^${key}=.*#${key}=${value}#" .env && rm -f .env.bak
    else
        echo "${key}=${value}" >> .env
    fi
}

echo "==> Configuration du .env"
set_env "APP_ENV" "production"
set_env "APP_DEBUG" "false"
set_env "APP_URL" "$APP_URL"
set_env "DB_CONNECTION" "mysql"
set_env "DB_HOST" "$DB_HOST"
set_env "DB_PORT" "$DB_PORT"
set_env "DB_DATABASE" "$DB_DATABASE"
set_env "DB_USERNAME" "$DB_USERNAME"
set_env "DB_PASSWORD" "$DB_PASSWORD"

echo "==> Installation des dépendances PHP"
composer install --no-dev --optimize-autoloader --no-interaction

if ! grep -q "^APP_KEY=base64:" .env; then
    echo "==> Génération de la clé d'application"
    php artisan key:generate --force
fi

echo "==> Installation des dépendances front-end et build"
npm ci
npm run build

echo "==> Lien symbolique de stockage"
php artisan storage:link

echo "==> Migrations de base de données"
php artisan migrate --force

echo "==> Optimisation"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Permissions (storage, bootstrap/cache)"
chmod -R ug+rwx storage bootstrap/cache

cat <<EOF

==> Installation terminée.

Reste à faire manuellement :
  1. Dans CloudPanel, faites pointer la racine du site (vhost) vers :
       ${TARGET_PATH}/public
  2. Ajoutez au crontab de cet utilisateur (crontab -e) :
       * * * * * php ${TARGET_PATH}/artisan schedule:run >> /dev/null 2>&1
  3. Ouvrez ${APP_URL}/admin, créez votre compte via "S'inscrire",
     puis promouvez-le en superadmin :
       php ${TARGET_PATH}/artisan users:promote votre@email.fr superadmin
  4. Voir DEPLOY.md pour les réglages PHP-FPM (timeout) nécessaires à la
     page "Mises à jour" de l'administration.
EOF
