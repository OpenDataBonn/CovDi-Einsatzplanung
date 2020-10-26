<?php
namespace base;

/*
Klasse zur Steuerung der Anzeige, 
verwaltet die Parameter zur Anzeigensteuerung inklsuive der Rechte zur Anzeige bestimmer Blöcke oder Bearbeitungsmöglichkeiten.
*/

class View {
    //vorhalten der Rechte des aktuellen Users
    private $rights = '';
    //Schalter ob der User bereits eingelogged ist
    private $loggedIn = false;
    //Userdaten
    private $user = false;
    //Basis-Anzeigetyp
    private $type = "main";
    //Suchparameter für die Listansicht
    private $search = array();
    
    private $month;
    private $year;
    private $week;
    
    private $calendar;
    private $monthZahlen;
    private $monthAbwesenheiten;
    
    function __construct() {
        //Basiseinstellungen aus der config.php
        global $basics;     
        $this->month = date("m");
        $this->year = date("Y");
        $this->calendar = new Calendar();
        //Check ob der User eingeloggt ist
        if (isset($_SESSION[$basics['session']]['user'])){
            $this->loggedIn = true;
            
            $this->rights = $_SESSION[$basics['session']]['user']['rechte'];            
            $this->user = $_SESSION[$basics['session']]['user'];            
            //var_dump($this->user);
        }
        
        //Anzeigetyp
        if (isset($_GET["type"])) $this->type = $_GET["type"];
        if (isset($_GET["month"])) $this->month = $_GET["month"];
        if (isset($_GET["year"])) $this->year = $_GET["year"];
        if (isset($_GET["week"])) $this->week = $_GET["week"];
        if($this->month == 13){
            $this->month = 1;
            $this->year ++;
        } else if ($this->month == 0){
            $this->month = 12;
            $this->year --;
        }
        
        $oData = new OData();
        if ($this->loggedIn) $this->monthZahlen = $oData->getBelegungForMonth($this->month, $this->year);                
    }
    
    //Basisfunktion der Klasse
    function getMain(){
        $main = "";       
        switch($this->getMainRight()){
            case 'roster':
                    switch ($this->type){
                        //Einsatzplanung
                        case 'roster':
                            $main = $this->getRoster();
                            break;
                        case 'weekroster':
                            if (isset($_GET['ts'])) $ts = $_GET['ts'];
                            else $ts = 0;
                            $main = $this->getWeekRoster($ts);
                            break;
                        case 'dayroster':
                            if (isset($_GET['ts'])) $ts = $_GET['ts'];
                            else $ts = 0;
                            $main = $this->getDayRoster($ts);                            
                            break;
                        case 'userlist':
                            $main = $this->getUserlist();
                            break;
                        default:
                            $template = file_get_contents("templates/start_loggedin.html");
                            $main = $template;
                            break;
                    }
                break;
            //Standard-Startseite oder Hinweis, das ein Login benötigt wird
            default:
                if (!$this->loggedIn) $main = file_get_contents("templates/start.html");                
                else $main = file_get_contents("templates/need_login.html");                
                break;
        }
        return $main;
    }
    
