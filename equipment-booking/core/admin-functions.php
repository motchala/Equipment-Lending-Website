<?php
require __DIR__ . '/../../shared/core/admin-bootstrap.php';

require __DIR__ . '/../../settings/core/admin-handlers.php';

require __DIR__ . '/admin-equipment-handlers.php';

require __DIR__ . '/admin-equipment-data.php';

require __DIR__ . '/../../account-management/core/admin-data.php';

require __DIR__ . '/../../settings/core/admin-data.php';


$init_view = $_GET['view'] ?? 'dashboard';
