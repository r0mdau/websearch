<?php
define('_RETRY_CONFLICT_', 100);

require 'vendor/autoload.php';

//$leSite="http://www.meilleurs-sites.fr/"; YEAH HISTORY
$client = new Elasticsearch\Client(array('hosts' => array('192.168.1.200')));
$result = $client->count(generateParams());

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
        $client->update(generateParams(array(
            'id' => $site['_id'],
            'body' => array('doc' => array('en_visite' => 1)),
            'retry_on_conflict' => _RETRY_CONFLICT_
        )));
    }

    foreach ($results['hits']['hits'] as $site) {
        $leSite = 'http://' . $site['_id'];
        $ch = curl_init($leSite);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
        curl_setopt($ch, CURLOPT_REFERER, $leSite);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 7000);
        $html = curl_exec($ch);

        if ($html == false) {
            $client->delete(generateParams(array(
                'id' => $site['_id']
            )));
            continue;
        }

        updateCurrentUrlMetaTags($html, $site['_id']);

        save(catchUrls($html));

        $client->update(generateParams(array(
            'id' => $site['_id'],
            'retry_on_conflict' => _RETRY_CONFLICT_,
            'body' => array('doc' => array('visite' => 1, 'en_visite' => 0)),
        )));
        curl_close($ch);
    }
    $result = $client->count(generateParams());
}

function generateParams($parameters = array())
{
    return array_merge(array(
        'index' => 'web',
        'type' => 'site',
        'ignore' => [400, 404]
    ), $parameters);
}

function save($urls)
{
    global $client;
    foreach ($urls as $url) {
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

function updateCurrentUrlMetaTags($html, $siteId)
{
    global $client;
    $meta = catchMetaTags($html);
    $client->update(generateParams(array(
        'id' => $siteId,
        'body' => array(
            'doc' => array(
                'title' => catchTitle($html),
                'description' => (isset($meta['description']) ? $meta['description'] : ''),
                'keywords' => (isset($meta['keywords']) ? $meta['keywords'] : '')
            )
        ),
        'retry_on_conflict' => _RETRY_CONFLICT_
    )));
}

function catchMetaTags($html)
{
    $meta = array();
    preg_match_all("|<meta[^>]+name=\"([^\"]*)\"[^>]+content=\"([^\"]*)\"[^>]*>|i", $html, $matchs, PREG_SET_ORDER);
    foreach ($matchs as $match) {
        foreach ($match as $key => $attr) {
            if ($attr == 'description') {
                $meta['description'] = addslashes($match[$key + 1]);
            } else if ($attr == 'keywords') {
                $meta['keywords'] = addslashes($match[$key + 1]);
            }
        }
    }
    return $meta;
}

function catchUrls($html)
{
    $urls = array();
    if (preg_match_all("|<a[^>]+href=\"http://([^\"\?\/]+\.[a-z]{2,4}).*\"[^>]*>|i", $html, $matchs)) {
        foreach ($matchs[1] as $url) {
            $urls[] = $url;
        }
    }
    return $urls;
}

function catchTitle($html)
{
    if (preg_match("|<title>(.+)</title>|i", $html, $match)) {
        return addslashes($match[1]);
    }
    return '';
}