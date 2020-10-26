<?php
/**
URL-Ziel zum hinzufügen von Kontaktpersonen
*/
include "../../includes.php";

$oData = new \base\OData;        
//var_dump($_POST);
$response = $oData->deleteP($_POST);
//var_dump($response);
if ($response->hasErrors()){
    echo false;
} else {
    echo true; 
}
?>