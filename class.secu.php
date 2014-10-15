<?php
    class Secu
    {
        private $db;
        private $hashi;
        
        public function __construct()
        {
            //On se connecte a mysql pour utiliser les fonctions qui lui sont spécifiques
            //ex : mysql_real_escape_string
            $this->db=new Database;
            $hashi= array('md2', 'md4', 'md5', 'sha1', 'sha256', 'sha384', 'sha512', 'ripemd128', 'ripemd256', 'ripemd320',
                            'whirlpool', 'snefru', 'gost', 'adler32', 'crc32', 'crc32b');
        }
        
        //Sécurise toutes les valeurs d'un tableau meme si celui-ci contient des tableaux
        private function parcourirTableauSecu($tableau, $fonction)
        {
            foreach($tableau as $val=>$key)
            {
                if(is_array($val))
                    $this->parcourirTableauSecu($val, $fonction);
                else
                    $tableau[$val]=$fonction($key);
            }
            return $tableau;
        }
        
        private function parcourirTableauHash($tableau, $fonction)
        {
            foreach($tableau as $val=>$key)
            {
                if(is_array($val))
                    $this->parcourirTableauHash($val, $fonction);
                else
                    $tableau[$val]=hash($fonction, $key);
            }
            return $tableau;
        }
        
        private function parcourirTableauParam($tableau, $fonction, $param)
        {
            foreach($tableau as $val=>$key)
            {
                if(is_array($val))
                    $this->parcourirTableauParam($val, $fonction, $param);
                else
                    $tableau[$val]=$fonction($key, $param);
            }
            return $tableau;
        }
        
        ///////////////////////////////////////////////////////////////////////////////////////
        // Travaux sur les chaînes de caractères
        ///////////////////////////////////////////////////////////////////////////////////////
        public function secuEntreeBDD($tab)
        {
            $tab=$this->secuMySql($tab);
            $tab=$this->secuCslashes($tab);
            return $tab;
        }
        
        public function secuCslashes($tableau)
        {
            if(is_array($tableau))
                $tableau=$this->parcourirTableauParam($tableau, 'addcslashes', '%_');
            else
                $tableau=addcslashes($tableau, '%_');
            return $tableau;
        }
        
        public function secuMySql($tableau)
        {
            if(is_array($tableau))
                $tableau=$this->parcourirTableauSecu($tableau, 'mysql_real_escape_string');
            else
                $tableau=mysql_real_escape_string($tableau);
            return $tableau;
        }
        
        //htmlspecialchars() est pratique pour éviter que des données fournies par les utilisateurs contiennent des balises HTML
        public function secuBalisesHtml($tab)
        {
            if(is_array($tab))
                $tab=$this->parcourirTableauSecu($tab, 'htmlspecialchars');
            else
                $tab=htmlspecialchars($tab);
            return $tab;
        }
        ///////////////////////////////////////////////////////////////////////////////////////
        // Expressions régulières.
        ///////////////////////////////////////////////////////////////////////////////////////
        public function regexMail($mail)
        {
            return preg_match("#^[a-z0-9._-]+@[a-z0-9._-]{2,}\.[a-z]{2,4}$#", $mail);
        }
        
        public function regexTelephone($tel)
        {
            return preg_match("#^0[1-9]([-. ]?[0-9]{2}){4}$#", $mail);
        }
        
        ///////////////////////////////////////////////////////////////////////////////////////
        // Cryptage
        ///////////////////////////////////////////////////////////////////////////////////////
        public function hashSingle($fct, $chaine)
        {
            if(in_array($fct, $hashi))
                return hash($fct, $chaine);
            else
                return "Ne peut pas être hashé !";
        }
        
        public function hashVar($fct, $tab)
        {
            // $hashi contient le tableau des algorithmes existants pour la fonction hash sous forme de chaines
            if(in_array($fct, $hashi))
            {
                if(is_array($tab))
                {
                    $tab=$this->parcourirTableauHash($tab, $fct);
                }
                else
                    $tab=hash($fct, $tab);
                return $tab;
            }
            else
                return "Fonction de hash non connue !";
        }                
    }
?>