    function getUserlist(){
        $template = file_get_contents("templates/userlist/list.html");
        $template_element = file_get_contents("templates/userlist/element.html");

        $oData = new OData();
        $user = $oData->getUserList();
        $rows = "";
        foreach ($user as $nutzer){
            $row = $template_element;
            $row_array = array();
            $row_array['###LID###'] = $nutzer->LID;
            $row_array['###STR_NACHNAME###'] = $nutzer->STR_NACHNAME;
            $row_array['###STR_VORNAME###'] = $nutzer->STR_VORNAME;
            $row_array['###STR_EMAIL###'] = $nutzer->STR_EMAIL;
            
            $nm_id = 0;
            foreach($nutzer->NutzerMeldung->results as $nm){                
                if ($nm->LID > $nm_id){
                    $row_array['###LAST_MELDUNG###'] = $oData->formatDate($nm->DT_GEMELDET);
                    $row_array['###STR_MATYP###'] = $nm->STR_MATYP;
                    if ($nm->B_NEU) $row_array['###STR_MATYP###'] .= " <br /><i>neu und noch einzuarbeiten</i>";
                    $row_array['###FLT_WOCHENSTUNDEN###'] = $nm->FLT_WOCHENSTUNDEN;
                    $row_array['###L_UEBERSTUNDEN###'] = $nm->L_UEBERSTUNDEN;
                    $row_array['###STR_TELPRIVAT###'] = $nm->STR_TELPRIVAT;
                    $row_array['###STR_EMAILPRIVAT###'] = $nm->STR_EMAILPRIVAT;       
                    $row_array['###TXT_GRUNDKEINDIENST###'] = $nm->TXT_GRUNDKEINDIENST;
                    $row_array['###TXT_ANMERKUNG###'] = $nm->TXT_ANMERKUNG;
                    $row_array['###DELBUTTON###'] = '<button class="btn btn-danger" onclick="delLastDialog('.$nm->LID.')" title="letzte Meldung löschen">x</button>';
                    $nm_id = $nm->LID;
                }
            }
            
            $rows .= $this->replaceMarker($row, $row_array);
        }
        $userlist = str_replace('###ROWS###',$rows, $template);
        
        return $userlist;
    }
    
    function getDayRoster($timestamp){
        if ($timestamp == 0) $timestamp = time();
        $weekday = date("w", strtotime('+3 hours', $timestamp));
        if ($weekday == 0 || $weekday == 6){
            $template = file_get_contents("templates/roster/day_we.html");
        } else {
            $template = file_get_contents("templates/roster/day.html");
        }
        $marker = array();
        
        $day = $this->calendar->getDay($timestamp);
        $marker = $this->getBaseDayMarker($day);
        $marker = $this->getDayItems($day, $marker, 'day');
        $marker['###DATUM###'] = date("d.m.Y", strtotime('+3 hours', $timestamp));
        $marker['###WOCHENTAG###'] = $this->getLocalWeekday(date("l", strtotime('+3 hours', $timestamp)));
        
        return $this->replaceMarker($template,$marker);
    }
    
    function getWeekRoster($timestamp){
        if ($timestamp == 0) $timestamp = mktime( 0, 0, 0, 1, 1,  $this->year ) + ( ($this->week-1) * 7 * 24 * 60 * 60 );
        $template = file_get_contents("templates/roster/week.html");
        $marker = array();
        $marker['###WEEK###'] = date("W", $timestamp);
        $marker['###MONTH###'] = date("m", $timestamp);
        $marker['###YEAR###'] = $this->year;
        
        $days = $this->calendar->getDaysForWeekView($timestamp);
        
        $writtendays = "";
        foreach ($days as $day){
            $day_template = file_get_contents("templates/roster/week_weekday_full.html");
            $day_marker = array();
            if ($day['weekday'] == 6 || $day['weekday'] == 0 || $day['weekday'] == 7){
                $day_template = file_get_contents("templates/roster/week_weekend_full.html");
            } 
            $day_marker = $this->getBaseDayMarker($day);
            $day_marker = $this->getDayItems($day, $day_marker, 'week');
            $writtendays .= $this->replaceMarker($day_template, $day_marker);
        }
        $marker['###DAYS###'] = $writtendays;
        
        return $this->replaceMarker($template,$marker);
    }
    
