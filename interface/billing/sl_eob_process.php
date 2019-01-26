<?php
// Copyright (C) 2006-2010 Rod Roark <rod@sunsetsystems.com>
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.

// This processes X12 835 remittances and produces a report.

// Buffer all output so we can archive it to a file.
ob_start();

require_once "../globals.php";
require_once "$srcdir/invoice_summary.inc.php";
require_once "$srcdir/sl_eob.inc.php";
require_once "$srcdir/classes/InsuranceCompany.class.php";
require_once "$srcdir/parse_era.inc.php";
require_once "claim_status_codes.php";
require_once "adjustment_reason_codes.php";
require_once "remark_codes.php";
require_once "$srcdir/formatting.inc.php";
require_once "$srcdir/billing.inc";

$debug = $_GET['debug'] ? 1 : 0; // set to 1 for debugging mode
$error=0;
$primary=0;
$paydate = parse_date($_GET['paydate']);
$encount = 0;
$Denied=0;
$ignoreSvcLine = 0;
$codetype = "";
$bgcolor="";
$patient_name = "";
$invoice_number = 0;

$inslabel="";
$last_ptname = '';
$last_invnumber = '';
$last_code = '';
$invoice_total = 0.00;
$InsertionId; //last inserted ID of

///////////////////////// Assorted Functions /////////////////////////

function parse_date($date)
{
    $date = substr(trim($date), 0, 10);
    if (preg_match('/^(\d\d\d\d)\D*(\d\d)\D*(\d\d)$/', $date, $matches)) {
        return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
    }
    return '';
}

function writeClaimSummary($class, $state)
{
    global $bgcolor;

    $dline =

        " <tr bgcolor='$bgcolor'>\n" .
        "  <td class='$class' colspan='4'>&nbsp;</td>\n" .
        "  <td class='$class'>$state</td>\n" .
        "  <td class='$class' colspan='2'>&nbsp;</td>\n" .
        " </tr>\n";
    echo $dline;
}

function writeMessageLine($class, $description)
{
    global $bgcolor;

    $dline =
        " <tr bgcolor='$bgcolor'>\n" .
        "  <td class='$class' colspan='4'>&nbsp;</td>\n" .
        "  <td class='$class'>$description</td>\n" .
        "  <td class='$class' colspan='2'>&nbsp;</td>\n" .
        " </tr>\n";
    echo $dline;
}

function writeDetailLine($class, $code, $date, $description, $amount, $balance) {

    global $bgcolor, $patient_name, $invoice_number, $last_ptname, $last_invnumber, $last_code;

    $ptname = $patient_name;

    if ($ptname == $last_ptname) {
        $ptname = '&nbsp;';
    } else {
        $last_ptname = $ptname;
    }

    $invnumber = $invoice_number;

    if ($invnumber == $last_invnumber) {
        $invnumber = '&nbsp;';
    } else {
        $last_invnumber = $invnumber;
    }

    if ($code == $last_code) {
        $code = '&nbsp;';
    } else {
        $last_code = $code;
    }

    if ($amount) {
        $amount = sprintf("%.2f", $amount);
    }

    if ($balance) {
        $balance = sprintf("%.2f", $balance);
    }

    $dline =
    " <tr bgcolor='$bgcolor'>\n" .
    "  <td class='$class'>$ptname</td>\n" .
    "  <td class='$class'>$invnumber</td>\n" .
    "  <td class='$class'>$code</td>\n" .
    "  <td class='$class'>" . oeFormatShortDate($date) . "</td>\n" .
    "  <td class='$class'>$description</td>\n" .
    "  <td class='$class' align='right'>" . oeFormatMoney($amount) . "</td>\n" .
    "  <td class='$class' align='right'>" . oeFormatMoney($balance) . "</td>\n" .
        " </tr>\n";
    echo $dline;
}

// This writes detail lines that were already in SQL-Ledger for a given
// charge item.
//
function writeOldDetail(&$prev, $dos, $code)
{
    global $invoice_total, $bgcolor;
    // $prev['total'] = 0.00; // to accumulate total charges
    ksort($prev['dtl']);
    foreach ($prev['dtl'] as $dkey => $ddata) {
        $ddate = substr($dkey, 0, 10);
        //$description = (isset($ddata['src']) && isset($ddata['rsn'])) ? $ddata['src'] .':'. $ddata['rsn'] : $ddata['rsn'];
        $description = $ddata['rsn'];
        if ($ddate == '          ') { // this is the service item
            $ddate = $dos;
            $description = 'Service Item';
	}
        $amount = sprintf("%.2f", (isset($ddata['chg']) ? $ddata['chg'] : 0 ) - (isset($ddata['pmt']) ? $ddata['pmt'] : 0));
        $invoice_total = sprintf("%.2f", $invoice_total + $amount);
        writeDetailLine('olddetail', $code, $ddate, $description, $amount, $invoice_total);
    }
}

function writeEncounterNote($encounter_id, $note)
{
   $userId = '1';
   $datetime = date('Y-m-d H:i:s');
   sqlInsert('INSERT INTO billing_notes (date, encounter, user_id, comments) VALUES (?,?,?,?)', array($datetime, $encounter_id, $userId, $note));
}

