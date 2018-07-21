<?php

require_once("code_query.php");

if (isset($_GET['keyword'])) {

   $keyword = $_GET['keyword'];
   $data = searchForKeyword($keyword);
   echo $data;
}

?>

