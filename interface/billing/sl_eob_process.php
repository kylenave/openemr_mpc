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

require_once("../globals.php");
require_once("$srcdir/invoice_summary.inc.php");
require_once("$srcdir/sl_eob.inc.php");
require_once("$srcdir/classes/InsuranceCompany.class.php");
require_once("$srcdir/parse_era.inc.php");
require_once("claim_status_codes.php");
require_once("adjustment_reason_codes.php");
require_once("remark_codes.php");
require_once("$srcdir/formatting.inc.php");
require_once("$srcdir/billing.inc");

    $debug = $_GET['debug'] ? 1 : 0; // set to 1 for debugging mode
    $paydate = parse_date($_GET['paydate']);
    $encount = 0;

    $last_ptname = '';
    $last_invnumber = '';
    $last_code = '';
    $invoice_total = 0.00;
    $InsertionId;//last inserted ID of 

///////////////////////// Assorted Functions /////////////////////////

    function parse_date($date) {
        $date = substr(trim($date), 0, 10);
        if (preg_match('/^(\d\d\d\d)\D*(\d\d)\D*(\d\d)$/', $date, $matches)) {
            return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
        }
        return '';
    }

    function writeClaimSummary($bgcolor, $class, $state)
    {
       $dline = 

            " <tr bgcolor='$bgcolor'>\n" .
            "  <td class='$class' colspan='4'>&nbsp;</td>\n" .
            "  <td class='$class'>$state</td>\n" .
            "  <td class='$class' colspan='2'>&nbsp;</td>\n" .
            " </tr>\n";
        echo $dline;
   }

    function writeMessageLine($bgcolor, $class, $description) {
        $dline =
            " <tr bgcolor='$bgcolor'>\n" .
            "  <td class='$class' colspan='4'>&nbsp;</td>\n" .
            "  <td class='$class'>$description</td>\n" .
            "  <td class='$class' colspan='2'>&nbsp;</td>\n" .
            " </tr>\n";
        echo $dline;
    }

    function writeDetailLine($bgcolor, $class, $ptname, $invnumber,
        $code, $date, $description, $amount, $balance)
    {
        global $last_ptname, $last_invnumber, $last_code;
        if ($ptname == $last_ptname) $ptname = '&nbsp;';
            else $last_ptname = $ptname;
        if ($invnumber == $last_invnumber) $invnumber = '&nbsp;';
            else $last_invnumber = $invnumber;
        if ($code == $last_code) $code = '&nbsp;';
            else $last_code = $code;
        if ($amount ) $amount  = sprintf("%.2f", $amount );
        if ($balance) $balance = sprintf("%.2f", $balance);
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
    function writeOldDetail(&$prev, $ptname, $invnumber, $dos, $code, $bgcolor) {
        global $invoice_total;
        // $prev['total'] = 0.00; // to accumulate total charges
        ksort($prev['dtl']);
        foreach ($prev['dtl'] as $dkey => $ddata) {
            $ddate = substr($dkey, 0, 10);
            $description = $ddata['src'] . $ddata['rsn'];
            if ($ddate == '          ') { // this is the service item
                $ddate = $dos;
                $description = 'Service Item';
            }
            $amount = sprintf("%.2f", $ddata['chg'] - $ddata['pmt']);
            $invoice_total = sprintf("%.2f", $invoice_total + $amount);
            writeDetailLine($bgcolor, 'olddetail', $ptname, $invnumber,
                $code, $ddate, $description, $amount, $invoice_total);
        }
    }

    // This is called back by parse_era() once per claim.
    //
    function era_callback_check(&$out)
    
    {
    global $InsertionId;//last inserted ID of 
        global $StringToEcho,$debug;
        
        if($_GET['original']=='original')
        {
        $StringToEcho="<br/><br/><br/><br/><br/><br/>";
        $StringToEcho.="<table border='1' cellpadding='0' cellspacing='0'  width='750'>";
        $StringToEcho.="<tr bgcolor='#cccccc'><td width='50'></td><td class='dehead' width='150' align='center'>".htmlspecialchars( xl('Check Number'), ENT_QUOTES)."</td><td class='dehead' width='400'  align='center'>".htmlspecialchars( xl('Payee Name'), ENT_QUOTES)."</td><td class='dehead'  width='150' align='center'>".htmlspecialchars( xl('Check Amount'), ENT_QUOTES)."</td></tr>";
        $WarningFlag=false;
        for ($check_count=1;$check_count<=$out['check_count'];$check_count++)
         {
            if($check_count%2==1)
             {
                $bgcolor='#ddddff';
             }
            else
             {
                $bgcolor='#ffdddd';
             }
             $rs=sqlQ("select reference from ar_session where reference='".$out['check_number'.$check_count]."'");
             if(sqlNumRows($rs)>0)
             {
                $bgcolor='#ff0000';
                $WarningFlag=true;
             }
            $StringToEcho.="<tr bgcolor='$bgcolor'>";
            $StringToEcho.="<td><input type='checkbox'  name='chk".$out['check_number'.$check_count]."' value='".$out['check_number'.$check_count]."' checked/></td>";
            $StringToEcho.="<td>".htmlspecialchars($out['check_number'.$check_count])."</td>";
            $StringToEcho.="<td>".htmlspecialchars($out['payee_name'.$check_count])."</td>";
            $StringToEcho.="<td align='right'>".htmlspecialchars(number_format($out['check_amount'.$check_count],2))."</td>";
            $StringToEcho.="</tr>";
        }
        $StringToEcho.="<tr bgcolor='#cccccc'><td colspan='4' align='center'><input type='submit'  name='CheckSubmit' value='Submit'/></td></tr>";
        if($WarningFlag==true)
            $StringToEcho.="<tr bgcolor='#ff0000'><td colspan='4' align='center'>".htmlspecialchars( xl('Warning, Check Number already exist in the database'), ENT_QUOTES)."</td></tr>";
         $StringToEcho.="</table>";
        }
        else
        {
        for ($check_count=1;$check_count<=$out['check_count'];$check_count++)
         {
        $chk_num=$out['check_number'.$check_count];
        $chk_num=str_replace(' ','_',$chk_num);
        if(isset($_REQUEST['chk'.$chk_num]))
        {
        $check_date=$out['check_date'.$check_count]?$out['check_date'.$check_count]:$_REQUEST['paydate'];
        $post_to_date=$_REQUEST['post_to_date']!=''?$_REQUEST['post_to_date']:date('Y-m-d');
        $deposit_date=$_REQUEST['deposit_date']!=''?$_REQUEST['deposit_date']:date('Y-m-d');
        $InsertionId[$out['check_number'.$check_count]]=arPostSession($_REQUEST['InsId'],$out['check_number'.$check_count],$out['check_date'.$check_count],$out['check_amount'.$check_count],$post_to_date,$deposit_date,$debug);
        
        
        }
        }
        }
    }

    function era_callback(&$out) {
        global $encount, $debug, $claim_status_codes, $adjustment_reasons, $remark_codes;
        global $invoice_total, $last_code, $paydate;
        global $InsertionId;//last inserted ID of

        // Some heading information.
        $chk_123=$out['check_number'];
        $chk_123=str_replace(' ','_',$chk_123);
        if(isset($_REQUEST['chk'.$chk_123])){

        if ($encount == 0) {
            writeMessageLine('#ffffff', 'infdetail', "Payer: " . htmlspecialchars($out['payer_name'], ENT_QUOTES));
            if ($debug) {
                writeMessageLine('#ffffff', 'infdetail', "WITHOUT UPDATE is selected; no changes will be applied.");
            }
        }

        $last_code = '';
        $invoice_total = 0.00;
        $bgcolor = (++$encount & 1) ? "#ddddff" : "#ffdddd";
        list($pid, $encounter, $invnumber) = slInvoiceNumber2($out);

        // Get details, if we have them, for the invoice.
        $inverror = true;
        $codes = array();

	$group = 0;

if ($pid && $encounter) 
{
            // Get invoice data into $arrow or $ferow.
        $ferow = sqlQuery("SELECT e.*, p.fname, p.mname, p.lname " .
          "FROM form_encounter AS e, patient_data AS p WHERE " .
          "e.pid = '$pid' AND e.encounter = '$encounter' AND ".
          "p.pid = e.pid");

        if (empty($ferow)) 
	{
          $pid = $encounter = 0;
          $invnumber = $out['our_claim_id'];
        } else 
	{
          $inverror = false;
          $codes = ar_get_invoice_summary2($pid, $encounter, true);
          // $svcdate = substr($ferow['date'], 0, 10);
        }
}

        // Show the claim status.
        $csc = $out['claim_status_code'];
        $inslabel = 'Ins1';
        if ($csc == '1' || $csc == '19') $inslabel = 'Ins1';
        if ($csc == '2' || $csc == '20') $inslabel = 'Ins2';
        if ($csc == '3' || $csc == '21') $inslabel = 'Ins3';
        $primary = ($inslabel == 'Ins1');

        writeMessageLine($bgcolor, 'infdetail', "Claim status $csc: " . $claim_status_codes[$csc]);

        // Show an error message if the claim is missing or already posted.
        if ($inverror) 
	{
            writeMessageLine($bgcolor, 'errdetail',
                "The following claim is not in our database");
        } else 
	{
            // Skip this test. Claims can get multiple CLPs from the same payer!
            //
            // $insdone = strtolower($arrow['shipvia']);
            // if (strpos($insdone, 'ins1') !== false) {
            //  $inverror = true;
            //  writeMessageLine($bgcolor, 'errdetail',
            //   "Primary insurance EOB was already posted for the following claim");
            // }
        }

        $hasPayment=false;

        if($csc == '1')
	{
            foreach ($out['svc'] as $svc) 
	    {
                if($svc['paid'] !=  0) $hasPayment=true;

                //if($svc['allowed']) $hasPayment=true;

                foreach ($svc['adj'] as $adj) 
                {
                        //Per code and modifier the reason will be showed in the billing manager.
			//45 is a contractual adjustment
                      if($adj['reason_code']=='45' ) $hasPayment=true;
                }

            }
        }

$Denied=false;
        if ($csc == '4' || ($csc=='1' && !$hasPayment)) 
        {
            //Denial case, code is stored in the claims table for display in the billing manager screen with reason explained.
            
            $inverror = true;
            if (!$debug) 
	    {
                if ($pid && $encounter) 
		{
                    $code_value = '';
		    $billing_id_handled=array();

                    foreach ($out['svc'] as $svc) 
		    {

			   // THIS ASSUMES A SINGLE INSTANCE OF A CODE PER ENCOUNTER WHICH IS WRONG
          		   $billing_row = sqlQuery(
				"SELECT id FROM billing WHERE pid = '$pid' " .
				"AND encounter = '$encounter' AND code='" . $svc['code'] . "' AND modifier='" .$svc['mod'] . 
				"' LIMIT 1");

                           $billing_id = $billing_row['id'];

			   //cycle through results until one is found that was not already handled.
                           if( $svc['remark'])
			   { 
			       $remarks = split(":", substr($svc['remark'],0,-1));
			       foreach($remarks as $remark)
                               {
                                  sqlStatement("insert into claim_denials (encounter, billing_id, date, reason, group_code) VALUES " .
                                               "( '$encounter', '$billing_id','$current_date','" . $remark . "','Remarks')"); 
			       }
			   }

                           foreach ($svc['adj'] as $adj) 
                           {
                                 //Per code and modifier the reason will be shown in the billing manager.
                                 $code_value .= $svc['code'].'_'.$svc['mod'].'_'.$adj['group_code'].'_'.$adj['reason_code'].',';
                                 $current_date= date("Ymd");

                                 sqlStatement("insert into claim_denials (encounter, billing_id, date, reason, group_code) VALUES " .
                                               "( '$encounter', '$billing_id','$current_date','" . $adj['reason_code'] . "','" . $adj['group_code'] . "')"); 
                                 
                            }
                    }

		    //This next line returns all but the last character (i.e. removes the last comma)
                    $code_value = substr($code_value,0,-1);

                    //We store the reason code to display it with description in the billing manager screen.
                    //process_file is used as for the denial case file name will not be there, and extra field(to store reason) can be avoided.
                    if($csc=='4') {
                      updateClaim(true, $pid, $encounter, $_REQUEST['InsId'], substr($inslabel,3),7,0,$code_value, $out['payer_name'], $out['payer_claim_id']);
		      arSetDeniedFlag($pid,$encounter,"Claim set to denied state because Claim Status on the ERA was set to '4' or 'Denied'.");
                    }else{
                      updateClaim(true, $pid, $encounter, $_REQUEST['InsId'], substr($inslabel,3),-1,-1,$code_value, $out['payer_name'], $out['payer_claim_id']);
		      arSetDeniedFlag($pid,$encounter,"Claim set to denied state even though Claim Status = '1' because there was no payment and no CO-45 adjustment.");
                    }
		    $Denied=true;
                }
            }
            writeMessageLine($bgcolor, 'errdetail', "Not posting adjustments for denied claims, please follow up manually!");
} // END OF DENIAL CASE
        else if ($csc == '22') 
{
            $inverror = true;
            writeMessageLine($bgcolor, 'errdetail', "Payment reversals are not automated, please enter manually!");
                      updateClaim(true, $pid, $encounter, $_REQUEST['InsId'], substr($inslabel,3),7,0,"Payment Reversal");
}

        if ($out['warnings']) 
	{
            writeMessageLine($bgcolor, 'infdetail', nl2br(rtrim($out['warnings'])));
        }

      // Simplify some claim attributes for cleaner code.
      $service_date = parse_date($out['dos']);
      $check_date      = $paydate ? $paydate : parse_date($out['check_date']);
      $production_date = $paydate ? $paydate : parse_date($out['production_date']);

      $insurance_id = arGetPayerID($pid, $service_date, substr($inslabel, 3));
      if (empty($ferow['lname'])) {
        $patient_name = $out['patient_fname'] . ' ' . $out['patient_lname'];
      } else {
        $patient_name = $ferow['fname'] . ' ' . $ferow['lname'];
      }

      $error = $inverror;

	$billing_id = 0;
        $billing_ids_handled = array();
	$allowToMoveOn=true;

        // This loops once for each service item in this claim.
foreach ($out['svc'] as $svc) 
{

	$group += 1;

        //Get the billing ID for this line item=====================================================================================
	$billing_row = sqlStatement(
				"SELECT id FROM billing WHERE pid = '$pid' " .
				"AND encounter = '$encounter' AND activity='1' and code='" . $svc['code'] . "' AND modifier='" .$svc['mod'] .  "' ");

        $billing_id=0;

        $noneFound=true;

        //Get all of the ID's and select the first one that has not yet been processed.
	while($billing_data = sqlFetchArray($billing_row))
	{
                $noneFound=false;
		if(!in_array($billing_data['id'], $billing_ids_handled))
		{
			$billing_ids_handled[] = $billing_data['id'];
			$billing_id = $billing_data['id'];
		}
	}

        if($noneFound)
        {

	    $billing_row = sqlStatement(
				"SELECT id FROM billing WHERE pid = '$pid' " .
				"AND encounter = '$encounter' AND activity='1' AND code='" . $svc['code'] . "' ");

            $billing_id=0;

            $noneFound=true;

	    while($billing_data = sqlFetchArray($billing_row))
	    {
                $noneFound=false;
		if(!in_array($billing_data['id'], $billing_ids_handled))
		{
			$billing_ids_handled[] = $billing_data['id'];
			$billing_id = $billing_data['id'];
		}
	    }  
        }

        if($billing_id==0)
	{
	   //We have already handled everything but there are more line items so reload and start again
           $billing_ids_handled = array();

	   $billing_row = sqlStatement(
				"SELECT id FROM billing WHERE pid = '$pid' " .
				"AND encounter = '$encounter' AND activity='1' and code='" . $svc['code'] . "' AND modifier='" .$svc['mod'] .  "' ");

	   while($billing_data = sqlFetchArray($billing_row))
	   {
		if(!in_array($billing_data['id'], $billing_ids_handled))
		{
			$billing_ids_handled[] = $billing_data['id'];
			$billing_id = $billing_data['id'];
		}
	   }
	}
       //=============================Got the billing ID... this should be moved to a function


      //The codekey used to be code-modifier but now it is the billing_id so that it can be unambiguous
      $codekey = $billing_id;

      $prev = $codes[$codekey];

      $ignoreSvcLine = false;
 
      $codetype = ''; //will hold code type, if exists

      // This reports detail lines already on file for this service item.
      if ($prev) 
	{

                if($csc=='2' or $csc=='20')
                {
                   //This is a secondary so any adjustment codes are historical and to be ignored

                   writeOldDetail($prev, $patient_name, $invnumber, $service_date, $svc['code'], $bgcolor);

		    $ic = new InsuranceCompany($out['payer_id']);

                   if($svc['adj'])
		   {
                        if($ic->get_ins_type_code() !='3') $ignoreSvcLine=true;
                   }else
                   {
                      //This should be the actual secondary payment
                      $codetype = $codes[$codekey]['code_type']; //store code type
                      //writeOldDetail($prev, $patient_name, $invnumber, $service_date, $svc['code'], $bgcolor);
                      
                      //unset($codes[$codekey]);
                   }
                }
                else
                {

                   $codetype = $codes[$codekey]['code_type']; //store code type
                   writeOldDetail($prev, $patient_name, $invnumber, $service_date, $svc['code'], $bgcolor);
                   // Check for sanity in amount charged.
                   $prevchg = sprintf("%.2f", $prev['chg'] + $prev['adj']);

                   if ( $prevchg != abs($svc['chg']) && 
			abs($svc['chg']) != $prev['chg'] &&
			$svc['chg'] != ($prev['chg'] - $svc['allowed']) 
                      ) 
		   {
                        writeMessageLine($bgcolor, 'errdetail',
                                 "EOB charge amount " . $svc['chg'] . " for this code (" . $svc['code']. ") does not match our invoice: " . $prev['chg'] . "," . $prev['adj'] );
                        //$error = true;
                   }

                // Check for already-existing primary remittance activity.
                // Removed this check because it was not allowing for copays manually
                // entered into the invoice under a non-copay billing code.
                /****
                if ((sprintf("%.2f",$prev['chg']) != sprintf("%.2f",$prev['bal']) ||
                    $prev['adj'] != 0) && $primary)
                {
                    writeMessageLine($bgcolor, 'errdetail',
                        "This service item already has primary payments and/or adjustments!");
                    $error = true;
                }
                ****/

                }
            }

            // If the service item is not in our database...
            else {

                // This is not an error. If we are not in error mode and not debugging,
                // insert the service item into SL.  Then display it (in green if it
                // was inserted, or in red if we are in error mode).
/*
                $description = "CPT4:$codekey Added by $inslabel $production_date";
                if (!$error && !$debug) {
                   arPostCharge($pid, $encounter, 0, 0, 1, $service_date,
                                $codekey, $description, $debug,'',$codetype);
                }
                //$invoice_total += $svc['chg'];
                $class = $error ? 'errdetail' : 'newdetail';
                writeDetailLine($bgcolor, $class, $patient_name, $invnumber,
                    $codekey, $production_date, $description,
                    $svc['chg'], ($error ? '' : $invoice_total));
*/
            }

            $class = $error ? 'errdetail' : 'newdetail';

            // Report Allowed Amount.
            if ($svc['allowed']) {
                // A problem here is that some payers will include an adjustment
                // reflecting the allowed amount, others not.  So here we need to
                // check if the adjustment exists, and if not then create it.  We
                // assume that any nonzero CO (Contractual Obligation) or PI
                // (Payer Initiated) adjustment is good enough.
                $contract_adj = sprintf("%.2f", $svc['chg'] - $svc['allowed']);
                
		foreach ($svc['adj'] as $adj) 
		{
                    if (($adj['group_code'] == 'CO' || $adj['group_code'] == 'PI') && $adj['amount'] != 0)
                        $contract_adj = 0;
                }
                
		if ($contract_adj > 0) 
		{
                    $svc['adj'][] = array('group_code' => 'CO', 'reason_code' => 'A2', 'amount' => $contract_adj);
                }
                
		writeMessageLine($bgcolor, 'infdetail',
                    'Allowed amount is ' . sprintf("%.2f", $svc['allowed']));
            }

            // Report miscellaneous remarks.
            if ($svc['remark']) {
                $rmk = $svc['remark'];
                writeMessageLine($bgcolor, 'infdetail', "$rmk: " . $remark_codes[$rmk]);
            }

            // Post and report the payment for this service item from the ERA.
            // By the way a 'Claim' level payment is probably going to be negative,
            // i.e. a payment reversal.
            if ($svc['paid']) 
	    {
                if (!$error && !$debug) 
		{
                   arPostPayment($pid, $encounter,$InsertionId[$out['check_number']], $svc['paid'],//$InsertionId[$out['check_number']] gives the session id
                                 $svc['code'], $svc['mod'], substr($inslabel,3), $out['check_number'], $debug,'',$codetype, $group, $billing_id);
                }
                $invoice_total -= $svc['paid'];
                $description = "$inslabel/" . $out['check_number'] . ' payment';

                if ($svc['paid'] < 0)
                {  
                   $description .= ' reversal';
                }

                writeDetailLine($bgcolor, $class, $patient_name, $invnumber,
                    $svc['code'], $check_date, $description,
                    0 - $svc['paid'], ($error ? '' : $invoice_total));

		//unset($codes[$codekey]);
            }

            // Post and report adjustments from this ERA.  Posted adjustment reasons
            // must be 25 characters or less in order to fit on patient statements.

            $adjAmounts = array();

//echo $pid . "." . $encounter . "  Counts: Adjustments - " . count($svc['adj']) . "   Payments - " . count($svc['paid']) . "<br>\n";
//foreach ($svc['adj'] as $adj) { echo $adj['amount'] . " "; }
//echo "<br><br>";

		$acceptableAdjustCodes=Array();

		$acceptableAdjustCodes[]='S0020';
		$acceptableAdjustCodes[]='A4550';
		$acceptableAdjustCodes[]='A4220';
		$acceptableAdjustCodes[]='77002';
		$acceptableAdjustCodes[]='Q9966';
	    $totalAdjAmount = 0.0;
            foreach ($svc['adj'] as $adj) 
	    {
                if($adj['amount'] > 0)
                {
		   $totalAdjAmount += $adj['amount'];
                }
	    }

/*
                if($svc['chg'] <= $totalAdjAmount && !in_array($svc['code'], $acceptableAdjustCodes))
                {
                    $description_prefix = 'Zeroed orig adj of $' . $adj['amount'] . "|";
                    $adj['amount']= 0.0;
                    $adjustmentFlag='*';
                    arSetDeniedFlag($pid,$encounter, "Claim Set to Denied because there was an adjustment for the full charge amount or more and it was not on the approved code list (i.e. S0020, A4550, etc)");
                    $Denied=true;
                }
*/	    
	    $allowToMoveOn=true;
            foreach ($svc['adj'] as $adj) 
	    {

		$postAdjustAmount = $adj['amount'];
                $description = $adj['group_code'].$adj['reason_code'] . ': ' . $adjustment_reasons[$adj['reason_code']];

		if($adj['amount'] < 0 && (!$ignoreSvcLine))
		{
		    arSetDeniedFlag($pid,$encounter, "Claim set to denied state because there is an adjustment with a negative value");
		    $Denied=true;
		}


                if((!$ignoreSvcLine) && $svc['chg'] <= $adj['amount'] && !in_array($svc['code'], $acceptableAdjustCodes) and $adj['group_code']!= 'PR')
                {
                    arSetDeniedFlag($pid, $encounter, "Claim Set to Denied because there was an adjustment for the full charge amount or more and it was not on the approved code list (i.e. S0020, A4550, etc)");
		    $Denied=true;
                }


		//PR Responsibility adjustments should be ignored as should secondary
                if ($adj['group_code'] == 'PR' || !$primary) 
		{
                    if ($primary) 
		    {
                        $reason = "$inslabel ptresp: "; // Reasons should be 25 chars or less.
                        if ($adj['reason_code'] == '1') $reason = "$inslabel dedbl: ";
                        else if ($adj['reason_code'] == '2') $reason = "$inslabel coins: ";
                        else if ($adj['reason_code'] == '3') $reason = "$inslabel copay: ";
                        $description .= sprintf(" ($%.2f)", $adj['amount']);
                    }
                    else 
		    {
                        $reason = "$inslabel note " . $adj['group_code'].$adj['reason_code'] . ': ';
		        $postAmount = 0;

		        if($adj['group_code']=='CO'&&$adj['reason_code']=='45')
                        {
		           $postAmount = $adj['amount'];
                        }

		        if($adj['reason_code']=='178')
                        {
		           $allowToMoveOn=true;
                        }else{
		           $allowToMoveOn=false;
			}
                    }

                    $reason .= sprintf("(%.2f)", $adj['amount']);
		    $reason .= " {$allowedToMoveOn} ";
                    // Post a zero-dollar adjustment just to save it as a comment.


                    if (!$error && !$debug) 
		    {
                        arPostAdjustment($pid, $encounter, $InsertionId[$out['check_number']], $postAmount, $svc['code'], $svc['mod'],
                        	substr($inslabel,3), $reason, $debug, '', $codetype, $group, $billing_id);
                    }
                    //writeMessageLine($bgcolor2, $class, $description_prefix . $description . ' ' . sprintf("%.2f", $adj['amount']));
                    $invoice_total -= $postAmount;
                    writeDetailLine($bgcolor, $class, $patient_name, $invnumber, $svc['code'], $production_date, $description, 0 - $postAmount, ($error ? '' : $invoice_total));
                }
                // Other group codes for primary insurance are real adjustments.
                else 
		{
                    if (!$error && !$debug) 
		    {
			$reason = "$inslabel note " . $adj['group_code'].$adj['reason_code'] . ': ';
			$reason .= sprintf("(%.2f).", $adj['amount']);

		        $postAdjAmount = $adj['amount'];
			if($Denied && $svc['paid'] <=0)
			{
			   $postAdjAmount=0;
			}

			if($ignoreSvcLine)
			{
			   $postAdjAmount = 0;
			}
                           arPostAdjustment($pid, $encounter, $InsertionId[$out['check_number']], 
				$postAdjAmount,//$InsertionId[$out['check_number']] gives the session id
                                $svc['code'], $svc['mod'], substr($inslabel,3), 
				$reason, $debug, '', $codetype, $group, $billing_id);
                    }
	
		   if(!$ignoreSvcLine)
		   {
                    $invoice_total -= $adj['amount'];
                    }
                    writeDetailLine($bgcolor, $class, $patient_name, $invnumber, $svc['code'], $production_date, $description, 0 - $adj['amount'], ($error ? '' : $invoice_total));
                }
            }
            

        } // End of service item


// KBN FIX SECONDARY HISTORY DATA HERE ... ALSO RESTORE UNSET LOGIC
        // Report any existing service items not mentioned in the ERA, and
        // determine if any of them are still missing an insurance response
        // (if so, then insurance is not yet done with the claim).
        $insurance_done = $allowToMoveOn;
$insurance_done=true;
        foreach ($codes as $code => $prev) {
      // writeOldDetail($prev, $arrow['name'], $invnumber, $service_date, $code, $bgcolor);
           // writeOldDetail($prev, $patient_name, $invnumber, $service_date, $prev['code'], $bgcolor);
            $got_response = false;
            foreach ($prev['dtl'] as $ddata) 
	    {
                if ($ddata['pmt'] || $ddata['rsn'])
		{ 
		   $got_response = true;
		}
            }
            if (!$got_response) $insurance_done = false;
        }

        $claimState = '[DEBUG] ' . $inslabel;

        // Cleanup: If all is well, mark Ins<x> done and check for secondary billing.
        if (!$debug && $insurance_done) 
	{
            $level_done = 0 + substr($inslabel, 3);

            if($out['crossover']==1)
            {

		if($Denied)
		{
		    writeMessageLine($bgcolor, 'infdetail', 
                      'This claim is processed by Insurance ' . $level_done . 
		      ' and automatically forwarded to Insurance ' . ($level_done+1) .
 		      ' for processing but is now in a DENIED state. ');
		   $claimState='DENIED';
		}else
		{

			//Automatic forward case.So need not again bill from the billing manager screen.
                	sqlStatement("UPDATE form_encounter " .
                      		"SET last_level_closed = $level_done, last_level_billed=".$level_done." WHERE " .
                      		"pid = '$pid' AND encounter = '$encounter'");
              
			writeMessageLine($bgcolor, 'infdetail',
                      		'This claim is processed by Insurance '.$level_done.
				' and automatically forwarded to Insurance '.($level_done+1) .
				' for processing. ');

			if($level_done=='1')
			{
				$claimState = 'Secondary';
			}else
			{
				$claimState = 'Tertiary';
			}
		}
             }
             else 
	     {
                 if($Denied)
		 {
		    	writeMessageLine($bgcolor, 'infdetail',
				"This claim is in a denied state so it will not be moved forward yet.");
			$claimState='DENIED';
		 }else
		 {
			sqlStatement("UPDATE form_encounter " .
                    		"SET last_level_closed = $level_done WHERE " .
                    		"pid = '$pid' AND encounter = '$encounter'");
			$claimState='Patient or Closed';
		 }
             }
             
	     // Check for secondary insurance.
             if (!$Denied && $primary && arGetPayerID($pid, $service_date, 2)) 
	     {
                 arSetupSecondary($pid, $encounter, $debug,$out['crossover']);
              
                 if($out['crossover']<>1)
                 {
                     writeMessageLine($bgcolor, 'infdetail',
                        'This claim is now re-queued for secondary paper billing');
                 }
		 $claimState = 'Secondary';
             }
        }else if(!$insurance_done)
	{
            $level_done = 0 + substr($inslabel, 3);
  	    if($level_done=='1')
	    {
		$claimState = 'Primary';
	    }else
	    {
		$claimState = 'Secondary';
	    }

	    if($Denied){
	       $claimState='DENIED';
	    }
	}

        //Write out claim summary line
	$openemr_state = ar_responsible_party($pid, $encounter);

        if($openemr_state==-1)
        {
           $claimState='Patient or Closed';
	   if($Denied) arClearDeniedFlag($pid,$encounter);
	}
        writeClaimSummary('#ccccdd', 'summary', 'This claim state is now: ' . $claimState . ' (' . $openemr_state . ')' );

	if( ($openemr_state == '1' && $claimState != 'Primary') || 
	    ($openemr_state=='2' && $claimState != 'Secondary') ||
	    ($openemr_state=='3' && $claimState != 'Tertiary') ||
	    ($openemr_state=='0' && $claimState != 'Patient or Closed') ||
	    ($openemr_state=='-1' && $claimState != 'Patient or Closed'))
	{
            writeClaimSummary('#ee8888', 'summary', 'Claim state is conflicted.' );
	}

        }
    }

/////////////////////////// End Functions ////////////////////////////

    $info_msg = "";

    $eraname = $_GET['eraname'];
    if (! $eraname) die(xl("You cannot access this page directly."));

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
        if (!$fhreport) die(xl("Cannot create") . " '$fnreport'");
    }

