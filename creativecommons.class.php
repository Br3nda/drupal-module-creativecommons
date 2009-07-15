<?php
// $Id: creativecommons.class.php,v 1.3.4.15 2009/07/15 00:14:22 balleyne Exp $

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

//TODO: PHP5
//TODO: CC0 support
//TODO: error handling http://api.creativecommons.org/docs/readme_15.html#error-handling
//TODO: optimize by storing values when functions are called (e.g. is_valid, is_available)
class creativecommons_license {
  // license attributes
  var $license_uri;
  var $license_name;
  var $license_class;
  var $license_type;
  var $permissions;
  var $metadata;

  // assigned license
  var $nid;


  /**
   * Initialize object
   */
  function __construct($license_uri, $metadata = array()) {
    // don't load a blank license
    if (!$license_uri) {
      return;
    }

    $this->license_class = 'standard'; // TODO: this is assumed...
    $this->license_uri = $license_uri;
    
    // Load license information
    $this->load();
    
    $this->metadata = $metadata;
  }

  /**
   * Load basic information from uri and XML data from API into object.
   */
  function load(){
    // Load basic data from uri
    $uri_parts = explode('/', $this->license_uri);
    $this->license_type = $uri_parts[4];
    $this->version = $uri_parts[5];
    $this->jurisdiction = $uri_parts[6];
  
    // Get license xml from API
    $xml = creativecommons_return_xml('/details?license-uri='. urlencode($this->license_uri));
    
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
              . ($this->error['id'] == 'invalid' ? ' '. $this->get_full_license_name() : '');
            drupal_set_message($message, 'error');

          }
        break;

        case 'license-uri':
          $this->license_uri = $xn['value'];
          break;

        case 'license-name':
          $this->license_name = $xn['value'];
          break;

        case 'rdf:RDF':
          if ($xn['type'] == 'open') {
            $this->rdf = array();
            $this->rdf['attributes'] = $xn['attributes'];
          }
          break;

