<?php
    $mongo= new Mongo;
    include('class.db.php');
    
    $db=new Database;
    
    $nombre=$db->querySingle("SELECT COUNT(id) AS total FROM liste WHERE mongo=0");
    
	while($nombre->total >= 1)
	{
	    $obj = $db->queryObject('SELECT id, url, visite, title, description, keywords, score FROM liste WHERE hacked=0 AND mongo=0 LIMIT 20000');
	    
	    foreach($obj as $prout => $key){
		$action= array(
			"url" => $key->url,
			"hacked" => 0,
			"visite" =>  (int)$key->visite,
			"en_visite" =>  0,
			"title" =>  (isset($key->title) ? $key->title : ''),
			"description" =>  (isset($key->description) ? $key->description : ''),
			"keywords" =>  (isset($key->description) ? $key->description : ''),
			"score" =>  (isset($key->score) ? (int)$key->score : 0)
		);
            $mongo->Dauby->liste->insert($action);
            $db->query('UPDATE liste SET mongo=1 WHERE id=\''.$key->id.'\'');
        }
        $nombre=$db->querySingle("SELECT COUNT(id) AS total FROM liste WHERE mongo=0");
    }
?>