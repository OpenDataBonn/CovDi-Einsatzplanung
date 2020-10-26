<?php
//Pfad der Entwicklungsumgebung (Absoluter Pfad da Windows-System)
$dev_path = 'C:\inetpub\wwwroot\gesundheitsamt\covdi-einsatzplanung_test';
$base_url = 'http://sv12566.intern.stadt-bn.de/gesundheitsamt/covdi-einsatzplanung/';

//der absolute Pfad wird ausgelesen
$path = getcwd();

//Basis-Einstellungen des Systems, globale Variable
$basics = array();
$basics['page_title'] = "Einsatzplanung für CovDi";
$basics['page_title_color'] = "black";

//Es werden zwei OData Dienste benötigt, einmal für den Datenzugriff
$basics['serviceRoot'] = "http://sv11777.intern.stadt-bn.de:9090/Covid19.svc";
//und einmal für Funktionen, die aufbereitete Daten liefern
$basics['serviceRootDienste'] = "http://sv11777.intern.stadt-bn.de:9090/Covid19Dienste.svc";
$basics['serviceRootEp'] = "http://sv11777.intern.stadt-bn.de:9090/Covid19Ep.svc";
$basics['serviceRootEpDienste'] = "http://sv11777.intern.stadt-bn.de:9090/Covid19EpDienste.svc";

//Login-Daten für OData
$basics['odata']['user'] = 'dmzbonnde';
$basics['odata']['pass'] = 'seriu3094';

//Name der Session
$basics['session'] = 'covdi';

//Anzeigen ohne Standard-Template-Rahmen
$basics['blank'] = array('print');
   
//Wenn wir uns im Dev-Pfad befinden, werden die Basisdaten überschrieben
if ($path == $dev_path || strpos($path, $dev_path) !== false){
    $basics['page_title'] = "Einsatzplanung für CovDi - Testumgebung";
    $basics['page_title_color'] = "#721c24";
    $base_url = 'http://sv12566.intern.stadt-bn.de/gesundheitsamt/covdi-einsatzplanung_test/';
    
    $basics['serviceRoot'] = "http://sv17433.intern.stadt-bn.de:9090/Covid19.svc";
    $basics['serviceRootDienste'] = "http://sv17433.intern.stadt-bn.de:9090/Covid19Dienste.svc";
    $basics['serviceRootEp'] = "http://sv17433.intern.stadt-bn.de:9090/Covid19Ep.svc";
    $basics['serviceRootEpDienste'] = "http://sv17433.intern.stadt-bn.de:9090/Covid19EpDienste.svc";
    
    $basics['session'] = 'covdit';
}
//Datums-Zeitzone
date_default_timezone_set('Europe/Berlin');
setlocale (LC_TIME, "de_DE");
//die Session wird immer gebraucht
session_start();
?>