<?php
require 'vendor/autoload.php';

$client = new Elasticsearch\Client(array('hosts' => array('192.168.1.200')));

$params = array(
    'index' => 'web',
    'type' => 'site',
    'ignore' => [400, 404]
);

function envoyerEnBDD(&$sites)
{
    global $client, $params;
    foreach($sites as $url){
        $rightNowParams = array(
            'index' => $params['index'],
            'type' => $params['type'],
            'id' => $url
        );

        $data = null;
        try {
            $data = $client->get($rightNowParams);
        } catch (Exception $e){

        }

        if(!isset($data['_source'])) {
            $rightNowParams['body'] = array(
                'visite' => 0,
                'en_visite' => 0,
                'score' => 0
            );
            echo $rightNowParams['id']."\n";
            $client->index($rightNowParams);
        } else {
            $site = $data['_source'];
            $client->delete($rightNowParams);

            $site['score']++;
            $rightNowParams['body'] = $site;
            $client->index($rightNowParams);
        }
    }
}

function _addslashes(&$t)
{
    foreach($t as $p => $k) {
        $t[$p] = addslashes($k);
    }
}

function updateMeta(&$html, &$data)
{
    global $client, $params; $meta = array(); $title = '';
    recupTitle($html, $title);
    recupererMetaTags($html, $meta);

    _addslashes($meta);
    $title = addslashes($title);

    $site = $data['_source'];
    $rightNowParams = array(
        'index' => $params['index'],
        'type' => $params['type'],
        'id' => $data['_id']
    );
    $client->delete($rightNowParams);

    $site['title'] = $title;
    $site['description'] = isset($meta['description']) ? $meta['description'] : '';
    $site['keywords'] = isset($meta['keywords']) ? $meta['keywords'] : '';
    $rightNowParams['body'] = $site;
    $client->index($rightNowParams);
}

function recupererMetaTags(&$html, &$meta)
{
    $trouve1 = $trouve2 = false;
    preg_match_all("|<meta[^>]+name=\"([^\"]*)\"[^>]+content=\"([^\"]*)\"[^>]*>|i", $html, $out, PREG_SET_ORDER);
    foreach($out as $tab ){
        foreach($tab as $jah){
            if($trouve1) {$meta['description']=$jah;$trouve1=false;}
            else if($trouve2) {$meta['keywords']=$jah; $trouve2=false;}
            if($jah=='description') $trouve1=true;
            else if($jah=='keywords') $trouve2=true;
        }
    }
}

function recupUrls(&$html, &$urls)
{
    if(preg_match_all("|<a[^>]+href=\"http://([^\"\?\/]+\.[a-z]{2,4}).*\"[^>]*>|i", $html, $datas)) {
        foreach ($datas[1] as $url) {
            $urls[] = $url;
        }
    }
}

function recupTitle(&$html, &$title)
{
    if(preg_match("|<title>(.+)</title>|i", $html, $tle)) {
        $title = $tle[1];
    }
}

function traitementUrlBDD(&$html, &$data)
{
    $urls = array();
    //updateMeta($html, $data);
    recupUrls($html, $urls);
    envoyerEnBDD($urls);
}

//$leSite="http://www.meilleurs-sites.fr/";

$result = $client->count($params);

while($result['count'] >= 1)
{
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
    $results = $client->search(array(
        'index' => $params['index'],
        'type' => $params['type'],
        'body' => $json
    ));

    foreach($results['hits']['hits'] as $data){
        $site = $data['_source'];
        $client->delete(array(
            'index' => $params['index'],
            'type' => $params['type'],
            'id' => $data['_id']
        ));

        $site['en_visite'] = 1;
        $client->index(array(
            'index' => $params['index'],
            'type' => $params['type'],
            'id' => $data['_id'],
            'body' => $site
        ));
    }
    foreach($results['hits']['hits'] as $data) {
        $leSite = 'http://'.$data['_id'];
        $useragent = "Mozilla/5.0";
        $ch = curl_init($leSite);
        curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
        curl_setopt($ch, CURLOPT_REFERER, $leSite);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 7000);
        $html = curl_exec($ch);

        $rightNowParams = array(
            'index' => $params['index'],
            'type' => $params['type'],
            'id' => $data['_id']
        );

        if($html != false){
            traitementUrlBDD($html, $data);
            $client->delete($rightNowParams);

            $site = $data['_source'];
            $site['visite'] = 1;
            $site['en_visite'] = 0;
            $rightNowParams['body'] = $site;
            $client->index($rightNowParams);
        }else{
            $client->delete($rightNowParams);
        }
        curl_close($ch);
    }
    $result = $client->count($params);
}