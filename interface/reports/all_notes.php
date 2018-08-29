<?php
/**
 *
 * Copyright (C) 2006-2016 Rod Roark <rod@sunsetsystems.com>
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
 * @link    http://www.open-emr.org
 */

$report_name = "All Notes";
$report_file = "all_notes";

$sanitize_all_escapes=true;
$fake_register_globals=false;

require_once("../globals.php");
require_once("$srcdir/patient.inc");
require_once("$srcdir/acl.inc");
require_once("$srcdir/formatting.inc.php");
require_once "$srcdir/options.inc.php";
require_once "$srcdir/formdata.inc.php";
require_once "$srcdir/appointments.inc.php";
require_once "$srcdir/report.inc";

$grand_total_units  = 0;
$grand_total_amt_billed  = 0;
$grand_total_amt_paid  = 0;
$grand_total_amt_adjustment  = 0;
$grand_total_amt_balance  = 0;


  if (! acl_check('acct', 'rep')) die(xlt("Unauthorized access."));

  $form_from_date = fixDate($_POST['form_from_date'], date('Y-m-d'));
  $form_to_date   = fixDate($_POST['form_to_date']  , date('Y-m-d'));
  $form_facility  = $_POST['form_facility'];
  $form_provider  = $_POST['form_provider'];

  if ($_POST['form_csvexport']) {
    header("Pragma: public");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Content-Type: application/force-download");
    header("Content-Disposition: attachment; filename=".$report_file."_".attr($form_from_date)."--".attr($form_to_date).".csv");
    header("Content-Description: File Transfer");
    // CSV headers:
    } // end export
  else {
?>
<html>
<head>
<link rel="stylesheet" href="<?php echo $css_header;?>" type="text/css">
<?php html_header_show();?>

<style type="text/css">
/* specifically include & exclude from printing */
@media print {
    #report_parameters {
        visibility: hidden;
        display: none;
    }
    #report_parameters_daterange {
        visibility: visible;
        display: inline;
    }
    #report_results {
       margin-top: 30px;
    }
}

/* specifically exclude some from the screen */
@media screen {
    #report_parameters_daterange {
        visibility: hidden;
        display: none;
    }
}
</style>

<script type="text/javascript" src="../../library/dialog.js?v=<?php echo $v_js_includes; ?>"></script>
<script type="text/javascript" src="<?php echo $GLOBALS['assets_static_relative']; ?>/jquery-min-1-9-1/index.js"></script>
<script type="text/javascript" src="../../library/js/common.js?v=<?php echo $v_js_includes; ?>"></script>
<script type="text/javascript" src="../../library/js/jquery-ui.js"></script>
<script type="text/javascript" src="../../library/js/report_helper.js?v=<?php echo $v_js_includes; ?>"></script>

<title><?php echo xlt($report_name) ?></title>

<script language="JavaScript">

 $(document).ready(function() {
  oeFixedHeaderSetup(document.getElementById('mymaintable'));
  var win = top.printLogSetup ? top : opener.top;
  win.printLogSetup(document.getElementById('printbutton'));
 });

</script>

</head>

<body leftmargin='0' topmargin='0' marginwidth='0' marginheight='0' class="body_top">
<span class='title'><?php echo xlt('Report'); ?> - <?php echo xlt($report_name); ?></span>
<form method='post' action='<?php echo xlt($report_file); ?>.php' id='theform'>
<div id="report_parameters">
<input type='hidden' name='form_refresh' id='form_refresh' value=''/>
<input type='hidden' name='form_csvexport' id='form_csvexport' value=''/>
<table>
 <tr>
  <td width='70%'>
	<div style='float:left'>
	<table class='text'>
	   <tr>
	        <td class='label'> <?php echo xlt('Facility'); ?>: </td>
  	        <td> <?php dropdown_facility($form_facility, 'form_facility', true); ?> </td>
                <td><?php echo xlt('Provider'); ?>:</td>
                <td><?php generateProviderSelection($_POST['form_provider']); ?> </td> 
            </tr>
            <tr>
                 <td colspan="2"> <?php echo xlt('From'); ?>:&nbsp;&nbsp;&nbsp;&nbsp;
                           <input type='text' name='form_from_date' id="form_from_date" size='10' value='<?php echo attr($form_from_date) ?>'
                                onkeyup='datekeyup(this,mypcc)' onblur='dateblur(this,mypcc)' title='yyyy-mm-dd'>
                           <img src='../pic/show_calendar.gif' align='absbottom' width='24' height='22'
                                id='img_from_date' border='0' alt='[?]' style='cursor:pointer'
                                title='<?php echo xla("Click here to choose a date"); ?>'>
                        </td>
                        <td class='label'>
                           <?php echo xlt('To'); ?>:
                        </td>
                        <td>
                           <input type='text' name='form_to_date' id="form_to_date" size='10' value='<?php echo attr($form_to_date) ?>'
                                onkeyup='datekeyup(this,mypcc)' onblur='dateblur(this,mypcc)' title='yyyy-mm-dd'>
                           <img src='../pic/show_calendar.gif' align='absbottom' width='24' height='22'
                                id='img_to_date' border='0' alt='[?]' style='cursor:pointer'
                                title='<?php echo xla("Click here to choose a date"); ?>'>
                        </td>
                        <td>
                           <input type='checkbox' name='form_details'<?php  if ($_POST['form_details']) echo ' checked'; ?>>
                           <?php echo xlt('Important Codes'); ?>
                        </td>
		</tr>
	</table>
	</div>
  </td>
  <td align='left' valign='middle' height="100%">
	<table style='border-left:1px solid; width:100%; height:100%' >
  	   <tr>
	      <td>
  		  <div style='margin-left:15px'>
		  <a href='#' class='css_button' onclick='$("#form_refresh").attr("value","true"); $("#form_csvexport").attr("value",""); $("#theform").submit();'>
		  <span> <?php echo xlt('Submit'); ?> </span> </a>

		<?php if ($_POST['form_refresh'] || $_POST['form_csvexport']) { ?>
		<div id="controls">
		<a href='#' class='css_button' id='printbutton'> <span> <?php echo xlt('Print'); ?> </span> </a>
		<a href='#' class='css_button' onclick='$("#form_refresh").attr("value",""); $("#form_csvexport").attr("value","true"); $("#theform").submit();'>
		<span> <?php echo xlt('CSV Export'); ?> </span> </a> </div>
		<?php } ?>
		</div>
		</td>
  	   </tr>
	</table>
  </td>
 </tr>