?>
<html>
<head>
<?php html_header_show();?>
<link rel=stylesheet href="<?php echo $css_header;?>" type="text/css">
<style type="text/css">
 body       { font-family:sans-serif; font-size:8pt; font-weight:normal }
 .dehead    { color:#000000; font-family:sans-serif; font-size:9pt; font-weight:bold }
 .olddetail { color:#000000; font-family:sans-serif; font-size:9pt; font-weight:normal }
 .newdetail { color:#0e8d0e; font-family:sans-serif; font-size:9pt; font-weight:normal }
 .errdetail { color:#dd0000; font-family:sans-serif; font-size:9pt; font-weight:normal }
 .infdetail { color:#0000ff; font-family:sans-serif; font-size:9pt; font-weight:normal }
</style>
<title><?php xl('EOB Posting - Electronic Remittances','e')?></title>
<script language="JavaScript">
</script>
</head>
<body leftmargin='0' topmargin='0' marginwidth='0' marginheight='0'>
<form action="sl_eob_process.php" method="get" >
<center>
<?php
  if($_GET['original']=='original')
  {
  $alertmsg = parse_era_for_check($GLOBALS['OE_SITE_DIR'] . "/era/$eraname.edi", 'era_callback');
  echo $StringToEcho;
    }
    else
    {
    ?>
        <table border='0' cellpadding='2' cellspacing='0' width='100%'>
        
         <tr bgcolor="#cccccc">
          <td class="dehead">
           <?php echo htmlspecialchars( xl('Patient'), ENT_QUOTES) ?>
          </td>
          <td class="dehead">
           <?php echo htmlspecialchars( xl('Invoice'), ENT_QUOTES) ?>
          </td>
          <td class="dehead">
           <?php echo htmlspecialchars( xl('Code'), ENT_QUOTES) ?>
          </td>
          <td class="dehead">
           <?php echo htmlspecialchars( xl('Date'), ENT_QUOTES) ?>
          </td>
          <td class="dehead">
           <?php echo htmlspecialchars( xl('Description'), ENT_QUOTES) ?>
          </td>
          <td class="dehead" align="right">
           <?php echo htmlspecialchars( xl('Amount'), ENT_QUOTES) ?>&nbsp;
          </td>
          <td class="dehead" align="right">
           <?php echo htmlspecialchars( xl('Balance'), ENT_QUOTES) ?>&nbsp;
          </td>
         </tr>
        
        <?php
        global $InsertionId;
        
          $eraname=$_REQUEST['eraname'];
          $alertmsg = parse_era_for_check($GLOBALS['OE_SITE_DIR'] . "/era/$eraname.edi");
          $alertmsg = parse_era($GLOBALS['OE_SITE_DIR'] . "/era/$eraname.edi", 'era_callback');
        if(!$debug)
         {
              $StringIssue=htmlspecialchars( xl("Total Distribution for following check number is not full"), ENT_QUOTES).': ';
              $StringPrint='No';
              foreach($InsertionId as $key => $value)
                {
                    $rs= sqlQ("select pay_total from ar_session where session_id='$value'");
                    $row=sqlFetchArray($rs);
                    $pay_total=$row['pay_total'];
                    $rs= sqlQ("select sum(pay_amount) sum_pay_amount from ar_activity where session_id='$value'");
                    $row=sqlFetchArray($rs);
                    $pay_amount=$row['sum_pay_amount'];
                    
                    if(($pay_total-$pay_amount)<>0)
                    {
                    $StringIssue.=$key.' ';
                    $StringPrint='Yes';
                    }
                }
            if($StringPrint=='Yes')
                echo "<script>alert('$StringIssue')</script>";
         }

         
        ?>
        </table>
<?php
}
?>
</center>
<script language="JavaScript">
<?php
    if ($alertmsg) echo " alert('" . htmlspecialchars($alertmsg, ENT_QUOTES) . "');\n";
?>
</script>
<input type="hidden" name="paydate" value="<?php echo DateToYYYYMMDD($_REQUEST['paydate']);?>" />
<input type="hidden" name="post_to_date" value="<?php echo DateToYYYYMMDD($_REQUEST['post_to_date']);?>" />
<input type="hidden" name="deposit_date" value="<?php echo DateToYYYYMMDD($_REQUEST['deposit_date']);?>" />
<input type="hidden" name="debug" value="<?php echo $_REQUEST['debug'];?>" />
<input type="hidden" name="InsId" value="<?php echo $_REQUEST['InsId'];?>" />
<input type="hidden" name="eraname" value="<?php echo $eraname?>" />
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
