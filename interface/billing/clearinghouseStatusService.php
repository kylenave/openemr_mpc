<?php

//-------ERROR CLAIM DETAIL
//------------------------------------------------------------------------------------------------------------------------------------------------------------------
//CLAIM# OA CLAIMID  PATIENT ID        LAST,FIRST          DOB          FROM DOS   TO DOS     CPT     DIAG     TAX ID      ACCNT#        PHYS.ID    PAYER  ERRORS 
//------------------------------------------------------------------------------------------------------------------------------------------------------------------


class clearinghouseStatusService {

   const I_CLAIM_ID_START = 7;
   const I_CLAIM_ID_LENGTH= 10;

   const I_INVOICE_NUMBER_START = 121;
   const I_INVOICE_NUMBER_LENGTH = 13;

   const I_PATIENT_NAME_START = 37;
   const I_PATIENT_NAME_LENGTH = 20;
   
   const I_PAYER_ID_START=146;
   const I_PAYER_ID_LENGTH=6;
   
   const I_COMMENTS_START=153;

   public $pid;
   public $encounter;
   public $done;
   public $claimId;
   public $invoiceNum;
   public $patientName;
   public $payer;

   public $date;
   public $status;
   public $comments;

   public $error=false;
   public $primed = false;
   public $errorMessage;

   public $hasProcessDate = false;
   public $hasRejects = false;
   public $hasAccepts = false;

   function insertStatus()
   {
      sqlQuery(@"INSERT INTO `claim_status`
      (`pid`,
      `encounter`,
      `payer_id`,
      `status`,
      `date`,
      `reason`)
      VALUES
      ('$this->pid',
      '$this->encounter',
      '$this->payer',
      '$this->status',
      '$this->date',
      'CH:$this->comments')");
   }

   function processInvoiceNumber()
   {
      $this->pid=0;
      $this->encounter=0;

      $data = explode("-", $this->invoiceNum);
      if(sizeof($data) == 2)
      {
         $this->pid = $data[0];
         $this->encounter=$data[1];
      }
   }

   function CheckForErrors()
   {
      $this->error=false;

      if($this->pid==0 || $this->pid=='MPC')
      {
         $this->errorMessage = "PID could not be determined from invoice number";
         $this->error = true;
      }

      if($this->encounter==0)
      {
         $this->errorMessage = "Encounter could not be determined from invoice number";
         $this->error = true;
      }
   }

   function isAccepted()
   {
      return ($this->status=="ACCEPTED");
   }

   function parseProcessDate($processDateLine)
   {
      $query = "Date Processed:";
      if(substr(trim($processDateLine), 0, strlen($query)) === $query)
      {
         $dateString = trim(substr($processDateLine, 15, 11));
         $mdy = explode($dateString);
         $this->date = sprintf('%04d%02d%02d', $mdy[2], $mdy[0], $mdy[1]);
         $this->hasProcessDate = true;
      }
   }

   function parseForErrors($line)
   {
      $query ="-------ERROR CLAIM DETAIL";
      if(substr(trim($line), 0, strlen($query)) === $query)
      {
         $this->hasRejects = true;
      }
   }

   function parseForAccepts($line)
   {
      $query ="-------ACCEPTED CLAIM DETAIL";
      if(substr(trim($line), 0, strlen($query)) === $query)
      {
         $this->hasAccepts = true;
      }
   }

   function isClaimLine($line)
   {
      return strpos(substr($line,0,6), ')') !== false;
   }

   function parseData($claimStatus)
   {

      $this->done = false;
      if(!$this->hasProcessDate)
      {
         $this->parseProcessDate($claimStatus);
         return;
      }

      $this->parseForErrors($claimStatus);
      $this->parseForAccepts($claimStatus);
      $this->status = "REJECTED";
      if($this->hasAccepts)
      {
         $this->status = "ACCEPTED";
      }

      if($this->isClaimLine($claimStatus) && ($this->hasAccepts || $this->hasRejects))
      {

         $this->claimId = trim(substr($claimStatus, clearinghouseStatusService::I_CLAIM_ID_START, clearinghouseStatusService::I_CLAIM_ID_LENGTH ));
         $this->patientName = trim(substr($claimStatus, clearinghouseStatusService::I_PATIENT_NAME_START, clearinghouseStatusService::I_PATIENT_NAME_LENGTH ));
         $this->invoiceNum = trim(substr($claimStatus, clearinghouseStatusService::I_INVOICE_NUMBER_START, clearinghouseStatusService::I_INVOICE_NUMBER_LENGTH ));
         $this->processInvoiceNumber();
         $this->payer = trim(substr($claimStatus, clearinghouseStatusService::I_PAYER_ID_START ,clearinghouseStatusService::I_PAYER_ID_LENGTH ));
         $this->comments = trim(substr($claimStatus,clearinghouseStatusService::I_COMMENTS_START));

         $this->CheckForErrors();

         if(!$this->error)
         {
            $this->insertStatus();
            $this->done = true;
         }
      }
   }

   function __construct() {
   }
   
}