    function getRoster(){
        $oData = new OData();
        $template = file_get_contents("templates/roster/base.html");
        $marker = array();
        $marker['###TITLE_MONTH###'] = $this->getLocalMonth(date("m", mktime(0, 0, 0, $this->month, 10)));
        $marker['###MONTH###'] = $this->month;
        $marker['###YEAR###'] = $this->year;
        $month_select = '<select id="monthToShow" onchange="gotToMonth()">';
        for ($i = 1; $i < 13; $i++){
            $month_select .= '<option value="'.$i.'" ';
            if ($i == $this->month) $month_select .= 'selected="selected"';
            $month_select .= '>'.$this->getLocalMonth(date("m", mktime(0, 0, 0, $i, 10))).'</option>';
        }
        $month_select .= '</select>';
        $marker['###MONTH_SELECT###'] = $month_select;     
        $year_select = '<select id="yearToShow" onchange="gotToMonth()">';
        for ($i = 2020; $i < 2030; $i++){
            $year_select .= '<option value="'.$i.'" ';
            if ($i == $this->year) $year_select .= 'selected="selected"';
            $year_select .= '>'.$i.'</option>';
        }
        $year_select .= '</select>';
        $marker['###YEAR_SELECT###'] = $year_select;
        
        $days = $this->calendar->getDaysForMonthView($this->month, $this->year);
        $writtendays = "";
        foreach ($days as $day){
            $day_template = file_get_contents("templates/roster/month_weekday.html");
            $day_marker = array();
            if ($day['weekday'] == 6 || $day['weekday'] == 0 || $day['weekday'] == 7){
                $day_template = file_get_contents("templates/roster/month_weekend.html");
            } 
            $day_marker = $this->getBaseDayMarker($day);
            $day_marker = $this->getBaseDayCounter($day, $day_marker);               
            
            $writtendays .= $this->replaceMarker($day_template, $day_marker);
        }
        $marker['###DAYS###'] = $writtendays;
        
        return $this->replaceMarker($template,$marker);
    }
    
