<?php

use Ini\Parser;

require_once './vendor/autoload.php';

$a = 'environment = testing

[testing]
debug = true
database.connection = "mysql:host=127.0.0.1"
database.name = test
database.username =
database.password =
secrets = [1,2,3]

[staging : testing]
database.name = stage
database.username = staging
database.password = 12345

[production : staging]
debug = false;
database.name = production
database.username = root';

$parser = new Parser();
$config = $parser->process($a);

echo "<pre>";
var_export($config);
echo "</pre>";