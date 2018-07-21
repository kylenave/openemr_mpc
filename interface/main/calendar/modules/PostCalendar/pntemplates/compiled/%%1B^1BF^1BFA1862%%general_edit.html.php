<?php /* Smarty version 2.6.29, created on 2018-02-11 11:55:40
         compiled from /var/www/openemr/templates/insurance_numbers/general_edit.html */ ?>
<?php require_once(SMARTY_CORE_DIR . 'core.load_plugins.php');
smarty_core_load_plugins(array('plugins' => array(array('function', 'xl', '/var/www/openemr/templates/insurance_numbers/general_edit.html', 16, false),array('function', 'html_options', '/var/www/openemr/templates/insurance_numbers/general_edit.html', 52, false),)), $this); ?>
<?php if ($this->_tpl_vars['ERROR']): ?>
 <?php echo $this->_tpl_vars['ERROR']; ?>

<?php else: ?>
<form name="provider" method="post" action="<?php echo $this->_tpl_vars['FORM_ACTION']; ?>
">
<!-- it is important that the hidden form_id field be listed first, when it is called it populates any old information attached with the id, this allows for partial edits
                if it were called last, the settings from the form would be overwritten with the old information-->
<input type="hidden" name="form_id" value="<?php echo $this->_tpl_vars['ins']->id; ?>
" />

<table class="showborder" CELLSPACING="0" CELLPADDING="3">

<tr><td colspan="5" style="border-style:none;" class="bold">
   <?php echo $this->_tpl_vars['provider']->get_name_display(); ?>

</td></tr>

<tr  class="showborder_head">
    <th class="small"><?php echo smarty_function_xl(array('t' => 'Company Name'), $this);?>
</th>
    <th class="small"><?php echo smarty_function_xl(array('t' => 'Provider Number'), $this);?>
</th>
    <th class="small"><?php echo smarty_function_xl(array('t' => 'Rendering Provider Number'), $this);?>
</th>
    <th class="small"><?php echo smarty_function_xl(array('t' => 'Group Number'), $this);?>
</th>
</tr>
<?php $_from = $this->_tpl_vars['provider']->get_insurance_numbers(); if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }$this->_foreach['inums'] = array('total' => count($_from), 'iteration' => 0);
if ($this->_foreach['inums']['total'] > 0):
    foreach ($_from as $this->_tpl_vars['num_set']):
        $this->_foreach['inums']['iteration']++;
?>
<tr>
    <td valign="middle">
        <a href="<?php echo $this->_tpl_vars['CURRENT_ACTION']; ?>
action=edit&id=<?php echo $this->_tpl_vars['num_set']->get_id(); ?>
&showform=true" onclick="top.restoreSession()"><?php echo $this->_tpl_vars['num_set']->get_insurance_company_name(); ?>
&nbsp;</a>
    </td>
    <td><?php echo $this->_tpl_vars['num_set']->get_provider_number(); ?>
&nbsp;</td>
    <td><?php echo $this->_tpl_vars['num_set']->get_rendering_provider_number(); ?>
&nbsp;</td>
    <td><?php echo $this->_tpl_vars['num_set']->get_group_number(); ?>
&nbsp;</td>
</tr>
<?php endforeach; else: ?>
<tr>
   <td colspan="5"><?php echo smarty_function_xl(array('t' => 'No entries found, use the form below to add an entry.'), $this);?>
</td>
</tr>
<?php endif; unset($_from); ?>

<tr> <td style="border-style:none;" colspan="5">
    <a href="<?php echo $this->_tpl_vars['CURRENT_ACTION']; ?>
action=edit&id=&provider_id=<?php echo $this->_tpl_vars['provider']->get_id(); ?>
&showform=true"
       class="css_button_small" style='margin-top:2px'
       onclick="top.restoreSession()">
            <span><?php echo smarty_function_xl(array('t' => 'Add New'), $this);?>
</span>
    </a>
</td> </tr>

