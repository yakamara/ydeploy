<?php

// Non-empty subtitle to overwrite the sub navi of original page
echo rex_view::title('YDeploy: Protected Page', ' ');

echo rex_view::error('The page <code>'.rex_be_controller::getCurrentPage().'</code> is protected in deployed instances!');
