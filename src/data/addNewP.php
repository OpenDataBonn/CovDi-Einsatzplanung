<?php
/**
URL-Ziel zum hinzufügen von Kontaktpersonen
*/
include "../../includes.php";

$oData = new \base\OData;        
//var_dump($_POST);
$response = $oData->addNewP($_POST);
$new_id = str_replace('Plan(','',$response->body->link['href']);
$new_id = str_replace(')','',$new_id);
//var_dump($new_id);
if ($response->hasErrors()){
    echo false;
} else {
    echo $new_id; 
}
?>