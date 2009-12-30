<?php
// $Id: creativecommons_views_handler_filter_license_uri.inc,v 1.1.2.2 2009/12/30 07:19:04 balleyne Exp $
/**
 * Filter by license type
 */
class creativecommons_views_handler_filter_license_uri extends views_handler_filter_in_operator {
  function get_value_options() {
    if (!isset($this->value_options)) {
      $this->value_title = t('License Types');
      $jurisdiction = variable_get('creativecommons_default_jurisdiction', '');
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