    function getDayItems($day, $day_marker, $item_type){
        $oData = new OData();
        $template = file_get_contents("templates/roster/".$item_type."_dayitem.html");
        $templaten = file_get_contents("templates/roster/".$item_type."_notitem.html");
        
        //Fest geplante Einträge
        $plaene = $oData->getPlanForDay($day['timestamp']);
        foreach ($plaene as $plan){
            if ($plan->STR_TAETIGKEIT == "") $plan->STR_TAETIGKEIT  = "Mitarbeiter";
            $marker = "";
            $item_marker = array();
            $item_marker['###ITEMTYPE###'] = 'plan';
            $item_marker['###ITEMID###'] = $plan->LID;
            $item_marker['###NUTZER###'] = $plan->Nutzer->STR_NACHNAME.', '.$plan->Nutzer->STR_VORNAME;
            $item_marker['###NUTZERID###'] = $plan->Nutzer->LID;
            $item_marker['###MAIL###'] = '<a href="mailto:'.$plan->Nutzer->STR_EMAIL.'">'.$plan->Nutzer->STR_EMAIL.'</a>';
            if ($plan->NutzerMeldung){
                if ($plan->NutzerMeldung->STR_EMAILPRIVAT) {
                    $item_marker['###PRIVATE_MAIL###'] = '<a href="mailto:'.$plan->NutzerMeldung->STR_EMAILPRIVAT.'">'.$plan->NutzerMeldung->STR_EMAILPRIVAT.'</a>';
                }
                $item_marker['###PRIVATE_TEL###'] = $plan->NutzerMeldung->STR_TELPRIVAT;
                $item_marker['###MATYP###'] = $plan->NutzerMeldung->STR_MATYP;
                $item_marker['###WOCHENSTUNDEN###'] = 'WS: '.$plan->NutzerMeldung->FLT_WOCHENSTUNDEN;
                if ($plan->NutzerMeldung->L_UEBERSTUNDEN) {
                    $item_marker['###UEBERSTUNDEN###'] = 'ÜS: '.$plan->NutzerMeldung->L_UEBERSTUNDEN;
                }
            }
            $item_marker['###TO_PLAN###'] = '<button type="button" class="btn btn-danger btn-sm btn-mini" onclick="deletePlan(this,'.$plan->LID.')">&cross;</button>';
            $item_marker['###COUNTING###'] = 'counting fixed';
            if ($plan->DT_FREIGABEAM != null ) $item_marker['###FREIGEGEBEN###'] = 'freigegeben'; 
            
            $item = $this->replaceMarker($template, $item_marker);

            if ($plan->Schicht){
                switch ($plan->Schicht->STR_TITEL){
                    case "Fruehschicht":
                        $marker = "FS_".strtoupper($plan->STR_TAETIGKEIT);
                        break;
                    case "Spaetschicht":
                        $marker = "SS_".strtoupper($plan->STR_TAETIGKEIT);
                        break;
                    case "Wochenend":
                        $marker = "WS_".strtoupper($plan->STR_TAETIGKEIT);
                        break;
                    default:
                        $marker = "SP";
                }
            } else {
                $marker = "SP";
            }
            $marker .= '_WITEMS';

            if (array_key_exists('###'.$marker.'###',$day_marker)) $day_marker['###'.$marker.'###'] .= $item;
            else $day_marker['###'.$marker.'###'] = $item;
        }
        
        //Wünsche
        $wuensche = $oData->getWuenscheForDay($day['timestamp']);
        foreach ($wuensche as $wunsch){
            if ($wunsch->STR_TYP != "nicht"){
                if ($wunsch->STR_TAETIGKEIT == "") $wunsch->STR_TAETIGKEIT  = $wunsch->NutzerMeldung->STR_MATYP;
                $marker = "";
                $item_marker = array();
                $item_marker['###ITEMTYPE###'] = 'wunsch';
                $item_marker['###TAETIGKEIT###'] = $wunsch->STR_TAETIGKEIT;
                $item_marker['###CHECKBOX###'] = '<input type="checkbox" class="addToPlan">';
                $item_marker['###ITEMID###'] = $wunsch->LID;
                $item_marker['###TYP###'] = $wunsch->STR_TYP;
                $item_marker['###NUTZER###'] = $wunsch->Nutzer->STR_NACHNAME.', '.$wunsch->Nutzer->STR_VORNAME;
                if ($wunsch->STR_TAETIGKEIT == "Mitarbeiter" && !$wunsch->Schicht) $item_marker['###NUTZER###'] .= ', nur MA!';
                $item_marker['###NUTZERID###'] = $wunsch->Nutzer->LID;
                $item_marker['###MAIL###'] = '<a href="mailto:'.$wunsch->Nutzer->STR_EMAIL.'">'.$wunsch->Nutzer->STR_EMAIL.'</a>';
                if ($wunsch->NutzerMeldung->STR_EMAILPRIVAT) {
                    $item_marker['###PRIVATE_MAIL###'] = '<a href="mailto:'.$wunsch->NutzerMeldung->STR_EMAILPRIVAT.'">'.$wunsch->NutzerMeldung->STR_EMAILPRIVAT.'</a>';
                }
                $item_marker['###PRIVATE_TEL###'] = $wunsch->NutzerMeldung->STR_TELPRIVAT;
                $item_marker['###MATYP###'] = $wunsch->NutzerMeldung->STR_MATYP;
                $item_marker['###WOCHENSTUNDEN###'] = 'WS: '.$wunsch->NutzerMeldung->FLT_WOCHENSTUNDEN;
                if ($wunsch->NutzerMeldung->L_UEBERSTUNDEN) {
                    $item_marker['###UEBERSTUNDEN###'] = 'ÜS: '.$wunsch->NutzerMeldung->L_UEBERSTUNDEN;
                }
                $item_marker['###TO_PLAN###'] = '<button type="button" class="btn btn-success btn-sm btn-mini" onclick="createPlan(this,'.$wunsch->LID.','.$wunsch->Nutzer->LID.')">&check;</button>';
                $item_marker['###COUNTING###'] = 'counting';
                if ($wunsch->STR_TYP == "will" && $wunsch->STR_TAETIGKEIT != "beide"){
                    //Auch feste Wünsche sollen verschiebbar sein für die bessere Planung
                    //$item_marker['###COUNTING###'] .= ' fixed';                      
                }
                
                $item = $this->replaceMarker($template, $item_marker);
                
                if ($wunsch->Schicht){
                    switch ($wunsch->Schicht->STR_TITEL){
                        case "Fruehschicht":
                            $marker = "FS_".strtoupper($wunsch->STR_TAETIGKEIT);
                            break;
                        case "Spaetschicht":
                            $marker = "SS_".strtoupper($wunsch->STR_TAETIGKEIT);
                            break;
                        case "Wochenend":
                            $marker = "WS_".strtoupper($wunsch->STR_TAETIGKEIT);
                            break;
                        default:
                            $marker = "SP";
                    }
                } else {
                    $marker = "SP";
                }
                $marker .= '_WITEMS';
                
                if (array_key_exists('###'.$marker.'###',$day_marker)) $day_marker['###'.$marker.'###'] .= $item;
                else $day_marker['###'.$marker.'###'] = $item;
            }
        }
        foreach ($wuensche as $wunsch){
            if ($wunsch->STR_TYP == "nicht")
            {
                $marker = "";
                $item_marker = array();
                $item_marker['###TYP###'] = $wunsch->STR_TYP;
                $item_marker['###TO_PLAN###'] = '';
                $item_marker['###COUNTING###'] = 'fixed';                
                $item_marker['###NUTZER###'] = $wunsch->Nutzer->STR_NACHNAME.', '.$wunsch->Nutzer->STR_VORNAME;
                
                $item = $this->replaceMarker($templaten, $item_marker);

                $marker .= 'NOT_WITEMS';
                if (array_key_exists('###'.$marker.'###',$day_marker)) $day_marker['###'.$marker.'###'] .= $item;
                else $day_marker['###'.$marker.'###'] = $item;
                //var_dump($day_marker);
            }
        }
        //Abwesenheiten
        $abwesenheiten = $oData->getAbwesenheitenForDay($day['timestamp']);
        foreach ($abwesenheiten as $ab){            
            $marker = "";
            $item_marker = array();
            $item_marker['###TYP###'] = 'Urlaub';
            $item_marker['###TO_PLAN###'] = '';
            $item_marker['###COUNTING###'] = 'fixed';                
            $item_marker['###NUTZER###'] = $ab->Nutzer->STR_NACHNAME.', '.$ab->Nutzer->STR_VORNAME;
            
            $item = $this->replaceMarker($templaten, $item_marker);

            $marker .= 'URLAUB_WITEMS';
            if (array_key_exists('###'.$marker.'###',$day_marker)) $day_marker['###'.$marker.'###'] .= $item;
            else $day_marker['###'.$marker.'###'] = $item;
        }
        //ohne Einträge
        if ($item_type == "day"){
            $unknown = $oData->getNutzerOhneMeldung($day['timestamp']);
            foreach ($unknown as $un){            
                $marker = "";
                $item_marker = array();
                $item_marker['###ITEMTYPE###'] = 'unknown';
                $item_marker['###CHECKBOX###'] = '<input type="checkbox" class="addToPlan">';                
                $item_marker['###TYP###'] = 'kann';
                $item_marker['###ITEMID###'] = $un->LID;
                $item_marker['###NUTZER###'] = $un->STR_NACHNAME.', '.$un->STR_VORNAME;
                $item_marker['###NUTZERID###'] = $un->LID;
                $item_marker['###MAIL###'] = '<a href="mailto:'.$un->STR_EMAIL.'">'.$un->STR_EMAIL.'</a>';
                $item_marker['###TO_PLAN###'] = '<button type="button" class="btn btn-success btn-sm btn-mini" onclick="createPlan(this,-1,'.$un->LID.')">&check;</button>';
                $item_marker['###COUNTING###'] = 'counting';

                $item = $this->replaceMarker($template, $item_marker);

                $marker .= 'UNKNOWN_WITEMS';

                if (array_key_exists('###'.$marker.'###',$day_marker)) $day_marker['###'.$marker.'###'] .= $item;
                else $day_marker['###'.$marker.'###'] = $item;
            }
        }
        return $day_marker;
    }
    
