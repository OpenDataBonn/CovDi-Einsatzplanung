<?php
namespace base;
/* 
Klasse zur Verwaltung der Daten über OData. 
Handhabt den Kontakt zum OData-Server und bereitet den Rücklauf auf.
*/

class OData {
    protected $basics;
    
    private $serviceRoot = '';
    private $serviceRootDienste = '';
    private $serviceRootEp = '';
    private $serviceRootEpDienste = '';
    
    public $rights = '';
    private $user = '';
    
    function __construct() {
        //basics müssen als Global definiert werden
        global $basics;
        $this->basics =& $basics;
        
        $this->serviceRoot = $basics['serviceRoot'];
        $this->serviceRootDienste = $basics['serviceRootDienste'];
        $this->serviceRootEp = $basics['serviceRootEp'];
        $this->serviceRootEpDienste = $basics['serviceRootEpDienste'];
        
        if (isset($_SESSION[$basics['session']]['user'])){
            $this->rights = $_SESSION[$basics['session']]['user']['rechte'];          
            $this->user = $_SESSION[$basics['session']]['user'];          
        } 
        //var_dump($this->user);
    }
    
    function deleteNM($data){        
        $delete = false;
        //Alle Wünsche der Nutzermeldung entfernen
        $filter = '?$expand=Wunsch';
        $url = $this->serviceRootEp.'/NutzerMeldung('.$data['nmId'].')'.$filter;
        $response = $this->fetchWithUri($url);
        $deleted = 0;
        $to_delete = count($response->Wunsch->results);
        var_dump($to_delete);
        foreach ($response->Wunsch->results as $wunsch){
            $delete_w = \Httpful\Request::delete($this->serviceRootEp.'/Wunsch('.$wunsch->LID.')')
            ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
            ->sendsJson()
            ->send();
            var_dump($delete_w);
            if (!$delete_w->hasErrors()){
                $deleted ++;
            }
        }
        var_dump($deleted);
        if ($deleted == $to_delete){
            //Nutzermeldung Entfernen
            $delete = \Httpful\Request::delete($this->serviceRootEp.'/NutzerMeldung('.$data['nmId'].')')
                ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                ->sendsJson()
                ->send(); 
        }
        return $delete;        
    }
    
    function deleteP($data){
        $this->unsetPlanForWunsch($data['planId']);
        
        $delete = false;
        $delete = \Httpful\Request::delete($this->serviceRootEp.'/Plan('.$data['planId'].')')
            ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
            ->sendsJson()
            ->send();   
        return $delete;        
    }
    
    function unsetPlanForWunsch($planId){
        $values = array();
        $values['REF_415E0036'] = null; 
        $url = "";
        if ($planId){
            //erst alle Wünsche suchen
            //Plan: REF_415E0036
            $filter = 'REF_415E0036%20eq%20'.$planId;
            $url = $this->serviceRootEp.'/Wunsch?$filter='.$filter;
            $response = $this->fetchWithUri($url);
            foreach ($response->results as $wunsch){
                //dann einzeln abarbeiten mit den ids
                $url = $this->serviceRootEp.'/Wunsch('.$wunsch->LID.')';
                echo $url;
                $update = \Httpful\Request::put($url)
                    ->body(json_encode($values))
                    ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                    ->sendsJson()
                    ->send();     
            }
            return true;
        } else {
            return false;
        }
    } 
    
    function addNewP($data){
        if ($data['wunschId'] > 0){
            //Wunsch        
            $url = $this->serviceRootEp.'/Wunsch('.$data['wunschId'].')?$expand=Nutzer,Schicht,NutzerMeldung';
            $wunsch = $this->fetchWithUri($url);
        } else {
            $wunsch = false;
        }
        //Schicht
        $filter = 'STR_TITEL%20eq%20%27'.$data['schicht'].'%27';
        $url = $this->serviceRootEp.'/Schicht?$filter='.$filter;
        $schichten = $this->fetchWithUri($url);
        $schicht = $schichten->results[0];
        
        //neuer Plan
        //Datum DT_DATUM, Nutzer REF_2B957519, Schicht REF_5522FF6E, Taetigkeit STR_TAETIGKEIT, NutzerMeldung REF_FC80F88B
        $vals = array();
        if ($wunsch){
            $vals['DT_DATUM'] = $wunsch->DT_DATUM;
            $vals['REF_2B957519'] = $wunsch->Nutzer->LID;
            $vals['REF_FC80F88B'] = $wunsch->NutzerMeldung->LID;
        } else {
            date_default_timezone_set('UTC');            
            $vals['DT_DATUM'] = "/Date(".$data['ts']."000+0000)/";
            $vals['REF_2B957519'] = $data['nutzer'];
        }
        $vals['REF_5522FF6E'] = $schicht->LID; //Schicht muss aus LI übernommen werden
        $vals['STR_TAETIGKEIT'] = $data['taetigkeit'];//Taetigkeit muss aus LI übernommen werden        
        
        $update = \Httpful\Request::post($this->serviceRootEp.'/Plan')
            ->body(json_encode($vals))
            ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
            ->sendsJson()
            ->send();   
        $new_id = str_replace('Plan(','',$update->body->link['href']);
        $new_id = str_replace(')','',$new_id);
        if ($wunsch) $this->setPlanForWunsch($new_id, $wunsch->LID);
        return $update;        
    }
    
