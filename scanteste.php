<?php
    include('class.db.php');
    $DB=new Database;

    
    $nombre=$DB->querySingle("SELECT COUNT(id) AS total FROM liste WHERE visite=0 AND en_visite=0");

    while($nombre->total >= 1)
    {
        $urls=$DB->queryArray('SELECT url FROM liste WHERE visite=0 AND en_visite=0 LIMIT 200');
        foreach($urls as $uri){
            $DB->query('UPDATE liste SET en_visite=1 WHERE url=\''.$uri['url'].'\'');
            echo $uri['url']."\n\n";
        }
        $nombre=$DB->querySingle("SELECT COUNT(id) AS total FROM liste WHERE visite=0 AND en_visite=0");
    }
    $DB->dbclose();
?>