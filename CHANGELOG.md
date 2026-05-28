Changelog
=========

Unreleased
----------

### Neu

* Klassen verwenden jetzt den Namespace `Alexplusde\Deploy` (z. B. `Alexplusde\Deploy\YDeploy`, `Alexplusde\Deploy\Handler`, `Alexplusde\Deploy\DiffFile`, `Alexplusde\Deploy\Command\Diff`/`Migrate`/`Warmup`, `Alexplusde\Deploy\Api\ProtectedPage`). Die alten `rex_ydeploy_*` / `rex_api_ydeploy_*` Klassennamen werden als Aliase weiterhin unterstützt (BC). Die Console-Befehlsnamen (`ydeploy:diff`, `ydeploy:migrate`, `ydeploy:warmup`) bleiben unverändert und sind kompatibel zum Original-YDeploy-Addon.
* Neuer Deployer-Task `database:backup`: Erstellt auf einem Remote-Host ein Datenbank-Backup unter `{{shared_path}}/{{data_dir}}/addons/backup/backup-data/ydeploy/backup-<timestamp>.sql` (`dep database:backup <host>`). Wird zudem im Release-Ablauf automatisch vor `database:migration` ausgeführt, sodass vor jedem Deployment ein aktuelles Backup vorliegt. Beim allerersten Deployment (kein `current`-Symlink vorhanden) wird das Backup übersprungen.
* Media-Manager-Cache wird auf `redaxo/data/addons/media_manager/cache` umgelegt (`rex_media_manager::setCacheDirectory(...)` in `boot.php`) und das Verzeichnis als Shared-Directory (`{{data_dir}}/addons/media_manager`) hinterlegt. Dadurch bleiben die ressourcenintensiv erzeugten Varianten (verschiedene Auflösungen, WebP/AVIF) über Releases und Cache-Leerungen hinweg erhalten.
* Neuer Deployer-Task `pull`: Lokale Datenbank und Medien können von einem konfigurierten Host übernommen werden (`dep pull`). Vor dem Import wird automatisch ein lokales Datenbank-Backup unter `redaxo/data/addons/ydeploy/backup-<timestamp>.sql` angelegt. Tabellen können projektspezifisch per `pull_exclude_tables`/`pull_include_tables` gesteuert werden; mit `pull_skip_database` / `pull_skip_media` lässt sich der jeweilige Teil überspringen.
* Cache-Dateien (`{{cache_dir}}/addons/*` und `{{cache_dir}}/core/*`) der vorherigen Releases werden nach einem erfolgreichen Release automatisch gelöscht, um Speicherplatz zu sparen (z. B. durch den MediaManager-Cache)
* `deploy:update_code` wurde überschrieben und holt Git-Submodule (auch private Repos via SSH) automatisch. Die bisher nötige Einstellung `set('update_code_strategy', 'clone_plus_submodules');` in der `deploy.php` des Projekts ist nicht mehr erforderlich und sollte entfernt werden; falls sie noch gesetzt ist, wird in der Konsole ein entsprechender Hinweis ausgegeben.


Version 2.1.1 – 22.07.2025
--------------------------

### Bugfixes

* Korrektur für Upgrade von v1, damit die Releasenummerierung wieder korrekt fortgesetzt wird
* Allgemeineren `gulp`-Pfad `node_modules/.bin/gulp` nutzen


Version 2.1.0 – 04.06.2025
--------------------------

### Neu

* Migration: Es werden Meldungen beim Start und Ende jeder Migrationsdatei ausgegeben


Version 2.0.2 – 23.05.2024
--------------------------

### Bugfixes

* git-cli nicht auf Remote-Server suchen


Version 2.0.1 – 21.05.2024
--------------------------

### Bugfixes

* Migration: Fehlermeldungen wurden nicht ausgegeben, wodurch Fehler nicht analysierbar waren
* Erstes Deployment: Upload der lokalen Datenbank schlug fehl
* Wenn Stage nicht gesetzt, kam es zum Fehler
* Build-Step-Info enthielt verwirrende Releasenummer "1"


Version 2.0.0 – 05.01.2024
--------------------------

### Neu

* Neue PHP-Mindestversion 8.1
* Neue REDAXO-Mindestversion 5.13
* Neue Deployer-Mindestversion 7.0
* Anpassungen für Deployer v7 und für die einfachere Nutzung über GitLab-CI
* Dazu separate [Upgrade-Anleitung](https://github.com/yakamara/ydeploy/blob/main/UPGRADE.md) beachten


Version 1.2.0 – 19.03.2023
--------------------------

### Neu

* Default-Config für rexstan
* Es wird geprüft, ob die passende Deployer-Version genutzt wird


Version 1.1.2 – 05.03.2023
--------------------------

### Bugfixes

* Fixtures für redactor-Adddon korrigiert (@tbaddade)
* Foreign keys: Action-Type `NO ACTION` wurde nicht berücksichtigt (@tyrant88)


Version 1.1.1 – 11.07.2022
--------------------------

### Bugfixes

* Je nach `setlocale`-Einstellung konnte es zu Fehlern beim `diff`-Command kommen (@alxndr-w)
* Beim Aufruf von ungültigen Backend-Pages konnte es zu einer Exception kommen (@gharlan)


Version 1.1.0 – 20.03.2022
--------------------------

### Neu

* Migration für Views (@gharlan)
* Wenn YForm-Mail-Templates über Developer-Addon synchronisiert werden, dann wird die Backend-Page auch geschützt (@gharlan)
* Konfiguration für `redactor`-Addon (@tyrant88)
* `.gitlab-ci.yml` wird vor dem Upload gelöscht (@tbaddade)

### Bugfixes

* Korrekturen für PHP 8 (@gharlan)
* Korrekturen für YForm 4 (@gharlan)
* Korrekturen für Alpine-Linux (@gharlan)


Version 1.0.0 – 02.04.2020
--------------------------

Erstes reguläres Release, Änderungen zu 1.0-beta7:

* Fremdschlüssel werden migriert
* Tabellen-Charsets und Collations werden migriert
* Server-Cache-Lösch-Task funktioniert zuverlässiger
* Weitere kleine Bugfixes