</table>
</div> <!-- end of parameters -->

<?php
}
   // end not export

  if ($_POST['form_refresh'] || $_POST['form_csvexport']) {
    $columns = array("Encounter"=>"encounter", "Date"=>"date","User"=>"user", "Comments"=>"comments");
    $moneyFormat = array(); //"Code"=>false, "Billed"=>true, "Paid"=>true);
    $calcGrandTotals = array(); //"Code"=>false, "Billed"=>true, "Paid"=>true);
    $grandTotals = array(); //"Code"=> 0, "Billed"=>0, "Paid"=>0);


    $rows = array();
    $from_date = $form_from_date;
    $to_date   = $form_to_date;
    $sqlBindArray = array();
    $query = "SELECT bn.encounter, bn.date, u.fname as user, bn.comments FROM openemr_mpc.billing_notes bn ".
             "left join users u on u.id=bn.user_id " .
        "WHERE " .
        "bn.date >=  ? AND bn.date <= ?";
   array_push($sqlBindArray,"$from_date 00:00:00","$to_date 23:59:59");
    // If a facility was specified.
      if ($form_facility) {
        $query .= " AND fe.facility_id = ?";
       array_push($sqlBindArray,$form_facility);
      }
    // If a provider was specified.
      if ($form_provider) {
        $query .= " AND fe.provider_id = ?";
        array_push($sqlBindArray,$form_provider);
      }
      $query .= " ORDER BY date(bn.date), u.fname";
      $res = sqlStatement($query,$sqlBindArray);

      while ($erow = sqlFetchArray($res)) 
{
      $row = array();
      foreach($columns as $x => $x_value) {
         $row[$x] = $erow[$x_value];
      }

      $rows[] = $row;
}


    if ($_POST['form_csvexport']) {
       // CSV headers:
       foreach($columns as $x => $x_value) { echo '"'.$x.'",'; }
       echo "\n";
    } else {
?> <div id="report_results">
<table id='mymaintable'>
 <thead>
<?php foreach($columns as $x => $x_value) { echo "<th>" . xlt($x)."</th>\n"; } ?>
 </thead>
 <?php
              }
     $orow = -1;

     foreach ($rows as $key => $row) 
{
     $print = '';
     $csv = '';


$bgcolor = ((++$orow & 1) ? "#ffdddd" : "#ddddff");
$print = "<tr bgcolor='$bgcolor'>";
foreach($columns as $x => $x_value) { 
   $print .= "<td class='detail'>". text($moneyFormat[$x]?oeFormatMoney($row[$x]):$row[$x]) ."</td>";
} 

$csv = '"'; 
foreach($columns as $x => $x_value) { 
   $csv .= text($moneyFormat[$x]?oeFormatMoney($row[$x]):$row[$x]) . '","';
} 
$csv .= '"' . "\n";


        if ($_POST['form_csvexport']) { echo $csv; }
	else { echo $print;
 }
     
}
       if (!$_POST['form_csvexport']) {
         echo "<tr bgcolor='#ffffff'>\n";

foreach($columns as $x => $x_value) { 
   if($computeGrandTotals[$x]){
         echo " <td class='detail'>" . text($moneyFormat[$x]?oeFormatMoney($grandTotals[$x]):$grandTotals[$x]) . "</td>\n";
   }else{
         echo " <td class='detail'></td>\n";
   }

}
         echo " </tr>\n";
          ?>
                </table>    </div>
        <?php
      }
	}

  if (! $_POST['form_csvexport']) {
       if ( $_POST['form_refresh'] && count($print) != 1)
	{
		echo "<span style='font-size:10pt;'>";
                echo xlt('No matches found. Try search again.');
                echo "</span>";
		echo '<script>document.getElementById("report_results").style.display="none";</script>';
		echo '<script>document.getElementById("controls").style.display="none";</script>';
		}
		
if (!$_POST['form_refresh'] && !$_POST['form_csvexport']) { ?>
<div class='text'>
 	<?php echo xlt('Please input search criteria above, and click Submit to view results.' ); ?>
</div>
<?php } ?>
</form>
</body>

<!-- stuff for the popup calendar -->

<link rel='stylesheet' href='<?php echo $css_header ?>' type='text/css'>
<style type="text/css">@import url(../../library/dynarch_calendar.css);</style>
<script type="text/javascript" src="../../library/dynarch_calendar.js"></script>
<?php include_once("{$GLOBALS['srcdir']}/dynarch_calendar_en.inc.php"); ?>
<script type="text/javascript" src="../../library/dynarch_calendar_setup.js"></script>
<script language="Javascript">
 Calendar.setup({inputField:"form_from_date", ifFormat:"%Y-%m-%d", button:"img_from_date"});
 Calendar.setup({inputField:"form_to_date", ifFormat:"%Y-%m-%d", button:"img_to_date"});
 top.restoreSession();
</script>
</html>
<?php
  } // End not csv export
?>
