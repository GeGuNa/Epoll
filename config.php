<?php
 
declare(strict_types=1);

define('APP_NAME', 'Ballot / Live');
define('APP_URL', 'http://localhost:8000');   
define('APP_DEMO_MODE', true);              

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'ballot_live');
define('DB_USER', 'root');
define('DB_PASS', '');

date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/functions.php';

?>
