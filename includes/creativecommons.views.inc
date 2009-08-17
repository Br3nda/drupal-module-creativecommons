<?php
// $Id: creativecommons.views.inc,v 1.1.2.1 2009/08/17 18:52:56 balleyne Exp $
/**
 * @file
 * Provide views data and handler information for creativecommons.module.
 */

//TODO: sort license_uri (by permissiveness)
//TODO: filter license_uri by type
//TODO: refine metadata, ccREL (e.g. combine attribution name / url)
//TODO: should metadata handlers still be defined if metadata unavailable?

/**
 * Implementation of hook_views_data().
 */
function creativecommons_views_data() {
  $data['creativecommons_node']['table']['group'] = t('Creative Commons');

  $data['creativecommons_node']['table']['base'] = array(
    'field' => 'nid',
    'title' => t('Creative Commons'),
    'help' => t("Creative Commons table contains licensing information related to nodes."),
    'weight' => -10,
  );

  // Explain how this table joins to others.
  $data['creativecommons_node']['table']['join'] = array(
    'node' => array(
      'field' => 'nid',
      'left_table' => 'node',
      'left_field' => 'nid',
    ),
  );

  $data['creativecommons_node']['nid'] = array(
    'title' => t('Creative Commons License'),
    'help' => t('License information that references a node.'),
    'relationship' => array(
      'base' => 'node',
      'field' => 'nid',
      'handler' => 'views_handler_relationship',
      'label' => t('Creative Commons License'),
    ),
  );

  $data['creativecommons_node']['license_uri'] = array(
    'title' => t('License'), // Appears in views UI.
    'help' => t('The Creative Commons license applied to this node.'),
    'field' => array(
      'handler' => 'creativecommons_views_handler_field_license_uri',
      'click sortable' => TRUE,
    ),
    'argument' => array(
      'handler' => 'creativecommons_views_handler_argument_license_uri',
      'click sortable' => TRUE,
    ),
  );

  foreach (creativecommons_get_metadata_types() as $key => $value) {
    if (creativecommons_metadata_is_available($key)) {
      $url_fields = array('attributionURL', 'morePermissions', 'source');
      $field = in_array($key, $url_fields) ? 'views_handler_field_url' : 'views_handler_field';
      $argument = in_array($key, $url_fields) ? 'views_handler_argument' : 'views_handler_argument_string';
      $data['creativecommons_node'][$key] = array(
        'title' => strip_tags($value), //Appears in views UI
        'help' => creativecommons_get_metadata_description($key),
        'field' => array('handler' => $field),
        'argument' => array('handler' => $argument),
      );
    }
  }

  return $data;
}


/**
 * Implementation of hook_views_handlers().
 */
function creativecommons_views_handlers() {
  return array(
    'info' => array(
      'path' => drupal_get_path('module', 'creativecommons') .'/includes',
    ),
    'handlers' => array(
      // argument handlers
      'creativecommons_views_handler_argument_license_uri' => array(
        'parent' => 'views_handler_argument',
      ),
      // field handlers
      'creativecommons_views_handler_field_license_uri' => array(
        'parent' => 'views_handler_field_url',
      ),
      // filter handlers
      // relationship handlers
      // sort handlers
    ),
  );
}

