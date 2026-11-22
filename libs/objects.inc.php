<?php
// Compatibilità dei vecchi oggetti del gioco, con persistenza PDO unica.
require_once __DIR__ . '/kernel.inc.php';
require_once __DIR__ . '/libpg.inc.php';
const SQL_WHERE = " WHERE ";
class AlphanoBase {
    public $className;
    public $table;
    public $key = 'id';
    public $dati = [];
    public function __construct($id=null) { if ($id !== null) {$this->load($id);} }
    protected function identifier($name) {
        if (!is_string($name) || !preg_match('/^[a-zA-Z_]\w*$/D', $name)) {throw new InvalidArgumentException('Identificatore non valido');}
        return '`' . $name . '`';
    }
    protected function condition($where) {
        if (is_array($where) && $where) {
            $parts=[]; foreach ($where as $key=>$value) {$parts[]=$this->identifier($key).' = ?';}
            return [implode(' AND ',$parts), array_values($where)];
        }
        // Sintassi storica dei chiamanti interni: confronti semplici, convertiti in parametri.
        if (is_string($where) && preg_match('/^([a-zA-Z_]\w*)\s*=\s*(\d+)$/D', trim($where), $m)) {return [$this->identifier($m[1]).' = ?',[(int)$m[2]]];}
        throw new InvalidArgumentException('Usare criteri di ricerca espliciti');
    }
    public function load($id,$key=null) { $rows=ConnectDB()->query('SELECT * FROM '.$this->identifier($this->table).SQL_WHERE.$this->identifier($key??$this->key).' = ? LIMIT 1',[$id]);$this->setArray($rows[0]??[]);return (bool)$rows; }
    public function loadList($ids) { $out=[];foreach ((array)$ids as $id) { $item=new $this->className($id);if($item->dati){$out[]=$item;} }return $out; }
    public function getKeyList() { return array_column(ConnectDB()->query('SELECT '.$this->identifier($this->key).' FROM '.$this->identifier($this->table)),$this->key); }
    public function search($where) { [$sql,$params]=$this->condition($where);$out=[];foreach(ConnectDB()->query('SELECT * FROM '.$this->identifier($this->table).SQL_WHERE.$sql,$params) as $row){$item=new $this->className();$item->setArray($row);$out[]=$item;}return $out; }
    public function create($data=null) { $data=$data??$this->dati;if(!$data){return false;}$keys=array_map([$this,'identifier'],array_keys($data));ConnectDB()->execute('INSERT INTO '.$this->identifier($this->table).' ('.implode(',',$keys).') VALUES ('.implode(',',array_fill(0,count($data),'?')).')',array_values($data));$data[$this->key]=$data[$this->key]??(int)ConnectDB()->lastInsertId();$this->setArray($data);return true; }
    public function delete($id=null) { return ConnectDB()->execute('DELETE FROM '.$this->identifier($this->table).SQL_WHERE.$this->identifier($this->key).' = ?',[$id??$this->getKey()])>0; }
    public function deleteQuery($where) { [$sql,$params]=$this->condition($where);return ConnectDB()->execute('DELETE FROM '.$this->identifier($this->table).SQL_WHERE.$sql,$params); }
    public function update($id=null) { return $this->updateQuery($this->dati,[$this->key=>$id??$this->getKey()]); }
    public function updateQuery($values,$where=null) { if(!$values){return 0;}[$sql,$params]=$this->condition($where);$set=[];foreach($values as $key=>$value){$set[]=$this->identifier($key).' = ?';}return ConnectDB()->execute('UPDATE '.$this->identifier($this->table).' SET '.implode(',',$set).SQL_WHERE.$sql,[...array_values($values),...$params]); }
    public function get($key) { return $this->dati[$key]??null; }
    public function getKey() { return $this->get($this->key); }
    public function getArray() { return $this->dati; }
    public function set($key,$value) { $this->identifier($key);$this->dati[$key]=$value; }
    public function setArray($values) { $this->dati=is_array($values)?$values:[]; }
    public function getQuery() { $keys=array_map([$this,'identifier'],array_keys($this->dati));$values=array_map(fn($v)=>$v===null?'NULL':ConnectDB()->quote((string)$v),array_values($this->dati));return 'INSERT INTO '.$this->identifier($this->table).' ('.implode(',',$keys).') VALUES ('.implode(',',$values).')'; }
}
    class Accademia extends AlphanoBase {
        public $className="Accademia";
        public $table="accademia";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Araldiche extends AlphanoBase {
        public $className="Araldiche";
        public $table="araldiche";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Armadio extends AlphanoBase {
        public $className="Armadio";
        public $table="armadio";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Assemblea extends AlphanoBase {
        public $className="Assemblea";
        public $table="assemblea";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Avvisi extends AlphanoBase {
        public $className="Avvisi";
        public $table="avvisi";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Bacheche extends AlphanoBase {
        public $className="Bacheche";
        public $table="bacheche";

        public function __construct($id=null) {
            parent::__construct($id);
        }

        public function threadsInfo($id=null) {
            $id = $id ?: $this->get('bacheca');
            $rows = ConnectDB()->query('SELECT replyid, MAX(data) AS lastreply FROM bacheche WHERE replyid>0 AND bacheca=? GROUP BY replyid', [(int)$id]);
            return array_column($rows, 'lastreply', 'replyid');
        }

    }

    class Bug extends AlphanoBase {
        public $className="Bug";
        public $table="bug";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Candidati extends AlphanoBase {
        public $className="Candidati";
        public $table="candidati";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class CaricheCorporazioni extends AlphanoBase {
        public $className="CaricheCorporazioni";
        public $table="carichecorporazioni";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class CaricheOrganizzazioni extends AlphanoBase {
        public $className="CaricheOrganizzazioni";
        public $table="caricheorganizzazioni";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Chat extends AlphanoBase {
        public $className="Chat";
        public $table="chat";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class City extends AlphanoBase {
        public $className="City";
        public $table="city";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Corporazioni extends AlphanoBase {
        public $className="Corporazioni";
        public $table="corporazioni";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Descrizioni extends AlphanoBase {
        public $className="Descrizioni";
        public $table="descrizioni";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Dimore extends AlphanoBase {
        public $className="Dimore";
        public $table="dimore";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Diritti extends AlphanoBase {
        public $className="Diritti";
        public $table="diritti";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Discussioni extends AlphanoBase {
        public $className="Discussioni";
        public $table="discussioni";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Elezioni extends AlphanoBase {
        public $className="Elezioni";
        public $table="elezioni";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Equip extends AlphanoBase {
        public $className="Equip";
        public $table="equip";

        public function __construct($id=null) {
            parent::__construct($id);
        }

        public function delete($id=null) {
            if(parent::delete($id)) {
                $filename=htmlroot."/uploads/equipment/".$this->getKey();
                if(file_exists($filename)) {unlink($filename);}
                $filename=htmlroot."/uploads/equipment-pending/".$this->getKey();
                if(file_exists($filename)) {unlink($filename);}
                return true;
            }
            return false;
        }
    }

    class EquipCorp extends AlphanoBase {
        public $className="EquipCorp";
        public $table="equipcorp";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class EquipPG extends AlphanoBase {
        public $className="EquipPG";
        public $table="equippg";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Etnie extends AlphanoBase {
        public $className="Etnie";
        public $table="etnie";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Giocate extends AlphanoBase {
        public $className="Giocate";
        public $table="giocate";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Indirizzi extends AlphanoBase {
        public $className="Indirizzi";
        public $table="indirizzi";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Lavori extends AlphanoBase {
        public $className="Lavori";
        public $table="lavori";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class LogsAdminPG extends AlphanoBase {
        public $className="LogsAdminPG";
        public $table="logs_adminpg";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class LogsDeleteMail extends AlphanoBase {
        public $className="LogsDeleteMail";
        public $table="logs_deletemail";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class LogsDeleteUser extends AlphanoBase {
        public $className="LogsDeleteUser";
        public $table="logs_deleteuser";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class LogsDimore extends AlphanoBase {
        public $className="LogsDimore";
        public $table="logs_dimore";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class LogsEquip extends AlphanoBase {
        public $className="LogsEquip";
        public $table="logs_equip";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class LogsFlag extends AlphanoBase {
        public $className="LogsFlag";
        public $table="logs_flag";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class LogsIngressi extends AlphanoBase {
        public $className="LogsIngressi";
        public $table="logs_ingressi";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class LogsMeteo extends AlphanoBase {
        public $className="LogsMeteo";
        public $table="logs_meteo";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class LogsProfili extends AlphanoBase {
        public $className="LogsProfili";
        public $table="logs_profili";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class LogsRegistrazione extends AlphanoBase {
        public $className="LogsRegistrazione";
        public $table="logs_registrazione";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class LogsStatistica extends AlphanoBase {
        public $className="LogsStatistica";
        public $table="logs_statistica";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class LogsVisite extends AlphanoBase {
        public $className="LogsVisite";
        public $table="logs_visite";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Luoghi extends AlphanoBase {
        public $className="Luoghi";
        public $table="luoghi";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Missive extends AlphanoBase {
        public $className="Missive";
        public $table="mail";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Meteo extends AlphanoBase {
        public $className="Meteo";
        public $table="meteo";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class NewEquip extends AlphanoBase {
        public $className="NewEquip";
        public $table="newequip";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class NomiVietati extends AlphanoBase {
        public $className="NomiVietati";
        public $table="nomivietati";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Organizzazioni extends AlphanoBase {
        public $className="Organizzazioni";
        public $table="organizzazioni";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class PareriSessioni extends AlphanoBase {
        public $className="PareriSessioni";
        public $table="parerisessioni";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Prestiti extends AlphanoBase {
        public $className="Prestiti";
        public $table="prestiti";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class PrestitiPG extends AlphanoBase {
        public $className="PrestitiPG";
        public $table="prestitipg";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class ProduzioniCorporazioni extends AlphanoBase {
        public $className="ProduzioniCorporazioni";
        public $table="produzionicorporazioni";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class RamiAraldiche extends AlphanoBase {
        public $className="RamiAraldiche";
        public $table="ramiaraldiche";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class RamiCorporazioni extends AlphanoBase {
        public $className="RamiCorporazioni";
        public $table="ramicorporazioni";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class RamiOrganizzazioni extends AlphanoBase {
        public $className="RamiOrganizzazioni";
        public $table="ramiorganizzazioni";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Razze extends AlphanoBase {
        public $className="Razze";
        public $table="razze";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class RecuperoPG extends AlphanoBase {
        public $className="RecuperoPG";
        public $table="recuperopg";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Registrazioni extends AlphanoBase {
        public $className="Registrazioni";
        public $table="registrazioni";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class RegistrazioniChat extends AlphanoBase {
        public $className="RegistrazioniChat";
        public $table="registrazionichat";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Regole extends AlphanoBase {
        public $className="Regole";
        public $table="regole";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Richieste extends AlphanoBase {
        public $className="Richieste";
        public $table="richieste";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class RiSondaggi extends AlphanoBase {
        public $className="RiSondaggi";
        public $table="risondaggi";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Sessioni extends AlphanoBase {
        public $className="Sessioni";
        public $table="sessioni";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class SezioniRegole extends AlphanoBase {
        public $className="SezioniRegole";
        public $table="sezioniregole";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Sondaggi extends AlphanoBase {
        public $className="Sondaggi";
        public $table="sondaggi";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class StoricoBacheche extends AlphanoBase {
        public $className="StoricoBacheche";
        public $table="storicobacheche";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class SubTipi extends AlphanoBase {
        public $className="SubTipi";
        public $table="subtipi";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Tipo extends AlphanoBase {
        public $className="Tipo";
        public $table="tipo";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Trasferimenti extends AlphanoBase {
        public $className="Trasferimenti";
        public $table="trasferimenti";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Umori extends AlphanoBase {
        public $className="Umori";
        public $table="umori";

        public function __construct($id=null) {
            parent::__construct($id);
        }
    }

    class Utenti extends AlphanoBase {
        public $className="Utenti";
        public $table="utenti";

        public function __construct($id=null) {
            parent::__construct($id);
        }

        public function ensureAdmin() {
            if ($this->get("admin") && getAdminKey() != admin_passwd) {
                header("location: /slogin.php");
            }
        }

        public function sendDelAdvice() {
            require_once __DIR__ . '/sendmail.inc.php'; sendDelAdvice($this->dati);
        }

        public function getEquip() {
            return (new EquipPG())->search(['idpg' => $this->get('id')]);
        }

        public function getArmadio() {
            return (new Armadio())->search(['idpg' => $this->get('id')]);
        }

        public function togliAraldica() {
            $this->set("araldica",1);//imposto a Viandante
            $this->set("sindaco",0);
            $this->update();
        }

        public function togliOrganizzazione() {
            $this->set("organizzazione",0);
            $this->set("caricaorganizzazione",0);
            $this->set("ramoorganizzazione",0);
            $this->set("adminorganizzazione",0);
            $this->set("adminstatosociale",0);
            $this->set("adminferite",0);
            $this->set("adminmorti",0);
            $this->update();
        }

        public function togliCorporazione() {
            $this->set("corporazione",0);
            $this->set("caricacorporazione",0);
            $this->set("ramocorporazione",0);
            $this->set("admincorporazione",0);
            $this->set("admincatalogo",0);
            $this->set("admintrasfcorp",0);
            $this->update();
        }
    }

    //classe per istanziare tutte le classi derivate da AlphanoBase
    class AlphanoProxy {
        private $classes = array();
        /*l'array delle classi e' attualmente inutile visto che l'istanziamento e' on demand
        private $classNames = array(
        "Accademia",
        "Araldiche",
        "Armadio",
        "Assemblea",
        "Avvisi",
        "Bacheche",
        "Bug",
        "Candidati",
        "CaricheCorporazioni",
        "CaricheOrganizzazioni",
        "Chat",
        "City",
        "Corporazioni",
        "Descrizioni",
        "Dimore",
        "Diritti",
        "Discussioni",
        "Elezioni",
        "Equip",
        "EquipCorp",
        "EquipPG",
        "Etnie",
        "Giocate",
        "Indirizzi",
        "Lavori",
        "LogsAdminPG",
        "LogsDeleteMail",
        "LogsDeleteUser",
        "LogsDimore",
        "LogsEquip",
        "LogsFlag",
        "LogsIngressi",
        "LogsMeteo",
        "LogsProfili",
        "LogsRegistrazione",
        "LogsStatistica",
        "LogsVisite",
        "Luoghi",
        "Missive",
        "Meteo",
        "NewEquip",
        "NomiVietati",
        "Organizzazioni",
        "PareriSessioni",
        "Prestiti",
        "PrestitiPG",
        "ProduzioniCorporazioni",
        "RamiAraldiche",
        "RamiCorporazioni",
        "RamiOrganizzazioni",
        "Razze",
        "RecuperoPG",
        "Registrazioni",
        "RegistrazioniChat",
        "Regole",
        "Richieste",
        "RiSondaggi",
        "Sessioni",
        "SezioniRegole",
        "Sondaggi",
        "StoricoBacheche",
        "SubTipi",
        "Tipo",
        "Trasferimenti",
        "Umori",
        "Utenti",
        );
        */


        public function __construct() {
            //non fa nulla poiche' l'istanziamento e' on demand
        }

        public function callClass($class, $function, $params=null) {
            if (!is_subclass_of($class, AlphanoBase::class)) {throw new InvalidArgumentException('Classe non ammessa');}
            if(!isset($this->classes[$class])) {
                $this->classes[$class]= new $class();//istanziamento on demand
            }
            if(!is_array($params)) {
                $params=array(0 => $params);
            }
            return call_user_func_array(array($this->classes[$class], $function), $params);
        }

        public static function call($class, $function, $params=null) {
            if(!isset($GLOBALS["_AlphanoProxy"])) {
                $GLOBALS["_AlphanoProxy"]= new AlphanoProxy();
            }
            return $GLOBALS["_AlphanoProxy"]->callClass($class, $function, $params);
        }
    }