function getBillingId($pid, $encounter, $code, $modifier, &$billing_ids_handled)
{

    logMessage("Looking for BillingID: With arguments pid: " . $pid . "  Encounter:" . $encounter . "  Code:" . $code . " modifier:" . $modifier);

    //Get the billing ID for this line item=====================================================================================
    $billing_row_sql = "SELECT id FROM billing WHERE pid = '$pid' " .
        "AND encounter = '$encounter' AND activity='1' and code='" . $code . "' AND modifier='" . $modifier . "' ";

    $billing_row = sqlStatement($billing_row_sql);
    logMessage("Looking for billing ID with sql: " . $billing_row_sql);

    $billing_id = 0;

    $noneFound = true;

    //Get all of the ID's and select the first one that has not yet been processed.
    while (($billing_data = sqlFetchArray($billing_row)) && $noneFound) {
        logMessage("Looking for BillingID 1: checking billing id - " . $billing_data['id']);
        if (!in_array($billing_data['id'], $billing_ids_handled)) {
            $billing_id = $billing_data['id'];
            $noneFound = false;
        }
    }

    if ($noneFound) {

        $billing_row_sql = "SELECT id FROM billing WHERE pid = '$pid' " .
            "AND encounter = '$encounter' AND activity='1' AND code='" . $code . "' ";

        $billing_row = sqlStatement($billing_row_sql);
        logMessage("Looking for billing ID with sql: " . $billing_row_sql);
       
         while (($billing_data = sqlFetchArray($billing_row)) && $noneFound) {
            logMessage("Looking for BillingID 2: checking billing id - " . $billing_data['id']);
            if (!in_array($billing_data['id'], $billing_ids_handled)) {
                $billing_id = $billing_data['id'];
                $noneFound = false;
            }
        }
    }

    if ($noneFound) {
        //We have already handled everything but there are more line items so reload and start again
        $billing_ids_handled = array();

        $billing_row = sqlStatement(
            "SELECT id FROM billing WHERE pid = '$pid' " .
            "AND encounter = '$encounter' AND activity='1' and code='" . $code . "' AND modifier='" . $modifier . "' ");

        while (($billing_data = sqlFetchArray($billing_row))) {
            logMessage("Looking for BillingID 3: checking billing id - " . $billing_data['id']);
            if (!in_array($billing_data['id'], $billing_ids_handled)) {
                $billing_id = $billing_data['id'];
                $noneFound = false;
            }
        }
    }

    if (!$noneFound) {
        $billing_ids_handled[] = $billing_id;
    }

    return $billing_id;
}

function logMessage($msg)
{
    $MessageLoggingOn = true;

    if ($MessageLoggingOn) {
        error_log($msg);
    }
}

// This is called back by parse_era() once per claim.
//
function era_callback_check(&$out)
{
    global $InsertionId; //last inserted ID of
    global $StringToEcho, $debug, $bgcolor;

    if ($_GET['original'] == 'original') {
        $StringToEcho = "<br/><br/><br/><br/><br/><br/>";
        $StringToEcho .= "<table border='1' cellpadding='0' cellspacing='0'  width='750'>";
        $StringToEcho .= "<tr bgcolor='#cccccc'><td width='50'></td><td class='dehead' width='150' align='center'>" .
        htmlspecialchars(xl('Check Number'), ENT_QUOTES) . "</td><td class='dehead' width='400'  align='center'>" .
        htmlspecialchars(xl('Payee Name'), ENT_QUOTES) . "</td><td class='dehead'  width='150' align='center'>" .
        htmlspecialchars(xl('Check Amount'), ENT_QUOTES) . "</td></tr>";
        $WarningFlag = false;
        for ($check_count = 1; $check_count <= $out['check_count']; $check_count++) {
            if ($check_count % 2 == 1) {
                $bgcolor = '#ddddff';
            } else {
                $bgcolor = '#ffdddd';
            }
            $rs = sqlQ("select reference from ar_session where reference='" . $out['check_number' . $check_count] . "'");
            if (sqlNumRows($rs) > 0) {
                $bgcolor = '#ff0000';
                $WarningFlag = true;
            }
            $StringToEcho .= "<tr bgcolor='$bgcolor'>";
            $StringToEcho .= "<td><input type='checkbox'  name='chk" . $out['check_number' . $check_count] . "' value='" . $out['check_number' . $check_count] . "' checked/></td>";
            $StringToEcho .= "<td>" . htmlspecialchars($out['check_number' . $check_count]) . "</td>";
            $StringToEcho .= "<td>" . htmlspecialchars($out['payer_name' . $check_count] . "(" .  $out['payer_tax_id'.$check_count] . ")") . "</td>";
            $StringToEcho .= "<td align='right'>" . htmlspecialchars(number_format($out['check_amount' . $check_count], 2)) . "</td>";
            $StringToEcho .= "</tr>";
        }
        $StringToEcho .= "<tr bgcolor='#cccccc'><td colspan='4' align='center'><input type='submit'  name='CheckSubmit' value='Submit'/></td></tr>";
        if ($WarningFlag == true) {
            $StringToEcho .= "<tr bgcolor='#ff0000'><td colspan='4' align='center'>" . htmlspecialchars(xl('Warning, Check Number already exist in the database'), ENT_QUOTES) . "</td></tr>";
        }

        $StringToEcho .= "</table>";
    } else {
        for ($check_count = 1; $check_count <= $out['check_count']; $check_count++) {
            $chk_num = $out['check_number' . $check_count];
            $chk_num = str_replace(' ', '_', $chk_num);
            if (isset($_REQUEST['chk' . $chk_num])) {
                $check_date = $out['check_date' . $check_count] ? $out['check_date' . $check_count] : $_REQUEST['paydate'];
                $post_to_date = $_REQUEST['post_to_date'] != '' ? $_REQUEST['post_to_date'] : date('Y-m-d');
                $deposit_date = $_REQUEST['deposit_date'] != '' ? $_REQUEST['deposit_date'] : date('Y-m-d');
logMessage("Posting Session with payer info: " .  $out['payer_name'.$check_count], $out['payer_tax_id'.$check_count]);

                $payerId = $_REQUEST['InsId'];

                if(!$payerId){
                   $payerId = getPayerIdGuess($out['payer_name'.$check_count]);
                }       

                $InsertionId[$out['check_number' . $check_count]] = arPostSession($_REQUEST['InsId'], $out['check_number' . $check_count],
                    $out['check_date' . $check_count], $out['check_amount' . $check_count], $post_to_date, $deposit_date, $debug, $out['payer_name'.$check_count], $out['payer_tax_id'.$check_count]);

            }
        }
    }
}