    function setPlanForWunsch($planId, $wunschId){
        $values = array();
        $values['REF_415E0036'] = $planId; 
        $url = "";
        if ($wunschId){
            $url = $this->serviceRootEp.'/Wunsch('.$wunschId.')';
            $update = \Httpful\Request::put($url)
                ->body(json_encode($values))
                ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
                ->sendsJson()
                ->send();  
            return $update;
        } else {
            return false;
        }
    } 
    
    /*
    Basisfunktion für die Kommunikation mit OData. Httpful wird für das Basisprotokoll genutzt. 
    Da der Aufruf fast immer identisch ist für den Abruf der Daten, wurde der Aufruf hier gebündelt.
    */
    function fetchWithUri($url){
        //Httpful get
        $response = \Httpful\Request::get($url)
            //gibt json
            ->sendsJson()
            //user und pass für den user in intrexx
            ->authenticateWith($this->basics['odata']['user'], $this->basics['odata']['pass'])
            //braucht json zurück
            ->addHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->send();
        //OData Protokoll 2 ist immer in d=> geschachtelt
        if (property_exists($response->body,'d')){
            $result = $response->body->d;        
            return $result;    
        } else {
            return false;
        }             
    }
    
    function getPlanForDay($timestamp){
        date_default_timezone_set('UTC');
        $date = new \DateTime();
        $date->setTimestamp($timestamp);
        $datestring = $date->format('Y-m-d H:i:s');
        $datestring = str_replace(' ','T',$datestring);
        $filter = 'DT_DATUM%20eq%20datetime%27'.$datestring.'%27';
        
        $url = $this->serviceRootEp.'/Plan?$expand=Nutzer,Schicht,NutzerMeldung&$filter='.$filter;
        $response = $this->fetchWithUri($url);
        return $response->results;
    }
    
    function getNutzerOhneMeldung($timestamp){
        date_default_timezone_set('UTC');
        $date = new \DateTime();
        $date->setTimestamp($timestamp);
        $datestring = $date->format('Y-m-d H:i:s');
        $datestring = str_replace(' ','T',$datestring);
        
        $url = $this->serviceRootEpDienste."/getNutzerOhneMeldung?day='".$datestring."'";
        //echo $url;
        $response = $this->fetchWithUri($url);
        return $response->results;
    }
    
    function getWuenscheForDay($timestamp){
        date_default_timezone_set('UTC');
        $date = new \DateTime();
        $date->setTimestamp($timestamp);
        $datestring = $date->format('Y-m-d H:i:s');
        $datestring = str_replace(' ','T',$datestring);
        $filter = 'DT_DATUM%20eq%20datetime%27'.$datestring.'%27';
        //Plan: REF_415E0036
        $filter .= '%20and%20(REF_415E0036%20eq%20null)';
       
        $url = $this->serviceRootEp.'/Wunsch?$expand=Nutzer,Schicht,NutzerMeldung&$filter='.$filter;
        $response = $this->fetchWithUri($url);
        return $response->results;
    }
    
    function getAbwesenheiten(){
        $url = $this->serviceRootEp.'/Abwesenheit?$expand=Nutzer';
        $response = $this->fetchWithUri($url);
        
        return $response->results;
    }
    
