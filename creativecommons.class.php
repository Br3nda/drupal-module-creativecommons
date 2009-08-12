<?php
// $Id: creativecommons.class.php,v 1.3.4.28 2009/08/12 00:20:22 balleyne Exp $

/**
 * @file
 * Creative Commons Drupal module
 *   Allows content within a site or attached to a node to
 *   be assigned a Creative Commons license.
 *   http://creativecommons.org/license/
 *
 *
 * By: Peter Bull <pbull@ltc.org>
 * 2005-02-28 / digitalbicycle.org / ltc.org
 * This software is released under the terms of the LGPL license, relicensed
 * under the GPL for drupal.org
 *
 * Utilizes code and inspiration from http://cclicense.sourceforge.net/
 *   Originally released by Blake Watters <sbw@ibiblio.org>
 *   under the terms of the LGPL license (now, GPL for drupal.org).
 *
 */

//TODO: 2.x PHP5 (useful for license types? http://us3.php.net/manual/en/language.oop5.late-static-bindings.php)
//TODO: error handling http://api.creativecommons.org/docs/readme_15.html#error-handling
//TODO: 2.x optimize by storing values when functions are called (e.g. is_valid, is_available)?
class creativecommons_license {
  // license attributes
  var $uri;
  var $name;
  var $license_class;
  var $type;
  var $permissions;
  var $metadata;

  // assigned license
  var $nid;


  /**
   * Initialize object
   */
  function __construct($license_uri, $nid = NULL, $metadata = array()) {
    // If nid set, load from databases
    if ($nid) {
      $this->nid = $nid;
      $this->load();
    }
    // Otherwise, load from parameters
    else {
      $this->uri = $license_uri;

      if ($metadata) {
        $this->metadata = $metadata;
      }
    }

    // Fetch license information if uri present
    if ($this->uri) {
      $this->fetch();
    }
    // don't fetch a blank license
    else {
      $this->name = 'None (All Rights Reserved)';
      $this->type = '';
    }
  }


  /**
   * Load from database into object.
   */
  function load() {
    if ($this->nid) {
      $result = db_query("SELECT * FROM {creativecommons} cc WHERE cc.nid = %d", $this->nid);
      if ($row = db_fetch_object($result)) {
        $this->uri = $row->license_uri;

        $this->metadata = array();
        foreach ($row as $key => $value) {
          if ($key != 'license_uri' && $key != 'nid') {
            $this->metadata[$key] = $value;
          }
        }
      }
    }
  }

