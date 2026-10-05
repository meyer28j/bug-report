<?php

// Set here (not php.ini) so dates like date_reported's default are local regardless of server config.
date_default_timezone_set('America/Vancouver');

$dbPath = __DIR__ . '/../../data/cases.db';

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
