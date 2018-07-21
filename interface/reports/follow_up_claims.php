<?php
/**
 * Report to view the Direct Message log.
 *
 * Copyright (C) 2013 Brady Miller <brady@sparmy.com>
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
 * @author  Brady Miller <brady@sparmy.com>
 * @link    http://www.open-emr.org
 */

//SANITIZE ALL ESCAPES
$sanitize_all_escapes=true;
//

//STOP FAKE REGISTER GLOBALS
$fake_register_globals=false;
//

require_once("../globals.php");
require_once("$srcdir/formatting.inc.php");
require_once "$srcdir/options.inc.php";
require_once "$srcdir/formdata.inc.php";
require_once "$srcdir/appointments.inc.php";
require_once("$srcdir/patient_tracker.inc.php");



$facility  = $_POST['form_facility']; 
$clearEncounter = $_POST['clear_fu'];

if($clearEncounter)
{
   echo "Clearing Follow up for encounter: " . $clearEncounter;

   sqlQuery("update ar_activity set follow_up='n' where follow_up='y' and encounter= " . $clearEncounter);
}

?>

<html>

<head>
<?php html_header_show();
$logstart = (isset($_POST['logstart'])) ? $_POST['logstart'] : 0;
if (isset($_POST['lognext']) && $_POST['lognext']) $logtop = $logstart + $_POST['lognext'];
else $logtop = 0;
?>

<link rel="stylesheet" href="<?php echo $css_header;?>" type="text/css">

<title>Follow Up Claims Report</title>

<script type="text/javascript" src="<?php echo $GLOBALS['assets_static_relative']; ?>/jquery-min-1-7-2/index.js"></script>

<script language="JavaScript">

function toencounter(rawdata) {
    var parts = rawdata.split("~");
    var pname = parts[0]
    var pid = parts[1];
    var pan = parts[2];
    var dob = parts[3];
    var enc = parts[4]
    var dos = parts[5];

    top.restoreSession();
    parent.left_nav.setPatient(pname,pid,pan,'',dob);
   
    parent.left_nav.setEncounter(dos, enc, window.name);
    parent.left_nav.loadFrame('enc2', window.name, 'patient_file/encounter/encounter_top.php?set_encounter=' + enc + '&pid=' + pid);
}

// Process a click to go to an encounter.
function toencounter2(pid, pubpid, pname, enc, datestr, dobstr) {
 top.restoreSession();

 encurl = 'patient_file/encounter/encounter_top.php?set_encounter=' + enc + '&pid=' + pid;

 parent.left_nav.setPatient(pname,pid,pubpid,'',dobstr);

 <?php if ($GLOBALS['new_tabs_layout']) { ?>
  parent.left_nav.setEncounter(datestr, enc, 'enc');
  parent.left_nav.loadFrame('enc2', 'enc', encurl);
 <?php } else  { ?>
  var othername = (window.name == 'RTop') ? 'RBot' : 'RTop';
  parent.left_nav.setEncounter(datestr, enc, othername);
  parent.frames[othername].location.href = '../' + encurl;
 <?php } ?>
}

// Process a click to go to an patient.
function topatient(pid, pubpid, pname, enc, datestr, dobstr) {
 top.restoreSession();
 paturl = 'patient_file/summary/demographics_full.php?pid=' + pid;
 parent.left_nav.setPatient(pname,pid,pubpid,'',dobstr);
 <?php if ($GLOBALS['new_tabs_layout']) { ?>
  parent.left_nav.loadFrame('ens1', 'enc', 'patient_file/history/encounters.php?pid=' + pid);
  parent.left_nav.loadFrame('dem1', 'pat', paturl);
 <?php } else  { ?>
  var othername = (window.name == 'RTop') ? 'RBot' : 'RTop';
  parent.frames[othername].location.href = '../' + paturl;
 <?php } ?>
}

</script>

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
    #report_results table {
       margin-top: 0px;
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
</head>

<body class="body_top">

<span class='title'>Follow Up Claims </span>

<form method='post' name='theform' id='theform' action='follow_up_claims.php' onsubmit='return top.restoreSession()'>
<input type='hidden' name='lognext' id='lognext' value=''>
<input type='hidden' name='clear_fu' id='clear_fu' value=''/>

