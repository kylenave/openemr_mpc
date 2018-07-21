<?php

require_once("../../globals.php");
require_once("$srcdir/acl.inc");
require_once("$srcdir/api.inc");

function searchForKeyword($keyword) {
$data = array();
$res = sqlStatement("SELECT CONCAT('CPT4|', code, '|') as codeValue, CONCAT(code, ': ', code_text) as description FROM codes where code_type='1' AND (code like '$keyword%' OR code_text like '%$keyword%');");
while($cdata = sqlFetchArray($res)){
$data2 = array(
"key" => $cdata['codeValue'],
"value"  => $cdata['description']);

//array_push($data,$cdata['description']);
array_push($data,$data2);
}

$res = sqlStatement("SELECT CONCAT('ICD10|', formatted_dx_code, '|') as codeValue, CONCAT(formatted_dx_code, ': ', short_desc) as description FROM icd10_dx_order_code where (formatted_dx_code like '%$keyword%' OR long_desc like '%$keyword%');");
while($cdata = sqlFetchArray($res)){
//array_push($data,$cdata['description']);
$data2 = array(
"key" => $cdata['codeValue'],
"value"  => $cdata['description']);
array_push($data,$data2);
}
return json_encode($data);
}
?>
