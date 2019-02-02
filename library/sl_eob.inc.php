<?php
  // Copyright (C) 2005-2009 Rod Roark <rod@sunsetsystems.com>
  //
  // This program is free software; you can redistribute it and/or
  // modify it under the terms of the GNU General Public License
  // as published by the Free Software Foundation; either version 2
  // of the License, or (at your option) any later version.

  include_once("patient.inc");
  include_once("billing.inc");
  include_once("invoice_summary.inc.php");

  $chart_id_cash   = 0;
  $chart_id_ar     = 0;
  $chart_id_income = 0;
  $services_id     = 0;


  // Try to figure out our invoice number (pid.encounter) from the
  // claim ID and other stuff in the ERA.  This should be straightforward
  // except that some payers mangle the claim ID that we give them.
  //
  function slInvoiceNumber(&$out) {
    $invnumber = $out['our_claim_id'];
    $atmp = preg_split('/[ -]/', $invnumber);
    $acount = count($atmp);

    $pid = 0;
    $encounter = 0;
    if ($acount == 2) {
      $pid = $atmp[0];
      $encounter = $atmp[1];
    }
    else if ($acount == 3) {
      $pid = $atmp[0];
      $brow = sqlQuery("SELECT encounter FROM billing WHERE " .
        "pid = '$pid' AND encounter = '" . $atmp[1] . "' AND activity = 1");
        
      $encounter = $brow['encounter'];
    }
    else if ($acount == 1) {
      $pres = sqlStatement("SELECT pid FROM patient_data WHERE " .
        "lname LIKE '" . addslashes($out['patient_lname']) . "' AND " .
        "fname LIKE '" . addslashes($out['patient_fname']) . "' " .
        "ORDER BY pid DESC");
      while ($prow = sqlFetchArray($pres)) {
        if (strpos($invnumber, $prow['pid']) === 0) {
          $pid = $prow['pid'];
          $encounter = substr($invnumber, strlen($pid));
          break;
        }
      }
    }

    if ($pid && $encounter) $invnumber = "$pid.$encounter";
    return array($pid, $encounter, $invnumber);
  }

  function slInvoiceNumber2(&$out) {
    //$logFile = "/var/www/openemr/tests/testlog.txt";
    //$currentLog = file_get_contents($logFile);

    $invnumber = $out['our_claim_id'];
    $atmp = preg_split('/[ -]/', $invnumber);
    $acount = count($atmp);

    //$currentLog .= $invnumber . "\n";

    $pid = 0;
    $encounter = 0;
    if ($acount == 2) {
      $pid = $atmp[0];
      $encounter = $atmp[1];
    }
    else if ($acount == 3) {
      $pid = $atmp[0];
      $brow = sqlQuery("SELECT encounter FROM billing WHERE " .
        "pid = '$pid' AND encounter = '" . $atmp[1] . "' AND activity = 1");
        
      $encounter = $brow['encounter'];
    }
    else if ($acount == 1) {
      $pres = sqlStatement("SELECT pid FROM patient_data WHERE " .
        "lname LIKE '" . addslashes($out['patient_lname']) . "' AND " .
        "fname LIKE '" . addslashes($out['patient_fname']) . "' " .
        "ORDER BY pid DESC");
      while ($prow = sqlFetchArray($pres)) {
        if (strpos($invnumber, $prow['pid']) === 0) {
          $pid = $prow['pid'];
          $encounter = substr($invnumber, strlen($pid));
          break;
        }
      }
    }

    //$currentLog .= "SELECT encounter, pid from form_encounter where pid='" . $pid . "' and encounter='" . $encounter . "'\n";
    $testres = sqlStatement("SELECT encounter, pid from form_encounter where pid='" . $pid . "' and encounter='" . $encounter . "'");
    $foundEnc=0;
    while($testrow = sqlFetchArray($testres)){
       $foundEnc++;
    }

    if($foundEnc == 0)
      unset($encounter);

    if(!$encounter){
    //$currentLog .= "Trying my logic... using claim date:" . $out['dos'] . " \n";
    //Compare date of service... if only one then match. If multiple then check for CPT Code
      $pres = sqlStatement("SELECT pid FROM patient_data WHERE " .
        "lname LIKE '" . addslashes($out['patient_lname']) . "' AND " .
        "fname LIKE '" . addslashes($out['patient_fname']) . "' " .
        "ORDER BY pid DESC");
      while ($prow = sqlFetchArray($pres)) {
          //$currentLog .= $prow['pid'] . "\n";
          $currentLog .= "SELECT date, pid, encounter FROM form_encounter where pid = '" . $prow['pid'] . "' and DATE_FORMAT(date, '%Y%m%d')='" . $out['dos'] . "'\n";   
          $eres = sqlStatement("SELECT date, pid, encounter FROM form_encounter where pid = '" . $prow['pid'] . "' and DATE_FORMAT(date, '%Y%m%d')='" . $out['dos'] . "'");   
          $matchingEncounters=array();

          while($erow=sqlFetchArray($eres)) {
             array_push($matchingEncounters, $erow['encounter']);
             //$currentLog .= "Matching Encounter: " . $erow['encounter'] . "\n";
          }

          if(sizeof($matchingEncounters) == 0)
          {
             //$currentLog .= "NOT FOUND\n";
          }

          if(sizeof($matchingEncounters) == 1)
          {
             $pid=$prow['pid'];
             $encounter=$matchingEncounters[0];
             //$currentLog .= "Single encounter found so using that: " . $pid . "  -  " . $encounter . "\n";

          }

          if(sizeof($matchingEncounters) > 1) 
          {
              $matchingBills = array();

              foreach($matchingEncounters as &$mEncounter) {
                 //$currentLog .= "SELECT encounter, code FROM billing where encounter='" . $mEncounter . "' and code='" . $out['svc'][0]['code'] . "'\n" ; 
                 $bres = sqlStatement("SELECT encounter, code FROM billing where encounter='" . $mEncounter . "' and code='" . $out['svc'][0]['code'] . "'" ); 
                 while($brow=sqlFetchArray($bres))
                 {
                    //$currentLog .= "Matched billing record: " . $brow['encounter'] . "\n";
                    array_push($matchingBills, $brow['encounter']);
                 }
              }
              if(sizeof($matchingBills) > 0)
              {
                 $encounter = $matchingBills[0];
                 $pid = $prow['pid'];
                 //$currentLog .= "Set encounter from billing to: " . $encounter . "\n";
              }
          }
      }
    }


    if ($pid && $encounter) $invnumber = "$pid.$encounter";

    //file_put_contents($logFile, $currentLog);

    return array($pid, $encounter, $invnumber);
  }





  // This gets a posting session ID.  If the payer ID is not 0 and a matching
  // session already exists, then its ID is returned.  Otherwise a new session
  // is created.
  //
  function arGetSession($payer_id, $reference, $check_date, $deposit_date='', $pay_total=0) {
    if (empty($deposit_date)) $deposit_date = $check_date;
    if ($payer_id) {
      $row = sqlQuery("SELECT session_id FROM ar_session WHERE " .
        "payer_id = '$payer_id' AND reference = '$reference' AND " .
        "check_date = '$check_date' AND deposit_date = '$deposit_date' " .
        "ORDER BY session_id DESC LIMIT 1");
      if (!empty($row['session_id'])) return $row['session_id'];
    }
    return sqlInsert("INSERT INTO ar_session ( " .
      "payer_id, user_id, reference, check_date, deposit_date, pay_total " .
      ") VALUES ( " .
      "'$payer_id', " .
      "'" . $_SESSION['authUserID'] . "', " .
      "'$reference', " .
      "'$check_date', " .
      "'$deposit_date', " .
      "'$pay_total' " .
      ")");
  }
 
