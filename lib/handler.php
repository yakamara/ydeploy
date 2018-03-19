<?php

/**
 * @internal
 */
class rex_ydeploy_handler
{
    public static function addBodyClasses(rex_extension_point $ep)
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

    public static function addBadge(rex_extension_point $ep)
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
            return;
        }

        $badge = '<div class="ydeploy-badge">'.$badge.'</div>';

        return str_replace('</body>', $badge.'</body>', $ep->getSubject());
    }

    public static function protectPages()
    {
        $protectedPages = rex_addon::get('ydeploy')->getProperty('config')['protected_pages'];

        foreach ($protectedPages as $page => $subpages) {
            $page = rex_be_controller::getPageObject($page);

            if (!$page) {
                continue;
            }

            if (!is_array($subpages)) {
                self::protectPage($page);

                continue;
            }

            foreach ($subpages as $subpage) {
                $subpage = $page->getSubpage($subpage);

                if ($subpage)
                    self::protectPage($subpage);
            }
        }
    }

    private static function protectPage(rex_be_page $page)
    {
        $page->setHidden(true);
        $page->setPath(rex_path::addon('ydeploy', 'pages/protected.php'));

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
}
