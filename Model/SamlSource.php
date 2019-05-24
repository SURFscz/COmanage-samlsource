<?php
/**
 * COmanage Registry SAML OrgIdentitySource Model
 *
 * Portions licensed to the University Corporation for Advanced Internet
 * Development, Inc. ("UCAID") under one or more contributor license agreements.
 * See the NOTICE file distributed with this work for additional information
 * regarding copyright ownership.
 *
 * UCAID licenses this file to you under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with the
 * License. You may obtain a copy of the License at:
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @link          http://www.internet2.edu/comanage COmanage Project
 * @package       registry-plugin
 * @since         COmanage Registry vTODO
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

class SamlSource extends AppModel {
  // Required by COmanage Plugins
  public $cmPluginType = "orgidsource";

  // Document foreign keys
  public $cmPluginHasMany = array();

  // Association rules from this model to other models
  public $belongsTo = array("OrgIdentitySource");

  // Default display field for cake generated views
//  public $displayField = "env_name_given";

  // Validation rules for table elements
  public $validate = array(
    'org_identity_source_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'message' => 'An Org Identity Source ID must be provided'
    ),
    'saml_var_prefix' => array(
      'rule' => 'notBlank',
      'required' => false,
      'allowEmpty' => true
    ),
    'saml_sorid' => array(
      'rule' => 'notBlank',
      'required' => false,
      'allowEmpty' => true
    ),
  );

  /**
   * Obtain the list of attributes available for loading into an Org Identity.
   *
   * @since  COmanage Registry vTODO
   * @return Array Array of available attributes
   */

  public function availableAttributes() {
    // Attributes should be listed in the order they are to be rendered in.
    // The various _name fields are default values that can be overridden.
    // Attribute types are forced to Official since they come from an "official" source.

    // The key is the column name in cm_saml_sources

    // If we have a configuration file with values for these fields, set
    // the default value and disable the attributes.
    $varprefix = $this->readFileConfiguration("var_prefix");
    $sorid = $this->readFileConfiguration("sorid");

    $attributes = array(
      'saml_var_prefix' => array(
        'label'    => _txt('pl.samlsource.prefix'),
        'default'  => $varprefix === FALSE ? 'MELLON_' : $varprefix,
        'required' => true,
        'desc'     => _txt('pl.samlsource.prefix.desc'),
        'modifiable' => $varprefix == null
      ),
      'saml_sorid' => array(
        'label'    => _txt('pl.samlsource.identifier'),
        'default'  => $sorid === FALSE ? 'cmuid' : $sorid,
        'required' => true,
        'desc'     => _txt('pl.samlsource.identifier.desc'),
        'modifiable' => $sorid == null
      )
    );

    return $attributes;
  }

  /**
   * Expose menu items.
   *
   * @ since COmanage Registry vTODO
   * @ return Array with menu location type as key and array of labels, controllers, actions as values.
   */

  public function cmPluginMenus() {
    return array();
  }

  /**
   * Set model values based on configuration, if such is present
   */
  public function beforeValidate($options=FALSE) {
    $varprefix = $this->readFileConfiguration("var_prefix");
    $sorid = $this->readFileConfiguration("sorid");

    if($varprefix !== FALSE) {
      $this->data[$this->alias]["saml_var_prefix"] = $varprefix;
    }
    if($sorid !== FALSE) {
      $this->data[$this->alias]["saml_sorid"] = $sorid;
    }
  }

  /**
   * Read configuration file variables, if present
   */
  private function readFileConfiguration($key, $def=FALSE) {
    if(!isset($this->_configread)) {
      $this->_configread = TRUE;
      if(!Configure::read('samlsource')) {
        try {
          Configure::load('samlsource');
        }
        catch(ConfigureException $e) {
          // no configuration file found
        }
      }
    }
    if(Configure::read('samlsource')) {
      $value = Configure::read('samlsource.'.$key);
      if($value === FALSE) {
        return $def;
      }
      return $value;
    }
    return $def;
  }

}