    function getAbwesenheitenForMonth($month, $year){
        $first = new \DateTime($year.'-'.$month.'-01');
        $firstday = $first->format('Y-m-d H:i:s');
        $lastday = $first->format('Y-m-t H:i:s');
        $firstday = str_replace(' ','T',$firstday);
        $lastday = str_replace(' ','T',$lastday);
        
        $url = $this->serviceRootEp.'/Abwesenheit?$expand=Nutzer&$filter=(';
        //start oder ende im monat
        $url .= '((DT_STARTDATUM%20ge%20datetime%27'.$firstday.'%27%20and%20DT_STARTDATUM%20le%20datetime%27'.$lastday.'%27)%20or%20';
        $url .= '(DT_ENDDATUM%20ge%20datetime%27'.$firstday.'%27%20and%20DT_ENDDATUM%20le%20datetime%27'.$lastday.'%27))';
        //oder
        $url .= '%20or%20';
        //start kleiner als 1. und ende größer als letzter
        $url .=  '(DT_STARTDATUM%20lt%20datetime%27'.$firstday.'%27%20and%20DT_ENDDATUM%20gt%20datetime%27'.$lastday.'%27)';
        $url .= ')';
        
        $response = $this->fetchWithUri($url);
        
        return $response->results;
    }
    
    function getAbwesenheitenForDay($timestamp){
        $day = new \DateTime();
        $day->setTimestamp($timestamp);
        $day = $day->format('Y-m-d H:i:s');
        $day = str_replace(' ','T',$day);
        
        $url = $this->serviceRootEp.'/Abwesenheit?$expand=Nutzer&$filter=';
        //start oder ende im zeitraum
        $url .= '(DT_STARTDATUM%20le%20datetime%27'.$day.'%27)%20and%20';
        $url .= '(DT_ENDDATUM%20ge%20datetime%27'.$day.'%27)';
        //echo $url;echo '<br />';
        
        $response = $this->fetchWithUri($url);
        
        return $response->results;
    }
    
    function getBelegungForDay($timestamp, $weekday){
        $schichten = array();
        date_default_timezone_set('UTC');
        $date = new \DateTime();
        $date->setTimestamp($timestamp);
        $datestring = $date->format('Y-m-d H:i:s');
        $datestring = str_replace(' ','T',$datestring);
        
        $url = $this->serviceRootEpDienste."/getIstBelegungTag?day='".$datestring."'";
        $response = $this->fetchWithUri($url);
        $result = json_decode($response->getIstBelegungTag);
        
        if ($weekday != 0 && $weekday < 6){
            $schichten['Frühschicht']['Teamleitung'] = $result->fs_tl;
            $schichten['Frühschicht']['Arzt'] = $result->fs_ar;
            $schichten['Frühschicht']['Mitarbeiter'] = $result->fs_ma;
            $schichten['Spätschicht']['Teamleitung'] = $result->ss_tl;
            $schichten['Spätschicht']['Arzt'] = $result->ss_ar;
            $schichten['Spätschicht']['Mitarbeiter'] = $result->ss_ma;
        } else {
            $schichten['Wochenendschicht']['Teamleitung'] = $result->ws_tl;
            $schichten['Wochenendschicht']['Arzt'] = $result->ws_ar;
            $schichten['Wochenendschicht']['Mitarbeiter'] = $result->ws_ma;           
        }
        
        return $schichten;
    }
    
    function getBelegungForMonth($month,$year){
        $schichten = array();
        
        $url = $this->serviceRootEpDienste."/getIstBelegungMonat?month='".$month."'&year='".$year."'";
        $response = $this->fetchWithUri($url);
        $result = json_decode($response->getIstBelegungMonat);
        $result = json_decode(json_encode($result), true);
        
        return $result;
    }
    
    function getSchichten(){
        $url = $this->serviceRootEp.'/Schicht?$expand=Besetzung';
        $response = $this->fetchWithUri($url);
        
        return $response->results;
    }
    
    function getUserList(){
        $filter = '?$expand=NutzerMeldung&$orderby=STR_NACHNAME';
        $url = $this->serviceRootEp.'/Nutzer'.$filter;
        $rows = '';
        
        $response = $this->fetchWithUri($url);
        //var_dump($response);
        return $response->results;
    }
    
