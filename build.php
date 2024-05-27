<?php
$phar = new Phar('nova.phar', 0, 'nova.phar');
$phar->buildFromDirectory(dirname(__FILE__) . '/src');
$phar->setDefaultStub('start.php', 'start.php');