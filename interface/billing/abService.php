<?php

class abService {
   
   const I_DOS = 0;
   const I_LAST = 1;
   const I_FIRST = 2;
   const I_DOB = 3;
   const I_PROVIDER = 4;
   const I_FACILITY_CODE=5;
   const I_CPT=6;
   const I_DESCRIPTION=7;
   const I_MODIFIER=8;
   const I_UNITS=9;
   const I_ICD = 10;
   const I_NDC = 11;
   const I_NDC_SUFFIX=12;
   const I_DOSE_MG=13;
   const I_BASE_UNITS=14;

   public $pid;
   public $lname;
   public $fname;
   public $dob;

   public $providerLastName;
   public $providerId;

   public $facilityId;
   public $facilityCode;

   public $icdCode;
   public $description;
   public $ndcCode;
   public $ndcSuffix;
   public $ndcString;
   public $cptCode;
   public $descr;
   public $modifier='';
   public $units;
   public $price;

   public $error=false;
   public $errorMessage;

   function getPatientId($last, $first)
   {
      $tmp = sqlQuery("SELECT pid from patient_data where upper(fname) = '" .
             strtoupper($first) . "' and upper(lname) = '" . strtoupper($last) . "'");

      return $tmp['pid'];
   }

   function getProviderId($last)
   {
      $tmp = sqlQuery("SELECT id from users where upper(lname) = '" . strtoupper($last) . "'");
      return $tmp['id'];
   }

   function getFacilityId($facilityCode)
   {
      $tmp = sqlQuery("SELECT id from facility where facility_code='" . $facilityCode . "'");
      return $tmp['id'];
   }

   function getStandardPrice($code)
   {
      $tmp = sqlQuery("Select p.pr_price from prices p left join codes c on c.id=p.pr_id " .
                      "where p.pr_level='standard' and c.code='" . $code . "'");
      return $tmp['pr_price'];
   }

   function endsWith($haystack, $needle)
   {
      $length = strlen($needle);
      if ($length == 0) {
         return true;
      }
      return (substr($haystack, -$length) === $needle);
   }

   function fixIcdCode($icdCode)
   {
      $icd10Prefix = 'ICD10|';

     //Add prefix
     $out = $icd10Prefix . $icdCode;
     //Remove training colon
     if($this->endsWith($out, ":"))
     {
        $out = substr($out, strlen($out)-1);
     }

     //convert remaining colons
     return str_replace(":", ":".$icd10Prefix, $out);
   }

   function CheckForErrors()
   {
      $this->error=false;

      if(!$this->pid)
      {
         $this->error=true;
	 $this->errorMessage = "Error - can't find patient: " . $this->lname . ", " . $this->fname . ". Check the spelling.";
      }

      if(!$this->facilityId)
      {
         $this->error=true;
	 $this->errorMessage = "The facility Code: " . $this->facilityCode . " was not found.";
      }

      if(!$this->providerId)
      {
         $this->error=true;
	 $this->errorMessage = "Error - can't find provider: " . $this->providerLastName . ". Check the spelling.";
      }
   }

   function parseData($svc)
   {
      $this->dos = date('Y-m-d 00:00:00', strtotime($svc[self::I_DOS]));

      $this->lname = $svc[self::I_LAST]; 
      $this->fname = $svc[self::I_FIRST];
      $this->pid = $this->getPatientId($this->lname, $this->fname);
      
      $this->dob = $svc[self::I_DOB];
      $this->providerLastName = $svc[self::I_PROVIDER];
      $this->providerId = $this->getProviderId($this->providerLastName);

      $this->facilityCode=$svc[self::I_FACILITY_CODE];
      $this->facilityId=$this->getFacilityId($this->facilityCode);
      
      $this->cptCode=$svc[self::I_CPT];
      $this->descr=$svc[self::I_DESCRIPTION];
      $this->modifier=$svc[self::I_MODIFIER];
      $this->units=$svc[self::I_UNITS];
      $this->icdCode=$svc[self::I_ICD];
      $this->icdCode=$this->fixIcdCode($this->icdCode);

      $this->ndcCode=$svc[self::I_NDC];
      $this->ndcSuffix=$svc[self::I_NDC_SUFFIX];
      $this->price=$this->getStandardPrice($this->cptCode) * $this->units;

      $this->ndcString = "N4" . $this->ndcCode . "   " . $this->ndcSuffix;

      $this->CheckForErrors();
   }

   function isSameVisit($svc2)
   {
      return ($this->pid==$svc2->pid && $this->dos==$svc2->dos);
   }

   function __construct($data) {
      $this->parseData($data);
   }
   
}
