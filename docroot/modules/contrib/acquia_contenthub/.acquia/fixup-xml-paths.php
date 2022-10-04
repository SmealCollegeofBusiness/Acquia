<?php

/**
 * @file
 * Script to fix paths in the clover coverage file.
 */

declare(strict_types=1);

$cloverXml = $argv[1] ?? '';
if ($cloverXml === '') {
  print 'Must provide a file path to clover coverage XML. Path empty' . PHP_EOL;
  exit(1);
}
if (!file_exists($cloverXml)) {
  print 'Must provide a valid file path to clover coverage XML. File not available' . PHP_EOL;
  exit(1);
}

$cloverContent = file_get_contents($cloverXml);
if ($cloverContent === FALSE) {
  print 'Could not read clover coverage XML.' . PHP_EOL;
  exit(1);
}
$cloverContent = str_replace('/ramfs', '', $cloverContent);
file_put_contents($cloverXml, $cloverContent);
