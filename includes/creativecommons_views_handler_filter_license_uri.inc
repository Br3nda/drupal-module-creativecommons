<?php
// $Id: creativecommons_views_handler_filter_license_uri.inc,v 1.1.2.1 2009/12/29 03:19:30 balleyne Exp $
/**
 * Filter by node type
 */
class creativecommons_views_handler_filter_license extends views_handler_filter_in_operator {
  function get_value_options() {
    if (!isset($this->value_options)) {
      $this->value_title = t('License Types');
      $types = creativecommons_get_available_license_list( array('jurisdiction' => $jurisdiction) );
        
      foreach ($types as $type => $info) {
        if(is_array($info)){
          foreach($info as $type => $value){
            $options[$type] = $value;
          }
        } else {
            $options[$type] = $info;
        }
      }
      $this->value_options = $options;
    }
  }
}
