# Copilot Instructions für `ydeploy` (Fork)

Diese Datei enthält projektspezifische Anweisungen für GitHub Copilot
(inkl. Copilot Chat und Copilot Coding Agent), damit Vorschläge zum
Codebestand, zur Architektur und zur Lizenzierung dieses Forks
passen.

## Über dieses Repository

* `ydeploy` ist ein Addon für [REDAXO CMS](https://redaxo.org), das
  Datenbank-Migrationen während des Deployments unterstützt und eine
  auf REDAXO abgestimmte Konfiguration für [deployer](https://deployer.org)
  liefert.
* Dieses Repository (`alexplusde/ydeploy`) ist ein **Fork** des
  Upstream-Addons [`yakamara/ydeploy`](https://github.com/yakamara/ydeploy).
* Mindestanforderungen (siehe `package.yml`): PHP `>= 8.1`,
  REDAXO `^5.13`.

## Verzeichnisstruktur (Auszug)

* `boot.php`, `install.php`, `uninstall.php` – REDAXO-Lebenszyklus.
* `lib/` – PHP-Klassen des Addons (REDAXO-Naming `rex_ydeploy_*`).
* `pages/` – Backend-Seiten.
* `assets/` – Statische Assets fürs Backend.
* `deployer/`, `deploy.php`, `deploy_yak.php` – Deployer-Konfiguration.
* `package.yml` – REDAXO-Addon-Manifest.
* `.tools/` – Hilfsskripte für rexstan.
* `.github/workflows/` – CI (php-cs-fixer, rexstan).

## Coding-Konventionen

* PHP-Code folgt dem Stil, den `vendor/bin/php-cs-fixer` mit der
  Konfiguration in `.php-cs-fixer.dist.php` erzwingt. Vor jedem
  Commit/Vorschlag mental „php-cs-fixer-konform“ denken.
* Klassen folgen dem REDAXO-Naming `rex_ydeploy_…` (snake_case mit
  Präfix) und liegen unter `lib/`.
* Strings und Ausgaben im Backend werden bei Bedarf über das
  REDAXO-i18n-System (`rex_i18n`) übersetzt.
* SQL/DB-Zugriffe nutzen die REDAXO-APIs (`rex_sql`, `rex::getTable*`),
  keine direkten PDO-Aufrufe.
* Es werden bestehende Bibliotheken aus `composer.json` bevorzugt; neue
  Abhängigkeiten nur, wenn unbedingt nötig.
* Kommentare nur, wenn sie zusätzlichen Mehrwert bieten oder dem Stil
  benachbarten Codes entsprechen.

## Tests, Lint, Build

Es gibt keine eigene Unit-Test-Suite. Qualitätssicherung läuft über
CI in `.github/workflows/check.yml`:

* `vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php` (lokal
  zum Beheben).
* `vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.dist.php`
  (Prüfung, wie in CI).
* `rexstan` läuft in CI gegen eine frische REDAXO-Installation; die
  Konfiguration liegt in `.tools/rexstan.neon` / `.tools/rexstan.php`.

Bitte vor Änderungen sicherstellen, dass php-cs-fixer und rexstan
weiterhin grün laufen.

## Migrations- / Fixtures-spezifische Hinweise

* `ydeploy:diff` erzeugt/aktualisiert `schema.yml`, `fixtures.yml` und
  Migrationsdateien unter `redaxo/data/addons/ydeploy/`.
* `ydeploy:migrate` führt offene Migrationen aus.
* Welche Tabellen mit Fixtures gespiegelt werden, ist in `package.yml`
  unter `config.fixtures.tables` definiert.
* Templates, Module und Actions werden bewusst nicht via fixtures
  synchronisiert (dafür wird das Developer-Addon verwendet).

## Lizenz – wichtig für Copilot

Dieses Repository steht unter **gemischter Lizenzierung**:

* Sämtlicher Code, der aus dem Upstream
  [`yakamara/ydeploy`](https://github.com/yakamara/ydeploy) stammt
  oder von dort übernommen wird, bleibt unter der **MIT-Lizenz**
  (siehe [`LICENSE-MIT`](../LICENSE-MIT)). Diese Lizenz kann nicht
  widerrufen werden.
* Alle **neuen** Beiträge, Änderungen und Dateien, die nach dem Fork
  durch oder im Auftrag von Alexander Walther entstehen, stehen unter
  einer **proprietären Lizenz** („Alle Rechte vorbehalten“, siehe
  [`LICENSE`](../LICENSE)).

Daraus ergeben sich folgende Regeln für Vorschläge:

* **Keine MIT-Lizenz-Header oder „MIT-Notices“** in neu erstellten
  Dateien dieses Forks vorschlagen. Wenn überhaupt ein Header nötig
  ist, dann sinngemäß:

  ```
  Copyright (c) 2026 Alexander Walther. Alle Rechte vorbehalten.
  ```

* Bestehende Lizenzhinweise oder Copyright-Header von
  Yakamara/Gregor Harlan in vorhandenen Dateien dürfen **nicht
  entfernt oder verändert** werden.
* Beim Übernehmen von Code aus dem Upstream-Repository bleibt dessen
  MIT-Status erhalten – solchen Code daher klar als Upstream-Übernahme
  behandeln (Commit-Message, ggf. Hinweis im Kommentar) und keine
  proprietären Headerzeilen darauf anwenden.
* In Zweifelsfällen zur Herkunft einer Codezeile ist die Git-Historie
  (`git blame`, `git log`) maßgeblich.
* Keine Code-Schnipsel, Snippets oder Beispiele aus diesem Repository
  in andere (offene) Projekte vorschlagen, ohne die proprietäre Lizenz
  zu respektieren.

## Pull Requests & Commits

* Sprache für Issue- und PR-Beschreibungen sowie Changelog-Einträge:
  **Deutsch**, passend zum bestehenden `CHANGELOG.md` und `README.md`.
* Commit-Messages knapp, im Imperativ; größere Änderungen im
  `CHANGELOG.md` unter einem passenden Versionsabschnitt
  dokumentieren.
* CI muss grün sein (`php-cs-fixer` + `rexstan`), bevor ein PR als
  fertig markiert wird.
