<?php

$loader = require __DIR__ . '/../vendor/autoload.php';

$loader->addPsr4('ThemeWright\\Sync\\', __DIR__ . '/../src');

$app = new ThemeWright\Sync\Application();

return $app;