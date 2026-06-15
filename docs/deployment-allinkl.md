# WaRa-Portal – Deployment auf all-inkl.com

> Stack: Laravel 11 · PHP 8.5 · MariaDB 10.11.14  
> Server: `w007ba65.kasserver.com`  
> Webroot: `/www/htdocs/w007ba65/wara-portal.de/`  
> Zieldomain: `https://wara-portal.de`  
> SSH-User: `w007ba65`  
> Mail: `administrator@wara-portal.de` via `w007ba65.kasserver.com`

---

## Architektur-Übersicht

```
[VS Code lokal / Laragon]
        │  git push
        ▼
[GitHub – main branch]
        │  GitHub Action (Trigger: push to main)
        ▼
[all-inkl.com SSH]
  git pull → composer install → php artisan migrate → cache clear
```

**Kein FTP nötig.** Alle Deployments laufen vollautomatisch über GitHub Actions + SSH.  
FTP bleibt nur als Fallback-Zugang für Notfälle.

---

## Teil 1 – Einmalige Server-Einrichtung

### 1.1 Dokumenten-Root auf `public/` setzen

Im **KAS-Panel** (kas.all-inkl.com) unter *Domains → wara-portal.de → Domaineinstellungen*:

```
Pfad: /www/htdocs/w007ba65/wara-portal.de/public
```

> Laravel muss so konfiguriert sein, dass nur der `public/`-Ordner vom Web erreichbar ist.  
> Alle anderen Dateien (`.env`, `app/`, `vendor/` etc.) liegen eine Ebene darüber – nicht öffentlich.

### 1.2 Per SSH verbinden und Repository klonen

```bash
ssh w007ba65@w007ba65.kasserver.com

cd /www/htdocs/w007ba65/wara-portal.de

# Sicherstellen, dass das Verzeichnis leer ist
ls -la

git clone https://github.com/mwilkens780/WaRa-Portal.git .
```

> Bei privatem Repository: **Deploy-Key** empfohlen (read-only SSH-Key speziell für dieses Repo).  
> Alternativ: persönliches GitHub Access-Token als HTTPS-Credential.

### 1.3 PHP und Composer prüfen

```bash
# PHP-Version prüfen
php -v           # sollte 8.5.x zeigen
php -v        # Fallback, falls php nicht auf 8.5 zeigt

# Composer installieren (falls nicht vorhanden)
curl -sS https://getcomposer.org/installer | php
mv composer.phar /www/htdocs/w007ba65/composer
# Oder global: php composer.phar → als Alias nutzbar

# Composer-Dependencies installieren (ohne Dev-Pakete)
php composer.phar install --no-dev --optimize-autoloader --no-interaction
```

### 1.4 `.env` Datei auf dem Server erstellen

```bash
cp .env.example .env
nano .env
```

Folgende Werte für Produktion setzen:

```dotenv
APP_NAME="WaRa-Portal"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://wara-portal.de

# DB-Zugangsdaten (generierte Namen vom Provider – im KAS-Panel einsehbar)
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=d0472160
DB_USERNAME=d0472160
DB_PASSWORD=SICHERES_PASSWORT      # ← das beim Anlegen vergebene Passwort

# Session: auf cookie umstellen (stabiler auf Shared Hosting)
SESSION_DRIVER=cookie
SESSION_DOMAIN=wara-portal.de
SESSION_SECURE_COOKIE=true

# Mail – all-inkl.com SMTP
MAIL_MAILER=smtp
MAIL_HOST=w007ba65.kasserver.com
MAIL_PORT=587
MAIL_USERNAME=administrator@wara-portal.de
MAIL_PASSWORD=MAIL_PASSWORT
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=administrator@wara-portal.de
MAIL_FROM_NAME="WaRa-Portal"

# KI-Auswertung (Wettkampfanalyse & Ausschreibungsparser)
ANTHROPIC_API_KEY=sk-ant-...

# Cache und Queue auf file (kein Redis/Memcached auf Shared Hosting)
CACHE_STORE=file
QUEUE_CONNECTION=sync

# Logging
LOG_CHANNEL=daily
LOG_LEVEL=error
```

