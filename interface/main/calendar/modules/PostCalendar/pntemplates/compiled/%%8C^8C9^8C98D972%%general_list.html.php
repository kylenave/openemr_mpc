<?php /* Smarty version 2.6.29, created on 2018-02-23 13:09:39
         compiled from /var/www/openemr/templates/documents/general_list.html */ ?>
<?php require_once(SMARTY_CORE_DIR . 'core.load_plugins.php');
smarty_core_load_plugins(array('plugins' => array(array('function', 'xl', '/var/www/openemr/templates/documents/general_list.html', 23, false),)), $this); ?>
<html>
<head>
<?php html_header_show(); ?>
<?php $this->assign('GLOBALS', $GLOBALS); ?>

<link rel="stylesheet" href="<?php echo $this->_tpl_vars['GLOBALS']['css_header']; ?>
" type="text/css">

<?php echo '
'; ?>


<script type="text/javascript" src="<?php echo $this->_tpl_vars['WEBROOT']; ?>
/library/js/DocumentTreeMenu.js"></script>
</head>
<!--<body bgcolor="<?php echo $this->_tpl_vars['STYLE']['BGCOLOR2']; ?>
">-->

<!-- ViSolve - Call expandAll function on loading of the page if global value 'expand_document' is set -->
<?php  if ($GLOBALS['expand_document_tree']) {   ?>
  <body class="body_top" onload="javascript:objTreeMenu_1.expandAll();return false;">
<?php  } else {  ?>
  <body class="body_top">
<?php  }  ?>

<div id="documents_list">
<a id="list_collapse" href="#" onclick="javascript:objTreeMenu_1.collapseAll();return false;">&nbsp;(<?php echo smarty_function_xl(array('t' => 'Collapse all'), $this);?>
)</a>
<div class="title"><?php echo smarty_function_xl(array('t' => 'Documents'), $this);?>
</div>
<?php echo $this->_tpl_vars['tree_html']; ?>

</div>
<div id="documents_actions">
		<?php if ($this->_tpl_vars['message']): ?>
			<div class='text' style="margin-bottom:-10px; margin-top:-8px"><i><?php echo $this->_tpl_vars['message']; ?>
</i></div><br>
		<?php endif; ?>
		<?php if ($this->_tpl_vars['messages']): ?>
            <div class='text' style="margin-bottom:-10px; margin-top:-8px"><i><?php echo $this->_tpl_vars['messages']; ?>
</i></div><br>
		<?php endif; ?>
		<?php echo $this->_tpl_vars['activity']; ?>

</div>
<script type="text/javascript" src="<?php echo $this->_tpl_vars['GLOBALS']['assets_static_relative']; ?>
/jquery-min-3-1-1/index.js"></script>
<script type="text/javascript">
$("#list_collapse").detach().appendTo("#objTreeMenu_1_node_1 nobr");
</script>
</body>
</html>