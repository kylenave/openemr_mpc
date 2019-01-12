<?php

 $fake_register_globals=false;
 $sanitize_all_escapes=true;

require_once('../globals.php');
require_once('abService.php');
require_once($GLOBALS['srcdir'].'/billing.inc');
require_once($GLOBALS['srcdir'].'/patient.inc');
require_once($GLOBALS['srcdir'].'/forms.inc');
require_once($GLOBALS['srcdir'].'/options.inc.php');
require_once($GLOBALS['srcdir'].'/encounter_events.inc.php');
require_once($GLOBALS['srcdir'].'/acl.inc');

 //Check access control


//Functions

function LoadAutoBillFile($filename)
{
   $csv = array();
   $lines = file($filename, FILE_IGNORE_NEW_LINES);

   foreach ($lines as $key => $value)
   {
      $csv[$key] = str_getcsv($value);
   }

   return $csv;
}

function hasSimilarEncounter($pid, $dos, $code)
{
  $query = "SELECT fe.encounter FROM form_encounter fe " . 
    "join billing b on b.encounter=fe.encounter " .
    "WHERE fe.pid = ? AND date(fe.date) = date(?) " .
    "and b.code=? ".
    "ORDER BY encounter DESC LIMIT 1";

  $tmprow = sqlQuery($query, array($pid,$dos,$code) );
  return empty($tmprow['encounter']) ? false : true;
}

function DisplayHeader()
{

   echo "<tr bgcolor='#aaaadd'>".
        "<th>PID</th>".
	"<th>Patient</th>".
	"<th>Provider</th>".
	"<th>Date of Service</th>".
	"<th>CPT</th>".
	"<th>Descr</th>".
	"<th>Units</th>".
	"<th>Dx</th>".
        "<th>Fee</th>".
        "<th>NDC</th>".
	"</tr>";
}

function DisplayServiceLine($color, $svc)
{
      echo "<tr bgcolor='" . $color . "'>".
         "<td>$svc->pid</td>".
	 "<td>$svc->lname,$svc->fname</td>".
	 "<td>$svc->providerLastName</td>".
	 "<td>$svc->dos</td>".
	 "<td>$svc->cptCode</td>".
	 "<td>$svc->descr</td>".
	 "<td>$svc->units</td>".
	 "<td>$svc->icdCode</td>".
         "<td>$svc->price</td>".
         "<td>$svc->ndcString</td>".
	 "</tr>";
}

function DisplayError($message)
{
   echo "<tr bgcolor='#cc8888'><td colspan='10' align='center'>$message</td></tr>";
}

function CreateNewEncounter($svc, $reason)
{
   $billing_facility='19'; //use default 
   $category = 'Office Visit'; 
   $return_existing=true;
   $force_create=true;

   return todaysEncounterCheck($svc->pid, $svc->dos, 
              $reason, $svc->facilityId, $billing_facility, $provider, $category, $return_existing, $force_create);
}

function AddBillingItem($enc, $svc)
{
   $codeType = 'CPT4';
   $authorized='1';
   $modifier='';
   
   addBilling($enc, $codeType, $svc->cptCode, $svc->descr, $svc->pid, $authorized, 
      $svc->providerId, $svc->modifier, $svc->units, $svc->price, $svc->ndcString, $svc->icdCode);
}

function ProcessAutoBillData($data, $displayOnly=true)
{
   echo "<table border=1 width='95%' padding='15px'>";

   $colors = array('#aaeeee', '#eeaaee');

   DisplayHeader();

   $header=true;
   $colorIndex=0;
   $enc = 0;

   foreach($data as $svcData)
   {
      if($header)
      {
         $header=false;
         continue;
      }

      $svc = new abService($svcData);


      if(!$svc->error)
      {

         if(!$svc->isSameVisit($lastSvc))
	 {
	    if(!$displayOnly) {
               $enc = CreateNewEncounter($svc, 'AutoBill');
	    }
	    $colorIndex = -$colorIndex + 1;
	 }

         if(!$displayOnly) {
	    AddBillingItem($enc, $svc);
	 }
         DisplayServiceLine($colors[$colorIndex], $svc);

      }else
      {
         DisplayError($svc->errorMessage);
      }

      $lastSvc=$svc;
   }

   echo "</table>";
}
?>

  <html>
  <head>
    <?php html_header_show(); ?>
    <link rel=stylesheet href="<?php echo $css_header;?>" type="text/css">
    <title><?php xl('Auto Charge Entry'); ?></title>
</head>

<body leftmargin='0' topmargin='0' marginwidth='0' marginheight='0'>
<center>
</center>
    <form method='post' action='auto_bill.php' enctype='multipart/form-data'>

      <table border='0' cellpadding='5' cellspacing='0'>
<?php 

if(!array_key_exists('form_auto', $_POST) && !array_key_exists('form_autoprocess', $_POST))
{
?>

<tr>
   <td>
      <?php xl('Upload AutoBill CSV file:','e'); ?>
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
      $Data = LoadAutoBillFile($auto_filename);
      ProcessAutoBillData($Data);

   }else
   {echo "No file found";}

?>
<tr><td>
     <input type='submit' name='form_autoprocess' value='<?php xl("Process AutoBill Data","e"); ?>'>
</td><tr>
<?php
}

if (array_key_exists('form_autoprocess', $_POST))
{
   $tmp_name = $_POST['tmp_auto_file'];
   $Data = LoadAutoBillFile($tmp_name);
   ProcessAutoBillData($Data, false);

   echo "<tr><td>Done!</td></tr>";
}
?>

</table>
</form>
</body>
</html>