function getPayerIdGuess($name)
{
   $sql="select payer_id, count(payer_id) from ar_session where description ='$name' group by payer_id order by count(payer_id) desc limit 1";

   $result = sqlQuery($sql);

   if(empty($result))
   {
      return '0';
   }

   return $result['payer_id'];
}

function getInsuranceLabel($csc)
{
    $inslabel = 'Ins1';
    if ($csc == '1' || $csc == '19') {
        $inslabel = 'Ins1';
    }

    if ($csc == '2' || $csc == '20') {
        $inslabel = 'Ins2';
    }

    if ($csc == '3' || $csc == '21') {
        $inslabel = 'Ins3';
    }

    return $inslabel;
}

function getEncounterData($pid, $encounter)
{
    if (!$pid || !$encounter) {
        return false;
    }

    $ferow = sqlQuery("SELECT e.*, p.fname, p.mname, p.lname " .
        "FROM form_encounter AS e, patient_data AS p WHERE " .
        "e.pid = '$pid' AND e.encounter = '$encounter' AND " .
        "p.pid = e.pid");

    return $ferow;
}

function isDenialException($primaryPayer, $hasAnesthesiaCode)
{
    //Aetna and Coventry don't pay for anesthesia
    $aetnaPayers = array('207', '208', '209', '210', '211', '240', '241');

    if ($primaryPayer && in_array($primaryPayer, $aetnaPayers) && $hasAnesthesiaCode) {
        $ignoreDenial = true;
    }

    return $ignoreDenial;
}

function saveClaimDenials($encounter, $billing_id, $svc)
{
    $current_date = date("Ymd");

    if (isset($svc['remark'])) {
        $remarks = split(":", substr($svc['remark'], 0, -1));

        foreach ($remarks as $remark) {
            sqlStatement("insert into claim_denials (encounter, billing_id, date, reason, group_code) VALUES " .
                "( '$encounter', '$billing_id','$current_date','" . $remark . "','Remarks')");
        }
    }


    foreach ($svc['adj'] as $adj) {
        sqlStatement("insert into claim_denials (encounter, billing_id, date, reason, group_code) VALUES " .
            "( '$encounter', '$billing_id','$current_date','" . $adj['reason_code'] . "','" . $adj['group_code'] . "')");

    }
}

function getDenialReasonCodes($svc)
{
    $code_value = "";
    foreach ($svc['adj'] as $adj) {
        $code_value .= $svc['code'] . '_' . $svc['mod'] . '_' . $adj['group_code'] . '_' . $adj['reason_code'] . ',';
    }
    $code_value = substr($code_value, 0, -1);
    return $code_value;
}

function processDenial($pid, $encounter, $services)
{
    global $debug, $inslabel;

    $code_value = "";

    if ($debug) {
        return;
    }

    if ($pid && $encounter) {
        unset($code_value);
        $billing_id_handled = array();

        foreach ($services as $svc) {
            $billing_id = getBillingId($pid, $encounter, $svc['code'], $svc['mod'], $billing_id_handled);
            saveClaimDenials($encounter, $billing_id, $svc);
            $code_value = (isset($code_value) ? $code_value . "," : "") . getDenialReasonCodes($svc);
        }

        updateClaim(true, $pid, $encounter, $_REQUEST['InsId'], substr($inslabel, 3), 7, 0, $code_value, $out['payer_name'], $out['payer_claim_id']);
        arSetDeniedFlag($pid, $encounter, "Claim set to denied state because Claim Status on the ERA was set to '4' or 'Denied'.");
    }

    writeMessageLine('errdetail', "Not posting adjustments for denied claims, please follow up manually!");
}

function _getPatientName($out, $ferow)
{
    if (empty($ferow['lname'])) {
        return $out['patient_fname'] . ' ' . $out['patient_lname'];
    }

    return $ferow['fname'] . ' ' . $ferow['lname'];
}

function isAnesthesiaCode($code)
{
    $anesthesiaCodes = array('00300', '00400', '00800', '01200',
        '01250', '01380', '01462', '01610', '01730', '01810', '01935',
        '01936', '01991', '01992');

    return in_array($code, $anesthesiaCodes);
}

//////////////ADJUSTMENT FUNCTIONS

