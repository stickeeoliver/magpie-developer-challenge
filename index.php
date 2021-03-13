<?php php_sapi_name() === 'cli' or die('Must be run from the command line.');

require_once __DIR__ . '/vendor/autoload.php';

use App\Scrape;

$scrape = new Scrape();
$scrape->run();
echo 'Job done!' . PHP_EOL;