    function getBaseDayMarker($day){
        $day_marker = array();
        
        if (!$day['act_month']) $day_marker['###ACT_MONTH###'] = "day_other_month";
        $day_marker['###DATESTRING###'] = $day['datestring'];
        $day_marker['###WEEKDAY###'] = $day['weekday'];
        $day_marker['###TIMESTAMP###'] = $day['timestamp'];
        if ($day['weekday'] != 0 && $day['weekday'] < 6){
            foreach ($day['schichten']['Fruehschicht']['Besetzung']['results'] as $bes){
                $day_marker['###FS_'.strtoupper($bes['STR_TYP']).'_BEDARF###'] = $bes['L_ANZAHL'];
            }
            foreach ($day['schichten']['Spaetschicht']['Besetzung']['results'] as $bes){
                $day_marker['###SS_'.strtoupper($bes['STR_TYP']).'_BEDARF###'] = $bes['L_ANZAHL'];
            }
        } else {
            foreach ($day['schichten']['Wochenend']['Besetzung']['results'] as $bes){
                $day_marker['###WS_'.strtoupper($bes['STR_TYP']).'_BEDARF###'] = $bes['L_ANZAHL'];
            }
        }            
        
        return $day_marker;
    }
    
    function getBaseDayCounter($day, $day_marker){
        $oData = new OData();
            
        if (!$day['act_month']) $day_marker['###ACT_MONTH###'] = "day_other_month";
        //var_dump($day['timestamp']);
        
        if ($day['weekday'] != 0 && $day['weekday'] < 6){
            foreach ($day['schichten']['Fruehschicht']['Besetzung']['results'] as $bes){
                $day_marker['###FS_'.strtoupper($bes['STR_TYP']).'_AKTUELL###'] = 0;                    
            }
            foreach ($day['schichten']['Spaetschicht']['Besetzung']['results'] as $bes){
                $day_marker['###SS_'.strtoupper($bes['STR_TYP']).'_AKTUELL###'] = 0;
            }
        } else {
            foreach ($day['schichten']['Wochenend']['Besetzung']['results'] as $bes){
                $day_marker['###WS_'.strtoupper($bes['STR_TYP']).'_AKTUELL###'] = 0;
            }
        }    
        
        if (array_key_exists($day['timestamp'].'000', $this->monthZahlen)) {
            $aktuelleZahlen = $this->monthZahlen[$day['timestamp'].'000'];
            //var_dump($aktuelleZahlen);
            if ($day['weekday'] != 0 && $day['weekday'] < 6){
                foreach ($day['schichten']['Fruehschicht']['Besetzung']['results'] as $bes){
                    if (array_key_exists('Fruehschicht', $aktuelleZahlen) && array_key_exists($bes['STR_TYP'],$aktuelleZahlen['Fruehschicht'])){
                        $day_marker['###FS_'.strtoupper($bes['STR_TYP']).'_AKTUELL###'] = $aktuelleZahlen['Fruehschicht'][$bes['STR_TYP']];                       
                    }                    
                }
                foreach ($day['schichten']['Spaetschicht']['Besetzung']['results'] as $bes){
                    if (array_key_exists('Spaetschicht', $aktuelleZahlen) && array_key_exists($bes['STR_TYP'],$aktuelleZahlen['Spaetschicht'])){
                        $day_marker['###SS_'.strtoupper($bes['STR_TYP']).'_AKTUELL###'] = $aktuelleZahlen['Spaetschicht'][$bes['STR_TYP']];                       
                    }  
                }
            } else {
                foreach ($day['schichten']['Wochenend']['Besetzung']['results'] as $bes){
                    if (array_key_exists('Wochenend', $aktuelleZahlen) && array_key_exists($bes['STR_TYP'],$aktuelleZahlen['Wochenend'])){
                        $day_marker['###WS_'.strtoupper($bes['STR_TYP']).'_AKTUELL###'] = $aktuelleZahlen['Wochenend'][$bes['STR_TYP']];                       
                    }  
                }
            }
            //var_dump($day_marker);
        }                
        
        return $day_marker;
    }
    
