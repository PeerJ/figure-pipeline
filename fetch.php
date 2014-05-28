<?php

require __DIR__ . '/lib/FiguresHandler.php';

$config = json_decode(file_get_contents('config.json'), true);

$client = new FiguresHandler($config, __DIR__ . '/data/');

$client->fetch_feed('https://peerj.com/articles/index.json');

/*
$client = new FlickrClient($config);
$status = $client->check_status();
print_r($status);
exit();
*/
