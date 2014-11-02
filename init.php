<?php
require 'vendor/autoload.php';

$client = new Elasticsearch\Client(array('hosts' => array('192.168.1.200')));

$params = array(
    'index' => 'web',
    'type' => 'site'
);

exec('curl -XDELETE "http://192.168.1.200:9200/web/"');

$params['id'] = "www.meilleurs-sites.fr";
$params['body'] = array(
    'visite' => 0,
    'en_visite' => 0,
    'score' => 0
);


var_dump($client->index($params));