        case 'permits':
        case 'prohibits':
        case 'requires':
          if (!in_array(current($xn['attributes']), $this->permissions[$xn['tag']]))
            $this->permissions[$xn['tag']][] = current($xn['attributes']);
          break;
      }
    }
    
          
    // Special case: HTML TODO: is there a better way to do this?
    preg_match('/<html>(.*)<\/html>/', $xml, $matches);
    $this->html = $matches[1];
  }

  /**
   * Return full license name.
   */
  function get_full_license_name() {
    if (!$this->has_license()) {
      return 'No License';
    }
    else if ($this->is_valid()) {
      $class = $this->license_class == 'standard' ? 'Creative Commons ' : '';
      return $class . $this->license_name;
    }
    else {
      return '"'. $this->license_uri .'"';
    }
  }
  /**
   * Set a value in the metadata array
   */
  function set_metadata($name, $value) {
    if ($name && $value)
      $this->metadata[$name] = $value;
  }


  /**
   * Return array of images relating to current license
   * - if ($site_license) then force return of standard license image
   */
  function get_images($site_license = FALSE) {
    $default = array(CC_IMG_PATH .'/somerights20.gif');
    if ($site_license)
      $display = 1;
    else
      $display = variable_get('creativecommons_display', 1);
    $images = array();
    switch ($display) {

      // no image
      case(0):
      case(3):
        return;

      // icons
      case(2):
        // public domain license
        if ($this->license_class == 'publicdomain') {
          $images[] = CC_IMG_PATH .'/icon-publicdomain.png';
          break;
        }

        // sampling license
        else if ($this->license_class == 'recombo') {
          if ($this->license_name == 'Sampling 1.0') {
            $images[] = CC_IMG_PATH .'/icon-sampling.png';
          }
          else if ($this->license_name == 'Sampling Plus 1.0') {
            $images[] = CC_IMG_PATH .'/icon-samplingplus.png';
          }
          else if ($this->license_name == 'NonCommericial Sampling Plus 1.0') {
            $images[] = CC_IMG_PATH .'/icon-noncommercial.png';
            $images[] = CC_IMG_PATH .'/icon-samplingplus.png';
          }
          $images[] = CC_IMG_PATH .'/icon-attribution.png';
        }

        // creative commons / other license
        else {
          if (in_array('http://web.resource.org/cc/Attribution', $this->permissions['requires']))
            $images[] = CC_IMG_PATH .'/icon-attribution.png';
          if (in_array('http://web.resource.org/cc/CommercialUse', $this->permissions['prohibits']))
            $images[] = CC_IMG_PATH .'/icon-noncommercial.png';
          if (!in_array('http://web.resource.org/cc/DerivativeWorks', $this->permissions['permits']))
            $images[] = CC_IMG_PATH .'/icon-derivative.png';
          if (in_array('http://web.resource.org/cc/ShareAlike', $this->permissions['requires']))
            $images[] = CC_IMG_PATH .'/icon-sharealike.png';
        }
        break;

      // single image
      case(1):
      default:
        switch ($this->license_class) {
          case 'standard':
            $images[] = CC_IMG_PATH .'/img-somerights.gif';
            break;
          case 'publicdomain':
            $images[] = CC_IMG_PATH .'/img-norights.gif';
            break;
          case 'recombo':
            $images[] = CC_IMG_PATH .'/img-recombo.gif';
            break;
          // generic 'some rights reserved' image
          default:
            return $default;
        }
        break;
    }
    foreach ($images as $k => $img_uri)
      $images[$k] = '<img alt="Creative Commons License" border="0" src="'. $img_uri .'" />';
    return $images;
  }

  /**
   * Returns true if license set, false otherwise
   */
  function has_license() {
    return !empty($this->license_uri);
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
    // A blank license is technically 'available'
    if (!$this->has_license())
      return TRUE;

    // Check if license is valid
    if (!$this->is_valid())
      return FALSE;

    // Check if license type is available
    $available_license_types = creativecommons_get_available_license_types();
    if (!in_array($this->license_type, $available_license_types))
      return FALSE;

    return TRUE;
  }

  /**
   * Return html containing license link (+ images)
   */
  function get_html() {

    // must have a license to display html
    if ($this->has_license())
      return $this->html;


    /* $txt = 'This work is licensed under a '.
      l(t('Creative Commons License'),
        $this->license_uri,
        array(
          'attributes' => array('rel' => 'license', 'title' => $this->license_name),
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
          $this->license_uri,
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


    foreach ($this->rdf['attributes'] as $attr => $val)
      $a .= " $attr=\"$val\"";
    $rdf = "<rdf:RDF$a>\n";

    // metadata
    $rdf .= "<work rdf:about=\"". $this->metadata['source'] ."\">\n";
    if ($this->metadata) {
      foreach ($this->metadata as $k => $v) {
        if ($v) {
          switch ($k) {

            case 'format':
              $k = 'type';
              if (!$v = $this->get_format_uri($v))
                break;
            case 'source':
              $rdf .= "<dc:$k rdf:resource=\"$v\" />\n";
              break;

            case 'rights':
            case 'creator':
              $rdf .= "<dc:$k><agent><dc:title>$v</dc:title></agent></dc:$k>\n";
              break;

            default:
              $rdf .= "<dc:$k>$v</dc:$k>\n";
              break;
          }
        }
      }
    }
    $rdf .= "<license rdf:resource=\"". $this->license_uri ."\" />\n";
    $rdf .= "</Work>\n";

    // permissions
    $rdf .= "<license rdf:about=\"". $this->license_uri ."\">\n";
    foreach ($this->permissions as $name => $perm)
      foreach ($perm as $v)
        $rdf .= "<$name rdf:resource=\"$v\" />\n";

    $rdf .= "</license>\n";
    $rdf .= "</rdf:RDF>";
    return $rdf;
  }


  /**
   * return url for rdf that defines license format
   */
  function get_format_uri($format) {
    switch (drupal_strtolower($format)) {
      case 'audio':             return 'http://purl.org/dc/dcmitype/Sound';
      case 'video':             return 'http://purl.org/dc/dcmitype/MovingImage';
      case 'image':             return 'http://purl.org/dc/dcmitype/StillImage';
      case 'text':              return 'http://purl.org/dc/dcmitype/Text';
      case 'interactive':       return 'http://purl.org/dc/dcmitype/Interactive';
      case 'other':             return;
      default:                  return;
    }
  }


  /**
   * Serialize object and save to the database
   */
  function save($nid) {
    //TODO: improve error handling
    if (!$nid) {
      drupal_set_message('A node must be specified to save a license', 'error');
    }

    if ($nid && $this->has_license() && $this->is_available()) {
      $result = db_query("INSERT INTO {creativecommons} (nid, license_uri) VALUES (%d, '%s')",  $nid, $this->license_uri);
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
      if (variable_get('creativecommons_rdf', FALSE)) {
        $output .= "\n<!-- ". $this->get_rdf() ." -->\n";
      }

      $output .= "<!--/Creative Commons License-->\n";
      return $output;
    }
  }
}
