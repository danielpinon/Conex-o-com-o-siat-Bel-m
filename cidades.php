<?php
header('Content-Type: application/json');
if(!isset($_GET['estado'])){
    echo '{"status":"Error","line":4}';
    exit;
}else{
    echo file_get_contents('https://antecedentes.policiacivil.pa.gov.br/cidade/'.$_GET['estado']); 
    exit;
}