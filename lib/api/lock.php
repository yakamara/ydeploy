<?php

class rex_api_ydeploy_lock_page extends rex_api_function
{
    public function execute()
    {
        $protectedPage = rex_get('protected_page', 'string');

        $lockPage = null;
        foreach (rex_ydeploy_handler::getProtectedPages() as $page => $subpages) {
            if (0 === strpos($protectedPage.'/', $page.'/')) {
                $lockPage = $page;

                break;
            }
        }

        if (!$lockPage) {
            throw new rex_api_exception('The page "'.$protectedPage.'" is not protected.');
        }

        rex_ydeploy_handler::lockPage($lockPage);

        $result = new rex_api_result(true);
        $result->setRequiresReboot(true);

        return $result;
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
