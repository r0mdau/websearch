<?php
    class Postgre
    {
        private $db;
        
        public function __construct(){
            $this->db=pg_pconnect('host=localhost port=5432 dbname=dauby user=romain password=rom0991');
        }
        
        public function queryObject($sql){
            if ($res=pg_query($sql)){
                $row=array();
                while($ligne=pg_fetch_object($res)){
                    $row[]=$ligne;					
                }
                pg_free_result($res); // permet de libérer les ressources rattachées à la requête
                return $row;
            }
        }
        
        public function queryArray($sql){
            if ($res=pg_query($sql)){
                $row=array();
                while($ligne=pg_fetch_assoc($res)){
                    $row[]=$ligne;					
                }
                pg_free_result($res); // permet de libérer les ressources rattachées à la requête
                return $row;
            }
        }
        
        public function querySingle($sql){
            if ($res=pg_query($sql)){
                $row=pg_fetch_object($res);
                pg_free_result($res); 
                return $row;
            }
        }
        
        public function query($sql){
            return pg_query($this->db, $sql);
        }
        
        public function dbclose()
        {
            pg_close($this->db);
        }
    }
?>
