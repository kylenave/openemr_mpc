<?php

//Patient Payment Posting

// Enter patient name
//   Query db and return selection
//   Select from drop down list

//Load patient balance if any

// Load patient encounters

// For each encounter, check to see if it is in patient balance
//   If so, list each line item. Green for paid, pink for balance due

// Allow user to enter a single payment and break it up - oldest first
// or Allow user to click buttons to apply payments
// Balance goes on patient account

require_once "../../globals.php";
require_once "$srcdir/acl.inc";
require_once "$srcdir/api.inc";
?>

<style>
.ui-autocomplete {
    background: #87ceeb;
    z-index: 2;
}

.autocomplete-items {
  position: absolute;
  border: 1px solid #d4d4d4;
  border-bottom: none;
  border-top: none;
  z-index: 99;
  /*position the autocomplete items to be the same width as the container:*/
  top: 100%;
  left: 0;
  right: 0;
}
.autocomplete-items div {
  padding: 10px;
  cursor: pointer;
  background-color: #fff; 
  border-bottom: 1px solid #d4d4d4; 
}
.autocomplete-items div:hover {
  /*when hovering an item:*/
  background-color: #e9e9e9; 
}
.autocomplete-active {
  /*when navigating through the items using the arrow keys:*/
  background-color: DodgerBlue; 
  color: #ffffff; 
}
</style>

<html>
    <head>
        <?php html_header_show();?>
        <link rel=stylesheet href="<?php echo $css_header; ?>" type="text/css">
        <title><?php xl('EOB Posting - Search', 'e');?></title>
        <script type="text/javascript" src="../../library/textformat.js"></script>

        <script language="JavaScript">
        </script>
        <script src=<?php echo "''$srcdir/public/assets/jquery-min-2-2-0/index.js''"; ?>></script>
        <script src=<?php echo "''$srcdir/public/assets/jquery-ui-1-11-4/jquery-ui.min.js''"; ?>></script>
    </head>

    <body leftmargin='0' topmargin='0' marginwidth='0' marginheight='0'>

    <form method='post' action='patient_payments.php' enctype='multipart/form-data'>

    <div>
        <label>Patient:</label> 
        <input type='text' name='patient_search' value='' class='auto' size='50'/>
    </div>

<?php
    if (isset($_POST['patient'])) {
        $pid=$_POST['patient'];

        

    }
?>
    <table>
        <tr>
            <th>DOS</th>
            <th>Encounter</th>
            <th>Location</th>
            <th>Provider</th>
        </tr>


    </table>

    </form>

    </body>
</html>

<script type="text/javascript">
$(function() {

    //autocomplete
    $(".auto").autocomplete(
    {
        select: function (a,b) {
            top.restoreSession();
            var f = document.forms[0];
            f.patient.value = b.item.pid;
            f.submit();
            $(this).focus();
        },
        source: "get_patient_by_name.php",
        minLength: 4
    });

$('input[name=patient_search]').focus();

});
</script>