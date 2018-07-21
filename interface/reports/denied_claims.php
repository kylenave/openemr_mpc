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
?>

<html>

<head>
<?php html_header_show();
$logstart = (isset($_POST['logstart'])) ? $_POST['logstart'] : 0;
if (isset($_POST['lognext']) && $_POST['lognext']) $logtop = $logstart + $_POST['lognext'];
else $logtop = 0;
?>

<link rel="stylesheet" href="<?php echo $css_header;?>" type="text/css">

<title>Denied Claims Report</title>

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

<span class='title'>Denied Claims</span>

<form method='post' name='theform' id='theform' action='denied_claims.php' onsubmit='return top.restoreSession()'>
<input type='hidden' name='lognext' id='lognext' value=''>

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
   <?php echo xlt('Last Name'); ?>
  </th>
  <th align='center'>
   <?php echo xlt('First Name'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('Date of Svc'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('Charges'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('Last Bill Date'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('Days Since Worked'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('Reason'); ?>
  </th>

 </thead>
 <tbody>  <!-- added for better print-ability -->
<?php

sqlQuery("DROP TABLE IF EXISTS t_claims");

sqlQuery("
create temporary table t_claims
( primary key encounter_id (encounter_id))
select encounter_id, max(version) as version from claims group by encounter_id");

sqlQuery("DROP TABLE IF EXISTS t_claims2");

sqlQuery("
create temporary table t_claims2
( primary key encounter_id (encounter_id))
select encounter_id, max(version) as version, payer_id from claims where payer_id!='0' group by encounter_id");

sqlQuery("DROP TABLE IF EXISTS t_claimDenials");

sqlQuery("
create temporary table t_claimDenials
(   primary key encounter_id (encounter_id))
select c.encounter_id, c.patient_id, tc2.payer_id, c.status, c.process_file as reasons, date(bill_time) as last_billed_date from claims c
left join t_claims tc on tc.encounter_id=c.encounter_id
left join t_claims2 tc2 on tc2.encounter_id=c.encounter_id
where c.status='7' and c.version=tc.version");


 $res = sqlStatement("
select distinct f.name as facility, ic.name as payer, doc.lname as provider, p.lname, p.fname, date(fe.date) as dos, c.status, c.reasons,
(select sum(fee) as fee from billing where encounter=fe.encounter and activity='1') as fees, c.last_billed_date, datediff(NOW(),c.last_billed_date) as days_since_billed
from t_claimDenials c
left join form_encounter fe on fe.encounter=c.encounter_id
left join patient_data p on p.pid=fe.pid
left join facility f on f.id=fe.facility_id
left join users doc on doc.id=fe.provider_id
left join insurance_companies ic on ic.id=c.payer_id
where
datediff(NOW(),c.last_billed_date) > 30
order by f.name, doc.lname, p.lname, fe.date
");

 $logstart = 0;
 while ($row = sqlFetchArray($res)) {

        $ptname = $row['fname'] . " " . $row['lname'];
        $rawdata = $ptname . "~" . $row['pid'] . "~" . $row['pubpid'] . "~" . oeFormatShortDate($row['DOB']) . "~" . $row['encounter'] . "~" . oeFormatShortDate($row['dos']);
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
      <td align='center'><?php echo text($row['last_billed_date']); ?></td>
      <td align='center'><?php echo text($row['days_since_billed']); ?></td>
      <td align='center'><?php echo text($row['reasons']); ?></td>

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

$(document).ready(function(){
    $(".encrow").click(function() { toencounter(this.id); }); 
    
});

</script>
</html>

