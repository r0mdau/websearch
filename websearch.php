<?php
define('_RETRY_CONFLICT_', 100);
ini_set('user_agent', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.79 Safari/537.36');

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
        if ($html = @file_get_contents('http://' . $site['_id'])) {

            updateURIMetaTags($html, $site['_id']);

            saveURI(catchURIs($html));

            $client->update(generateParams(array(
                'id' => $site['_id'],
                'retry_on_conflict' => _RETRY_CONFLICT_,
                'body' => array('doc' => array('visite' => 1, 'en_visite' => 0)),
            )));
        } else {
            $client->delete(generateParams(array(
                'id' => $site['_id']
            )));
            continue;
        }
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

function saveURI($urls)
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

function updateURIMetaTags($html, $siteId)
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
    if (preg_match_all("|<meta[^>]+name=\"([^\"]*)\"[^>]+content=\"([^\"]*)\"[^>]*>|iU", $html, $matchs, PREG_SET_ORDER)) {
        foreach ($matchs as $match) {
            if ($match[1] == 'description') {
                $meta['description'] = addslashes($match[2]);
            } elseif ($match[1] == 'keywords') {
                $meta['keywords'] = addslashes($match[2]);
            }
        }
    }
    return $meta;
}

function catchURIs($html)
{
    $urls = array();
    if (preg_match_all("|<a[^>]+href=\"http://([^\"\?\/]+\.[a-z]{2,4}).*\"[^>]*>|i", $html, $matchs)) {
        foreach ($matchs[1] as $url) {
            $urls[$url] = $url;
        }
    }
    return $urls;
}

function catchTitle($html)
{
    if (preg_match("|<title>(.+)</title>|iU", $html, $match)) {
        return addslashes($match[1]);
    }
    return '';
}