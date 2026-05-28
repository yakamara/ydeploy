<?php

namespace Alexplusde\Deploy\Api;

use Alexplusde\Deploy\Handler;
use rex;
use rex_api_exception;
use rex_api_function;
use rex_api_result;
use rex_csrf_token;
use rex_response;

use function rex_escape;
use function rex_get;

/**
 * @internal
 */
final class ProtectedPage extends rex_api_function
{
    public function execute(): rex_api_result
    {
        if (!rex::requireUser()->isAdmin()) {
            throw new rex_api_exception('Protected pages can be (un)locked only by admins.');
        }

        $action = rex_get('action', 'string');

        if (!in_array($action, ['lock', 'unlock'], true)) {
            throw new rex_api_exception('Supported protected page actions are "lock" and "unlock", but "' . $action . '" given.');
        }

        $protectedPage = rex_get('protected_page', 'string');

        $foundPage = null;
        foreach (Handler::getProtectedPages() as $page => $subpages) {
            // `yform/manager/table_edit` must not match `yform/man`
            // so we add slashes to avoid this
            if (str_starts_with($protectedPage . '/', $page . '/')) {
                $foundPage = $page;

                break;
            }
        }

        if ($foundPage === null) {
            throw new rex_api_exception('The page "' . $protectedPage . '" is not protected.');
        }

        if ('unlock' === $action) {
            Handler::unlockPage($foundPage);
        } else {
            Handler::lockPage($foundPage);
        }

        $redirect = rex_get('redirect', 'string');
        if ($redirect !== '') {
            rex_response::sendRedirect($redirect);
        }

        $result = new rex_api_result(true);
        $result->setRequiresReboot(true);

        return $result;
    }

    /**
     * Ensure URLs continue to reference the legacy `ydeploy_protected_page`
     * API name (mapped to `rex_api_ydeploy_protected_page` via class alias)
     * so existing backend URLs remain backwards-compatible.
     *
     * @return array<string, string>
     */
    public static function getUrlParams(): array
    {
        return [
            rex_api_function::REQ_CALL_PARAM => 'ydeploy_protected_page',
            rex_csrf_token::PARAM => rex_csrf_token::factory(static::class)->getValue(),
        ];
    }

    public static function getHiddenFields(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s"/>',
            rex_api_function::REQ_CALL_PARAM,
            rex_escape('ydeploy_protected_page'),
        ) . rex_csrf_token::factory(static::class)->getHiddenField();
    }

    protected function requiresCsrfProtection(): bool
    {
        return true;
    }
}

\class_alias(ProtectedPage::class, 'rex_api_ydeploy_protected_page');