function isWriteoffAllowed($code)
{
    $acceptableAdjustCodes = array('S0020', 'A4550', 'A4220', '77002', '77003', 'Q9966');
    return in_array($code, $acceptableAdjustCodes);
}

function processPatientResponsibility($pid, $encounter, $billing_id, $out, $svc, $adj, &$description)
{
    global $debug, $InsertionId, $codetype, $inslabel;

    $postAmount = $adj['amount'];
    $reason_code = $adj['reason_code'];

    $reason = "$inslabel ptresp: "; // Reasons should be 25 chars or less.
    if ($adj['reason_code'] == '1') {
        $reason = "$inslabel dedbl: ";
    } else if ($adj['reason_code'] == '2') {
        $reason = "$inslabel coins: ";
    } else if ($adj['reason_code'] == '3') {
        $reason = "$inslabel copay: ";
    } else {
       //Som other PR situation...
       $reason .= $reason_code;
       arSetDeniedFlag($pid, $encounter, "Denied due to unusual PR code");
    }

    $description = $reason . "(" . $postAmount . ")";
    if (!$debug) {
        arPostPatientResponsibility($pid, $encounter, $InsertionId[$out['check_number']], $postAmount, $svc['code'], $svc['mod'],
            substr($inslabel, 3), $reason, $debug, '', $codetype, $reason_code, $billing_id);
    }
}

function processSecondaryAdjustment($pid, $encounter, $billing_id, $out, $svc, $adj, &$postAmount, &$description)
{
    global $debug, $InsertionId, $codetype, $inslabel;

    $postAmount = 0;

    logMessage("Processing secondary adjustment of: " . $adj['amount']);

    if(isset($adj['amount']))
    {
      if(isCO45($adj))
      {
         logMessage("This is a CO 45... checking name: " . $out['payer_name']);
         $allowedSI = array("ILLINOIS COMPTROLLER", "ILLINOIS MEDICAID");
         if(in_array( $out['payer_name'], $allowedSI))
         {
            $postAmount=$adj['amount'];
            logMessage("Its allowed!");
         } 
      } 
    }

    $reason = "$inslabel note " . $adj['group_code'] . $adj['reason_code'] . ': ';

    $reason .= sprintf("(%.2f)", $adj['amount']);
    $description = $reason;

    if (!$debug) {
        arPostAdjustment($pid, $encounter, $InsertionId[$out['check_number']], $postAmount, $svc['code'], $svc['mod'],
            substr($inslabel, 3), $reason, $debug, '', $codetype, 0, $billing_id);
    }
}

function getAdjustDescription($adj)
{
    global $adjustment_reasons;

    return $adj['group_code'] . $adj['reason_code'] . ': ' . $adjustment_reasons[$adj['reason_code']];
}

function isTimelyFiling($adj)
{
    return ( ($adj['group_code'] == 'CO') && ($adj['reason_code'] == '29')) ;
}

function isDuplicate($adj)
{
    return ( ($adj['group_code'] == 'OA') && ($adj['reason_code'] == '18')) ;
}

function isCO45($adj)
{
    return ($adj['group_code']=='CO' && $adj['reason_code'] == '45');
}

function checkAdjust($adj, $group, $reason)
{
    return ($adj['group_code']==$group && $adj['reason_code'] == $reason);
}

function processAdjustments($pid, $encounter, $billing_id, $out, $svc)
{
    global $error, $debug, $Denied, $ignoreSvcLine;
    global $InsertionId, $adjustment_reasons, $invoice_total, $codetype, $inslabel, $bgcolor, $paydate, $primary;

    $production_date = $paydate ? $paydate : parse_date($out['production_date']);

    foreach ($svc['adj'] as $adj) {

        //$postAdjustAmount = $adj['amount'];
        $description = getAdjustDescription($adj);

        $displayCode = $svc['code']."(".$svc['mod'].")";

        if ($adj['amount'] < 0) {
            arSetDeniedFlag($pid, $encounter, "Claim set to denied state because there is an adjustment with a negative value");
            $Denied = true;
        }

        $PatientHasNoteMetSpendDownReqt = ($adj['reason_code'] == '178');
        $isAWriteoff = ($adj['amount'] >= $svc['chg']);
        $patientResponsibility = ($adj['group_code'] == 'PR');

        if (($isAWriteoff && !isWriteoffAllowed($svc['code']))
            && !$patientResponsibility && !$PatientHasNoteMetSpendDownReqt) {
            arSetDeniedFlag($pid, $encounter,
                "Claim Set to Denied because there was an adjustment for the full charge amount " .
                "or more and it was not on the approved code list (i.e. S0020, A4550, etc)");
            $Denied = true;
        }

        //PR Responsibility adjustments should be ignored as should secondary
        if ($patientResponsibility) {
            $description = "";
            processPatientResponsibility($pid, $encounter, $billing_id, $out, $svc, $adj, $description);

            writeDetailLine('infdetail', $displayCode, $production_date,
                $description, 0, ($error ? '' : $invoice_total));

        } else if (!$primary) {
            $postAmount = 0;
            $description="";
            processSecondaryAdjustment($pid, $encounter, $billing_id, $out, $svc, $adj, $postAmount, $description);

            $invoice_total -= $postAmount;

            writeDetailLine('infdetail', $displayCode, $production_date, $description, $postAmount, $invoice_total);
        }
        // Other group codes for primary insurance are real adjustments.
        else {
            $postAdjAmount = $adj['amount'];

            if (!$error && !$debug) {
                $reason = "$inslabel Note " . $adj['group_code'] . $adj['reason_code'] . ': ';
                $reason .= sprintf("(%.2f).", $adj['amount']);

                $postAdjAmount = $adj['amount'];

                if (  isTimelyFiling($adj) || 
                      ($Denied && $svc['paid'] <= 0)
                   ) {
                    $postAdjAmount = 0;
                }

                if ($ignoreSvcLine ) {
                    $postAdjAmount = 0;
                }

                if (isDuplicate($adj))
                {
                   arClearDeniedFlag($pid, $encounter,'Clear denial for duplicate encounters');
                }

                arPostAdjustment($pid, $encounter, $InsertionId[$out['check_number']],
                    $postAdjAmount, $svc['code'], $svc['mod'], substr($inslabel, 3),
                    $reason, $debug, '', $codetype, 0, $billing_id);
            }

            if (!$ignoreSvcLine) {
                $invoice_total -= $postAdjAmount;
            }
            writeDetailLine('infdetail', $displayCode,
                $production_date, $description, 0 - $postAdjAmount, ($error ? '' : $invoice_total));
        }
    }

}

