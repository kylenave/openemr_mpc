<?php
/** 
 * Billing notes.
 *
 * Copyright (C) 2007 Rod Roark <rod@sunsetsystems.com>
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://opensource.org/licenses/gpl-license.php>;.
 *
 * @package OpenEMR
 * @author  Rod Roark <rod@sunsetsystems.com>
 * @author  Brady Miller <brady@sparmy.com>
 * @link    http://www.open-emr.org
 */

//SANITIZE ALL ESCAPES
$sanitize_all_escapes=true;
//

//STOP FAKE REGISTER GLOBALS
$fake_register_globals=false;
//

 include_once("../globals.php");
 include_once("$srcdir/log.inc");
 include_once("$srcdir/acl.inc");

 $feid = $_GET['feid'] + 0; // id from form_encounter table

 $info_msg = "";

 if (!acl_check('acct', 'bill','','write')) die(htmlspecialchars(xl('Not authorized'),ENT_NOQUOTES));
?>
<html>
<head>
<?php html_header_show();?>
<link rel=stylesheet href='<?php echo $css_header ?>' type='text/css'>

<style>
</style>

<script>
var oemr_session_name = '<?php echo session_name(); ?>';
var oemr_session_id   = '<?php echo session_id(); ?>';
var oemr_dialog_close_msg = '<?php echo (function_exists('xla')) ? xla("OK to close this other popup window?") : "OK to close this other popup window?"; ?>';
//
function restoreSession() {
<?php if (!empty($GLOBALS['restore_sessions'])) { ?>
 var ca = document.cookie.split('; ');
 for (var i = 0; i < ca.length; ++i) {
  var c = ca[i].split('=');
  if (c[0] == oemr_session_name && c[1] != oemr_session_id) {
<?php if ($GLOBALS['restore_sessions'] == 2) { ?>
   alert('Changing session ID from\n"' + c[1] + '" to\n"' + oemr_session_id + '"');
<?php } ?>
   document.cookie = oemr_session_name + '=' + oemr_session_id + '; path=/';
  }
 }
<?php } ?>
 return true;
}
</script>

</head>

<body>
<?php
if ($_POST['form_submit'] || $_POST['form_cancel']) {
  $fenote = trim($_POST['form_note']);
  if ($_POST['form_submit']) {
    sqlStatement("UPDATE form_encounter " .
      "SET billing_note = ? WHERE encounter = ?", array($fenote,$feid) );

$user_id=$_SESSION['authUserID'];
$datestamp = date("Y-m-d H:i:s");

$insertSql = "INSERT INTO billing_notes
(encounter, date, user_id, comments) VALUES
('" . $feid . "','" . $datestamp . "','" . $user_id . "','" . htmlspecialchars($fenote,ENT_QUOTES) ."')"; 

sqlStatement($insertSql);
  }
  else {
    $tmp = sqlQuery("SELECT billing_note FROM form_encounter " .
      " WHERE id = ?", array($feid) );
    $fenote = $tmp['billing_note'];
  }
  // escape and format note for viewing
  $fenote = htmlspecialchars($fenote,ENT_QUOTES);
  $fenote = str_replace("\r\n", "<br />", $fenote);
  $fenote = str_replace("\n"  , "<br />", $fenote);
  if (! $fenote) $fenote = '['. xl('Add') . ']';
  echo "<script language='JavaScript'>\n";
  echo " parent.closeNote($feid, '$fenote')\n";
  echo "</script></body></html>\n";
  exit();
}

$tmp = sqlQuery("SELECT billing_note FROM form_encounter " .
  " WHERE encounter = ?", array($feid) );
$fenote = $tmp['billing_note'];

$res = sqlStatement("Select date, user_id, comments, u.fname, u.lname from billing_notes " .
"left join users u on u.id=user_id " .
"where encounter = " . $feid . " order by date asc");

$notes = "";
while($data = sqlFetchArray($res))
{
  $notes .= "<p><b>" . $data['date'] . ":  " . $data['fname'] . " " . $data['lname'] . "</b><br>" . $data['comments'] . "</p>";
}


?>

<form method='post' action='edit_billnote_v2.php?feid=<?php echo htmlspecialchars($feid,ENT_QUOTES); ?>' onsubmit='return restoreSession()'>
<center>
<textarea name='form_note' rows='8' style='width:100%'></textarea>
<p>
<input type='submit' name='form_submit' value='<?php echo htmlspecialchars( xl('Save'), ENT_QUOTES); ?>' />
&nbsp;&nbsp;
<input type='submit' name='form_cancel' value='<?php echo htmlspecialchars( xl('Cancel'), ENT_QUOTES); ?>' />
</center>
</form>
</body>
</html>
