<?php
require_once 'vendor/autoload.php';

use Leadvertex\Plugin\Components\Db\Commands\CreateTablesCommand;
use Leadvertex\Plugin\Components\Db\Components\Connector;
use Medoo\Medoo;
use Symfony\Component\Console\Application;

Connector::config(new Medoo([
    'database_type' => 'sqlite',
    'database_file' => __DIR__ . '/testDB.db'
]));

$application = new Application();
$application->add(new CreateTablesCommand());
$application->run();