  /**
   * Load basic information from uri and XML data from API into object.
   */
  function fetch() {
    // Load basic data from uri
    $uri_parts = explode('/', $this->uri);
    $this->type = $uri_parts[4];
    $this->version = $uri_parts[5];
    $this->jurisdiction = $uri_parts[6];

    // TODO: Is PD really standard? Does it matter?
    $this->license_class = 'standard';

    // Special Case: CC0
    if ($this->type == 'zero') {
      $this->name = 'CC0 1.0 Universal';
      $this->license_class = 'publicdomain';
      $this->permissions = array();
      $this->permissions['permits'][] = 'http://creativecommons.org/ns#Reproduction';
      $this->permissions['permits'][] = 'http://creativecommons.org/ns#Distribution';
      $this->permissions['permits'][] = 'http://creativecommons.org/ns#DerivativeWorks';


      //TODO: <p xmlns> stuff is redundant, check and strip if I'm right
      $this->html = '<p xmlns:dct="http://purl.org/dc/terms/" xmlns:vcard="http://www.w3.org/2001/vcard-rdf/3.0#">
        <a rel="license" href="'. check_plain($this->uri) .'" style="text-decoration:none;">
          <img src="http://i.creativecommons.org/l/zero/1.0/88x31.png" border="0" alt="CC0" />
        </a>
        <br />
        To the extent possible under law, all copyright and related or neighboring rights to this work have been waived.</p>';
      return;
    }

    // Get license xml from API
    $xml = creativecommons_return_xml('/details?license-uri='. urlencode($this->uri));

    // Parse XML
    $parser = xml_parser_create();
    xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
    xml_parse_into_struct($parser, $xml, $values, $tags);
    xml_parser_free($parser);

    // Extract values
    $this->permissions = array();
    $this->permissions['requires'] = array();
    $this->permissions['prohibits'] = array();
    $this->permissions['permits'] = array();

    foreach ($values as $xn) {
      switch ($xn['tag']) {
        case 'error':
          if ($xn['type'] == 'open') {
            $this->error = array();
            $this->error['id'] = $values[1]['value'];
            $this->error['message'] = $values[2]['value'];
            //TODO: should this set the error here?
            $message = 'CC API Error ('. $this->error['id'] .'): '. $this->error['message']
              . ($this->error['id'] == 'invalid' ? ' '. $this->get_name() : '');
            drupal_set_message($message, 'error');

          }
        break;

        case 'license-name':
          $this->name = $xn['value'];
          break;

        case 'rdf:RDF':
          if ($xn['type'] == 'open') {
            $this->rdf = array();
            $this->rdf['attributes'] = $xn['attributes'];
          }
          break;

        //TODO: 2.x remove when RDF/XML support is dropped, this creates redundancy, violates DRY
        case 'permits':
        case 'prohibits':
        case 'requires':
          if (!in_array(current($xn['attributes']), $this->permissions[$xn['tag']]))
            $this->permissions[$xn['tag']][] = current($xn['attributes']);
          break;
      }
    }


    // Special case: HTML TODO: 2.x is there a better way to do this? or does it matter, if it'll be constructed from scratch eventually anyways?
    preg_match('/<html>(.*)<\/html>/', $xml, $matches);
    $this->html = $matches[1];
  }

  /**
   * Sanitize values and check keys. If key is valid metadata
   * type, sanitize the value. Otherwise, unset it. After running this function,
   * all metadata should be safe for output in HTML.
   */
  function check_metadata() {
    if ($this->metadata) {
      $metadata_types = creativecommons_get_metadata_types();
      foreach ($this->metadata as $key => $value) {
        if (array_key_exists($key, $metadata_types)) {
          $this->metadata[$key] = check_plain($value);
        }
        else {
          unset($this->metadata[$key]);
        }
      }
    }
  }

  /**
   * Return full license name.
   */
  function get_name($style = 'full_text') {
    if ($this->is_valid()) {
      // CCO
      $prefix = ($this->type && $this->type != 'zero') ? 'Creative Commons ' : '';

      switch ($style) {
        case 'full_text':
          $name = $prefix . $this->name;
          break;
        case 'generic_text':
          $name = creativecommons_generic_license_name($prefix . $this->name);
          break;
        case 'short_text':
          switch ($this->type) {
            case 'zero':
              $name = 'CC0';
              break;
            case 'publicdomain';
              $name = 'PD';
              break;
            default:
              $name = 'CC '. drupal_strtoupper($this->type);
          }
          break;
      }

      return $name;
    }
    else {
      return '"'. $this->uri .'"';
    }
  }


