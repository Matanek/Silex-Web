<?php

declare(strict_types=1);

use Silex\Web\ApplicationFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

ApplicationFactory::create(dirname(__DIR__))->run();
