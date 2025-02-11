<?php

  header('X-Robots-Tag: noindex');

  if (empty(cart::$items)) return;

  if (empty($shipping)) $shipping = new mod_shipping();
  if (empty($payment)) $payment = new mod_payment();

// Resume incomplete order in session
  if (!empty(session::$data['order']->data['id'])) {
    session::$data['order'] = new ent_order(session::$data['order']->data['id']);
  } else {
    session::$data['order'] = new ent_order();
  }

  $order = &session::$data['order'];

  if (!empty($order->data['id']) && empty($order->data['order_status_id']) && strtotime($order->data['date_created']) > strtotime('-15 minutes')) {
    $resume_id = $order->data['id'];
  }

  $order->reset();

  if (!empty($resume_id)) {
    $order->data['id'] = $resume_id;
  }

// Build Order
  $order->data['weight_class'] = settings::get('store_weight_class');
  $order->data['currency_code'] = currency::$selected['code'];
  $order->data['currency_value'] = currency::$currencies[currency::$selected['code']]['value'];
  $order->data['language_code'] = language::$selected['code'];
  $order->data['customer'] = customer::$data;
  $order->data['display_prices_including_tax'] = !empty(customer::$data['display_prices_including_tax']) ? true : false;

  foreach (cart::$items as $item) {
    $order->add_item($item);
  }

  if (!empty($shipping->data['selected'])) {
    $order->data['shipping_option'] = $shipping->data['selected'];
  }

  if (!empty($payment->data['selected'])) {
    $order->data['payment_option'] = $payment->data['selected'];
  }

  $order_total = new mod_order_total();
  $rows = $order_total->process($order);
  foreach ($rows as $row) {
    $order->add_ot_row($row);
  }

// Output
  $box_checkout_summary = new ent_view();

  $box_checkout_summary->snippets = array(
    'items' => array(),
    'order_total' => array(),
    'tax_total' => !empty($order->data['tax_total']) ? currency::format($order->data['tax_total'], false) : null,
    'incl_excl_tax' => !empty(customer::$data['display_prices_including_tax']) ? language::translate('title_including_tax', 'Including Tax') : language::translate('title_excluding_tax', 'Excluding Tax'),
    'payment_due' => $order->data['payment_due'],
    'error' => $order->validate(),
    'selected_shipping' => null,
    'selected_payment' => null,
    'consent' => null,
    'confirm' => !empty($payment->data['selected']['confirm']) ? $payment->data['selected']['confirm'] : language::translate('title_confirm_order', 'Confirm Order'),
  );

  foreach ($order->data['items'] as $item) {
    $box_checkout_summary->snippets['items'][] = array(
      'link' => document::ilink('product', array('product_id' => $item['product_id'])),
      'name' => $item['name'],
      'sku' => $item['sku'],
      'options' => $item['options'],
      'price' => $item['price'],
      'tax' => $item['tax'],
      'sum' => !empty(customer::$data['display_prices_including_tax']) ? currency::format(($item['price'] + $item['tax']) * $item['quantity'], false) : currency::format($item['price'] * $item['quantity'], false),
      'quantity' => (float)$item['quantity'],
    );
  }

  if (!empty($shipping->data['selected'])) {
    $box_checkout_summary->snippets['selected_shipping'] = array(
      'icon' => is_file(FS_DIR_APP . $shipping->data['selected']['icon']) ? functions::image_thumbnail(FS_DIR_APP . $shipping->data['selected']['icon'], 160, 60, 'FIT_USE_WHITESPACING') : '',
      'title' => $shipping->data['selected']['title'],
    );
  }

  if (!empty($payment->data['selected'])) {
    $box_checkout_summary->snippets['selected_payment'] = array(
      'icon' => is_file(FS_DIR_APP . $payment->data['selected']['icon']) ? functions::image_thumbnail(FS_DIR_APP . $payment->data['selected']['icon'], 160, 60, 'FIT_USE_WHITESPACING') : '',
      'title' => $payment->data['selected']['title'],
    );
  }

  foreach ($order->data['order_total'] as $row) {
    $box_checkout_summary->snippets['order_total'][] = array(
      'title' => $row['title'],
      'value' => $row['value'],
      'tax' => $row['tax'],
    );
  }

  $terms_of_purchase_id = settings::get('privacy_policy');
  $privacy_policy_id = settings::get('terms_of_purchase');

  switch(true) {
    case ($terms_of_purchase_id && $privacy_policy_id):
      $box_checkout_summary->snippets['consent'] = language::translate('consent:privacy_policy_and_terms_of_purchase', 'I have read the <a href="%privacy_policy_link" target="_blank">Privacy Policy</a> and <a href="%terms_of_purchase_link" target="_blank">Terms of Purchase</a> and I consent.');
      break;
    case ($privacy_policy_id):
      $box_checkout_summary->snippets['consent'] = language::translate('consent:privacy_policy', 'I have read the <a href="%privacy_policy_link" target="_blank">Privacy Policy</a> and I consent.');
      break;
    case ($terms_of_purchase_id):
      $box_checkout_summary->snippets['consent'] = language::translate('consent:terms_of_purchase', 'I have read the <a href="%terms_of_purchase_link" target="_blank">Terms of Purchase</a> and I consent.');
      break;
  }

  $aliases = array(
    '%privacy_policy_link' => document::href_ilink('information', array('page_id' => $privacy_policy_id)),
    '%terms_of_purchase_link' => document::href_ilink('information', array('page_id' => $terms_of_purchase_id)),
  );

  $box_checkout_summary->snippets['consent'] = strtr($box_checkout_summary->snippets['consent'], $aliases);

  echo $box_checkout_summary->stitch('views/box_checkout_summary');
