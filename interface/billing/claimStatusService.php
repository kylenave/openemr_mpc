<?php

class abService {
   
   const I_FILE_ID_START = 4;
   const I_FILE_ID_LENGTH = 9;

   const I_CLAIM_ID_START = 15;
   const I_CLAIM_ID_LENGTH= 11;

   const I_INVOICE_NUMBER_START = 27;
   const I_INVOICE_NUMBER_LENGTH = 14;

   const I_PATIENT_NAME_START = 42;
   const I_PATIENT_NAME_LENGTH = 20;
   
   const I_AMOUNT_START = 62;
   const I_AMOUNT_LENGTH = 11;

   const I_PRACTICE_ID_START=74;
   const I_PRACTICE_ID_LENGTH=10;

   const I_PRACTICE_TAXID_START=85;
   const I_PRACTICE_TAXID_LENGTH=9;

   const I_PAYER_ID_START=96;
   const I_PAYER_ID_LENGTH=96;
   
   const I_DATE_START=106;
   const I_DATE_LENGTH=10;

   const I_STATUS_START=143;
   const I_STATUS_LENGTH=8;

   const I_COMMENTS_START=153;

   public $pid;
   public $encounter;
   public $done;
   public $fileId;
   public $claimId;
   public $invoiceNum;
   public $patientName;

   public $amount;
   public $practiceId;

   public $taxId;
   public $payer;

   public $date;
   public $status;
   public $comments;

   public $error=false;
   public $errorMessage;

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
      '$this->comments')");
   }

   function processInvoiceNumber()
   {
      $pid=0;
      $encounter=0;

      $data = trim($this->invoiceNum, "-");
      if(sizeof($data) == 2)
      {
         $pid = $data[0];
         $encounter=$data[1];
      }
   }

   function CheckForErrors()
   {
      $this->error=false;

      if($pid==0 || $pid=='MPC')
      {
         $this->errorMessage = "PID could not be determined from invoice number";
         $this->error = true;
      }

      if($encounter==0)
      {
         $this->errorMessage = "Encounter could not be determined from invoice number";
         $this->error = true;
      }
   }

   function isAccepted()
   {
      return ($this->status=="ACCEPTED");
   }

   function parseData($claimStatus)
   {
      $tmpfileId= trim(substr($claimStatus, I_FILE_ID_START, I_FILE_ID_LENGTH ));
      $tmpcomments = trim(substr($claimStatus,I_COMMENTS_START));

      if(!empty($tmpfileId))
      {
         $this->fileId = $tmpfileId;
         $this->comments = $tmpcomments;
         $this->done = false;

      }else if (!empty($tmpcomments))
      {
         $this->comments .= " " . $tmpcomments;
      }else{
         $this->done = true;
      }

      $this->claimId = trim(substr($claimStatus, I_CLAIM_ID_START, I_CLAIM_ID_LENGTH ));
      $this->invoiceNum = trim(substr($claimStatus, I_INVOICE_NUMBER_START, I_INVOICE_NUMBER_LENGTH ));
      processInvoiceNumber();
      $this->patientName = trim(substr($claimStatus, I_PATIENT_NAME_START, I_PATIENT_NAME_LENGTH ));
      $this->amount = trim(substr($claimStatus,I_AMOUNT_START ,I_AMOUNT_LENGTH ));
      $this->practiceId = trim(substr($claimStatus, I_PRACTICE_ID_START,I_PRACTICE_ID_LENGTH ));
   
      $this->taxId = trim(substr($claimStatus,I_PRACTICE_TAXID_START ,I_PRACTICE_TAXID_LENGTH ));
      $this->payer = trim(substr($claimStatus, I_PAYER_ID_START ,I_PAYER_ID_LENGTH ));
   
      $this->date = trim(substr($claimStatus, I_DATE_START, I_DATE_LENGTH));
      $this->status = trim(substr($claimStatus, I_STATUS_START, I_STATUS_LENGTH));
  
      $this->CheckForErrors();

      if($this->done && !$this->errors)
      {
         //Insert the status
         insertStatus();
      }
   }

   function __construct() {
   }
   
}