    //Aufbau der zentralen Navigation
    function getNav(){
        $nav_array = array();
        //Die Navigationsleite wird erst angezeigt, wenn ein Nutzer sich eingeloggt hat
        if ($this->loggedIn){
            //Anzeige Logout-Button
            $login = file_get_contents("templates/logout.html");
            $login = str_replace('###NACHNAME###',$this->user['nachname'],$login);
            $login = str_replace('###VORNAME###',$this->user['vorname'],$login);   
            $nav = file_get_contents("templates/nav.html");
            
            $nav_array['###EP_ACT_MONTH###'] = "Einsatzplanung ".$this->getLocalMonth(date('m',time()));
            $nav_array['###EP_NEXT_MONTH###'] = "Einsatzplanung ".$this->getLocalMonth(date('m',strtotime("+1 month")));
            $nav_array['###NEXT_MONTH###'] = date('m',strtotime("+1 month"));
            $nav_array['###NEXT_YEAR###'] = date('Y',strtotime("+1 month"));            
        } else {
            $login = file_get_contents("templates/login.html");
            $nav = file_get_contents("templates/nav_nomenu.html");
        }        
        $nav_array["###".strtoupper($this->type)."###"] = 'active';
        $nav_array["###LOGIN###"] = $login;
        
        return $this->replaceMarker($nav, $nav_array); 
    }
    
