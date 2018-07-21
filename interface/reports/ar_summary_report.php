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

<title>AR Summary Report</title>

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

<span class='title'>AR Summary</span>

<form method='post' name='theform' id='theform' action='ar_summary_report.php' onsubmit='return top.restoreSession()'>
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
   <?php echo xlt('Employee'); ?>
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
   <?php echo xlt('Process'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('Charges'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('Payments'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('Adjustments'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('Balance'); ?>
  </th>
  <th align='center'>
   <?php echo xlt('Patient Resp'); ?>
  </th>
  <th align='center'>
   <?php echo xlt('Last Post'); ?>
  </th>
  <th align='center'>
   <?php echo xlt('Age (dos)'); ?>
  </th>
  <th align='center'>
   <?php echo xlt('Age (worked)'); ?>
  </th>

 </thead>
 <tbody>  <!-- added for better print-ability -->
<?php

  sqlQuery("
DROP TABLE IF EXISTS t_charges;
");

  sqlQuery("
create temporary table t_charges
( primary key encounter (encounter))
select sum(fee) as fee, max(user) as user, encounter, max(bill_process) as bill_process, code_type
                  from billing bi where fee>0 and bi.activity='1'
                   group by encounter;
");

  sqlQuery("
DROP TABLE IF EXISTS t_payments;
");

  sqlQuery("
create temporary table t_payments
( primary key encounter (encounter))
select encounter, max(date(post_time)) as posted, sum(pay_amount) as payments, sum(adj_amount) as adjustments,
                sum(if(locate(':', memo), trim(substring(memo, locate(':', memo)+1,10)),if(locate('$', memo), trim(substring(memo, locate('$', memo)+1,10)),0))) as patient_resp
                from ar_activity group by encounter;
");

 $res = sqlStatement("
SELECT
    atlas.fname as employee,
    p.pid, fe.encounter,
    p.lname, p.fname, p.DOB,
    p.pubpid,
    date(fe.date) as dos,
    b.bill_process as process,
    b.fee as charges,
    ar.payments,
    ar.adjustments,
    (b.fee - ar.payments - ar.adjustments) as balance,
        ar.patient_resp,
    ar.posted as last_post,
    DATEDIFF(now(), fe.date) as age,
    Datediff(now(), ar.posted) as DaysSinceWorked
from form_encounter fe
join t_charges b on b.encounter=fe.encounter
left join t_payments ar on ar.encounter=fe.encounter

join patient_data p on (p.pid=fe.pid)
left join users atlas on atlas.id=b.user
where
 fe.facility_id='" . $facility . "'
and
(
   #old charges not yet billed out
   (b.bill_process=0 and DATEDIFF(now(), fe.date) > 14)
or
   #Claims with zero payments and a balance
   ((b.fee - ar.payments - ar.adjustments) > (ar.patient_resp + 0.01))
or
   #Claims with 100% adjustments
   (ar.payments=0 and (b.fee = ar.adjustments))
or
 #nothing back after 3 weeks...
   (ar.payments is null and datediff(now(), fe.date) > 21)
)

group by fe.encounter
order by atlas.fname, b.fee desc

");

 $logstart = 0;
 while ($row = sqlFetchArray($res)) {

        $ptname = $row['fname'] . " " . $row['lname'];
        $rawdata = $ptname . "~" . $row['pid'] . "~" . $row['pubpid'] . "~" . oeFormatShortDate($row['DOB']) . "~" . $row['encounter'] . "~" . oeFormatShortDate($row['dos']);
        echo "<tr class='encrow text' id='" . htmlspecialchars($rawdata, ENT_QUOTES) .
          "'>\n";
?>
      <td align='center'><?php echo text($row['employee']); ?></td>
      <td align='center'><?php echo text($row['lname']); ?></td>
      <td align='center'><?php echo text($row['fname']); ?></td>
      <td align='center'><?php echo text($row['dos']); ?></td>
      <td align='center'><?php echo text($row['process']); ?></td>
      <td align='center'><?php echo text($row['charges']); ?></td>
      <td align='center'><?php echo text($row['payments']); ?></td>
      <td align='center'><?php echo text($row['adjustments']); ?></td>
      <td align='center'><?php echo text($row['balance']); ?></td>
      <td align='center'><?php echo text($row['patient_resp']); ?></td>
      <td align='center'><?php echo text($row['last_post']); ?></td>
      <td align='center'><?php echo text($row['age']); ?></td>
      <td align='center'><?php echo text($row['DaysSinceWorked']); ?></td>

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

