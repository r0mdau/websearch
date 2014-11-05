<?php
require 'vendor/autoload.php';

$client = new Elasticsearch\Client(array('hosts' => array('192.168.1.200')));

define('_RETRY_CONFLICT_', 100);
$params = array(
    'index' => 'web',
    'type' => 'site',
    'ignore' => [400, 404]
);

function generateParams($parameters = array())
{
    global $params;
    return array_merge($params, $parameters);
}

function save(&$sites)
{
    global $client;
    foreach ($sites as $url) {
        $parameters = generateParams(array('id' => $url));
        $data = $client->get($parameters);

        if (!isset($data['_source'])) {
            $parameters['body'] = array(
                'visite' => 0,
                'en_visite' => 0,
                'score' => 0
            );
            echo $url . "\n";
            $client->index($parameters);
        } else {
            $parameters['retry_on_conflict'] = _RETRY_CONFLICT_;
            $parameters['body'] = array(
                'doc' => array(
                    'score' => $data['_source']['score'] + 1
                )
            );
            $client->update($parameters);
        }
    }
}

function _addslashes(&$t)
{
    foreach ($t as $p => $k) {
        $t[$p] = addslashes($k);
    }
}

function updateCurrentUrlMetaTags(&$html, &$data)
{
    global $client;
    $meta = array();
    $title = '';
    catchTitle($html, $title);
    catchMetaTags($html, $meta);

    _addslashes($meta);
    $title = addslashes($title);

    $parameters = generateParams(array(
        'id' => $data['_id'],
        'body' => array(
            'doc' => array(
                'title' => $title,
                'description' => (isset($meta['description']) ? $meta['description'] : ''),
                'keywords' => (isset($meta['keywords']) ? $meta['keywords'] : '')
            )
        ),
        'retry_on_conflict' => _RETRY_CONFLICT_
    ));
    $client->update($parameters);
}

function catchMetaTags(&$html, &$meta)
{
    preg_match_all("|<meta[^>]+name=\"([^\"]*)\"[^>]+content=\"([^\"]*)\"[^>]*>|i", $html, $matchs, PREG_SET_ORDER);
    foreach($matchs as $match ){
        foreach($match as $key => $attr){
            if($attr == 'description') {
                $meta['description'] = $match[$key + 1];
            } else if($attr == 'keywords') {
                $meta['keywords'] = $match[$key + 1];
            }
        }
    }
}

function catchUrls(&$html, &$urls)
{
    if (preg_match_all("|<a[^>]+href=\"http://([^\"\?\/]+\.[a-z]{2,4}).*\"[^>]*>|i", $html, $matchs)) {
        foreach ($matchs[1] as $url) {
            $urls[] = $url;
        }
    }
}

function catchTitle(&$html, &$title)
{
    if (preg_match("|<title>(.+)</title>|i", $html, $match)) {
        $title = $match[1];
    }
}

//$leSite="http://www.meilleurs-sites.fr/";

$result = $client->count($params);

while ($result['count'] >= 1) {
    $json = '
    {
        "size" : 200,
        "query": {
            "bool": {
                "must": [
                    { "match": { "visite":  0 }},
                    { "match": { "en_visite": 0 }}
                ]
            }
        }
    }';
    $results = $client->search(generateParams(array('body' => $json)));

    foreach ($results['hits']['hits'] as $site) {
        $parameters = generateParams(array(
            'id' => $site['_id'],
            'body' => array('doc' => array('en_visite' => 1)),
            'retry_on_conflict' => _RETRY_CONFLICT_
        ));
        $client->update($parameters);
    }
    foreach ($results['hits']['hits'] as $site) {
        $leSite = 'http://' . $site['_id'];
        $useragent = "Mozilla/5.0";
        $ch = curl_init($leSite);
        curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
        curl_setopt($ch, CURLOPT_REFERER, $leSite);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 7000);
        $html = curl_exec($ch);

        $parameters = generateParams(array('id' => $site['_id']));
        if ($html != false) {
            $urls = array();
            updateCurrentUrlMetaTags($html, $site);
            catchUrls($html, $urls);
            save($urls);

            $parameters['retry_on_conflict'] = _RETRY_CONFLICT_;
            $parameters['body'] = array('doc' => array('visite' => 1, 'en_visite' => 0));
            $client->update($parameters);
        } else {
            $client->delete($parameters);
        }
        curl_close($ch);
    }
    $result = $client->count($params);
}