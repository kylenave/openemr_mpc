<?php /* Smarty version 2.6.29, created on 2018-02-07 12:33:45
         compiled from /var/www/openemr/templates/insurance_companies/general_list.html */ ?>
<?php require_once(SMARTY_CORE_DIR . 'core.load_plugins.php');
smarty_core_load_plugins(array('plugins' => array(array('function', 'xl', '/var/www/openemr/templates/insurance_companies/general_list.html', 2, false),array('modifier', 'upper', '/var/www/openemr/templates/insurance_companies/general_list.html', 14, false),)), $this); ?>
<a href="controller.php?practice_settings&<?php echo $this->_tpl_vars['TOP_ACTION']; ?>
insurance_company&action=edit" onclick="top.restoreSession()" class="css_button" >
<span><?php echo smarty_function_xl(array('t' => 'Add a Company'), $this);?>
</span></a><br>
<br>
<table cellpadding="1" cellspacing="0" class="showborder">
	<tr class="showborder_head">
		<th width="140px"><b><?php echo smarty_function_xl(array('t' => 'Name'), $this);?>
</b></th>
		<th width="300px"><b><?php echo smarty_function_xl(array('t' => 'City, State'), $this);?>
</b></th>
		<th><b><?php echo smarty_function_xl(array('t' => 'Default X12 Partner'), $this);?>
</b></th>
		<th><b><?php echo smarty_function_xl(array('t' => 'Deactivated'), $this);?>
</b></th>
	</tr>
	<?php $_from = $this->_tpl_vars['icompanies']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['insurancecompany']):
?>
	<tr height="22">
		<td><a href="<?php echo $this->_tpl_vars['CURRENT_ACTION']; ?>
action=edit&id=<?php echo $this->_tpl_vars['insurancecompany']->id; ?>
" onsubmit="return top.restoreSession()"><?php echo $this->_tpl_vars['insurancecompany']->name; ?>
&nbsp;</a></td>
		<td><?php echo $this->_tpl_vars['insurancecompany']->address->city; ?>
 <?php echo ((is_array($_tmp=$this->_tpl_vars['insurancecompany']->address->state)) ? $this->_run_mod_handler('upper', true, $_tmp) : smarty_modifier_upper($_tmp)); ?>
&nbsp;</td>
		<td><?php echo $this->_tpl_vars['insurancecompany']->get_x12_default_partner_name(); ?>
&nbsp;</td>
		<td><?php if ($this->_tpl_vars['insurancecompany']->get_inactive() == 1): ?><?php echo smarty_function_xl(array('t' => 'Yes'), $this);?>
<?php endif; ?>&nbsp;</td>
	</tr>
	<?php endforeach; else: ?>
	<tr class="center_display">
		<td colspan="3"><?php echo smarty_function_xl(array('t' => 'No Insurance Companies Found'), $this);?>
</td>
	</tr>
	<?php endif; unset($_from); ?>
</table>