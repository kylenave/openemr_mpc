<?php

require_once("../../globals.php");
require_once("$srcdir/acl.inc");
require_once("$srcdir/api.inc");

function searchForKeyword($keyword) {
$data = array();
$res = sqlStatement("SELECT CONCAT(ct.ct_key, '|', c.code, '|') as codeValue, CONCAT(c.code, ': ', c.code_text) as description FROM codes c " .
 " left join code_types ct on ct.ct_id=c.code_type where (c.code_type='1' or c.code_type='3') AND (c.code like '$keyword%' OR c.code_text like '%$keyword%');");
while($cdata = sqlFetchArray($res)){
$data2 = array( "value" => $cdata['codeValue'], "label"  => $cdata['description']);
array_push($data,$data2);
}

$res = sqlStatement("SELECT CONCAT('ICD10|', formatted_dx_code, '|') as codeValue, CONCAT(formatted_dx_code, ': ', short_desc) as description FROM icd10_dx_order_code where (formatted_dx_code like '%$keyword%' OR long_desc like '%$keyword%');");
while($cdata = sqlFetchArray($res)){
$data2 = array( "value" => $cdata['codeValue'], "label"  => $cdata['description']);
array_push($data,$data2);
}
return json_encode($data);
}



if(isset($_GET['term']))
{
    echo searchForKeyword($_GET['term']);
}


?>
