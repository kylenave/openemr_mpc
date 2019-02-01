<?php

$fake_register_globals = false;
$sanitize_all_escapes = true;

require_once '../globals.php';
require_once 'claimStatusService.php';
require_once 'clearinghouseStatusService.php';
require_once $GLOBALS['srcdir'] . '/billing.inc';
require_once $GLOBALS['srcdir'] . '/patient.inc';
require_once $GLOBALS['srcdir'] . '/forms.inc';
require_once $GLOBALS['srcdir'] . '/options.inc.php';
require_once $GLOBALS['srcdir'] . '/encounter_events.inc.php';
require_once $GLOBALS['srcdir'] . '/acl.inc';

//Check access control

//Functions

function LoadClaimStatusFile($filename)
{
    return file($filename, FILE_IGNORE_NEW_LINES);
}

function DisplayHeader()
{

    echo "<tr bgcolor='#aaaadd'>" .
        "<th>PID</th>" .
        "<th>Encounter</th>" .
        "<th>Patient</th>" .
        "<th>Status</th>" .
        "<th>Comments</th>" .
        "</tr>";
}

function DisplayStatusLine($color, $cs)
{
    echo "<tr bgcolor='" . $color . "'>" .
        "<td>$cs->pid</td>" .
        "<td>$cs->encounter</td>" .
        "<td>$cs->patientName</td>" .
        "<td>$cs->status</td>" .
        "<td>$cs->comments</td>" .
        "</tr>";
}

function DisplayError($message)
{
    echo "<tr bgcolor='#cc8888'><td colspan='10' align='center'>$message</td></tr>";
}

function ProcessClaimStatusData($data, $displayOnly = true)
{
    echo "<table border=1 width='95%' padding='15px'>";

    $colors = array('#aaeeee', '#eeaaee');

    DisplayHeader();

    $header = true;
    $colorIndex = 0;
    $enc = 0;

    $claimStatus = new claimStatusService();

    foreach ($data as $csData) {
        if ($header) {
            $header = false;
            continue;
        }

        $claimStatus->parseData($csData);

        if ($claimStatus->done) {
            $colorIndex = -$colorIndex + 1;
            DisplayStatusLine($colors[$colorIndex], $claimStatus);
            $claimStatus->done = false;
        }

    }

    echo "</table>";
}

function ProcessClearinghouseStatusData($data, $displayOnly = true)
{
    echo "<table border=1 width='95%' padding='15px'>";

    $colors = array('#aaeeee', '#eeaaee');
    $colorIndex = 0;

    DisplayHeader();

    $clearinghouseStatus = new clearinghouseStatusService();

    foreach ($data as $csData) {
        $clearinghouseStatus->parseData($csData);

        if ($clearinghouseStatus->done) {
            $colorIndex = -$colorIndex + 1;
            DisplayStatusLine($colors[$colorIndex], $clearinghouseStatus);
            $clearinghouseStatus->done = false;
        }

    }

    echo "</table>";
}

?>

  <html>
  <head>
    <?php html_header_show();?>
    <link rel=stylesheet href="<?php echo $css_header; ?>" type="text/css">
    <title><?php xl('Load Claim Status File');?></title>
</head>

<body leftmargin='0' topmargin='0' marginwidth='0' marginheight='0'>
<center>
</center>
    <form method='post' action='load_claim_status.php' enctype='multipart/form-data'>

      <table border='0' cellpadding='5' cellspacing='0'>
<?php

if (!array_key_exists('form_auto', $_POST) && !array_key_exists('form_autoprocess', $_POST)) {
    ?>

<tr>
   <td>
      <?php xl('Upload Claim Status file (Note: Multiple files allowed):', 'e');?>
      <input type="hidden" name="MAX_FILE_SIZE" value="5000000" />
      <input name="form_autofile[]" type="file" multiple="multiple" />
   </td>
</tr>

<tr>
   <td>
      <input type='submit' name='form_auto' value='<?php xl("Upload", "e");?>'>
   </td>
</tr>

<?php
}

if (array_key_exists('form_auto', $_POST)) {
   $files = array_filter($_FILES['form_autofile']['name']);
   $filesTmp = array_filter($_FILES['form_autofile']['tmp_name']);
   $fileSizes = array_filter($_FILES['form_autofile']['size']);

    $fileCount = count($files);

    for ($i = 0; $i < $fileCount; $i++) {
        if ($fileSizes[$i]) {
            $tmp_name = $filesTmp[$i];
            error_log("Process file: " . $tmp_name);
            echo "<tr><td>Processing File: $files[$i]</td></tr>";
            $auto_filename = "/tmp/autofile.txt";
            rename($tmp_name, $auto_filename);

            //echo "<input type='hidden' name='tmp_auto_file' value='" . $auto_filename . "' />";

            $Data = LoadClaimStatusFile($auto_filename);

            if( strpos($files[$i], 'HCFA')!== false)
            {
               ProcessClearinghouseStatusData($Data);
            }else
            {
               ProcessClaimStatusData($Data);
            }

        } else {echo "No file found";}
      }
      echo "<tr><td>Done!</td></tr>";

   }

?>

</table>
</form>
</body>
</html>
