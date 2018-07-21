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
?>

<html>

<head>
<?php html_header_show();
$logstart = (isset($_POST['logstart'])) ? $_POST['logstart'] : 0;
if (isset($_POST['lognext']) && $_POST['lognext']) $logtop = $logstart + $_POST['lognext'];
else $logtop = 0;
?>

<link rel="stylesheet" href="<?php echo $css_header;?>" type="text/css">

<title>Demographic Errors</title>

<script type="text/javascript" src="<?php echo $GLOBALS['assets_static_relative']; ?>/jquery-min-1-7-2/index.js"></script>

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

<span class='title'>Demographic Errors</span>

<form method='post' name='theform' id='theform' action='direct_message_log.php' onsubmit='return top.restoreSession()'>
<input type='hidden' name='lognext' id='lognext' value=''>

<div id="report_parameters">
<table>
 <tr>
  <td width='470px'>
	<div style='float:left'>

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
   <?php echo xlt('Payer'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('Patient Zip'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('Subscriber Zip'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('Relationship'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('Policy Num'); ?>
  </th>
  <th align='center'>
   <?php echo xlt('Subsc DOB'); ?>
  </th>
  <th align='center'>
   <?php echo xlt('Subsc Sex'); ?>
  </th>
  <th align='center'>
   <?php echo xlt(''); ?>
  </th>

 </thead>
 <tbody>  <!-- added for better print-ability -->
<?php

 $res = sqlStatement("
select distinct atlas.fname as employee, p.lname, p.fname, fe.date, ic.name as payer, p.postal_code as zip, 
   i.subscriber_postal_code as izip, i.subscriber_relationship as relationship, i.policy_number, i.subscriber_DOB as idob, i.subscriber_sex as isex

from patient_data p
left join form_encounter fe
   on fe.pid=p.pid
left join billing b
   on b.encounter=fe.encounter
left join users atlas
   on atlas.id=b.user
left join facility fac
   on fac.id = fe.facility_id
left join insurance_data i
   on i.pid=p.pid and i.type='primary'
left join insurance_companies ic
   on ic.id=i.provider
where
(
(b.billed = '0' and p.pid>'1')
and 

(
#Demographic Errors
(
   p.postal_code=''
or
   (ic.name is null or ic.name!='Self Pay') and
(
   i.subscriber_postal_code=''
or 
   i.subscriber_relationship=''
or
   i.policy_number=''
)
)

))
");

 $logstart = 0;
 while ($row = sqlFetchArray($res)) {
?>
 <tr>
      <td align='center'><?php echo text($row['employee']); ?></td>
      <td align='center'><?php echo text($row['lname']); ?></td>
      <td align='center'><?php echo text($row['fname']); ?></td>
      <td align='center'><?php echo text($row['payer']); ?></td>
      <td align='center'><?php echo text($row['zip']); ?></td>
      <td align='center'><?php echo text($row['izip']); ?></td>
      <td align='center'><?php echo text($row['relationship']); ?></td>
      <td align='center'><?php echo text($row['policy_number']); ?></td>
      <td align='center'><?php echo text($row['idob']); ?></td>
      <td align='center'><?php echo text($row['isex']); ?></td>
      <td align='center'><?php echo text($row['']); ?></td>

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
</html>

