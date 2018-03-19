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
}
