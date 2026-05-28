<?php

namespace Alexplusde\Deploy;

use Alexplusde\Deploy\Api\ProtectedPage;
use rex;
use rex_addon;
use rex_be_controller;
use rex_be_page;
use rex_extension;
use rex_extension_point;
use rex_string;
use rex_url;
use rex_view;

use function rex_escape;
use function rex_session;

/**
 * @internal
 */
final class Handler
{
    /** @param rex_extension_point<string> $ep */
    public static function addPendingMigrationsWarning(rex_extension_point $ep): string
    {
        $pending = rex_addon::get('ydeploy')->getProperty('pending_migrations');

        if (!is_array($pending) || !$pending) {
            return $ep->getSubject();
        }

        $user = rex::getUser();
        if (!$user || !$user->isAdmin()) {
            return $ep->getSubject();
        }

        $count = count($pending);
        $items = [];
        foreach ($pending as $timestamp => $path) {
            $items[] = '<li><code>' . rex_escape(basename($path)) . '</code> <small>(' . rex_escape($timestamp) . ')</small></li>';
        }

        $message = '<strong>' . sprintf('YDeploy: %d ausstehende Migration(en)', $count) . '</strong>'
            . '<p>Bitte führe <code>redaxo/bin/console ydeploy:migrate</code> aus, um Datenverlust zu vermeiden.</p>'
            . '<ul>' . implode('', $items) . '</ul>';

        return $ep->getSubject() . rex_view::warning($message);
    }

    /** @param rex_extension_point<array<mixed>> $ep */
    public static function addBodyClasses(rex_extension_point $ep): array
    {
        $ydeploy = YDeploy::factory();

        $attr = $ep->getSubject();

        if ($ydeploy->isDeployed()) {
            $attr['class'][] = 'ydeploy-is-deployed';

            if ($ydeploy->getStage()) {
                $attr['class'][] = 'ydeploy-stage-' . rex_string::normalize($ydeploy->getStage(), '-');
            }
        } else {
            $attr['class'][] = 'ydeploy-is-not-deployed';
        }

        return $attr;
    }

    /** @param rex_extension_point<string> $ep */
    public static function addBadge(rex_extension_point $ep): ?string
    {
        $ydeploy = YDeploy::factory();

        if ($ydeploy->isDeployed()) {
            $host = $ydeploy->getHost();
            $stage = $ydeploy->getStage();

            $badge = ucfirst($stage);
            if ($host !== $stage) {
                $badge = $host . ' – ' . $badge;
            }
        } else {
            $badge = 'Development';
        }

        $badge = rex_extension::registerPoint(new rex_extension_point('YDEPLOY_BADGE', $badge)); // @phpstan-ignore-line

        if (!$badge) {
            return null;
        }

        $badge = '<div class="ydeploy-badge">' . $badge . '</div>';

        return str_replace('</body>', $badge . '</body>', $ep->getSubject());
    }

    public static function protectPages(): void
    {
        $unlockedPages = self::getUnlockedPages();

        foreach (self::getProtectedPages() as $page => $subpages) {
            $page = rex_be_controller::getPageObject($page);

            if (!$page) {
                continue;
            }

            if (isset($unlockedPages[$page->getFullKey()])) {
                self::handleUnlockedPage($page, is_array($subpages) ? $subpages : null);

                continue;
            }

            if (!is_array($subpages)) {
                self::protectPage($page);

                continue;
            }

            $first = true;
            $allProtected = true;
            foreach ($page->getSubpages() as $subpage) {
                $key = $subpage->getKey();

                if (!array_key_exists($key, $subpages) && !in_array($key, $subpages, true)) {
                    if ($allProtected && !$first) {
                        $page->setHref($subpage->getFirstSubpagesLeaf()->getHref());
                    }

                    $allProtected = false;
                    $first = false;

                    continue;
                }

                $first = false;

                if (!isset($subpages[$key])) {
                    self::protectPage($subpage);

                    continue;
                }

                foreach ($subpages[$key] as $subsubpage) {
                    $subsubpage = $subpage->getSubpage($subsubpage);

                    if ($subsubpage) {
                        self::protectPage($subsubpage);
                    }
                }
            }

            if ($allProtected) {
                $page->setHidden(true);
            }
        }
    }

    public static function getProtectedPages(): array
    {
        return rex_addon::get('ydeploy')->getProperty('config')['protected_pages'];
    }

    public static function getUnlockedPages(): array
    {
        return rex_session('ydeploy_unlocked_pages', 'array', []);
    }

    public static function unlockPage(string $page): void
    {
        $unlockedPages = self::getUnlockedPages();
        $unlockedPages[$page] = true;
        \rex_set_session('ydeploy_unlocked_pages', $unlockedPages);
    }

    public static function lockPage(string $page): void
    {
        $unlockedPages = self::getUnlockedPages();
        unset($unlockedPages[$page]);
        \rex_set_session('ydeploy_unlocked_pages', $unlockedPages);
    }

    private static function protectPage(rex_be_page $page): void
    {
        if (rex_be_controller::getCurrentPage() && $page->isActive()) {
            rex_be_controller::setCurrentPage('system/ydeploy');
        }

        $page->setHidden(true);
    }

    private static function handleUnlockedPage(rex_be_page $page, ?array $subpages = null): void
    {
        if (!rex_be_controller::getCurrentPage() || !$page->isActive()) {
            return;
        }

        if (is_array($subpages)) {
            $subpage = substr(rex_be_controller::getCurrentPage(), strlen($page->getFullKey()) + 1);
            $parts = explode('/', $subpage, 3);

            if (array_key_exists($parts[0], $subpages)) {
                if (is_array($subpages[$parts[0]]) && !in_array($parts[1], $subpages[$parts[0]], true)) {
                    return;
                }
            } elseif (!in_array($parts[0], $subpages, true)) {
                return;
            }
        }

        rex_extension::register('PAGE_TITLE_SHOWN', static function (rex_extension_point $ep) {
            $url = rex_url::backendPage('system/ydeploy', ProtectedPage::getUrlParams() + [
                'action' => 'lock',
                'protected_page' => rex_be_controller::getCurrentPage(),
            ]);
            $error = rex_view::error('
                    The page <code>' . rex_escape(rex_be_controller::getCurrentPage()) . '</code> is protected in deployed instances, but currently unlocked. Changes via this page should be made in development instances only! <br><br>

                    <a href="' . $url . '">Lock and leave this page</a>
                ');

            return $ep->getSubject() . $error;
        });
    }
}

\class_alias(Handler::class, 'rex_ydeploy_handler');