/////////////////////// PAYMENT FUNCTIONS
function processPayments($pid, $encounter, $billing_id, $out, $svc, $prev)
{
    global $error, $debug, $InsertionId, $codetype, $allowed_amount, $invoice_total, $inslabel;

    $actual_paid_amount = $svc['paid'];
    $delta_paid_amount = 0;

        //Get total of adjustments...will need this shortly.
        $totalAdjAmount = 0.0;
        foreach ($svc['adj'] as $adj) {
            if ($adj['amount'] != 0) {
                $totalAdjAmount += $adj['amount'];
            }
        }

        logMessage("Previous state of: " . $svc['code'] . "-  Charge: " . $svc['chg'] . 
            "  Adjustments: " . $prev['adj'] . "   Payments: " . $prev['pay']);

        $prevWrittenOff = abs($svc['chg'] - $prev['adj']) < 0.01;
        $prevPaid = ($prev['pay'] > 0);
        
        $prev_balance = abs($svc['chg'] - ($prev['adj'] + $prev['pay']));
        $new_balance = abs($svc['chg'] - $svc['paid'] - $totalAdjAmount);
        $newBalancePaid = $new_balance < 0.02;


        if ($prevPaid && $newBalancePaid) {
            $delta_paid_amount = $svc['paid'] - $prev['pay'];
            logMessage("This is a restatement with a delta of: " . $delta_paid_amount);
        }

        if (!$error && !$debug) {

            arPostPayment($pid, $encounter, $InsertionId[$out['check_number']], 
                $actual_paid_amount, $svc['code'], $svc['mod'], substr($inslabel, 3), 
                $out['check_number'], $debug, '', $codetype, 0, $billing_id, $allowed_amount);

            if (false && $delta_paid_amount) {
                if ($new_balance < 0.02) {
                    //In this case we wipe out patient responsibility
                    $delta_paid_amount = -($svc['chg'] - $svc['paid'] - $prev['adj']);
                    error_log("Updated Delta Amount to : " . $delta_paid_amount);
                }
                arPostAdjustment($pid, $encounter, $InsertionId[$out['check_number']],
                    -$delta_paid_amount, //$InsertionId[$out['check_number']] gives the session id
                    $svc['code'], $svc['mod'], substr($inslabel, 3),
                    "Payment offset", $debug, '', $codetype, $group, $billing_id);
            }

        }
        $invoice_total -= $svc['paid'];
        $description = "$inslabel/" . $out['check_number'] . ' payment';

        if ($svc['paid'] < 0) {
            $description .= ' reversal';
        }
   return $description; 
}

/////////// ALLOWED AMOUNT ///////
function processAllowedAmount($pid, $encounter, $billing_id, &$svc)
{
    global $allowed_amount, $error, $debug, $codetype;

    $allowed_amount = 0.0;

    if (array_key_exists('allowed', $svc)) {
        $allowed_amount = $svc['allowed'];

        /*
        if (!$error && !$debug) {
            arPostPayment($pid, $encounter, "Allowed Amount", 0.0, //$InsertionId[$out['check_number']] gives the session id
                $svc['code'], $svc['mod'], substr($inslabel, 3), $out['check_number'], $debug, '', $codetype, 0, $billing_id, $allowed_amount);
        }
        */

        // A problem here is that some payers will include an adjustment
        // reflecting the allowed amount, others not.  So here we need to
        // check if the adjustment exists, and if not then create it.  We
        // assume that any nonzero CO (Contractual Obligation) or PI
        // (Payer Initiated) adjustment is good enough.
        $contract_adj = sprintf("%.2f", $svc['chg'] - $svc['allowed']);

        foreach ($svc['adj'] as $adj) {

            if (($adj['group_code'] == 'CO' || $adj['group_code'] == 'PI') && $adj['amount'] != 0) {
                $contract_adj = 0;
            }

        }

        //Add this as an adjustment
        if ($contract_adj > 0) {
            $svc['adj'][] = array('group_code' => 'CO', 'reason_code' => 'A2', 'amount' => $contract_adj);
        }

        writeMessageLine('infdetail', 'Allowed amount is ' . sprintf("%.2f", $svc['allowed']));
    }

}

