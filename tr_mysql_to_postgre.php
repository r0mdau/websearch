<?php
    include('class.db.php');
    include('class.pg.php');
    $pg=new Postgre;
    $db=new Database;
    
    $nombre=$db->querySingle('SELECT COUNT(id) AS total FROM liste WHERE mongo=0');
    
    while($nombre->total >= 1){
        $obj = $db->queryObject('SELECT * FROM liste WHERE mongo=0 LIMIT 2000');	    
        foreach($obj as $p){
            $pg->query('INSERT INTO liste (url, hacked, visite, title, description, keywords, score)
                       VALUES (\''.$p->url.'\', \''.$p->hacked.'\', \''.$p->visite.'\', \''.substr(addslashes($p->title), 0, 250).'\',
                       \''.substr(addslashes($p->description), 0, 300).'\', \''.substr(addslashes($p->keywords), 0, 300).'\', \''.$p->score.'\')');
            $db->query('UPDATE liste SET mongo=1 WHERE id='.$p->id);
        }            
        $nombre=$db->querySingle('SELECT COUNT(id) AS total FROM liste WHERE mongo=0');
    }
    echo "\n".'Script de transfere DONE'."\n";
?>