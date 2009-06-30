<?php
// $Id: creativecommons.class.php,v 1.3.4.6 2009/06/30 21:31:18 balleyne Exp $

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
class creativecommons_license {
  // license attributes
  var $license_uri;
  var $license_name;
  var $license_type;
  var $permissions;
  var $metadata;

  // assigned license
  var $nid;


  /**
   * Initialize object
   */
  function __construct($license, $questions = NULL, $metadata = array()) {
    // Destruct if no license value provided
    if (!$license) {
      unset($this);
      return;
    }


    $this->permissions = array();
    $this->permissions['requires'] = array();
    $this->permissions['prohibits'] = array();
    $this->permissions['permits'] = array();

    if ($questions) {
      $this->license_type = $license;
      $xml = $this->post_answers($questions);
      foreach ($questions as $q => $a)
        $this->$q = $a['selected'];
    }
    else {
      $this->license_type = 'standard'; // TODO: this is assumed...
      $xml = $this->license_details($license);
    }


    if ($xml) {
      $parser = xml_parser_create();
      xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
      xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
      xml_parse_into_struct($parser, $xml, $values, $tags);
      xml_parser_free($parser);
      $this->extract_values($values, $tags);
    }

    $this->metadata = $metadata;

    // TODO: is there a better way to do this? will this work with questions too?
    preg_match('/<html>(.*)<\/html>/', $xml, $matches) ? 'yes' : 'no';
    $this->html = $matches[1];
  }


  /**
   * Get license details from API by uri
   */
  function license_details($license_uri) {
    $response = creativecommons_api_request('/details?license-uri='. urlencode($license_uri));
    if ($response->code == 200)
      return $response->data;
  }

  /**
   * Post answer data to creative commons web api, return xml response.
   */
  function post_answers($questions) {
    $id = $this->license_type;
    if (isset($id) && $id != 'none') {

      // required header
      $headers = array();
      $headers['Content-Type'] = 'application/x-www-form-urlencoded';

      // request
      $uri = '/license/'. $id .'/issue';

      foreach ($questions as $q => $a)
        $answer_xml .= "<$q>". $a['selected'] ."</$q>";
      $answer_xml = "<answers><license-$id>$answer_xml</license-$id></answers>";

      // post to cc api
      $post_data = 'answers='. urlencode($answer_xml) ."\n";
      $response = creativecommons_api_request($uri, $headers, 'POST', $post_data);
      if ($response->code == 200)
        return $response->data;
    }
    return;
  }


  /**
   * Extract values from array of xml data.
   */
  function extract_values($values, $tags) {
    foreach ($values as $xn) {
      switch ($xn['tag']) {
        //TODO: better error handling
        case 'error':
          drupal_set_message('Error retrieving license information from CC API: '. $values[2]['value'], 'error');
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
          $this->permissions['permits'][] = current($xn['attributes']);
          break;

        case 'prohibits':
          $this->permissions['prohibits'][] = current($xn['attributes']);
          break;

        case 'requires':
          $this->permissions['requires'][] = current($xn['attributes']);
          break;
      }
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
        if ($this->license_type == 'publicdomain') {
          $images[] = CC_IMG_PATH .'/icon-publicdomain.png';
          break;
        }

        // sampling license
        else if ($this->license_type == 'recombo') {
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
        switch ($this->license_type) {
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
    return !is_null($this->license_uri);
  }

  /**
   * Return html containing license link (+ images)
   */
  function get_html($site_license = FALSE) {

    // must have a license to display html
    if (!$this->has_license())
      return;

    $html = "\n<!--Creative Commons License-->\n". $this->html;

    if ($site_license) {
      if ($footer_text = variable_get('creativecommons_site_license_additional_text', NULL))
        $html .= '<br />'. $footer_text;
    }
    $html .= "<!--/Creative Commons License-->\n";

    return $html;


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
  function save() {
    if ($this->nid && $this->license_uri) {
      $data = serialize($this);
      $data = str_replace("'", "\'", $data);
      $result = db_query("INSERT INTO {creativecommons} (nid, data) VALUES (%d, '%s')",  $this->nid, $data);
      return $result;
    }
    return;
  }
}