    function checkPin($pin){
        //$url = $this->serviceRoot.'/Nutzer?$filter=STR_PIN%20eq%20'.$pin;
        $url = $this->serviceRootDienste."/login?pin='".$pin."'";
        $result = $this->fetchWithUri($url);
        
        //var_dump($result);
        if ($result){
            $rights = explode('||',$result->TXT_RECHTE);
            $login = array(
                'uid'       => $result->LID,
                'vorname'   => $result->STR_VORNAME,
                'nachname'  => $result->STR_NACHNAME,
                'rechte'    => $rights
            );            
            return $login;
        } else {
            return false;
        }
    }
    
    function processSaveData($data){
        $values = array();
        //var_dump($data);
        foreach ($data as $key => $val){
            if (!is_array($val)){
                if ($key != "LID"){
                    $check = explode('_',$key);
                    switch ($check[0]){
                        case 'B':
                            if ($val == "on") $val = true;
                            else $val = false;
                            $values[$key] = $val;
                            break;
                        case 'DT':
                            date_default_timezone_set('UTC');
                            if ($val == "0") $val = "";
                            $values[$key] = "/Date(".strtotime($val)."000+0000)/";
                            break;
                        case 'L':
                            if ($val == "" || count($val) == 0) $values[$key] = null;
                            else $values[$key] = $val;
                            break;
                        case 'STR':
                            $values[$key] = htmlentities($val);
                            break;
                        default:
                            $values[$key] = $val;
                            break;
                    }                
                }
            } 
        }
        return $values;
    }
    
    function formatDate($value, $format = 'd.m.Y',$type = "local") {
        $utc = new \DateTimeZone("UTC");
        $local = new \DateTimeZone("Europe/Berlin");
        
        $value = str_replace("/Date(","",$value);
        $value = str_replace("000+0000)/","",$value);
        /*$value = str_replace("00+0000)/","",$value);
        $value = str_replace("0+0000)/","",$value);*/
        $value = str_replace("+0000)/","",$value);        
        $value = str_replace(")/","",$value);
        //$value = $value / 1000;
        //$value = (float)$value;
        $date = "";
        //echo $value .'<br />';
        if ($value > 0 && strlen($value) > 10){
            $value = substr($value,0,10);
        }
        if ($value != 0) {
            try {
                $dt = new \DateTime("@$value", $local);
                if ($type == 'utc') $dt->setTimeZone($utc);
                else $dt->setTimeZone($local);
                //$dt->add(new \DateInterval('PT1H'));
                $date = $dt->format($format);
            } catch (Exception $e) {
                echo "Datumsfehler: ".$e->getMessage()."\n";
            }
        }
        return $date;
    }
    
    function formatBoolean($value){
        if ($value == null) return "";
        if ($value){
            return "ja";
        } else {
            return "nein";
        }
    }
    
    //Basisrechte ermitteln
    function translateMainRights($rights){
        $rights = explode('||',$rights);
        //var_dump($rights);
        if (is_array($rights)){
            //wenn der Fall abgeschlossen ist, wird immer nur lese-Rechte zurück gegeben
            if (in_array('locked', $rights)) return 'Nur Lesen';
            //Falls Basis-Rechte mehrfach vergeben wurden, sollte immer nur das höchste Recht zurück gegeben werden
            if (in_array('doc', $rights)) return 'Maßnahmen anordnen';
            if (in_array('edit', $rights)) return 'Basisdaten bearbeiten';
            return 'Nur Lesen';    
        }        
    }
    
    function translateExtraRights($rights){
        $rights = explode('||',$rights);
        $returner = array();
        
        foreach ($rights as $r){
            switch ($r) {
                case 'aZahlen':
                    $returner[] = 'Auswertungen anzeigen';
                    break;
                case 'freig':
                    $returner[] = 'Freigaben erteilen';
                    break;
                case 'ovEdit':
                    $returner[] = 'OV Informationen erfassen';
                    break;
                case 'abortQs':
                    $returner[] = 'Quarantänen abbrechen';
                    break;
                case 'admMs':
                    $returner[] = 'Maßnahmen administrieren (Löschen von Einträgen und Subeinträgen)';
                    break;
                case 'closeMs':
                    $returner[] = 'Maßnahmen abschließen';
                    break;
                case 'openMs':
                    $returner[] = 'abgeschlossene Maßnahmen wieder zur Bearbeitung öffnen';
                    break;
                case 'survnet':
                    $returner[] = 'Suvent Eintrag vermerken';
                    break;                
                case 'admUser':
                    $returner[] = 'Nutzerliste anzeigen';
                    break;                
            }            
        }
        
        
        return implode(', ',$returner);
    }
}

