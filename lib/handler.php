<?php

/**
 * @internal
 */
final class rex_ydeploy_handler
{
    public static function addBodyClasses(rex_extension_point $ep): array
    {
        $ydeploy = rex_ydeploy::factory();

        $attr = $ep->getSubject();

        if ($ydeploy->isDeployed()) {
            $attr['class'][] = 'ydeploy-is-deployed';

            if ($ydeploy->getStage()) {
                $attr['class'][] = 'ydeploy-stage-'.rex_string::normalize($ydeploy->getStage(), '-');
            }
        } else {
            $attr['class'][] = 'ydeploy-is-not-deployed';
        }

        return $attr;
    }

    public static function addBadge(rex_extension_point $ep): ?string
    {
        $ydeploy = rex_ydeploy::factory();

        if ($ydeploy->isDeployed()) {
            $badge = $ydeploy->getHost();

            if ($ydeploy->getStage()) {
                $badge .= ' – '.ucfirst($ydeploy->getStage());
            }
        } else {
            $badge = 'Development';
        }

        $badge = rex_extension::registerPoint(new rex_extension_point('YDEPLOY_BADGE', $badge));

        if (!$badge) {
            return null;
        }

        $badge = '<div class="ydeploy-badge">'.$badge.'</div>';

        return str_replace('</body>', $badge.'</body>', $ep->getSubject());
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

            foreach ($subpages as $subpage) {
                $subpage = $page->getSubpage($subpage);

                if ($subpage) {
                    self::protectPage($subpage);
                }
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
        rex_set_session('ydeploy_unlocked_pages', $unlockedPages);
    }

    public static function lockPage(string $page): void
    {
        $unlockedPages = self::getUnlockedPages();
        unset($unlockedPages[$page]);
        rex_set_session('ydeploy_unlocked_pages', $unlockedPages);
    }

    private static function protectPage(rex_be_page $page): void
    {
        if (rex_be_controller::getCurrentPage() && $page->isActive()) {
            rex_be_controller::setCurrentPage('system/ydeploy');
        }

        $page->setHidden(true);

        // If page is first subpage of other page, then the other page must be also hidden
        while ($parent = $page->getParent()) {
            $subpages = $parent->getSubpages();

            if ($page !== reset($subpages)) {
                break;
            }

            $parent->setHidden(true);
            $page = $parent;
        }
    }

    private static function handleUnlockedPage(rex_be_page $page, ?array $subpages = null): void
    {
        if (!rex_be_controller::getCurrentPage() || !$page->isActive()) {
            return;
        }

        if (is_array($subpages)) {
            $subpage = substr(rex_be_controller::getCurrentPage(), strlen($page->getFullKey()) + 1);
            $subpage = explode('/', $subpage, 2)[0];

            if (!in_array($subpage, $subpages, true)) {
                return;
            }
        }

        rex_extension::register('PAGE_TITLE_SHOWN', static function (rex_extension_point $ep) {
            $url = rex_url::backendPage('system/ydeploy', rex_api_ydeploy_protected_page::getUrlParams() + [
                'action' => 'lock',
                'protected_page' => rex_be_controller::getCurrentPage(),
            ]);
            $error = rex_view::error('
                    The page <code>'.rex_escape(rex_be_controller::getCurrentPage()).'</code> is protected in deployed instances, but currently unlocked. Changes via this page should be made in development instances only! <br><br>
                    
                    <a href="'.$url.'">Lock and leave this page</a>
                ');

            return $ep->getSubject().$error;
        });
    }
}
