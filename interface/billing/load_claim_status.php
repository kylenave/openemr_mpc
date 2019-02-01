<?php

 $fake_register_globals=false;
 $sanitize_all_escapes=true;

require_once('../globals.php');
require_once('claimStatusService.php');
require_once($GLOBALS['srcdir'].'/billing.inc');
require_once($GLOBALS['srcdir'].'/patient.inc');
require_once($GLOBALS['srcdir'].'/forms.inc');
require_once($GLOBALS['srcdir'].'/options.inc.php');
require_once($GLOBALS['srcdir'].'/encounter_events.inc.php');
require_once($GLOBALS['srcdir'].'/acl.inc');

 //Check access control


//Functions

function LoadClaimStatusFile($filename)
{
   $csv = array();
   $lines = file($filename, FILE_IGNORE_NEW_LINES);

   foreach ($lines as $key => $value)
   {
      $csv[$key] = str_getcsv($value);
   }

   return $csv;
}

function DisplayHeader()
{

   echo "<tr bgcolor='#aaaadd'>".
        "<th>PID</th>".
	"<th>Encounter</th>".
	"<th>Patient</th>".
	"<th>Status</th>".
	"<th>Comments</th>".
	"</tr>";
}

function DisplayStatusLine($color, $cs)
{
      echo "<tr bgcolor='" . $color . "'>".
         "<td>$cs->pid</td>".
	 "<td>$cs->encounter</td>".
	 "<td>$cs->patient</td>".
	 "<td>$cs->status</td>".
	 "<td>$cs->comments</td>".
	 "</tr>";
}

function DisplayError($message)
{
   echo "<tr bgcolor='#cc8888'><td colspan='10' align='center'>$message</td></tr>";
}


function ProcessClaimStatusData($data, $displayOnly=true)
{
   echo "<table border=1 width='95%' padding='15px'>";

   $colors = array('#aaeeee', '#eeaaee');

   DisplayHeader();

   $header=true;
   $colorIndex=0;
   $enc = 0;

   $claimStatus = new claimStatusService();

   foreach($data as $csData)
   {
      if($header)
      {
         $header=false;
         continue;
      }

      $claimStatus->parseData($csData);

      if($claimStatus->Done)
      {
        $colorIndex = -$colorIndex + 1;
        DisplayServiceLine($colors[$colorIndex], $claimStatus);
      }

   }

   echo "</table>";
}
?>

  <html>
  <head>
    <?php html_header_show(); ?>
    <link rel=stylesheet href="<?php echo $css_header;?>" type="text/css">
    <title><?php xl('Load Claim Status File'); ?></title>
</head>

<body leftmargin='0' topmargin='0' marginwidth='0' marginheight='0'>
<center>
</center>
    <form method='post' action='load_claim_status.php' enctype='multipart/form-data'>

      <table border='0' cellpadding='5' cellspacing='0'>
<?php 

if(!array_key_exists('form_auto', $_POST) && !array_key_exists('form_autoprocess', $_POST))
{
?>

<tr>
   <td>
      <?php xl('Upload Claim Status file:','e'); ?>
      <input type="hidden" name="MAX_FILE_SIZE" value="5000000" />
      <input name="form_autofile" type="file" />
   </td>
</tr>

<tr>
   <td>
      <input type='submit' name='form_auto' value='<?php xl("Upload","e"); ?>'>
   </td>
</tr>

<?php
}

if (array_key_exists('form_auto', $_POST))
{
   if($_FILES['form_autofile']['size'])
   {
      $tmp_name = $_FILES['form_autofile']['tmp_name'];
      $auto_filename = "/tmp/autofile.txt";
      rename($tmp_name, $auto_filename);

      echo "<input type='hidden' name='tmp_auto_file' value='". $auto_filename . "' />";

      error_log('Temp file: ' . $auto_filename);
      $Data = LoadClaimStatusFile($auto_filename);
      ProcessClaimStatusData($Data);

   }else
   {echo "No file found";}

?>
<tr><td>
     <input type='submit' name='form_autoprocess' value='<?php xl("Process Claim Status Data","e"); ?>'>
</td><tr>
<?php
}

if (array_key_exists('form_autoprocess', $_POST))
{
   $tmp_name = $_POST['tmp_auto_file'];
   $Data = LoadAutoBillFile($tmp_name);
   ProcessClaimStatusData($Data, false);

   echo "<tr><td>Done!</td></tr>";
}
?>

</table>
</form>
</body>
</html>
