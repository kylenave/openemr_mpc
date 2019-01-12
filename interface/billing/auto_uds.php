<?php

 $fake_register_globals=false;
 $sanitize_all_escapes=true;

require_once('../globals.php');
require_once($GLOBALS['srcdir'].'/billing.inc');
require_once($GLOBALS['srcdir'].'/patient.inc');
require_once($GLOBALS['srcdir'].'/forms.inc');
require_once($GLOBALS['srcdir'].'/options.inc.php');
require_once($GLOBALS['srcdir'].'/encounter_events.inc.php');
require_once($GLOBALS['srcdir'].'/acl.inc');

 //Check access control


//Functions
function udsGetPatientId($name)
{
   list($last, $first) = split(",", $name, 2);

   $tmp = sqlQuery("SELECT pid from patient_data where upper(fname) = '" . strtoupper($first) . "' and upper(lname) = '" . strtoupper($last) . "'");

   return $tmp['pid'];
 
}

function udsGetProviderId($name)
{
   list($last, $first) = split(",", $name, 2);

   if($last == "Cermack") $last = "Cermak";
   if($last == "Schrierer") $last = "Schierer";

   $tmp = sqlQuery("SELECT id from users where upper(lname) = '" . strtoupper($last) . "'");

   return $tmp['id'];
 
}

function LoadUdsFile($filename)
{

   //$udsData = str_getcsv(file_get_contents($filename));
$csv = array();
$lines = file($filename, FILE_IGNORE_NEW_LINES);

foreach ($lines as $key => $value)
{
    $csv[$key] = str_getcsv($value);
}

   return $csv;

      //$fh = fopen($tmp_name, 'r');
      //while ( ($line = fgetcsv($fh) !== FALSE ) 
      //{
      //   echo $line;
      //}
      //fclose($fh);
}

function hasUdsEncounter($pid, $dos)
{
  $query = "SELECT fe.encounter FROM form_encounter fe " . 
    "join billing b on b.encounter=fe.encounter " .
    "WHERE fe.pid = ? AND date(fe.date) = date(?) " .
    "and b.code='80307' ".
    "ORDER BY encounter DESC LIMIT 1";

  $tmprow = sqlQuery($query, array($pid,$dos) );
  return empty($tmprow['encounter']) ? false : true;

}

function ProcessUdsData($udsData)
{

   echo "<table border=1 width='80%' padding='15px'>";
   echo "<tr bgcolor='#aaaadd'><th>Patient</th><th>Provider</th><th>Date of Service</th></th></tr>";
   $header=true;

   foreach($udsData as $uds)
   {
      if($header)
      {
         $header=false;
         continue;
      }

      $pid = udsGetPatientId($uds[0]);
      $provider = udsGetProviderId($uds[1]);

      echo "<tr><td>$uds[0] ($pid)</td><td>$uds[1] ($provider)</td><td>$uds[2]</td></td></tr>";
      if($pid && $provider)
      {
         $dos = date('Y-m-d 00:00:00', strtotime($uds[2]));

               if(hasUdsEncounter($pid, $dos))
               {
                     echo "<tr bgcolor='#cc8888'><td colspan='3' align='center'>Patient already has a UDS for this date</td></tr>";
               }else
               {
		   $BloomingtonFacilityId='3'; 
                   $enc = todaysEncounterCheck($pid, $dos, $reason = 'UTOX Screen', $BloomingtonFacilityId, '', $provider, 'Office Visit', true, true);
                   addBilling($enc, 'CPT4', '80307', 'UTOX Screening', $pid, '1', $provider, '', '1', '326', '', 'ICD10|Z79.891:ICD10|Z51.81:');
                   echo "<tr bgcolor='#88cc88'><td colspan='3' align='center'>New UDS Encounter Added</td></tr>";
               }

      }else
      {
         if(!$pid){
            echo "<tr bgcolor='#cc8888'><td colspan='3' align='center'>Patient not recognized</td></tr>";
         }
         else{
            echo "<tr bgcolor='#cc8888'><td colspan='3' align='center'>Provider not recognized</td></tr>";
         }
      }
   }

   echo "</table>";

 }

function PrintUdsData($udsData)
{

   echo "<table border=1 width='80%' padding='15px'>";
   echo "<tr bgcolor='#aaaadd'><th>Patient</th><th>Provider</th><th>Date of Service</th></th></tr>";
   $header=true;

   foreach($udsData as $uds)
   {
      if($header)
      {
         $header=false;
         continue;
      }

      $pid = udsGetPatientId($uds[0]);
      $provider = udsGetProviderId($uds[1]);
      $dos = date('Y-m-d 00:00:00', strtotime($uds[2]));


      echo "<tr><td>$uds[0] ($pid)</td><td>$uds[1] ($provider)</td><td>$uds[2]</td></td></tr>";
      if($pid && $provider)
      {

               if(hasUdsEncounter($pid, $dos))
               {
                     echo "<tr bgcolor='#cc8888'><td colspan='3' align='center'>Patient already has a UDS for this date</td></tr>";
               }
      }else
      {
         if(!$pid){
            echo "<tr bgcolor='#cc8888'><td colspan='3' align='center'>Patient not recognized</td></tr>";
         }
         else{
            echo "<tr bgcolor='#cc8888'><td colspan='3' align='center'>Provider not recognized</td></tr>";
         }
      }
   }
   echo "</table>";
}

?>

  <html>
  <head>
    <?php html_header_show(); ?>
    <link rel=stylesheet href="<?php echo $css_header;?>" type="text/css">
    <title><?php xl('UDS Posting'); ?></title>
</head>

<body leftmargin='0' topmargin='0' marginwidth='0' marginheight='0'>
<center>
</center>

    <form method='post' action='auto_uds.php' enctype='multipart/form-data'>

      <table border='0' cellpadding='5' cellspacing='0'>
<?php if(!array_key_exists('form_uds', $_POST) && !array_key_exists('form_udsprocess', $_POST))
{
?>

<tr>
<td>
   <?php xl('Upload UDS Lab CSV file:','e'); ?>
   <input type="hidden" name="MAX_FILE_SIZE" value="5000000" />
   <input name="form_udsfile" type="file" />
</td>
</tr>
<tr>
<td>
     <input type='submit' name='form_uds' value='<?php xl("Upload","e"); ?>'>
</td>
	</tr>

<?php
}

if (array_key_exists('form_uds', $_POST))
{
   if($_FILES['form_udsfile']['size'])
   {
      $tmp_name = $_FILES['form_udsfile']['tmp_name'];
      $uds_filename = "/tmp/udsfile.txt";
      rename($tmp_name, $uds_filename);

      echo "<input type='hidden' name='tmp_uds_file' value='". $uds_filename . "' />";
      $udsData = LoadUdsFile($uds_filename);
      PrintUdsData($udsData);

   }else
   {echo "No file found";}

?>
<tr><td>
     <input type='submit' name='form_udsprocess' value='<?php xl("Process Uds Data","e"); ?>'>
</td><tr>
<?php
}

if (array_key_exists('form_udsprocess', $_POST))
{
   $tmp_name = $_POST['tmp_uds_file'];
   $udsData = LoadUdsFile($tmp_name);

   ProcessUdsData($udsData);
}
?>

</table>
</form>
</body>
</html>