<div id="report_parameters">
<table>
 <tr>
  <td width='470px'>
	<div style='float:left'>
            <table class='text'>
            <tr>
                <td class='label'><?php echo xlt('Facility'); ?>:</td>
                <td><?php dropdown_facility($facility, 'form_facility'); ?> </td>
                <td>
                <div style='margin-left: 15px'>
                <a href='#' class='css_button' onclick='$("#form_refresh").attr("value","true"); $("#theform").submit();'>
                <span> <?php echo xlt('Submit'); ?> </span> </a>
                </div>
                    </td>

 </tr>
</table>
</div>  <!-- end of search parameters -->

<br>



<div id="report_results">
<table>

 <thead>

  <th align='center'>
   <?php echo xlt('Facility'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('Insurance'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('Provider'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('First Name'); ?>
  </th>
  <th align='center'>
   <?php echo xlt('Last Name'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('Date of Svc'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('Fees'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('Reason'); ?>
  </th>

  <th align='right'>
   <?php echo xlt('TEST'); ?>
  </th>

 </thead>
 <tbody>  <!-- added for better print-ability -->
<?php

sqlQuery(" DROP TABLE IF EXISTS last_claim; ");

sqlQuery("
create temporary table last_claim
(
  index encounter(encounter_id)
)
select encounter_id, patient_id, max(version) as version, payer_id as payer from claims
group by patient_id, encounter_id;
");

sqlQuery(" DROP TABLE IF EXISTS encounter_charges; ");

sqlQuery("
create temporary table encounter_charges
(
  index encounter(encounter)
)
select encounter, sum(fee) as fee from billing where activity='1' and fee>0 group by encounter;
");

 $res = sqlStatement("
select distinct fe.encounter, f.name as facility, doc.lname as provider, p.lname, p.fname, date(fe.date) as dos, ar.follow_up_note as reasons, ec.fee, ic.name as payer
from form_encounter fe
left join encounter_charges ec on fe.encounter=ec.encounter
left join patient_data p on p.pid=fe.pid
left join facility f on f.id=fe.facility_id
left join users doc on doc.id=fe.provider_id
left join last_claim lc on lc.encounter_id=fe.encounter
left join insurance_companies ic on ic.id=lc.payer
left join ar_activity ar on ar.pid=fe.pid and ar.encounter=fe.encounter
where ar.follow_up='y'
order by f.name, doc.lname, p.lname, fe.date
");

 $logstart = 0;
 while ($row = sqlFetchArray($res)) {

        $ptname = $row['fname'] . " " . $row['lname'];
        $rawdata = $ptname . "~" . $row['pid'] . "~" . $row['pubpid'] . "~" . oeFormatShortDate($row['DOB']) . "~" . $row['encounter'] . "~" . oeFormatShortDate($row['dos']);
$clearEnc = $row['encounter'];
        echo "<tr class='encrow text' id='" . htmlspecialchars($rawdata, ENT_QUOTES) .
          "'>\n";
?>
      <td align='center'><?php echo text($row['facility']); ?></td>
      <td align='center'><?php echo text($row['payer']); ?></td>
      <td align='center'><?php echo text($row['provider']); ?></td>
      <td align='center'><?php echo text($row['fname']); ?></td>
      <td align='center'><?php echo text($row['lname']); ?></td>
      <td align='center'><?php echo text($row['dos']); ?></td>
      <td align='center'><?php echo text($row['fees']); ?></td>
      <td align='center'><?php echo text($row['reasons']); ?></td>
      <td align='right'>
                <div style='margin-left: 1px'>
                <a href='#' class='css_button' onclick='$("#clear_fu").attr("value","<?php echo $clearEnc; ?>"); $("#theform").submit();'>
                <span> <?php echo xlt('Clear'); ?> </span> </a>
                </div>
      </td>


 </tr>
<?php
 } // $row = sqlFetchArray($res) while
?>
</tbody>
</table>
</div>  <!-- end of search results -->

<input type='hidden' name='logstart' id='logstart' value='<?php echo text($logstart); ?>'>
</form>

</body>
<script language="javascript">
// jQuery stuff to make the page a little easier to use

//$(document).ready(function(){
//    $(".encrow").click(function() { toencounter(this.id); }); 
    
});

</script>
</html>