### 1.5 App-Key generieren und Datenbank einrichten

```bash
# App-Key generieren (einmalig!)
php artisan key:generate

# Datenbank-Tabellen anlegen
php artisan migrate --force

# (Optional) Seed-Daten einspielen – nur beim Erststart
# php artisan db:seed --force

# Storage-Symlink erstellen (public/storage → storage/app/public)
php artisan storage:link
```

### 1.6 Verzeichnis-Berechtigungen setzen

```bash
chmod -R 775 storage bootstrap/cache
```

### 1.7 Cron-Job für den Laravel-Scheduler einrichten

Im **KAS-Panel** unter *Cronjobs* einen neuen Eintrag anlegen:

```
Intervall: * * * * *
Befehl:    /usr/bin/php /www/htdocs/w007ba65/wara-portal.de/artisan schedule:run >> /dev/null 2>&1
```

> Tipp: Den genauen PHP-Pfad per SSH prüfen: `which php`  
> Falls `php` nicht gefunden wird, alternativ `/usr/local/bin/php` oder einfach `php` testen.

> Der Scheduler läuft dann minütlich und führt intern nur die fälligen Jobs aus  
> (SHSV/NSV/DSV-Crawler montags/dienstags/donnerstags um 06:00 Uhr).

### 1.8 HTTPS / SSL

Im KAS-Panel unter *SSL* → **Let's Encrypt** für `wara-portal.de` (und ggf. `www.wara-portal.de`) aktivieren.  
Läuft automatisch und kostenlos.

---

## Teil 2 – Automatisches Deployment via GitHub Actions

### 2.1 SSH-Schlüsselpaar für GitHub Actions erzeugen

**Lokal in VS Code Terminal:**

```bash
ssh-keygen -t ed25519 -C "github-actions-wara" -f ~/.ssh/wara_deploy
```

Das erzeugt:
- `~/.ssh/wara_deploy` – privater Schlüssel (→ GitHub Secret)
- `~/.ssh/wara_deploy.pub` – öffentlicher Schlüssel (→ Server)

**Öffentlichen Schlüssel auf dem Server hinterlegen:**

```bash
# Inhalt von wara_deploy.pub in authorized_keys einfügen
cat ~/.ssh/wara_deploy.pub | ssh w007ba65@w007ba65.kasserver.com "cat >> ~/.ssh/authorized_keys"
```

### 2.2 GitHub Secrets anlegen

Unter `https://github.com/mwilkens780/WaRa-Portal/settings/secrets/actions`:

| Secret-Name | Wert |
|-------------|------|
| `SSH_HOST` | `w007ba65.kasserver.com` |
| `SSH_USER` | `w007ba65` |
| `SSH_PRIVATE_KEY` | Inhalt von `~/.ssh/wara_deploy` (inkl. `-----BEGIN...`) |
| `SSH_PATH` | `/www/htdocs/w007ba65/wara-portal.de` |
| `PHP_BIN` | `php` | ← Binary-Pfad: `/usr/bin/php` (v8.3.2) |
| `ANTHROPIC_API_KEY` | Dein Anthropic API-Key (für KI-Auswertung) |

### 2.3 GitHub Actions Workflow anlegen

Datei: `.github/workflows/deploy.yml`

```yaml
name: Deploy to all-inkl.com

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    name: Deploy via SSH
    runs-on: ubuntu-latest

    steps:
      - name: Deploy to Server
        uses: appleboy/ssh-action@v1.0.3
        with:
          host: ${{ secrets.SSH_HOST }}
          username: ${{ secrets.SSH_USER }}
          key: ${{ secrets.SSH_PRIVATE_KEY }}
          script: |
            set -e
            cd ${{ secrets.SSH_PATH }}

            echo "── Git pull ──"
            git pull origin main

            echo "── Composer install ──"
            ${{ secrets.PHP_BIN }} composer.phar install \
              --no-dev \
              --optimize-autoloader \
              --no-interaction \
              --no-progress

            echo "── Migrations ──"
            ${{ secrets.PHP_BIN }} artisan migrate --force

            echo "── Cache leeren und neu aufbauen ──"
            ${{ secrets.PHP_BIN }} artisan config:cache
            ${{ secrets.PHP_BIN }} artisan route:cache
            ${{ secrets.PHP_BIN }} artisan view:cache
            ${{ secrets.PHP_BIN }} artisan event:cache

            echo "── Deployment abgeschlossen ──"
```