//Find function without insert... 
function arGetSession2($payer_id, $reference, $check_date, $deposit_date='', $pay_total=0) {
    if (empty($deposit_date)) $deposit_date = $check_date;
    if ($payer_id) {
      $row = sqlQuery("SELECT session_id FROM ar_session WHERE " .
        "payer_id = '$payer_id' AND reference = '$reference' AND " .
        "check_date = '$check_date' AND deposit_date = '$deposit_date' " .
        "ORDER BY session_id DESC LIMIT 1");
    return $row['session_id'];
    }
}

function arGetAllSessions($encounter)
{
   //KBN Todo: Complete function to get all sessions for an encounter.
}

  //writing the check details to Session Table on ERA proxcessing
function arPostSession($payer_id,$check_number,$check_date,$pay_total,$post_to_date,$deposit_date,$debug,$description='',$patient_id='0') {
      $query = "INSERT INTO ar_session( " .
      "payer_id,user_id,closed,reference,check_date,pay_total,post_to_date,deposit_date,patient_id,payment_type,adjustment_code,payment_method,description " .
      ") VALUES ( " .
      "'$payer_id'," .
      $_SESSION['authUserID']."," .
      "0," .
      "'$check_number'," .
      "'$check_date', " .
      "$pay_total, " .
      "'$post_to_date','$deposit_date', " .
      "$patient_id,'insurance','insurance_payment','electronic', '$description'" .
        ")";
    if ($debug) {
      echo $query . "<br>\n";
    } else {
     $sessionId=sqlInsert($query);
    return $sessionId;
    }
  }
  
  // Post a payment, new style.
  //
  function arPostPayment($patient_id, $encounter_id, $session_id, $amount, $code, $modifier, $payer_type, $memo, $debug, $time='', $codetype='', $group=-1, $billing_id=-1, $allowed_amount=-1) {
    if (empty($time)) $time = date('Y-m-d H:i:s');

    $codeonly = $code;
    $tmp = strpos($code, ':');
    if ($tmp) 
    {
      $modifier = substr($code, $tmp+1);
      $code = substr($code, 0, $tmp);
    }

    sqlBeginTrans();
    $sequence_no = sqlQuery( "SELECT IFNULL(MAX(sequence_no),0) + 1 AS increment FROM ar_activity WHERE pid = ? AND encounter = ?", array($patient_id, $encounter_id));
    $query = "INSERT INTO ar_activity ( " .
      "pid, encounter, sequence_no, code_type, code, modifier, payer_type, post_time, post_user, " .
      "session_id, memo, pay_amount, billing_id, billing_group, allowed_amount " .
      ") VALUES ( " .
      "'$patient_id', " .
      "'$encounter_id', " .
      "'{$sequence_no['increment']}', " .
      "'$codetype', " .
      "'$code', " .
      "'$modifier', " .
      "'$payer_type', " .
      "'$time', " .
      "'" . $_SESSION['authUserID'] . "', " .
      "'$session_id', " .
      "'$memo', " .
      "'$amount', " .
      "'$billing_id', " .
      "'$group', " .
      "'$allowed_amount' " .
      ")";
    sqlStatement($query);
    sqlCommitTrans();
    return;
  }

  // Post a charge.  This is called only from sl_eob_process.php where
  // automated remittance processing can create a new service item.
  // Here we add it as an unauthorized item to the billing table.
  //
  function arPostCharge($patient_id, $encounter_id, $session_id, $amount, $units, $thisdate, $code, $description, $debug, $codetype='') {
    /*****************************************************************
    // Select an existing billing item as a template.
    $row= sqlQuery("SELECT * FROM billing WHERE " .
      "pid = '$patient_id' AND encounter = '$encounter_id' AND " .
      "code_type = 'CPT4' AND activity = 1 " .
      "ORDER BY id DESC LIMIT 1");
    $this_authorized = 0;
    $this_provider = 0;
    if (!empty($row)) {
      $this_authorized = $row['authorized'];
      $this_provider = $row['provider_id'];
    }
    *****************************************************************/

    if (empty($codetype)) {
      // default to CPT4 if empty, which is consistent with previous functionality.
      $codetype="CPT4";
    }
    $codeonly = $code;
    $modifier = '';
    $tmp = strpos($code, ':');
    if ($tmp) {
      $codeonly = substr($code, 0, $tmp);
      $modifier = substr($code, $tmp+1);
    }

    addBilling($encounter_id,
      $codetype,
      $codeonly,
      $description,
      $patient_id,
      0,
      0,
      $modifier,
      $units,
      $amount,
      '',
      '', 1); //Added the '1' for billing since in this context they were already billed out: KBN
  }

  // Post an adjustment, new style.
  //
  function arPostAdjustment($patient_id, $encounter_id, $session_id, $amount, $code, $modifier, $payer_type, $reason, $debug, $time='', $codetype='', $reason_code='', $account_code='', $group=-1, $billing_id=-1) {
    if (empty($time) || $time=='') $time = date('Y-m-d H:i:s');

    $codeonly = $code;
    $tmp = strpos($code, ':');
    if ($tmp) 
    {
      $modifier = substr($code, $tmp+1);
      $code = substr($code, 0, $tmp);
    }
    
    sqlBeginTrans();
    $sequence_no = sqlQuery( "SELECT IFNULL(MAX(sequence_no),0) + 1 AS increment FROM ar_activity WHERE pid = ? AND encounter = ?", array($patient_id, $encounter_id));
    $query = "INSERT INTO ar_activity ( " .
      "pid, encounter, sequence_no, code_type, code, modifier, payer_type, post_user, post_time, " .
      "session_id, memo, adj_amount, billing_id, billing_group, account_code, reason_code " .
      ") VALUES ( " .
      "'$patient_id', " .
      "'$encounter_id', " .
      "'{$sequence_no['increment']}', " .
      "'$codetype', " .
      "'$code', " .
      "'$modifier', " .
      "'$payer_type', " .
      "'" . $_SESSION['authUserID'] . "', " .
      "'$time', " .
      "'$session_id', " .
      "'$reason', " .
      "'$amount', " .
      "'$billing_id', " .
      "'$group', " .
      "'$account_code', " .
      "'$reason_code' " .
      ")";
    sqlStatement($query);
    sqlCommitTrans();
    return;
  }

  function arPostPatientResponsibility($patient_id, $encounter_id, $session_id, $amount, $code, $modifier, $payer_type, $reason, $debug, $time='', $codetype='', $pr_code=-1, $billing_id=-1) {
    if (empty($time)) $time = date('Y-m-d H:i:s');

    $codeonly = $code;
    $tmp = strpos($code, ':');
    if ($tmp) 
    {
      $modifier = substr($code, $tmp+1);
      $code = substr($code, 0, $tmp);
    }
    
    sqlBeginTrans();
    $sequence_no = sqlQuery( "SELECT IFNULL(MAX(sequence_no),0) + 1 AS increment FROM ar_activity WHERE pid = ? AND encounter = ?", array($patient_id, $encounter_id));
    $query = "INSERT INTO ar_activity ( " .
      "pid, encounter, sequence_no, code_type, code, modifier, payer_type, post_user, post_time, " .
      "session_id, memo, adj_amount, billing_id, pr_code, pr_amount " .
      ") VALUES ( " .
      "'$patient_id', " .
      "'$encounter_id', " .
      "'{$sequence_no['increment']}', " .
      "'$codetype', " .
      "'$code', " .
      "'$modifier', " .
      "'$payer_type', " .
      "'" . $_SESSION['authUserID'] . "', " .
      "'$time', " .
      "'$session_id', " .
      "'$reason', " .
      "'0.0', " .
      "'$billing_id', " .
      "'$pr_code', " .
      "'$amount' " .
      ")";
    sqlStatement($query);
    sqlCommitTrans();
    return;
}


  function arGetPayerID($patient_id, $date_of_service, $payer_type) {
    if ($payer_type < 1 || $payer_type > 3) return 0;
    $tmp = array(1 => 'primary', 2 => 'secondary', 3 => 'tertiary');
    $value = $tmp[$payer_type];
    $query = "SELECT provider FROM insurance_data WHERE " .
      "pid = ? AND type = ? AND date <= ? and (term_date is null or term_date=0 or term_date > ?)" .
      "ORDER BY date DESC LIMIT 1";
    $nprow = sqlQuery($query, array($patient_id,$value,$date_of_service, $date_of_service) );
    if (empty($nprow)) return 0;
    return $nprow['provider'];
  }

  // Make this invoice re-billable, new style.
  //
  function arSetupSecondary($patient_id, $encounter_id, $debug,$crossover=0) {
    if ($crossover==1) {
    //if claim forwarded setting a new status 
    $status=6;
    
    } else {
    
    $status=1;
    
    }
    // Determine the next insurance level to be billed.
    $ferow = sqlQuery("SELECT date, last_level_billed " .
      "FROM form_encounter WHERE " .
      "pid = '$patient_id' AND encounter = '$encounter_id'");
    $date_of_service = substr($ferow['date'], 0, 10);
    $new_payer_type = 0 + $ferow['last_level_billed'];
    if ($new_payer_type < 3 && !empty($ferow['last_level_billed']) || $new_payer_type == 0)
      ++$new_payer_type;

    $new_payer_id = arGetPayerID($patient_id, $date_of_service, $new_payer_type);

    if ($new_payer_id) {
      // Queue up the claim.
      if (!$debug)
        updateClaim(true, $patient_id, $encounter_id, $new_payer_id, $new_payer_type,$status, 5, '', 'hcfa','',$crossover);
    }
    else {
      // Just reopen the claim.
      if (!$debug)
        updateClaim(true, $patient_id, $encounter_id, -1, -1, $status, 0, '','','',$crossover);
    }

    return xl("Encounter ") . $encounter_id . xl(" is ready for re-billing.");
  }

