<?php
  
// Load template settings structure
  include vqmod::modcheck(FS_DIR_HTTP_ROOT . WS_DIR_TEMPLATES . settings::get('store_template_catalog') .'/config.inc.php');
  
// Get settings from database
  $settings = unserialize(settings::get('store_template_catalog_settings'));
  
// Build template settings
  foreach (array_keys($template_config) as $i) {
    $template_config[$i]['value'] = isset($settings[$template_config[$i]['key']]) ? $settings[$template_config[$i]['key']] : $template_config[$i]['default_value'];
  }
  
  if (!empty($_POST['save'])) {
    
    $new_settings = array();
    foreach (array_keys($template_config) as $i) {
      $new_settings[$template_config[$i]['key']] = isset($_POST[$template_config[$i]['key']]) ? $_POST[$template_config[$i]['key']] : $template_config[$i]['default_value'];
    }
    
    database::query(
      "update ". DB_TABLE_SETTINGS ."
      set
        `value` = '". database::input(serialize($new_settings)) ."',
        date_updated = '". date('Y-m-d H:i:s') ."'
      where `key` = '". database::input('store_template_catalog_settings') ."'
      limit 1;"
    );
    
    notices::add('success', language::translate('success_changes_saved', 'Changes were successfully saved.'));
    
    header('Location: '. document::link('', array(), true, array('action')));
    exit;
  }
  
?>
<h1 style="margin-top: 0px;"><img src="<?php echo WS_DIR_ADMIN . $_GET['app'] .'.app/icon.png'; ?>" width="32" height="32" style="vertical-align: middle; margin-right: 10px;" /><?php echo language::translate('title_template_settings', 'Template Settings'); ?></h1>

<?php echo functions::form_draw_form_begin('template_settings_form', 'post'); ?>
<table width="100%" align="center" class="dataTable">
  <tr class="header">
    <th nowrap="nowrap" align="left" width="250"><?php echo language::translate('title_key', 'Key'); ?></th>
    <th nowrap="nowrap" align="left"><?php echo language::translate('title_value', 'Value'); ?></th>
    <th>&nbsp;</th>
  </tr>
<?php
  if (!empty($template_config)) {
    
    foreach ($template_config as $setting) {
      
      if (!isset($rowclass) || $rowclass == 'even') {
        $rowclass = 'odd';
      } else {
        $rowclass = 'even';
      }
      
      if (isset($_GET['action']) && $_GET['action'] == 'edit' && $_GET['key'] == $setting['key']) {
?>
  <tr class="<?php echo $rowclass; ?>">
    <td align="left" nowrap="nowrap"><u><?php echo $setting['title']; ?></u><br /><?php echo $setting['description']; ?></td>
    <td align="left" valign="middle"><?php echo functions::form_draw_function($setting['function'], $setting['key'], $setting['value']); ?></td>
    <td align="right" valign="middle" nowrap="nowrap"><?php echo functions::form_draw_button('save', language::translate('title_save', 'Save'), 'submit', '', 'save'); ?> <?php echo functions::form_draw_button('cancel', language::translate('title_cancel', 'Cancel'), 'button', 'onclick="history.go(-1);"', 'cancel'); ?></td>
  </tr>
<?php
    } else {
      if (in_array(strtolower($setting['value']), array('1', 'active', 'enabled', 'on', 'true', 'yes'))) {
        $setting['value'] = language::translate('title_true', 'True');
      } else if (in_array(strtolower($setting['value']), array('', '0', 'inactive', 'disabled', 'off', 'false', 'no'))) {
        $setting['value'] = language::translate('title_false', 'False');
      }
?>
  <tr class="<?php echo $rowclass; ?>">
    <td align="left" nowrap="nowrap"><?php echo language::translate('settings_key:title_'.$setting['key'], $setting['title']); ?></td>
    <td align="left"><?php echo nl2br((strlen($setting['value']) > 128) ? substr($setting['value'], 0, 128).'...' : $setting['value']); ?></td>
    <td align="right" nowrap="nowrap"><a href="<?php echo document::href_link('', array('action' => 'edit', 'key' => $setting['key']), true); ?>"><img src="<?php echo WS_DIR_IMAGES . 'icons/16x16/edit.png'; ?>" width="16" height="16" alt="<?php echo language::translate('title_edit', 'Edit'); ?>" title="<?php echo language::translate('title_edit', 'Edit'); ?>" /></a></td>
  </tr>
<?php
      }
    }
  } else {
?>
  <tr class="odd">
    <td colspan="3" align="left" nowrap="nowrap"><?php echo language::translate('text_no_template_settings', 'There are no settings available for this template.'); ?></td>
  </tr>
<?php
}
?>
</table>
<?php
  echo functions::form_draw_form_end();
?>