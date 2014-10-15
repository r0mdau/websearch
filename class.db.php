<?php
    class Database
    {
        private $db;
        
        public function __construct(){
            $this->db=mysql_connect('localhost', 'root', 'rom0991');
            mysql_select_db('goobis', $this->db);
            mysql_query("SET NAMES UTF8");
        }
        
        public function queryObject($sql){
            if ($res=mysql_query($sql)){
                $row=array();
                while($ligne=mysql_fetch_object($res)){
                    $row[]=$ligne;					
                }
                mysql_free_result($res); // permet de libérer les ressources rattachées à la requête
                return $row;
            }
        }
        
        public function queryArray($sql){
            if ($res=mysql_query($sql)){
                $row=array();
                while($ligne=mysql_fetch_assoc($res)){
                    $row[]=$ligne;					
                }
                mysql_free_result($res); // permet de libérer les ressources rattachées à la requête
                return $row;
            }
        }
        
        public function querySingle($sql){
            if ($res=mysql_query($sql)){
                $row=mysql_fetch_object($res);
                mysql_free_result($res); 
                return $row;
            }
        }
        
        public function query($sql){
            return mysql_query($sql);
        }
        
        public function dbclose()
        {
            mysql_close($this->db);
        }
    }
?>
