Changelog
=========

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
