<?php

requireUser($db, true);
requireCsrfToken();
logoutCurrentUser();
http_response_code(204);
exit;
