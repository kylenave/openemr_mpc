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

<title>Coding Errors</title>

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

<span class='title'>Coding Errors</span>

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
   <?php echo xlt('Date of Svc'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('Provider'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('Facility'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('Code'); ?>
  </th>

  <th align='center'>
   <?php echo xlt('Modifier'); ?>
  </th>
  <th align='center'>
   <?php echo xlt('Justify'); ?>
  </th>
  <th align='center'>
   <?php echo xlt('Charge'); ?>
  </th>
  <th align='center'>
   <?php echo xlt('Price'); ?>
  </th>

 </thead>
 <tbody>  <!-- added for better print-ability -->
<?php

 $res = sqlStatement("
select distinct atlas.fname as employee, p.lname, p.fname, fe.date as dos, doc.lname as provider, fac.name as facility, b.code, b.modifier, b.fee as chargeAmount, pr.pr_price, b.justify

from patient_data p
left join form_encounter fe
   on fe.pid=p.pid
left join billing b
   on b.encounter=fe.encounter and b.activity='1'
left join users doc
   on doc.id=fe.provider_id
left join facility fac
   on fac.id = fe.facility_id
left join insurance_data i
   on i.pid=p.pid and i.type='primary'
left join codes c
   on b.code=c.code
left join prices pr
   on pr.pr_id=c.id
left join users atlas
   on atlas.id=b.user
where
p.pid!='1' AND
((fe.provider_id='' or fe.provider_id is null) and b.activity='1' and b.fee>0)
or
(
   b.billed = '0' and p.pid>'1'
and 
(
#Fee not doubled Errors
(
   b.modifier='50' and b.fee=pr.pr_price
)
or
( #no justification
   b.code_type='CPT4' and b.justify=''
)
))
order by atlas.fname
");

 $logstart = 0;
 while ($row = sqlFetchArray($res)) {
?>
 <tr>
      <td align='center'><?php echo text($row['employee']); ?></td>
      <td align='center'><?php echo text($row['lname']); ?></td>
      <td align='center'><?php echo text($row['fname']); ?></td>
      <td align='center'><?php echo text($row['dos']); ?></td>
      <td align='center'><?php echo text($row['provider']); ?></td>
      <td align='center'><?php echo text($row['facility']); ?></td>
      <td align='center'><?php echo text($row['code']); ?></td>
      <td align='center'><?php echo text($row['modifier']); ?></td>
      <td align='center'><?php echo text($row['justify']); ?></td>
      <td align='center'><?php echo text($row['chargeAmount']); ?></td>
      <td align='center'><?php echo text($row['pr_price']); ?></td>

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