function era_callback(&$out)
{
    global $encount, $debug, $error, $claim_status_codes, $adjustment_reasons, $remark_codes;
    global $invoice_total, $last_code, $paydate;
    global $InsertionId; //last inserted ID of
    global $patient_name, $invoice_number, $codetype, $inslabel, $allowed_amount;
    global $bgcolor, $primary;
    global $Denied, $ignoreSvcLine;

    $last_code = '';
    $invoice_total = 0.00;
    $error=0;

    // Some heading information.
    $chk_123 = str_replace(' ', '_', $out['check_number']);
    logMessage("Working on check number: " . $chk_123);

    if (!isset($_REQUEST['chk' . $chk_123])) {
        logMessage("Not found check: " . $chk_123 . ". Nothing to process so returning.");
        return;
    }

    if ($encount == 0) {
        writeMessageLine('infdetail', "Payer: " . htmlspecialchars($out['payer_name'], ENT_QUOTES));
        if ($debug) {
            writeMessageLine('infdetail', "WITHOUT UPDATE is selected; no changes will be applied.");
        }
    }

    //Alternate color of encounters in HTML Table
    $bgcolor = (++$encount & 1) ? "#ddddff" : "#ffdddd";

    //Find matching patient and encounter from information available in era
    list($pid, $encounter, $invoice_number) = slInvoiceNumber2($out);

    logMessage("Invoice number found: " . $invoice_number);

    $ferow = getEncounterData($pid, $encounter);

    if ($ferow) {
        $codes = ar_get_invoice_summary2($pid, $encounter, true);
    }

    if (!$ferow || !$codes) {
        $invoice_number = $out['our_claim_id'];
        logMessage("This is not an Atlas Invoice. Nothing to process so returning.");
        return;
    }

    // Show the claim status.
    $csc = $out['claim_status_code'];
    $inslabel = getInsuranceLabel($csc);
    $primary = ($inslabel == 'Ins1');

    writeMessageLine('infdetail', "Claim status $csc: " . $claim_status_codes[$csc]);

    // Show an error message if the claim is missing or already posted.
    if (!$codes) {
        writeMessageLine('errdetail', "The following claim is not in our database");
    }

    $hasPayment = false;
    $hasAnesthesiaCode = false;

    foreach ($out['svc'] as $svc) {
        if ($svc['paid'] > 0) {
            $hasPayment = true;
        }
        $hasAnesthesiaCode |= isAnesthesiaCode($svc['code']);
    }

    $error = false;
    $Denied = false;
    $primaryPayer = $out['payer_id'];

    if ($csc == '4') {
        if (!isDenialException($primaryPayer, $hasAnesthesiaCode)) {
            processDenial($pid, $encounter, $out['svc']);
            $Denied = true;
        }
    }

    if ($csc == '22') {
        $error = true;
        writeMessageLine('errdetail', "Payment reversals are not automated, please enter manually!");
        updateClaim(true, $pid, $encounter, $_REQUEST['InsId'], substr($inslabel, 3), 22, 0, "Payment Reversal");
    }

    if ($out['warnings']) {
        writeMessageLine('infdetail', nl2br("Warning from claim parser: " . rtrim($out['warnings'])));
    }

    // Simplify some claim attributes for cleaner code.
    $service_date = parse_date($out['dos']);
    $check_date = $paydate ? $paydate : parse_date($out['check_date']);
    $production_date = $paydate ? $paydate : parse_date($out['production_date']);
    $insurance_id = arGetPayerID($pid, $service_date, substr($inslabel, 3));
    $patient_name = _getPatientName($out, $ferow);

    $billing_ids_handled = array();
    $allowToMoveOn = true;

    // This loops once for each service item in this claim.
    logMessage("Starting to loop through services...");

    foreach ($out['svc'] as $svc) {

        $displayCode = $svc['code']."(".$svc['mod'].")";

        $billing_id = getBillingId($pid, $encounter, $svc['code'], $svc['mod'], $billing_ids_handled);
        logMessage("Service: " . $svc['code'] . " found as BillingID: " . $billing_id);

        if($billing_id){
           $prev = $codes[$billing_id];
        }else{
           unset($prev);
        }

        $ignoreSvcLine = false;
        $codetype = ''; //will hold code type, if exists

        // This reports detail lines already on file for this service item.
        if (isset($prev)) {
            logMessage("Previous adjudications found.");
            $codetype = $prev['code_type']; //store code type

            if ($csc == '2' or $csc == '20') {
                //This is a secondary so any adjustment codes are historical and to be ignored
                writeOldDetail($prev, $service_date, $displayCode);

                $ic = new InsuranceCompany($out['payer_id']);

                if ($svc['adj']) {
                    if ($ic->get_ins_type_code() != '3') {
                        $ignoreSvcLine = true;
                    }
                }
            } else //NOT A SECONDARY PAYMENT
            {
                writeOldDetail($prev, $service_date, $displayCode);
                $prevchg = sprintf("%.2f", $prev['chg'] + $prev['adj']);

                if ($prevchg != abs($svc['chg']) &&
                    abs($svc['chg']) != $prev['chg'] &&
                    $svc['chg'] != ($prev['chg'] - $svc['allowed'])) {
                    writeMessageLine('errdetail',
                        "EOB charge amount " . $svc['chg'] . " for this code (" . $svc['code'] . ") " . 
                        "does not match our invoice: " . $prev['chg'] . "," . $prev['adj']);
                }
            }
        }

        $class = $error ? 'errdetail' : 'newdetail';

        logMessage("Reporting Previous Amount");

        // Allowed Amount
        processAllowedAmount($pid, $encounter, $billing_id, $svc);

        // Report miscellaneous remarks.
        if (array_key_exists('remark', $svc)) {
            $remarks = explode(":",  $svc['remark']);
            $note="";
            foreach ($remark as $rmk){
               $rmk = $svc['remark'];
               writeMessageLine('infdetail', "$rmk: " . $remark_codes[$rmk]);
               $note .= $rmk . ": " .  $remark_codes[$rmk] . ". ";

            }
            writeEncounterNote($encounter, $note);
        }

        // Post and report the payment for this service item from the ERA.
        // By the way a 'Claim' level payment is probably going to be negative,
        // i.e. a payment reversal.

        ///////// PAYMENTS ////////////
        logMessage("Reporting Payments");
        if ($svc['paid']) {
            $description = processPayments($pid, $encounter, $billing_id, $out, $svc, isset($prev)?$prev:0);

            writeDetailLine('infdetail', $displayCode, $check_date, $description,
            0 - $svc['paid'], $error ? '' : $invoice_total);
        }

        ///////// ADJUSTMENTS ///////////
        logMessage("Reporting Adjustments");
        $allowToMoveOn = true;

        processAdjustments($pid, $encounter, $billing_id, $out, $svc, $inslabel);

    } // End of service item

    $insurance_done = $allowToMoveOn && !$Denied;

    $claimState = '[DEBUG] ' . $inslabel;

    $secondaryPayer = arGetPayerID($pid, $service_date, 2);


    $claimPaid=false;
    if(abs($invoice_total) <= 0.02)
    {
       $claimPaid = true;
    }

    // Cleanup: If all is well, mark Ins<x> done and check for secondary billing.
    if (!$debug && $insurance_done) {
        $level_done = 0 + substr($inslabel, 3);

        if ($out['crossover'] == 1 && $secondaryPayer) {

            if ($Denied) {
                writeMessageLine('infdetail',
                    'This claim is processed by Insurance ' . $level_done .
                    ' and automatically forwarded to Insurance ' . ($level_done + 1) .
                    ' for processing but is now in a DENIED state. ');
                $claimState = 'DENIED';
            } else {

                //Automatic forward case.So need not again bill from the billing manager screen.
                sqlStatement("UPDATE form_encounter " .
                    "SET last_level_closed = $level_done, last_level_billed=" . $level_done . " WHERE " .
                    "pid = '$pid' AND encounter = '$encounter'");

                writeMessageLine('infdetail',
                    'This claim is processed by Insurance ' . $level_done .
                    ' and automatically forwarded to Insurance ' . ($level_done + 1) .
                    ' for processing. ');

                if ($level_done == '1') {
                    $claimState = 'Secondary';
                } else {
                    $claimState = 'Tertiary';
                }
            }
        } else {
            if ($Denied) {
                writeMessageLine('infdetail',
                    "This claim is in a denied state so it will not be moved forward yet.");
                $claimState = 'DENIED';
            } else {
                sqlStatement("UPDATE form_encounter " .
                    "SET last_level_closed = $level_done WHERE " .
                    "pid = '$pid' AND encounter = '$encounter'");
                $claimState = 'Patient or Closed';
            }
        }

        // Check for secondary insurance.
        //KBN TODO: Add check for balance...if zero, set to closed, otherwise go to secondary
        if (!$Denied && $primary && $secondaryPayer && $invoice_total > 0.01) {
            arSetupSecondary($pid, $encounter, $debug, $out['crossover']);

            if ($out['crossover'] != 1) {
                writeMessageLine('infdetail',
                    'This claim is now re-queued for secondary paper billing');
            }
            $claimState = 'Secondary';
        }
    } else if (!$insurance_done) {
        $level_done = 0 + substr($inslabel, 3);
        if ($level_done == '1') {
            $claimState = 'Primary';
        } else {
            $claimState = 'Secondary';
        }

        if ($Denied) {
            $claimState = 'DENIED';
        }
    }

    //Write out claim summary line
    $openemr_state = ar_responsible_party($pid, $encounter);

    if ($openemr_state == -1) {
        $claimState = 'Patient or Closed';
        if ($Denied) {
            arClearDeniedFlag($pid, $encounter);
        }

    }
    writeClaimSummary('summary', 'This claim state is now: ' . $claimState . ' (' . $openemr_state . ')');

    if (($openemr_state == '1' && $claimState != 'Primary') ||
        ($openemr_state == '2' && $claimState != 'Secondary') ||
        ($openemr_state == '3' && $claimState != 'Tertiary') ||
        ($openemr_state == '0' && $claimState != 'Patient or Closed') ||
        ($openemr_state == '-1' && $claimState != 'Patient or Closed')) {
        writeClaimSummary('summary', 'Claim state is conflicted.');
    }

}