<?php  if ( $_GET['showform'] == 'true' ) {  ?>
<tr> <td style="border-style:none;" colspan="5">
<br>
<b><span><?php if ($this->_tpl_vars['ins']->get_id() == ""): ?><?php echo smarty_function_xl(array('t' => 'Add Provider Number'), $this);?>
<?php else: ?><?php echo smarty_function_xl(array('t' => 'Update Provider Number'), $this);?>
<?php endif; ?></span></b><br>
	<table width="100%"><tr>
        <td style="border-style:none;" width="200px" VALIGN="MIDDLE" ><?php echo smarty_function_xl(array('t' => 'Insurance Company'), $this);?>
</td>
        <td style="border-style:none;" VALIGN="MIDDLE" >
                <?php if ($this->_tpl_vars['ins']->get_id() == ""): ?>
                        <?php echo smarty_function_html_options(array('name' => 'insurance_company_id','options' => $this->_tpl_vars['ic_array'],'values' => $this->_tpl_vars['ic_array'],'selected' => $this->_tpl_vars['ins']->get_insurance_company_id()), $this);?>

                <?php else: ?>
                 <?php echo $this->_tpl_vars['ins']->get_insurance_company_name(); ?>

                <?php endif; ?>
        </td>
</tr>
<tr>
        <td style="border-style:none;" VALIGN="MIDDLE" ><?php echo smarty_function_xl(array('t' => 'Provider Number'), $this);?>
</td>
        <td style="border-style:none;" VALIGN="MIDDLE" >
           <input type="text" size="20" name="provider_number" value="<?php echo $this->_tpl_vars['ins']->get_provider_number(); ?>
" onKeyDown="PreventIt(event)" />
        </td>
</tr>
<tr>
        <td style="border-style:none;" VALIGN="MIDDLE" ><?php echo smarty_function_xl(array('t' => 'Provider Number'), $this);?>
 (<?php echo smarty_function_xl(array('t' => 'Type'), $this);?>
)</td>
        <td style="border-style:none;" VALIGN="MIDDLE" >
           <?php echo smarty_function_html_options(array('name' => 'provider_number_type','options' => $this->_tpl_vars['ic_type_options_array'],'values' => $this->_tpl_vars['ins']->provider_number_type_array,'selected' => $this->_tpl_vars['ins']->get_provider_number_type()), $this);?>

        </td>
</tr>
<tr>
        <td style="border-style:none;" VALIGN="MIDDLE" ><?php echo smarty_function_xl(array('t' => 'Rendering Provider Number'), $this);?>
</td>
        <td style="border-style:none;" VALIGN="MIDDLE" >
                <input type="text" size="20" name="rendering_provider_number" value="<?php echo $this->_tpl_vars['ins']->get_rendering_provider_number(); ?>
" onKeyDown="PreventIt(event)" />
		</td>
</tr>
<tr>
        <td style="border-style:none;" VALIGN="MIDDLE" ><?php echo smarty_function_xl(array('t' => 'Rendering Provider Number'), $this);?>
 (<?php echo smarty_function_xl(array('t' => 'Type'), $this);?>
)</td>
        <td style="border-style:none;" VALIGN="MIDDLE" >
            <?php echo smarty_function_html_options(array('name' => 'rendering_provider_number_type','options' => $this->_tpl_vars['ic_rendering_type_options_array'],'values' => $this->_tpl_vars['ins']->rendering_provider_number_type_array,'selected' => $this->_tpl_vars['ins']->get_rendering_provider_number_type()), $this);?>

        </td>
</tr>
<tr>
        <td style="border-style:none;" VALIGN="MIDDLE" ><?php echo smarty_function_xl(array('t' => 'Group Number'), $this);?>
</td>
        <td style="border-style:none;" VALIGN="MIDDLE" >
                <input type="text" size="20" name="group_number" value="<?php echo $this->_tpl_vars['ins']->get_group_number(); ?>
" onKeyDown="PreventIt(event)" />
        </td>
</tr>
<tr><td style="border-style:none;"></td></tr>
<tr>
        <td style="border-style:none;" colspan="2">
                <?php if ($this->_tpl_vars['ins']->get_id() == ""): ?>
                	<a href="javascript:submit_insurancenumbers_add();" class="css_button"><span><?php echo smarty_function_xl(array('t' => 'Save'), $this);?>
</span></a>
                <?php else: ?>
                   	<a href="javascript:submit_insurancenumbers_update();" class="css_button"><span><?php echo smarty_function_xl(array('t' => 'Save'), $this);?>
</span></a>
                <?php endif; ?>

		<a href="controller.php?practice_settings&insurance_numbers&action=list" class="css_button" onclick="top.restoreSession()">
<span><?php echo smarty_function_xl(array('t' => 'Cancel'), $this);?>
</span></a>
</td> </tr>
<?php  } else {  ?>
<input type="hidden" name="provider_number" value="<?php echo $this->_tpl_vars['ins']->get_provider_number(); ?>
" />
<input type="hidden" name="provider_number_type" value="<?php echo $this->_tpl_vars['ins']->get_provider_number_type(); ?>
" />
<input type="hidden" name="rendering_provider_number" value="<?php echo $this->_tpl_vars['ins']->get_rendering_provider_number(); ?>
" />
<input type="hidden" name="rendering_provider_number_type" value="<?php echo $this->_tpl_vars['ins']->get_rendering_provider_number_type(); ?>
" />
<input type="hidden" name="group_number" value="<?php echo $this->_tpl_vars['ins']->get_group_number(); ?>
" />

<?php  }  ?>

    </table>
</td></tr>
</table>
<input type="hidden" name="id" value="<?php echo $this->_tpl_vars['ins']->id; ?>
" />
<input type="hidden" name="provider_id" value="<?php echo $this->_tpl_vars['ins']->get_provider_id(); ?>
" />
<input type="hidden" name="process" value="<?php echo $this->_tpl_vars['PROCESS']; ?>
" />
</form>
<?php endif; ?>
<?php echo '
<script language="javascript">
function submit_insurancenumbers_update() {
    top.restoreSession();
    document.provider.submit();
}
function submit_insurancenumbers_add() {
    top.restoreSession();
    document.provider.submit();
	//Z&H Removed redirection
}

function Waittoredirect(delaymsec) {
 var st = new Date();
 var et = null;
 do {
 et = new Date();
 } while ((et - st) < delaymsec);

 }
</script>
<style type="text/css">
text,select {font-size:9pt;}
</style>
'; ?>
