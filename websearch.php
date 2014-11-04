<?php
require 'vendor/autoload.php';

$client = new Elasticsearch\Client(array('hosts' => array('192.168.1.200')));

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

function envoyerEnBDD(&$sites)
{
    global $client;
    foreach ($sites as $url) {
        $parameters = generateParams(array('id' => $url));
        $data = null;
        try {
            $data = $client->get($parameters);
        } catch (Exception $e) {
            echo $e->getMessage()."\n";
        }

        if (!isset($data['_source'])) {
            $parameters['body'] = array(
                'visite' => 0,
                'en_visite' => 0,
                'score' => 0
            );
            echo $url . "\n";
            $client->index($parameters);
        } else {
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

function updateMeta(&$html, &$data)
{
    global $client;
    $meta = array();
    $title = '';
    recupTitle($html, $title);
    recupererMetaTags($html, $meta);

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
        )
    ));
    $client->update($parameters);
}

function recupererMetaTags(&$html, &$meta)
{
    $trouve1 = $trouve2 = false;
    preg_match_all("|<meta[^>]+name=\"([^\"]*)\"[^>]+content=\"([^\"]*)\"[^>]*>|i", $html, $out, PREG_SET_ORDER);
    foreach($out as $tab ){
        foreach($tab as $jah){
            if($trouve1) {$meta['description']=$jah;$trouve1=false;}
            else if($trouve2) {$meta['keywords']=$jah; $trouve2=false;}
            if($jah=='description') {$trouve1=true;}
            else if($jah=='keywords') {$trouve2=true;}
        }
    }
}

function recupUrls(&$html, &$urls)
{
    if (preg_match_all("|<a[^>]+href=\"http://([^\"\?\/]+\.[a-z]{2,4}).*\"[^>]*>|i", $html, $datas)) {
        foreach ($datas[1] as $url) {
            $urls[] = $url;
        }
    }
}

function recupTitle(&$html, &$title)
{
    if (preg_match("|<title>(.+)</title>|i", $html, $tle)) {
        $title = $tle[1];
    }
}

function traitementUrlBDD(&$html, &$data)
{
    $urls = array();
    updateMeta($html, $data);
    recupUrls($html, $urls);
    envoyerEnBDD($urls);
}

//$leSite="http://www.meilleurs-sites.fr/";

$result = $client->count($params);

while ($result['count'] >= 1) {
    // TODO limit filter
    $json = '
    {
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

    foreach ($results['hits']['hits'] as $data) {
        $parameters = generateParams(array(
            'id' => $data['_id'],
            'body' => array('doc' => array('en_visite' => 1))
        ));
        $client->update($parameters);
    }
    foreach ($results['hits']['hits'] as $data) {
        $leSite = 'http://' . $data['_id'];
        $useragent = "Mozilla/5.0";
        $ch = curl_init($leSite);
        curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
        curl_setopt($ch, CURLOPT_REFERER, $leSite);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 7000);
        $html = curl_exec($ch);

        $parameters = generateParams(array('id' => $data['_id']));
        if ($html != false) {
            traitementUrlBDD($html, $data);
            $parameters['body'] = array('doc' => array('visite' => 1, 'en_visite' => 0));
            $client->update($parameters);
        } else {
            $client->delete($parameters);
        }
        curl_close($ch);
    }
    $result = $client->count($params);
}