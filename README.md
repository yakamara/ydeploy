YDeploy
=======

Das Addon bietet Tools für die Datenbank-Migration während des Deployments von REDAXO-Projekten.
 Zusätzlich bietet es eine auf REDAXO abgestimmte Konfiguration für [deployer](https://deployer.org).

Migration
---------

Das Addon bietet zwei Konsolen-Befehle für die Migration. Sie bilden ein Gegensatzpaar:
`ydeploy:diff` wird **lokal beim Entwickeln** verwendet, um Datenbank-Änderungen in versionierbare
Dateien zu schreiben; `ydeploy:migrate` wird **auf dem Zielsystem (oder lokal nach einem `git pull`)**
verwendet, um genau diese Änderungen anzuwenden.

### `redaxo/bin/console ydeploy:diff`

**Wann benutzen?** Immer dann, wenn lokal Änderungen an der Datenbank-Struktur (Tabellen, Spalten,
Indizes, Fremdschlüssel) oder an synchronisierten Stamm-Daten (z. B. YForm-Tabellen, Metainfo-Felder,
MediaManager-Typen, bestimmte `rex_config`-Namespaces) vorgenommen wurden und diese in andere
Umgebungen (Staging/Live) übertragen werden sollen. Der Befehl ist das Gegenstück zu
`ydeploy:migrate` und gehört in den Entwicklungs-Workflow vor jedem `git commit`/`git push`.

**Was passiert konkret?**

Beim **ersten Aufruf** legt der Befehl in `redaxo/data/addons/ydeploy/` zwei Dateien an:

* `schema.yml` – enthält den aktuellen Stand des Datenbank-Schemas (alle Tabellen mit
  Spalten, Datentypen, Indizes und Fremdschlüsseln, soweit sie zum REDAXO-Tabellen-Prefix passen).
* `fixtures.yml` – enthält die Datensätze aller Tabellen, deren Inhalte mit synchronisiert werden
  sollen. Welche Tabellen das sind, wird in der [`package.yml`](package.yml) unter
  `config.fixtures.tables` festgelegt. Standardmäßig sind das u. a.:
  * `rex_config` – nur ausgewählte Namespaces (standardmäßig u. a. `core`, `mblock`,
    `media_manager`, `mform`, `rexstan`, `sprog`, `media_manager_responsive`, `bloecks`,
    `2factor_auth`, `easymde`, `slice_select`, `googleplaces`, `yform_spam_protection`,
    `yform_usability`). Die vollständige, projekt-spezifisch erweiterbare Liste steht in
    [`package.yml`](package.yml) unter `config.fixtures.tables.config`.
  * Metainfo: `rex_metainfo_field`, `rex_metainfo_type`.
  * MediaManager: `rex_media_manager_type`, `rex_media_manager_type_effect`,
    `rex_media_manager_type_group`, sowie `…_type_meta` für `media_manager_responsive`.
  * **YForm-Manager-Definitionen**: `rex_yform_table` (Tabellen-Definitionen) und
    `rex_yform_field` (Feld-Definitionen). Damit werden YForm-Tabellen mitsamt ihren Feldern
    voll mit-deployt.
  * Weitere optionale Ergänzungen (Redactor-Profile, URL-Generator-Profile, easymde-Profile,
    markitup-Profile, „wenns_sein_muss“ iframes …).

  Die Liste lässt sich in der eigenen `package.yml` oder in einer Projekt-Konfiguration erweitern.

Bei **jedem weiteren Aufruf** vergleicht der Befehl:

1. den aktuellen Stand der Datenbank-Struktur mit dem in `schema.yml` festgehaltenen Zustand,
2. die Datensätze der in der `package.yml` definierten Fixture-Tabellen mit dem Stand in
   `fixtures.yml`.

Gibt es Unterschiede, werden:

* `schema.yml` und `fixtures.yml` mit dem aktuellen Stand überschrieben (so sind beide Dateien
  immer das „Soll“ für den nächsten Vergleich),
* in `redaxo/data/addons/ydeploy/migrations/` eine neue Migrationsdatei mit Zeitstempel-Namen
  (z. B. `2024-01-15 12-34-56.123456.php`) erzeugt, die direkt ausführbaren PHP-Code mit allen
  notwendigen SQL- bzw. Fixture-Operationen enthält,
* die Migration in der Tabelle `rex_ydeploy_migration` direkt als ausgeführt markiert (damit
  `ydeploy:migrate` lokal nicht versucht, die eben erst erzeugte Änderung erneut anzuwenden).

Optionen:

* `--empty` – erzeugt auch dann eine (leere) Migrationsdatei, wenn keine Änderungen erkannt
  wurden. Nützlich, um z. B. eine manuell zu pflegende Migration zu erstellen.
* `--unmarked` – markiert die neue Migration **nicht** als bereits ausgeführt. Sinnvoll, wenn die
  Migration nach dem Erzeugen lokal nochmal mit `ydeploy:migrate` getestet werden soll.

Details des Kommandos erhält man über `redaxo/bin/console help ydeploy:diff`.

Templates, Module und Actions werden bewusst **nicht** über die `fixtures.yml` synchronisiert,
da sie häufig in den eigentlichen `*.php`/`*.html`-Dateien gepflegt werden. Dafür sollte das
[Developer-Addon](https://github.com/FriendsOfREDAXO/developer) genutzt werden, das diese
Inhalte als Dateien im Repository ablegt.

### `redaxo/bin/console ydeploy:migrate`

**Wann benutzen?** Auf dem Zielsystem (Staging/Live) im Rahmen des Deployments – dort wird der
Befehl von YDeploy ohnehin automatisch im Task `database:migration` aufgerufen. Lokal eignet
sich der Befehl, um Migrations-Dateien, die andere Entwickler:innen per `git pull` mitgebracht
haben, in die eigene Datenbank einzuspielen, ohne diese komplett neu aufzubauen.

**Was passiert konkret?**

* Es werden alle `*.php`-Dateien aus `redaxo/data/addons/ydeploy/migrations/` ausgeführt, deren
  Zeitstempel (aus dem Dateinamen) noch nicht in der Tabelle `rex_ydeploy_migration` als
  „ausgeführt“ markiert ist – und zwar in chronologischer Reihenfolge.
* Nach erfolgreichem Durchlauf einer Migration wird ihr Zeitstempel in
  `rex_ydeploy_migration` eingetragen, sodass sie kein zweites Mal ausgeführt wird.
* Bei Nutzung von deployer (siehe unten) wird zusätzlich – sofern das
  [Developer-Addon](https://github.com/FriendsOfREDAXO/developer) installiert ist – im selben
  Schritt `developer:sync --force-files` aufgerufen, damit Templates/Module/Actions aus den
  Dateien in die Datenbank übernommen werden.

Details des Kommandos erhält man über `redaxo/bin/console help ydeploy:migrate`.

Optionen:

* `--fake` – markiert alle ausstehenden Migrationen als ausgeführt, ohne die
  Migrationsdateien tatsächlich auszuführen.

### `redaxo/bin/console ydeploy:warmup`

Dieses Kommando wärmt den Cache nach einem Deployment auf, indem es URLs per Multi-cURL parallel abruft.
Dabei werden — sofern verfügbar — die `sitemap.xml`-Dateien aller im yrewrite-Addon konfigurierten Domains
ausgewertet und alle darin enthaltenen URLs abgerufen. Steht das yrewrite-Addon nicht zur Verfügung,
wird die in REDAXO konfigurierte Server-URL verwendet.

Ist das Addon [search_it](https://github.com/FriendsOfREDAXO/search_it) installiert, wird zusätzlich der
Suchindex neu aufgebaut. Der URL-Addon-Index baut sich beim ersten Frontend-Aufruf automatisch wieder auf
und wird damit ebenfalls indirekt durch das Warmup erzeugt.

Optionen:

* `--url=URL` (mehrfach möglich): Konkrete URL(s), die aufgewärmt werden sollen. Überspringt die automatische
  Erkennung über `sitemap.xml`.
* `--no-sitemap`: Lädt nur die jeweilige Domain-Root, ohne `sitemap.xml` auszuwerten.
* `--skip-search-it`: Überspringt den Neuaufbau des `search_it`-Index.
* `--concurrency=N`: Anzahl paralleler HTTP-Requests (Standard `5`).
* `--timeout=N`: Timeout pro Request in Sekunden (Standard `30`).

Details des Kommandos erhält man über `redaxo/bin/console help ydeploy:warmup`.

### Ausstehende Migrations im Backend

Solange noch nicht ausgeführte Migrationsdateien in `redaxo/data/addons/ydeploy/migrations/` existieren, wird im Backend (nur für Admins) ein Warnhinweis mit der Liste der ausstehenden Migrations ausgegeben. Damit wird verhindert, dass `ydeploy:migrate` versehentlich vergessen wird.

Die Liste der ausstehenden Migrations liegt zusätzlich als Addon-Property `pending_migrations` vor und kann programmatisch abgefragt werden, z.B. für eine eigene Ausgabe im Footer:

```php
$pending = rex_addon::get('ydeploy')->getProperty('pending_migrations');

if (is_array($pending) && $pending) {
    foreach ($pending as $timestamp => $path) {
        // $timestamp z.B. "2024-01-15 12:34:56.789123"
        // $path absoluter Pfad zur Migrationsdatei
        echo basename($path) . ' (' . $timestamp . ')';
    }
}
```

Alternativ kann auch direkt `rex_ydeploy::getPendingMigrations()` aufgerufen werden, um die Liste neu zu ermitteln.

Deployment über deployer
------------------------

Zunächst sollte man sich mit den Grundlagen von deployer vertraut machen: https://deployer.org

Das Addon liefert deployer selbst nicht mit. Es kann über verschiedene Wege instaliert werden:

* Lokal im Projekt (ggf. in separatem `.tools`-Ordner: `composer require deployer/deployer`
* Global über composer: `composer global require deployer/deployer`
* Als Phar-Archive: https://deployer.org/download

### Konfiguration

Im Projekt-Root sollte die Konfigurationsdatei `deploy.php`  angelegt werden, die die auf REDAXO abgestimmte 
[Basis-Konfiguration](https://github.com/yakamara/ydeploy/blob/main/deploy.php) aus diesem Addon einbindet:

```php
<?php

namespace Deployer;

if ('cli' !== PHP_SAPI) {
    throw new \Exception('The deployer configuration must be used in cli.');
}

// Der Pfad ist ggf. anzupassen, falls der Projekt-Root nicht dem REDAXO-Root entspricht
// Falls die Yak-Struktur (https://github.com/yakamara/yak) verwendet wird, sollte stattdessen die `deploy_yak.php` eingebunden werden
// require __DIR__ . '/redaxo/src/addons/ydeploy/deploy_yak.php';
require __DIR__ . '/redaxo/src/addons/ydeploy/deploy.php';

set('repository', 'git@github.com:user/repo.git');

host('servername')
    ->setHostname('example.com')
    ->setDeployPath('/var/www/com.example')
;
```

In dieser Datei kann die Konfiguration individuell auf das Projekt abgestimmt werden, sowie durch eigene weitere Tasks
ergänzt werden.
Siehe dazu: 
* https://deployer.org/docs/7.x/basics
* https://deployer.org/docs/7.x/hosts
* https://deployer.org/docs/7.x/tasks

### .gitignore

Die folgende `.gitignore` hat sich als Basis bewährt bei Nutzung von deployer:

```
/.build
/media/*
!/media/.redaxo
/redaxo/cache/*
!/redaxo/cache/.*
/redaxo/data/addons/*/*
!/redaxo/data/addons/developer/*
!/redaxo/data/addons/mblock/*
!/redaxo/data/addons/mform/*
!/redaxo/data/addons/ydeploy/*
/redaxo/data/core/*
/redaxo/data/log/*
```

Sollte REDAXO nicht direkt im Projekt-Root liegen, müssen die Pfade entsprechend angepasst werden.

### Deployment

Führe `dep deploy` aus, um das Deployment auf den Zielserver zu starten. Dieser Befehl besteht aus zwei Teilen, die sich auch einzeln ausführen lassen:

1. Lokal vorbereiten: `dep build local`
2. Vorbereitetes Paket auf den Server spielen: `dep release [host]`

So lässt sich bspw. über `dep deploy staging` auf den `staging`-Server deployen, testen und anschließend mit dem bereits vorliegenden Build auf den Produktivserver aufspielen: `dep release production`.

Im Release-Ablauf wird vor den Datenbank-Migrationen automatisch der Task `database:backup` ausgeführt und ein Datenbank-Backup auf dem Zielhost unter `{{shared_path}}/{{data_dir}}/addons/backup/backup-data/ydeploy/backup-<timestamp>.sql` abgelegt. Beim allerersten Deployment (es gibt noch kein `current`-Release) wird das Backup übersprungen.
Für den automatischen Backup-Schritt muss auf den Zielhosts ein MySQL/MariaDB-Client inklusive `mysqldump` installiert sein.

#### Was macht `dep build [local]`?

Der `build`-Task bereitet auf dem **lokalen** Host (genauer: im Verzeichnis `.build/release` im Projekt-Root) ein vollständiges Release-Paket vor, das anschließend per `dep release` auf den Zielserver übertragen wird. Er besteht aus folgenden Unter-Tasks (siehe [`deployer/tasks/build.php`](deployer/tasks/build.php)):

1. **`build:info`** – gibt eine kurze Statusausgabe „building <target>“ aus.
2. **`build:setup`** – stellt sicher, dass der Task auf dem `local`-Host läuft, leert `.build/release/` und checkt den konfigurierten Branch/Tag (`target`) frisch aus dem Git-Repository aus. Im CI-Kontext (`getenv('CI')`) wird der gesamte Schritt übersprungen – weder das Aufräumen von `.build/release/` noch der Git-Checkout finden statt, weil das Repository im CI bereits in den Workspace ausgecheckt ist und direkt verwendet wird.
3. **`build:vendors`** – leerer Platzhalter, der im Projekt durch eigene Logik überschrieben werden sollte (typischerweise `composer install --no-dev --optimize-autoloader` o. Ä.).
4. **`build:assets`** – installiert per `yarn install` bzw. `npm install` Frontend-Dependencies und ruft `yarn build` / `npm run build` bzw. `gulp build` auf, falls eine entsprechende Konfiguration im Repository liegt. `node_modules/` werden zwischen Builds in `.build/.node_modules` zwischengelagert, damit sie nicht jedes Mal neu installiert werden müssen.
5. **`deploy:clear_paths`** – entfernt Pfade aus dem Build, die im Projekt unter `clear_paths` gelistet sind (z. B. Entwicklungs-Dateien, die nicht aufs Live-System sollen).

Das Ergebnis ist ein „bereinigtes“ Release im Ordner `.build/release/`, das exakt dem entspricht, was anschließend auf den Server hochgeladen wird.

#### Was macht `dep release [host]`?

Der `release`-Task spielt das bereits per `build` lokal vorbereitete Paket auf einem Ziel-Host aus. Definiert ist die Reihenfolge in [`deployer/tasks/release.php`](deployer/tasks/release.php):

1. **`deploy:info`** – Statusausgabe „deploying <host>“.
2. **`deploy:setup`** – legt auf dem Server (falls noch nicht vorhanden) die Standard-Struktur `releases/`, `shared/` und `.dep/` an.
3. **`deploy:lock`** – setzt eine Lock-Datei (`.dep/deploy.lock`), damit kein zweites Deployment parallel läuft. Wird das Deployment unterbrochen, muss der Lock ggf. mit `dep deploy:unlock <host>` manuell entfernt werden.
4. **`deploy:release`** – legt einen neuen, mit Zeitstempel versehenen Release-Ordner unter `releases/` an.
5. **`deploy:copy_dirs`** – kopiert in der Projekt-`deploy.php` unter `copy_dirs` konfigurierte Verzeichnisse aus dem vorherigen Release in den neuen (z. B. `node_modules`).
6. **`deploy:upload`** – synchronisiert per `rsync` den Inhalt von `.build/release/` (vom `local`-Host) in den neuen Release-Ordner auf dem Zielserver. Ausgenommen werden u. a. `.git/`, `.cache/`, `.tools/`, `node_modules/` und die Projekt-eigene `deploy.php`.
7. **`deploy:shared`** – verlinkt geteilte Verzeichnisse/Dateien aus `shared/` ins neue Release (z. B. `media/`, `redaxo/data/`, `redaxo/cache/` – konfiguriert über `shared_dirs`/`shared_files`).
8. **`deploy:dump_info`** – schreibt unter `redaxo/data/addons/ydeploy/info.json` Meta-Informationen zum Deployment (Host, Stage, Branch, Commit-SHA, Timestamp). Das wird im Backend angezeigt und ist hilfreich für die Nachvollziehbarkeit.
9. **`deploy:writable`** – setzt die Schreibrechte auf konfigurierte Verzeichnisse (`writable_dirs`).
10. **`setup`** – nur beim allerersten Deployment relevant: legt interaktiv `redaxo/data/core/config.yml` an, kopiert Datenbank und Medien von einem Quell-Host oder Dump-File auf den Ziel-Host, konfiguriert das Developer-Addon und ersetzt YRewrite-Domains. Existiert bereits eine `config.yml` auf dem Host, ist dieser Schritt ein No-Op.
11. **`database:backup`** – legt vor den Migrationen ein `mysqldump`-Backup unter `{{shared_path}}/{{data_dir}}/addons/backup/backup-data/ydeploy/backup-<timestamp>.sql` ab. Beim allerersten Deployment (noch kein `current`-Release) wird das Backup übersprungen.
12. **`database:migration`** – ruft im neuen Release `redaxo/bin/console ydeploy:migrate -v` auf und – falls vorhanden – `developer:sync --force-files -v`, damit Schema, Fixtures und Developer-Files in Einklang gebracht werden.
13. **`deploy:publish`** – schaltet das neue Release per `current`-Symlink scharf. Dieser Schritt umfasst intern auch `deploy:symlink`, `deploy:unlock`, `deploy:cleanup` und `deploy:success`.

Zusätzlich sind folgende Hooks aktiv:

* Vor `server:clear_cache` läuft beim **ersten** Deployment der Task `setup:wait_for_symlink`, der pausiert, bis bestätigt wurde, dass der Webserver die Domain auf `{{current_path}}/{{base_dir}}` zeigt. Damit wird verhindert, dass `clear_web_php_cache` ins Leere läuft.
* Vor `deploy:cleanup` läuft `deploy:clear_previous_releases_cache`, der die Cache-Verzeichnisse (`redaxo/cache/addons/*`, `redaxo/cache/core/*`) **älterer** Releases leert. So bleibt das `current`-Release voll funktionsfähig, alte Releases belegen aber keinen unnötigen Platz mehr.
* `server:clear_cache` selbst kann via `restart_apache`, `kill_process` (z. B. `'fcgi'`) und `clear_web_php_cache` projektspezifisch konfiguriert werden, um nach dem Symlink-Wechsel den PHP-OPcache zu leeren.

#### Was macht `dep deploy [host]`?

`dep deploy` (siehe [`deployer/tasks/deploy.php`](deployer/tasks/deploy.php)) ist eine reine Komfort-Verkettung: zuerst wird auf dem `local`-Host `build` aufgerufen, anschließend `release` auf dem angegebenen Zielhost. In CI-Setups bietet es sich oft an, `build` und `release` getrennt aufzurufen (Build im CI-Container, anschließend `dep release <host>` von einem Bastion-/Deployment-Host).

#### Deployment entsperren: `dep deploy:unlock [host]`

Bricht ein Deployment unerwartet ab (z. B. SSH-Verbindungsabbruch, Strg+C, Fehler in einem Task), bleibt der Lock aus `deploy:lock` bestehen und der nächste `dep deploy`/`dep release` schlägt mit „Deploy is locked“ fehl. In diesem Fall mit

```
dep deploy:unlock <host>
```

den Lock manuell entfernen. Vorher prüfen, dass kein paralleles Deployment tatsächlich noch läuft, und ggf. den Zustand des letzten Release-Ordners aufräumen.

#### Weitere nützliche Deployer-Befehle

Deployer bringt von Haus aus weitere Befehle mit, die in Kombination mit YDeploy oft praktisch sind:

* **`dep rollback <host>`** – schaltet den `current`-Symlink wieder auf das vorherige Release zurück. **Wichtig:** ein Rollback gibt nur den **Code** auf den vorherigen Stand zurück – Datenbank-Migrationen werden nicht rückgängig gemacht. Für ein vollständiges Rollback muss zusätzlich das vor dem Deployment angelegte `database:backup`-SQL-File eingespielt werden.
* **`dep ssh <host>`** – öffnet eine SSH-Session im `current`-Release-Verzeichnis des Hosts. Nützlich, um schnell `redaxo/bin/console …` direkt auf dem Server auszuführen.
* **`dep config:hosts`** / **`dep config:current`** – zeigt die konfigurierten Hosts bzw. den Stand des aktuellen Release-Symlinks.
* **`dep run '<command>' <host>`** – führt ein beliebiges Shell-Kommando im `current`-Release des Hosts aus.
* **`dep setup <host>`** – läuft im normalen `release`-Flow ohnehin automatisch und richtet einen frischen Host (config.yml, Datenbank, Medien) ein. Lokal kann `dep setup` genutzt werden, um den Entwickler-Arbeitsplatz aus einem Dump oder von einem Host zu befüllen.

Eine vollständige Übersicht aller verfügbaren Tasks liefert `dep list`, Details zu einem Task `dep help <task>`.

### Datenbank-Backup auf einem Host (`dep database:backup`)

Mit `dep database:backup <host>` lässt sich jederzeit manuell ein Datenbank-Backup eines konfigurierten Hosts anlegen. Der Befehl

1. verbindet sich mit dem ausgewählten Host,
2. nutzt die Datenbank-Einstellungen des dort installierten REDAXO,
3. legt das Backup unter `{{shared_path}}/{{data_dir}}/addons/backup/backup-data/ydeploy/backup-<timestamp>.sql` ab.

### Live-Daten lokal einspielen (`dep pull`)

Mit `dep pull` lassen sich Datenbank und Medien-Dateien eines konfigurierten Hosts in die lokale Installation übernehmen, z. B. um lokal mit echten Inhalten zu arbeiten.

Der Befehl

1. wählt den Quell-Host aus (interaktiv, wenn mehrere konfiguriert sind),
2. legt automatisch ein Backup der lokalen Datenbank unter `redaxo/data/addons/ydeploy/backup-<timestamp>.sql` an,
3. dumpt die Datenbank des Quell-Hosts (mit optionalem Tabellen-Filter), lädt den Dump herunter und importiert ihn lokal,
4. lädt den Medien-Ordner als Tar-Archiv vom Quell-Host und entpackt ihn ins lokale `media/`-Verzeichnis.

Über folgende Optionen lässt sich der Vorgang in der `deploy.php` projektspezifisch anpassen:

```php
// Diese Tabellen werden beim Datenbank-Pull ausgelassen
set('pull_exclude_tables', [
    'rex_ydeploy_migration', // Standard: lokalen Migrations-Stand nicht überschreiben
    // 'rex_config',
    // 'rex_yrewrite_domain',
]);

// Alternativ: NUR diese Tabellen pullen (überschreibt 'pull_exclude_tables')
set('pull_include_tables', [
    // 'rex_article',
    // 'rex_article_slice',
    // 'rex_media',
    // 'rex_media_category',
]);

// Komplett überspringen
set('pull_skip_database', false);
set('pull_skip_media', false);
```

Der Befehl muss auf dem `local`-Host laufen, was beim Aufruf von `dep pull` (ohne weiteren Host) automatisch geschieht.

### Cache-Warmup nach dem Deployment

Das Addon stellt einen Deployer-Task `deploy:warmup` bereit, der nach `server:clear_cache` ausgeführt wird und
den `ydeploy:warmup`-Konsolen-Befehl auf dem Ziel-Host startet (siehe oben). Das initiale Deployment wird
übersprungen, da die Domain in diesem Fall i.d.R. noch nicht auf das `current`-Symlink zeigt.

Standardmäßig fragt der Task im interaktiven Modus per Y/N-Abfrage, ob das Warmup laufen soll. Über die
folgenden Optionen lässt sich das Verhalten anpassen:

```php
// true: ohne Rückfrage immer ausführen; false (Standard): interaktiv fragen bzw. im
// nicht-interaktiven Modus überspringen
set('warmup_after_deploy', false);

// Beim initialen Deployment überspringen (Standard true)
set('warmup_skip_initial', true);

// Zusätzliche CLI-Optionen, die an `ydeploy:warmup` durchgereicht werden
set('warmup_console_options', ['--concurrency=10']);
```

Lizenz
------

Dieses Repository ist ein Fork von [yakamara/ydeploy](https://github.com/yakamara/ydeploy)
und steht unter einer gemischten Lizenzierung:

* Der bereits bestehende Code aus dem Upstream-Repository bleibt unverändert unter der
  MIT-Lizenz (siehe [`LICENSE-MIT`](LICENSE-MIT)).
* Alle Änderungen und Erweiterungen, die nach dem Fork durch Alexander Walther in diesem
  Repository hinzugefügt wurden, stehen unter einer proprietären Lizenz.

Die vollständigen Lizenzbedingungen sind in der Datei [`LICENSE`](LICENSE) hinterlegt.
