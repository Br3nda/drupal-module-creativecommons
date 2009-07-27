<?php
// $Id: creativecommons.class.php,v 1.3.4.21 2009/07/27 18:01:59 balleyne Exp $

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
//TODO: error handling http://api.creativecommons.org/docs/readme_15.html#error-handling
//TODO: optimize by storing values when functions are called (e.g. is_valid, is_available)?
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
  function __construct($license_uri, $metadata = array()) {
    // Store metadata
    $this->metadata = $metadata;

    // don't load a blank license
    if (!$license_uri) {
      $this->name = 'None (All Rights Reserved)';
      $this->type = '';
      return;
    }

    // Load license information
    $this->uri = $license_uri;
    $this->load();
  }

  /**
   * Load basic information from uri and XML data from API into object.
   */
  function load() {
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

      $this->html = '<p xmlns:dct="http://purl.org/dc/terms/" xmlns:vcard="http://www.w3.org/2001/vcard-rdf/3.0#">
        <a rel="license" href="'. $this->uri .'" style="text-decoration:none;">
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
              . ($this->error['id'] == 'invalid' ? ' '. $this->get_full_name() : '');
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
  function get_full_name() {
    if ($this->is_valid()) {
      // Special Case
      $class = ($this->type && $this->type != 'zero') ? 'Creative Commons ' : '';
      return $class . $this->name;
    }
    else {
      return '"'. $this->uri .'"';
    }
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
   * Return html containing license link (+ images)
   */
  function get_html() {

    // must have a license to display html
    if ($this->has_license())
      return $this->html;

    //TODO: review
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

    if ($this->rdf) {
      foreach ($this->rdf['attributes'] as $attr => $val)
        $a .= " $attr=\"$val\"";
    }
    $rdf = "<rdf:RDF$a>\n";

    // metadata
    // TODO: review
    $rdf .= "<work rdf:about=\"". $this->metadata['source'] ."\">\n";
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
              $rdf .= "<$ns:$key><agent><dc:title>$value</dc:title></agent></dc:$key>\n";
              break;

            case 'attributionName':
            case 'attributionURL':
              $ns = 'cc';
            default:
              $rdf .= "<$ns:$key>$value</dc:$key>\n";
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
   */
  function save($nid) {
    if (!$nid) {
      drupal_set_message('A node must be specified to save a license', 'error');
    }
    else if (!$this->is_available()) {
      drupal_set_message('License is not available', 'error');
    }
    else {
      $result = db_query("INSERT INTO {creativecommons} (nid, license_uri, attributionName, attributionURL, title, type, description, creator, rights, date, source) ".
        "VALUES (%d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')",
          $nid,
          $this->uri,
          $this->metadata['attributionName'],
          $this->metadata['attributionURL'],
          $this->metadata['title'],
          $this->metadata['type'],
          $this->metadata['description'],
          $this->metadata['creator'],
          $this->metadata['rights'],
          $this->metadata['date'],
          $this->metadata['source']
        );

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
