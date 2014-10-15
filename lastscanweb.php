<?php
    include('class.db.php');
    $DB=new Database;

    function UrlExist(&$url)
    {
        global $DB;
        $base=$DB->querySingle('SELECT id FROM liste WHERE url=\''.$url.'\'');
        return (!empty($base));
    }
    
    function envoyerEnBDD(&$result)
    {
        global $DB;
        foreach($result as $tab){
            if(!UrlExist($tab)){
                    //echo $tab."\n\n";
                    $DB->query('INSERT INTO liste (url) VALUES(\''.$tab.'\')');
            }else
                $DB->query('UPDATE liste SET score = (score + 1) WHERE url=\''.$tab.'\'');
        }
    }
    
    function _addslashes(&$t)
    {
        foreach($t as $p => $k)
            $t[$p]=addslashes($k);
    }
    
    function updateMeta(&$site, &$head, &$id)
    {
        global $DB; global $secu; $tete=array(); $title='';
        recupTitle($head, $title);
        recupererMetaTags($head, $tete);
        
        _addslashes($tete); //echappement des apostrophes
        $title=addslashes($title);
        $DB->query('UPDATE liste SET'
                    .(!empty($title) ? ' title=\''.$title.'\'' : '')
                    .((isset($tete['description']) AND !empty($title) OR !empty($title) AND isset($tete['keywords'])) ? ', ' : '')
                    .(isset($tete['description']) ? ' description=\''.$tete['description'].'\'' : '')
                    .((isset($tete['description']) AND isset($tete['keywords'])) ? ', ' : '')
                    .(isset($tete['keywords']) ? ' keywords=\''.$tete['keywords'].'\'' : '').' 
                    WHERE id='.$id);
    }
    
    function recupererMetaTags(&$head, &$tete)
    {
        $trouve1=false;$trouve2=false;
        preg_match_all("|<meta[^>]+name=\"([^\"]*)\"[^>]+content=\"([^\"]*)\"[^>]*>|i", $head, $out, PREG_SET_ORDER);
        foreach($out as $tab ){
            foreach($tab as $jah){
                if($trouve1) {$tete['description']=$jah;$trouve1=false;}
                else if($trouve2) {$tete['keywords']=$jah; $trouve2=false;}
                if($jah=='description') $trouve1=true;
                else if($jah=='keywords') $trouve2=true;
            }
        }
    }
    
    function recupUrls(&$result, &$tabl)
    {
        if(preg_match_all("|<a[^>]+href=\"http://([^\"\?\/]+\.[a-z]{2,4}).*\"[^>]*>|i", $result, $tablo))
            foreach($tablo[1] as $cle)
                $tabl[]=$cle;
    }
    
    function recupTitle(&$head, &$title)
    {
        if(preg_match("|<title>(.+)</title>|i", $head, $tle))
            $title=$tle[1];
    }
    
    function traitementUrlBDD(&$result, &$actif, &$id)
    {
        $tabl=array();
        updateMeta($actif, $result, $id);
        recupUrls($result, $tabl);
        envoyerEnBDD($tabl);
    }        	

    //$leSite="http://www.meilleurs-sites.fr/";

    $nombre=$DB->querySingle('SELECT COUNT(id) total FROM liste WHERE visite=0 AND en_visite=0');

    while($nombre->total >= 1)
    {
        $urls=$DB->queryArray('SELECT url, id FROM liste WHERE visite=0 AND en_visite=0 ORDER BY id LIMIT 200');
        foreach($urls as $uri){
            $DB->query('UPDATE liste SET en_visite=1 WHERE id='.$uri['id']);
        }
        foreach($urls as $url){
            $leSite='http://'.$url['url'];
            $useragent = "Mozilla/5.0";   
            $ch = curl_init($leSite);                             
            curl_setopt($ch, CURLOPT_USERAGENT, $useragent);                
            curl_setopt($ch, CURLOPT_REFERER, $leSite);                
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 7000);
            $result = curl_exec($ch);
            if($result!=false){                    
                    traitementUrlBDD($result, $url['url'], $url['id']);
                    $DB->query('UPDATE liste SET visite=1, en_visite=0 WHERE id='.$url['id']);
            }else{
                    $DB->query('DELETE FROM liste WHERE id='.$url['id']);
            }
            curl_close($ch);
        }
        $nombre=$DB->querySingle('SELECT COUNT(id) total FROM liste WHERE visite=0 AND en_visite=0');
        //echo '--'."\n\n";
    }
    $DB->dbclose();
?>