/////////////////////////// End Functions ////////////////////////////

$info_msg = "";

$eraname = $_GET['eraname'];
if (!$eraname) {
    die(xl("You cannot access this page directly."));
}

// Open the output file early so that in case it fails, we do not post a
// bunch of stuff without saving the report.  Also be sure to retain any old
// report files.  Do not save the report if this is a no-update situation.
//
if (!$debug) {
    $nameprefix = $GLOBALS['OE_SITE_DIR'] . "/era/$eraname";
    $namesuffix = '';
    for ($i = 1; is_file("$nameprefix$namesuffix.html"); ++$i) {
        $namesuffix = "_$i";
    }
    $fnreport = "$nameprefix$namesuffix.html";
    $fhreport = fopen($fnreport, 'w');
    if (!$fhreport) {
        die(xl("Cannot create") . " '$fnreport'");
    }

}

?>
<html>
<head>
<?php html_header_show();?>
<link rel=stylesheet href="<?php echo $css_header; ?>" type="text/css">
<style type="text/css">
 body       { font-family:sans-serif; font-size:8pt; font-weight:normal }
 .dehead    { color:#000000; font-family:sans-serif; font-size:9pt; font-weight:bold }
 .olddetail { color:#000000; font-family:sans-serif; font-size:9pt; font-weight:normal }
 .newdetail { color:#0e8d0e; font-family:sans-serif; font-size:9pt; font-weight:normal }
 .errdetail { color:#dd0000; font-family:sans-serif; font-size:9pt; font-weight:normal }
 .infdetail { color:#0000ff; font-family:sans-serif; font-size:9pt; font-weight:normal }
</style>
<title><?php xl('EOB Posting - Electronic Remittances', 'e')?></title>
<script language="JavaScript">
</script>
</head>
<body leftmargin='0' topmargin='0' marginwidth='0' marginheight='0'>
<form action="sl_eob_process.php" method="get" >
<center>
<?php
if ($_GET['original'] == 'original') {
    $alertmsg = parse_era_for_check($GLOBALS['OE_SITE_DIR'] . "/era/$eraname.edi", 'era_callback');
    echo $StringToEcho;
} else {
    ?>
        <table border='0' cellpadding='2' cellspacing='0' width='100%'>

         <tr bgcolor="#cccccc">
          <td class="dehead">
           <?php echo htmlspecialchars(xl('Patient'), ENT_QUOTES) ?>
          </td>
          <td class="dehead">
           <?php echo htmlspecialchars(xl('Invoice'), ENT_QUOTES) ?>
          </td>
          <td class="dehead">
           <?php echo htmlspecialchars(xl('Code'), ENT_QUOTES) ?>
          </td>
          <td class="dehead">
           <?php echo htmlspecialchars(xl('Date'), ENT_QUOTES) ?>
          </td>
          <td class="dehead">
           <?php echo htmlspecialchars(xl('Description'), ENT_QUOTES) ?>
          </td>
          <td class="dehead" align="right">
           <?php echo htmlspecialchars(xl('Amount'), ENT_QUOTES) ?>&nbsp;
          </td>
          <td class="dehead" align="right">
           <?php echo htmlspecialchars(xl('Balance'), ENT_QUOTES) ?>&nbsp;
          </td>
         </tr>

        <?php
global $InsertionId;

    $eraname = $_REQUEST['eraname'];
    $alertmsg = parse_era_for_check($GLOBALS['OE_SITE_DIR'] . "/era/$eraname.edi");
    $alertmsg = parse_era($GLOBALS['OE_SITE_DIR'] . "/era/$eraname.edi", 'era_callback');
    if (!$debug) {
        $StringIssue = htmlspecialchars(xl("Total Distribution for following check number is not full"), ENT_QUOTES) . ': ';
        $StringPrint = 'No';
        foreach ($InsertionId as $key => $value) {
            $rs = sqlQ("select pay_total from ar_session where session_id='$value'");
            $row = sqlFetchArray($rs);
            $pay_total = $row['pay_total'];
            $rs = sqlQ("select sum(pay_amount) sum_pay_amount from ar_activity where session_id='$value'");
            $row = sqlFetchArray($rs);
            $pay_amount = $row['sum_pay_amount'];

            if (($pay_total - $pay_amount) != 0) {
                $StringIssue .= $key . ' ';
                $StringPrint = 'Yes';
            }
        }
        if ($StringPrint == 'Yes') {
            echo "<script>alert('$StringIssue')</script>";
        }

    }

    ?>
        </table>
<?php
}
?>
</center>
<script language="JavaScript">
<?php
if ($alertmsg) {
    echo " alert('" . htmlspecialchars($alertmsg, ENT_QUOTES) . "');\n";
}

?>
</script>
<input type="hidden" name="paydate" value="<?php echo DateToYYYYMMDD($_REQUEST['paydate']); ?>" />
<input type="hidden" name="post_to_date" value="<?php echo DateToYYYYMMDD($_REQUEST['post_to_date']); ?>" />
<input type="hidden" name="deposit_date" value="<?php echo DateToYYYYMMDD($_REQUEST['deposit_date']); ?>" />
<input type="hidden" name="debug" value="<?php echo $_REQUEST['debug']; ?>" />
<input type="hidden" name="InsId" value="<?php echo $_REQUEST['InsId']; ?>" />
<input type="hidden" name="eraname" value="<?php echo $eraname ?>" />
</form>
</body>
</html>
<?php
// Save all of this script's output to a report file.
if (!$debug) {
    fwrite($fhreport, ob_get_contents());
    fclose($fhreport);
}
ob_end_flush();
?>
