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

function ProcessUdsData($udsData)
{
   echo "<pre>";

   $errors = 'These names were not found:';

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

      if($pid && $provider)
      {
         $dos = date('Y-m-d 00:00:00', strtotime($uds[2]));

         $enc = todaysEncounterCheck($pid, $dos, $reason = 'UTOX Screen', '3', '', $provider, '', $return_existing = true);

         addBilling($enc, 'CPT4', '80307', 'UTOX Screening', $pid, '1', $provider, '', '1', '326', '', 'ICD10|Z79.891:ICD10|Z51.81:');

         echo "Added encounter ($dos) for patient: " . $uds[0] . "\n";

      }else
      {
         if(!$pid){
         $errors .= "\nPatient:" . $uds[0];}
         else{
            $errors .= "\nProvider:" . $uds[1];
         }
      }
   }

   echo $errors;
   echo "</pre>";
 }

function PrintUdsData($udsData)
{
   echo "<pre>";

   $errors = 'These names were not found:';

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

      if($pid && $provider)
      {
         echo "Patient: " . $uds[0] . "[" . $pid . "]   -  Provider: " . $uds[1] . "[" . $provider . "]  -  Date: " . $uds[2] . "\n";
      }else
      {
         if(!$pid){
         $errors .= "\nPatient:" . $uds[0];}
         else{
            $errors .= "\nProvider:" . $uds[1];
         }
      }
   }

   echo $errors;
   echo "</pre>";
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
<?php if(!$_POST['form_uds'])
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

if ($_POST['form_uds'])
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

if ($_POST['form_udsprocess'])
{
   echo "<pre>Displaying Data To Process:\n";
   $tmp_name = $_POST['tmp_uds_file'];
   $udsData = LoadUdsFile($tmp_name);

   ProcessUdsData($udsData);

   echo "</pre>";
}
?>

	</table>
</form>
</body>
</html>