  /**
   * Return array of images relating to current license
   * - if ($site_license) then force return of standard license image
   * @param $style -- either button_large, button_small, icons or tiny_icons
   */
  //TODO: internationalization for NC (could implement as a setting... instead of parsing automatically)
  function get_image($style) {
    $img = array();
    $img_dir = base_path() . drupal_get_path('module', 'creativecommons') .'/images';

    switch ($style) {
      case 'button_large':
      case 'button_small':
        // The directory which the icons reside
        $dir = $img_dir . '/' . str_replace('_', 's/', $style) .'/';

        $img[] = '<img src="'. $dir . $this->type .'.png" style="border-width: 0pt;" title="'. t($this->get_name('full_text')) .'" alt="'. t($this->get_name('full_text')) .'"/>';
        break;
      case 'tiny_icons':
        $px = '15';
      case 'icons':
        $name = array(
          'by' => 'Attribution',
          'nc' => 'Noncommercial',
          'sa' => 'Share Alike',
          'nd' => 'No Derivatives',
          'pd' => 'Public Domain',
          'zero' => 'Zero',
        );
        if (!$px) {
          $px = '32';
        }
        foreach (explode('-', $this->type) as $filename) {
          $img[] = '<img src="'. $img_dir .'/icons/'. $filename .'.png" style="border-width: 0pt; width: '. $px .'px; height: '. $px .'px;" alt="'. t($name[$filename]) .'"/>';
        }
        break;
    }

    return implode(($style == 'tiny_icons' ? '' : ' '), $img);
  }