    //Duckansicht anzeigen, das Rahmentemplate wird hier nicht geladen
    function getPrint($printType, $id){
        $template = "";
        $oData = new\base\OData;
        
        switch($printType){
            //Deckblatt für Papiertakte
            case "frontpage":
                $template = file_get_contents("templates/print/front_page.html");
                $template = $oData->getSingleFrontPage($id, $template);
                break;                
        }
        
        return $template;
    }
    
    //Einbinden der Javascripte nach Anzeigetyp
    function getScript(){
        $script = "";
        switch ($this->type){
            case "roster":               
                $script.= "<script src=\"js/month.js\"></script>";
                break;
            case "weekroster":               
                $script.= "<script src=\"js/week.js\"></script>";
                break;
            case "dayroster":               
                $script.= "<script src=\"js/day.js\"></script>";
                break;
            case "userlist":
                $script.= "<script src=\"js/userlist.js\"></script>";
                break;
        }

        return $script;
    }
    
    //Basisrechte ermitteln
    function getMainRight(){
        if (is_array($this->rights)){
            if (in_array('roster', $this->rights)) return 'roster';            
            return 'read';    
        }
        
    }
    
    //Marker aus einem Template entfernen (Marker: ###___###)
    function replaceMarker($template, $marker){
        foreach($marker as $m => $v){
            $template = str_replace($m, $v, $template);
        }
        // preg_replace('/CROPSTART[\s\S]+?CROPEND/', '', $string);
        $template = preg_replace('/###[\s\S]+?###/','',$template);
        return $template;
    }
    
    //Wochentag Lokalisieren
    function getLocalWeekday($weekday){
        switch ($weekday){
            case 'Monday':
                $weekday = 'Montag';
                break;
            case 'Tuesday':
                $weekday = 'Dienstag';
                break;
            case 'Wednesday':
                $weekday = 'Mittwoch';
                break;
            case 'Thursday':
                $weekday = 'Donnerstag';
                break;
            case 'Friday':
                $weekday = 'Freitag';
                break;
            case 'Saturday':
                $weekday = 'Samstag';
                break;
            case 'Sunday':
                $weekday = 'Sonntag';
                break;
        }
        return $weekday;
    }
    
    function getLocalMonth($month){
        $monthis = array("Januar","Februar","März","April","Mai","Juni","Juli","August","September","Oktober","November","Dezember");
        return ($monthis[$month-1]);
    }
    
}

?>