<?php
echo "<pre>";
echo "__FILE__         : " . __FILE__ . PHP_EOL;
echo "__DIR__          : " . __DIR__ . PHP_EOL;
echo "getcwd()         : " . getcwd() . PHP_EOL;
echo "DOCUMENT_ROOT    : " . ($_SERVER['DOCUMENT_ROOT'] ?? '') . PHP_EOL;
echo "realpath('.')    : " . realpath('.') . PHP_EOL;
echo "realpath('..')   : " . realpath('..') . PHP_EOL;
echo "HOME (env)       : " . (getenv('HOME') ?: '(leer)') . PHP_EOL;
echo "</pre>";
