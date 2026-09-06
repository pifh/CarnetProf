# Déploiement (CloudPanel)

Ce document part du principe que CloudPanel est déjà installé sur le VPS de
production et que le domaine pointe vers son IP. Le dépôt GitHub
(`https://github.com/pifh/CarnetProf`) est **privé** : `git clone`/`git
pull` se font en SSH avec une clé de déploiement dédiée (lecture seule),
provisionnée automatiquement par `deploy/install.sh` la première fois (voir
étape 2). Aucun mot de passe ni jeton GitHub à saisir ou à stocker sur le
serveur.

## 1. Créer le site dans CloudPanel

Dans l'interface CloudPanel :

1. **Add Site → Create a PHP Site**, avec le domaine du site et **PHP 8.3**
   (ou 8.4 — la version exacte utilisée par PHP-FPM sur ce site).
2. Créez la base de données associée (onglet **Databases** du site, ou
   `clpctl db:add` en SSH) — notez le nom, l'utilisateur et le mot de passe,
   ils serviront à `deploy/install.sh`.
3. Activez le certificat SSL (Let's Encrypt) pour le domaine.

CloudPanel place le code dans `/home/<site-user>/htdocs/<domaine>` et sert
ce dossier tel quel. Laravel doit être servi depuis son sous-dossier
`public/` — il faut donc éditer le **vhost** du site (onglet **Vhost** dans
CloudPanel) pour faire pointer `root` vers
`/home/<site-user>/htdocs/<domaine>/public` au lieu de la racine. Gardez le
reste du bloc généré par CloudPanel (le bloc PHP-FPM `location ~ \.php$`
notamment) tel quel. Le libellé exact de cet onglet peut varier selon la
version de CloudPanel — c'est le seul réglage manuel non automatisable par
un script.

## 2. Installation applicative

Le dépôt étant privé, `deploy/install.sh` ne peut pas être récupéré par un
`curl` anonyme sur GitHub — copiez-le depuis votre machine locale avant de
le lancer :

```bash
# Depuis votre machine locale, dans le dépôt :
scp deploy/install.sh <site-user>@<serveur>:/home/<site-user>/htdocs/<domaine>/install.sh
```

Puis en SSH, en tant qu'utilisateur du site :

```bash
cd /home/<site-user>/htdocs/<domaine>
chmod +x install.sh
./install.sh --path . --url https://<domaine> \
  --db-database <db> --db-username <user>
```

(Le mot de passe de la base est demandé de façon interactive si vous ne
passez pas `--db-password`.)

Le dépôt étant privé, le script commence par générer une clé SSH de
déploiement dédiée (`~/.ssh/carnetprof_deploy_key`, lecture seule) et
affiche la clé publique à ajouter dans **GitHub → Settings → Deploy keys**
du dépôt (`https://github.com/pifh/CarnetProf/settings/keys`) — laissez
la case **Allow write access** décochée, il n'a besoin que de lire. Le
script attend une confirmation avant de continuer.

Il configure ensuite `.env` (`APP_ENV=production`, `APP_DEBUG=false`,
connexion DB), installe les dépendances PHP et front-end, génère la clé
d'application, exécute les migrations et optimise les caches. Voir la
sortie du script pour les étapes manuelles restantes (cron, premier compte
superadmin).

## 3. Planificateur (cron)

Laravel a besoin que `artisan schedule:run` tourne chaque minute (backups
nocturnes, synchro Ecole-Directe). Ajoutez au crontab de l'utilisateur du
site (`crontab -e`, ou l'onglet **Cron Jobs** de CloudPanel) :

```
* * * * * php /home/<site-user>/htdocs/<domaine>/artisan schedule:run >> /dev/null 2>&1
```

## 4. Premier compte superadmin

Ouvrez `https://<domaine>/admin`, inscrivez-vous normalement, puis en SSH :

```bash
php artisan users:promote votre@email.fr superadmin
```

## 5. Réglages PHP pour la page « Mises à jour »

La mise à jour (`git pull` + `composer install` + `npm run build` +
migrations) peut prendre plusieurs minutes. Pour que le bouton « Mettre à
jour » de `/admin` (menu Administration, réservé aux superadmins) ne
tombe pas en timeout :

- Dans CloudPanel, onglet **PHP Settings** du site : augmentez
  `max_execution_time` à au moins `600`.
- Si le vhost personnalisé définit `fastcgi_read_timeout` /
  `proxy_read_timeout`, portez-les aussi à `600s`.

Si `composer` ou `npm` ne sont pas dans le `PATH` de PHP-FPM (chemins
différents de ceux d'un shell SSH interactif — fréquent avec Node installé
via nvm), renseignez leur chemin absolu dans `.env` :

```
DEPLOY_COMPOSER_BIN=/usr/local/bin/composer
DEPLOY_NPM_BIN=/home/<site-user>/.nvm/versions/node/vXX/bin/npm
```

(`which composer` / `which npm` en SSH pour les trouver.)

Le dépôt étant privé, `git fetch`/`git pull` (déclenchés par le bouton
« Mettre à jour » comme par `deploy/update.sh` en SSH) ont besoin de la
clé de déploiement configurée à l'étape 2, elle-même dans `~/.ssh/config`
du `$HOME` de l'utilisateur qui lance le script. Le pool PHP-FPM ne
définissant généralement pas `env[HOME]`, `deploy/update.sh` le recalcule
lui-même en tout début de script à partir de l'utilisateur système réel
(`getent passwd`) — sans ça, composer/npm (qui s'appuient sur `$HOME` pour
leur cache) peuvent se bloquer indéfiniment sans jamais rien logger quand
le script est déclenché depuis la page web, alors qu'il tourne normalement
en SSH où `$HOME` est déjà correct. Le journal affiché sur la page « Mises
à jour » indique la ligne `HOME=...` utilisée, utile en cas de souci.

## 6. Mises à jour ultérieures

Toutes les mises à jour suivantes se font depuis `/admin` → **Mises à
jour** (menu Administration) : le bouton compare le HEAD local à
`origin/main` sur GitHub, liste les commits en attente, puis — après
confirmation — passe le site en maintenance (`php artisan down`, page
dédiée pendant l'opération), tire les nouveaux commits, réinstalle les
dépendances, rebuild les assets, exécute les migrations, et repasse le
site en ligne (`php artisan up`).

En cas d'échec en cours de route, le site **reste volontairement en mode
maintenance** plutôt que de servir du code à moitié mis à jour. Le journal
affiché sur la page indique l'étape en cause. Une fois corrigée :

```bash
php artisan up
```

remet le site en ligne manuellement, ou relancez simplement le bouton
« Mettre à jour ».

Le même script (`deploy/update.sh`) peut aussi être lancé à la main en
SSH depuis la racine du projet.