//Clear the denied flag and store the date for tracing purposes
function arClearDeniedFlag($patient_id, $encounter_id,$note='', $userId='1')
{
   sqlStatement("UPDATE form_encounter set external_id='0' where encounter='$encounter_id' and pid='$patient_id'");   

   if($note!='' && $encounter_id)
   {
      $datetime = date('Y-m-d H:i:s');
      sqlInsert('INSERT INTO billing_notes (date, encounter, user_id, comments) VALUES (?,?,?,?)', array($datetime, $encounter_id, $userId, $note));
   }
}

function arSetDeniedFlag($patient_id, $encounter_id, $note='', $userId='1')
{
   sqlStatement("UPDATE form_encounter set external_id='1' where encounter='$encounter_id' and pid='$patient_id'");   

   if($note!='' && $encounter_id)
   {
      $datetime = date('Y-m-d H:i:s');
      sqlInsert('INSERT INTO billing_notes (date, encounter, user_id, comments) VALUES (?,?,?,?)', array($datetime, $encounter_id, $userId, $note));
   }
}

function arClearAuthFlag($patient_id, $encounter_id,$note='', $userId='1')
{
   sqlStatement("UPDATE form_encounter set denial_auth='0' where encounter='$encounter_id' and pid='$patient_id'");   

   if($note!='' && $encounter_id)
   {
      $datetime = date('Y-m-d H:i:s');
      sqlInsert('INSERT INTO billing_notes (date, encounter, user_id, comments) VALUES (?,?,?,?)', array($datetime, $encounter_id, $userId, $note));
   }
}

function arSetAuthFlag($patient_id, $encounter_id, $note='', $userId='1')
{
   sqlStatement("UPDATE form_encounter set denial_auth='1' where encounter='$encounter_id' and pid='$patient_id'");   

   if($note!='' && $encounter_id)
   {
      $datetime = date('Y-m-d H:i:s');
      sqlInsert('INSERT INTO billing_notes (date, encounter, user_id, comments) VALUES (?,?,?,?)', array($datetime, $encounter_id, $userId, $note));
   }
}
function arGetPayerClaimId($encounter_id)
{
   $res = sqlQuery("Select c.payer_claim_id from claims c where c.encounter_id='$encounter_id' and c.version = " .
            "  (select version from claims where encounter_id=c.encounter_id and not payer_claim_id is null and payer_claim_id != '' order by version desc limit 1)");
   if(!$res) return "Not Available";

   return $res['payer_claim_id'];
}
?>