  /**
   * Returns true if any metadata fields are non-blank, false otherwise.
   */
  function has_metadata() {
    if ($this->metadata) {
      foreach ($this->metadata as $key => $value) {
        if (!empty($value)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }
  /**
   * Returns true if license set, false otherwise.
   */
  function has_license() {
    return !empty($this->uri);
  }

  /**
   * Returns true if license uri is valid, false otherwise.
   */
  function is_valid() {
    // note: license xml was already extracted in constructor
    return !($this->error['id'] == 'invalid');
  }

  /**
   * Returns true if license is available, false otherwise.
   * A license is available if it has a valid uri and if its license type is
   * available. Blank licenses are 'available'.
   */
  function is_available() {
    // Check if license is valid
    if (!$this->is_valid())
      return FALSE;

    // Check if license type is available
    $available_license_types = creativecommons_get_available_license_types();
    if (!in_array($this->type, $available_license_types))
      return FALSE;

    return TRUE;
  }
  
  /**
   * Return true if this license allows commercial use, false otherwise.
   */
  function permits_commercial_use() {
    return !is_array($this->permissions['prohibits']) || !in_array('http://creativecommons.org/ns#CommercialUse', $this->permissions['prohibits']);
  }
  
  /**
   * Return true if this license allows derivative works, false otherwise.
   */
  function permits_derivative_works() {
    return is_array($this->permissions['permits']) && in_array('http://creativecommons.org/ns#DerivativeWorks', $this->permissions['permits']);
  }

  /**
   * Return html containing license link (+ images)
   */
  //TODO: 2.x implement ccREL fully (p. ~14 http://wiki.creativecommons.org/images/d/d6/Ccrel-1.0.pdf), with Drupal defaults
  function get_html() {

    // must have a license to display html
    if (!$this->has_license()) {
      return;
    }

    // Sanitize metadata
    $this->check_metadata();

    $html = str_replace("\n", '', $this->html);

    // Strip image, replace with image from settings
    $html = preg_replace('/<a.*<img.*br ?\/>/', '', $html);
    $img = $this->get_image(variable_get('creativecommons_image_style', 'button_large'));
    if ($img) {
      $attributes['rel'] = 'license';
      $html = l($img, $this->uri, array('attributes' => $attributes, 'html' => TRUE)) .'<br/>'. $html;
    }

    $marker_text = $this->type == 'zero' ? 'have been waived' : 'is licensed';

    // Adjust default type in API html if user has specified a type
    if ($this->metadata['type']) {
      $dcmi_types = creativecommons_get_dcmi_types();
      $html = str_replace('work', drupal_strtolower($dcmi_types[$this->metadata['type']]), $html);

      // Insert the type
      $html = str_replace('http://purl.org/dc/dcmitype/', 'http://purl.org/dc/dcmitype/'. $this->metadata['type'], $html);

      //Remove ns definition, as we do this in the encompassing div
      $html = str_replace(' xmlns:dc="http://purl.org/dc/elements/1.1/"', '', $html);
    }

    // Add title of work, if specified
    if ($this->metadata['title']) {
      $html = str_replace(' '. $marker_text, ', <span property="dc:title">'. $this->metadata['title'] .'</span>, '. $marker_text, $html);
    }

    // Add attribution name, if specified
    if ($this->metadata['attributionName']) {
      $author = 'by ';

      if ($this->metadata['attributionURL']) {
        $attributes = array('property' => 'cc:attributionName',
                            'rel' => 'cc:attributionURL');
        $author .= l($this->metadata['attributionName'], $this->metadata['attributionURL'], array('attributes' => $attributes));
      }
      else {
        $author .= '<span property="cc:attributionName">'. $this->metadata['attributionName'] .'</span>';
      }

      $html = str_replace($marker_text, $author .' '. $marker_text, $html);
    }

    // Alternative licensing options cc:morePermissions
    if ($this->metadata['morePermissions']) {
      $attributes = array('rel' => 'cc:morePermissions');
      $html .= ' There are '. l(t('alternative licensing options'), $this->metadata['morePermissions'], array('attributes' => $attributes)) .'.';
    }

    $html = "\n<div about=\"". url('node/'. $this->nid, array('absolute' => TRUE)) ."\" instanceof=\"cc:Work\"".
              "\n\txmlns:cc=\"http://creativecommons.org/ns#\"".
              "\n\txmlns:dc=\"http://purl.org/dc/elements/1.1/\"".
              "\n\tclass=\"creativecommons\">\n\t\t". $html ."\n</div>\n";

    return $html;



    //TODO: remove when fully replaced -- need to review date output, etc
    /* $txt = 'This work is licensed under a '.
      l(t('Creative Commons License'),
        $this->uri,
        array(
          'attributes' => array('rel' => 'license', 'title' => $this->name),
        )
      ) .".\n";
    $html = "\n<!--Creative Commons License-->\n";
    if ($site_license)
      $html .= "<div id=\"ccFooter\">\n";

    // site license header (with copyright year(s))
    if ($site_license) {
      if (!$start_year = variable_get('creativecommons_start_year', NULL)) {
        $start_year = date('Y');
        variable_set('creativecommons_start_year', $start_year);
      }
      $this_year = date('Y');
      if ($start_year < date('Y'))
        $copy_years = $start_year .'-'. $this_year;
      else
        $copy_years = $start_year;
      $html .= 'Copyright &copy; '. $copy_years .', Some Rights Reserved<br />';
    }

    // construct images + links
    if ($img = $this->get_images($site_license)) {
      foreach ($img as $img_tag)
        $html .= l(
          $img_tag,
          $this->uri,
          array(
            'attributes' => array('rel' => 'license'),
            'html' => TRUE,
          )) ."\n";
      $html .= '<br />';
    }

    // display site footer text
    $html .= $txt;
    if ($site_license) {
      if ($footer_text = variable_get('creativecommons_site_license_additional_text', NULL))
        $html .= '<br />'. $footer_text;
      $html .= "</div>\n";
    }
    $html .= "<!--/Creative Commons License-->\n";

    return $html;*/
  }


  /**
   * Return rdf with license and metadata embedded
   */
  function get_rdf() {

    // must have a license to display rdf
    if (!$this->has_license())
      return;

    // Sanitize metadata
    $this->check_metadata();

    if ($this->rdf) {
      foreach ($this->rdf['attributes'] as $attr => $val)
        $a .= " $attr=\"$val\"";
    }
    $rdf = "<rdf:RDF$a>\n";

    // metadata
    $rdf .= "<work rdf:about=\"". url('node/'. $this->nid, array('absolute' => TRUE)) ."\">\n";
    if ($this->has_metadata()) {
      foreach ($this->metadata as $key => $value) {
        if ($value) {
          $ns = 'dc';

          switch ($key) {
            case 'type':
              $value = "http://purl.org/dc/dcmitype/$value";

            case 'source':
              $rdf .= "<$ns:$key rdf:resource=\"$value\" />\n";
              break;

            case 'rights':
            case 'creator':
              $rdf .= "<$ns:$key><agent><dc:title>$value</dc:title></agent></$ns:$key>\n";
              break;

            case 'attributionName':
            case 'attributionURL':
            case 'morePermissions':
              $ns = 'cc';
            default:
              $rdf .= "<$ns:$key>$value</$ns:$key>\n";
              break;
          }
        }
      }
    }
    $rdf .= "<license rdf:resource=\"". $this->uri ."\" />\n";
    $rdf .= "</Work>\n";

    // permissions
    $rdf .= "<license rdf:about=\"". $this->uri ."\">\n";
    if ($this->permissions) {
      foreach ($this->permissions as $name => $perm) {
        foreach ($perm as $v) {
          $rdf .= "<$name rdf:resource=\"$v\" />\n";
        }
      }
    }

    $rdf .= "</license>\n";
    $rdf .= "</rdf:RDF>";
    return $rdf;
  }


  /**
   * Save to the database.
   * @param $nid - node id
   * @param $op - either 'insert' or 'update'
   */
  function save($nid, $op) {
    if (!$nid) {
      drupal_set_message('A node must be specified to save a license', 'error');
    }
    else if (!$this->is_available()) {
      drupal_set_message('License is not available', 'error');
    }
    else {
      switch ($op) {
          case 'update':
            // This check exists in case an entry doesn't yet exist for the node
            // (for example, if a node was created before the CC module was
            // setup for that content type)
            $exists = FALSE;
            $check_result = db_query('SELECT COUNT(*) as count FROM {creativecommons} WHERE nid=%d', $nid);
            if ($check_result) {
              $row = db_fetch_object($check_result);
              if ($row->count == 1) {
                $exists = TRUE;
              }
            }
            if ($exists) {
              $result = db_query("UPDATE {creativecommons} SET license_uri='%s', attributionName='%s', attributionURL='%s', morePermissions='%s', ".
                                "title='%s', type='%s', description='%s', creator='%s', rights='%s', date='%s', source='%s' WHERE nid=%d",
                $this->uri,
                $this->metadata['attributionName'],
                $this->metadata['attributionURL'],
                $this->metadata['morePermissions'],
                $this->metadata['title'],
                $this->metadata['type'],
                $this->metadata['description'],
                $this->metadata['creator'],
                $this->metadata['rights'],
                $this->metadata['date'],
                $this->metadata['source'],
                $nid
              );
              break;
          }
          // otherwise, insert
        case 'insert':
          $result = db_query("INSERT INTO {creativecommons} (nid, license_uri, attributionName, attributionURL, morePermissions, title, type, description, creator, rights, date, source) ".
            "VALUES (%d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')",
              $nid,
              $this->uri,
              $this->metadata['attributionName'],
              $this->metadata['attributionURL'],
              $this->metadata['morePermissions'],
              $this->metadata['title'],
              $this->metadata['type'],
              $this->metadata['description'],
              $this->metadata['creator'],
              $this->metadata['rights'],
              $this->metadata['date'],
              $this->metadata['source']
            );
            break;
      }
      //TODO: check for error here?
      return $result;
    }
  }

  /**
   * Output license information for web.
   */
  function output($additional_text = '') {
    // Check for empty license
    if ($this->has_license()) {
      $output = "\n<!--Creative Commons License-->\n".

      // HTML output
      $output .= $this->get_html();

      // Additional text
      if ($additional_text) {
        $output .= '<br/>'. $additional_text;
      }

      // RDF output
      if (variable_get('creativecommons_rdf', TRUE)) {
        $output .= "\n<!-- ". $this->get_rdf() ." -->\n";
      }

      $output .= "<!--/Creative Commons License-->\n";
      return $output;
    }
  }
}