### 2.4 Composer auf dem Server erreichbar machen

Damit der GitHub-Action-SSH-Befehl `composer.phar` findet, muss Composer im Projektstamm liegen (siehe 1.3) **oder** per `$HOME/composer.phar` referenziert werden. Im Workflow ggf. anpassen:

```bash
# Alternativ: composer global im Home-Verzeichnis
~/composer.phar install ...
```

---

## Teil 3 – Entwicklungs-Workflow (täglich)

```
1. Code lokal in VS Code bearbeiten (Laragon läuft)
2. Lokal testen: http://wara-portal.test
3. git add / git commit / git push origin main
4. GitHub Action startet automatisch (~60 Sek.)
5. Änderungen sind live auf https://wara-portal.de
```

### Notfall-Rollback

```bash
# Auf dem Server: letzten Stand wiederherstellen
ssh w007ba65@w007ba65.kasserver.com
cd /www/htdocs/w007ba65/wara-portal.de
git log --oneline -10              # letzten guten Commit finden
git reset --hard <commit-hash>
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Nur Migrations ohne Code-Änderung ausführen

```bash
ssh w007ba65@w007ba65.kasserver.com
cd /www/htdocs/w007ba65/wara-portal.de
php artisan migrate --force
```

---

## Teil 4 – Ordnerstruktur auf dem Server

```
/www/htdocs/w007ba65/wara-portal.de/   ← git repo root
├── app/
├── bootstrap/
│   └── cache/                          ← beschreibbar (chmod 775)
├── config/
├── database/
├── public/                             ← Dokumenten-Root der Domain
│   ├── index.php
│   └── storage → ../storage/app/public (Symlink)
├── resources/
├── routes/
├── storage/                            ← beschreibbar (chmod 775)
│   ├── app/
│   ├── framework/
│   └── logs/
├── vendor/                             ← von composer install befüllt
├── .env                                ← NICHT im Git (in .gitignore)
└── artisan
```

---

## Teil 5 – Checkliste Erstinstallation

- [ ] KAS: Dokumenten-Root auf `…/public` gesetzt
- [ ] KAS: SSL / Let's Encrypt aktiviert
- [ ] KAS: MariaDB-Datenbank und Benutzer angelegt
- [ ] KAS: Cron-Job eingerichtet
- [ ] Server: Repository geklont
- [ ] Server: `composer.phar` vorhanden
- [ ] Server: `composer install --no-dev` ausgeführt
- [ ] Server: `.env` mit Produktions-Werten erstellt
- [ ] Server: `php artisan key:generate` ausgeführt
- [ ] Server: `php artisan migrate --force` ausgeführt
- [ ] Server: `php artisan storage:link` ausgeführt
- [ ] Server: `chmod -R 775 storage bootstrap/cache`
- [ ] GitHub: Deploy-SSH-Key in `~/.ssh/authorized_keys` eingetragen
- [ ] GitHub: Secrets (`SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY`, `SSH_PATH`, `PHP_BIN`) angelegt
- [ ] GitHub: `.github/workflows/deploy.yml` ins Repo commitet
- [ ] Test: Push → GitHub Action grün → Seite lädt

---

## Bekannte Einschränkungen (Shared Hosting)

| Thema | Einschränkung | Lösung |
|-------|--------------|--------|
| Queue-Worker | Kein dauerhafter Hintergrundprozess | `QUEUE_CONNECTION=sync` – Jobs laufen synchron |
| Redis | Nicht verfügbar | Cache/Session auf `file` |
| Websockets | Nicht verfügbar | Kein Broadcasting benötigt (`LOG` ist ok) |
| Supervisor | Nicht verfügbar | Scheduler läuft über Cron |
| PHP-Pfad | Könnte `php` oder `php` sein | Im ersten SSH-Login prüfen: `which php` |
