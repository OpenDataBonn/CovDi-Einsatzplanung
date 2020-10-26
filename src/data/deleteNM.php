<?php
/**
URL-Ziel zum hinzufügen von Kontaktpersonen
*/
include "../../includes.php";

$oData = new \base\OData;        
//var_dump($_POST);
$response = $oData->deleteNM($_POST);
var_dump($response);
if (!$response){
    echo false;
} else {
    echo true; 
}
?>