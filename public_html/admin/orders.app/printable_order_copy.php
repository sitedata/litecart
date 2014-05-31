<?php
  require_once(FS_DIR_HTTP_ROOT . WS_DIR_INCLUDES . 'app_header.inc.php');
  user::require_login();
  
  document::$layout = 'printable';
  
  if (empty($_GET['order_id'])) die('Missing order ID');
  
  $order = new ctrl_order('load', $_GET['order_id']);
  
  echo $order->draw_printable_copy();
  
  require_once(FS_DIR_HTTP_ROOT . WS_DIR_INCLUDES . 'app_footer.inc.php');
?>