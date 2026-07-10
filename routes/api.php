<?php
error_log('[ROUTES] api.php LOADED');

global $router;

require __DIR__ . '/api/dashboard.php';
require __DIR__ . '/api/settings.php';
require __DIR__ . '/api/materials.php';
require __DIR__ . '/api/ledger.php';
require __DIR__ . '/api/approval.php';
require __DIR__ . '/api/system.php';
require __DIR__ . '/api/user-settings.php';
require __DIR__ . '/api/shop.php';
require __DIR__ . '/api/public.php';
