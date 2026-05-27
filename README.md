YDeploy
=======

Das Addon bietet Tools für die Datenbank-Migration während des Deployments von REDAXO-Projekten.
 Zusätzlich bietet es eine auf REDAXO abgestimmte Konfiguration für [deployer](https://deployer.org).

Migration
---------

Das Addon bietet zwei Konsolen-Befehle für die Migration:

### `redaxo/bin/console ydeploy:diff`

Beim ersten Aufruf dieses Kommandos werden in `redaxo/data/addons/ydeploy` zwei Dateien angelegt:

* `schema.yml` mit den Tabellendefinitionen der Datenbank
* `fixtures.yml` mit allen Datensätzen der Tabellen, deren Daten mit synchronisiert werden sollen (Metainfo-Definitionen, MediaManager-Typen, YForm-Manager-Defintionen etc.)

Beim erneuten Aufruf wird dann die aktuelle Datenbank-Struktur mit der aus der `schema.yml` verglichen, und die relevanten Daten mit der `fixtures.yml`. Sollte es Abweichungen geben, werden die beiden Dateien aktualisiert und eine Migrationsdatei in `redaxo/data/addons/ydeploy/migrations/` erstellt, die alle Änderungen enthält.

Details des Kommandos erhält man über `redaxo/bin/console help ydeploy:diff`.

Templates, Module und Actions werden nicht über die `fixtures.yml` synchronisiert, sondern dafür sollte das [Developer-Addon](https://github.com/FriendsOfREDAXO/developer) genutzt werden.

### `redaxo/bin/console ydeploy:migrate`

Dieses Kommando führt alle noch ausstehenden Migrationsdateien aus.

Bei Nutzung von deployer (siehe unten) wird dieses Kommando automatisch während des Deployments ausgeführt.
Es ist aber auch geeignet, um Datenbank-Änderungen der anderen Entwickler in die lokale Entwicklungsumgebung zu übernehmen.

Details des Kommandos erhält man über `redaxo/bin/console help ydeploy:migrate`.

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
set('warmup_console_options', '--concurrency=10');
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
