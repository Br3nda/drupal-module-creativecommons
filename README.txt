/* $Id: README.txt,v 1.1.2.2 2009/08/05 05:06:35 balleyne Exp $ */

-- SUMMARY --

The Creative Commons module allows users to assign a Creative Commons license to
the content of a node, or to specify a site-wide license. It uses to Creatve 
Commons API to retrieve up-to-date license information. Licenses are diplayed 
using a Creative Commons Node License block and the Creative Commons Site 
License block. The module also supports some license metadata fields. License
information is output using ccREL RDFa inside the blocks, and can optionally be
output as RDF/XML in the body of a node.

For a full description of the module, visit the project page:
  http://drupal.org/project/creativecommons

To submit bug reports and feature suggestions, or to track changes:
  http://drupal.org/project/issues/creativecommons


-- REQUIREMENTS --

None.


-- INSTALLATION --

* Install as usual, see http://drupal.org/node/70151 for further information.


-- CONFIGURATION --

* Configure user permissions in Administer >> User management >> Permissions >>
  creativecommons module:

  - administer creative commons

    Users in roles with the "administer creative commons" permission can
    customize the module settings in Administer >> Settings >> Creative Commons

  - attach creative commons

    Users in roles with the "attach creative commons" permission will be able to
    attach license information to the content of a node.

* Set available license types, required metadata and display settings
  Administer >> Settings >> Creative Commons. To make it mandatory to specify a
  license, simply make the 'None' type unavailable.

* Set default license type and jurisdiction in Administer >> Settings >> 
  Creative Commons >> site defaults. Here, you can set the default license to be
  used as a site-wide license if you wish.
  
* Enable Creative Commons licensing for desired content types in Administer >>
  Settings >> Creative Commons >> content types. For example, you might wish to 
  allow Creative Commons licensing for blog posts, but not forum posts.


-- CONTACT --

Current maintainers:
* Blaise Alleyne (balleyne) - http://drupal.org/user/362044
* Kevin Reynen (kreynen) - http://drupal.org/user/48877

Initial development done by Kevin Reynen (kreynen). Rewrite for Drupal 6 
by Blaise Alleyne (balleyne) as part of the Google Summer of Code 2009.

This project has been sponsored by:
* Google
  A project to update and expand the module for Drupal 6 was part of the Google
  Summer of Code 2009


