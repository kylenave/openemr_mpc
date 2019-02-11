<?php

$PATIENT_NAME = 'pname';

require_once "../../globals.php";
require_once "$srcdir/acl.inc";
require_once "$srcdir/api.inc";

function searchForKeyword($keyword)
{
    $data = array();

    $delimiters = array(" ",",");
    $ready = str_replace($delimiters, $delimiters[0], $keyword);
    $keywords = explode($delimiters[0], $ready);

    $where = "";

    if(count($keywords) > 1)
    {
        $kw1 = $keywords[0];
        $kw2 = $keywords[1];

        $where = "((lname like '$kw1%' and fname like '$kw2%') or  ".
                 "(lname like '$kw2%' and fname like '$kw1%')) ";
    }else
    {
        $kw1 = $keywords[0];

        $where = "lname like '$kw1%' or fname like '$kw1%' ";
    }

    $res = sqlStatement("select pid, fname, lname from patient_Data where $where");

    while ($cdata = sqlFetchArray($res)) {
        $data2 = array("pid" => $cdata['pid'], "name" => $cdata['lname'] . ", " . $cdata['fname']);
        array_push($data, $data2);
    }
    return json_encode($data);
}

if (isset($_GET[$PATIENT_NAME])) {
    echo searchForKeyword($_GET[$PATIENT_NAME]);
}
