<?php
    require_once('class.db.php');
    $DB=new Database;	

    $urls=$DB->queryArray("SELECT url FROM liste WHERE en_visite=0 ORDER BY id LIMIT 38768");
    foreach($urls as $uri){
        $uri=$uri['url'];
        $DB->query("UPDATE liste SET en_visite=1 WHERE url='$uri'");
    }
    foreach($urls as $url){
        $site=$url['url'];
        $leSite='http://'.$url['url'];                        
        
        $useragent = "Mozilla/5.0";
        $referer = $leSite;     
        $ch = curl_init($leSite);
        curl_setopt($ch, CURLOPT_HTTPGET, 1);                               
        curl_setopt($ch, CURLOPT_USERAGENT, $useragent);                
        curl_setopt($ch, CURLOPT_REFERER, $referer);                
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000);
        $result = curl_exec($ch);
        
        if($result==false)
            $DB->query("DELETE FROM liste WHERE url='$site'");
        else
            $DB->query("UPDATE liste SET en_visite=0 WHERE url='$site'");
        
        curl_close($ch);			
	}
?>