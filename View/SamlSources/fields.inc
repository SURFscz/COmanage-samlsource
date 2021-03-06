<!--
/**
 * COmanage Registry SAML Source Plugin Fields
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
-->
<?php
  // Determine if fields are editable
  $e = false;

  if(($this->action == "add" && $permissions['add']) || ($this->action == "edit" && $permissions['edit']))
    $e = true;

  // We shouldn't get here if we don't have at least read permission, but check just in case

  if(!$e && !$permissions['view'])
    return false;

  // Add breadcrumbs
  print $this->element("coCrumb");

  $args = array();
  $args['plugin'] = null;
  $args['controller'] = 'org_identity_sources';
  $args['action'] = 'index';
  $args['co'] = $cur_co['Co']['id'];
  $this->Html->addCrumb(_txt('ct.org_identity_sources.pl'), $args);

  $args = array();
  $args['plugin'] = null;
  $args['controller'] = 'org_identity_sources';
  $args['action'] = 'edit';
  $args[] = $vv_oisid;
  $this->Html->addCrumb($saml_sources[0]['OrgIdentitySource']['description'], $args);

  $crumbTxt = _txt('op.config');
  $this->Html->addCrumb($crumbTxt);

  print $this->Form->hidden('org_identity_source_id', array('default' => $vv_oisid)) . "\n";
?>
<div id="saml_attributes" class="field-children table-container">
  <table id="<?php print $this->action; ?>_saml_attributes">
    <?php
      $l = 1;
    ?>
    <tbody>
      <?php foreach($vv_available_attributes as $field => $attr): ?>
      <tr class="line<?php print ($l % 2); $l++; ?>">
        <td>
          <?php
            print $attr['label'];

            if(isset($attr['required']) && $attr['required']) {
              print "<span class=\"required\">*</span>";
            }

            if(!empty($attr['desc'])) {
              print "<span class=\"descr\">" . $attr['desc'] . "</span>";
            }
          ?>
        </td>
        <td>
          <?php
            $args = array();
            $args['size'] = 40;

            if(!empty($attr['default'])) {
              $args['default'] = $attr['default'];
            }

            if(isset($attr['required']) && $attr['required']) {
              $args['required'] = true;
            }

            if(isset($attr['modifiable']) && !$attr['modifiable']) {
              print filter_var($args['default'],FILTER_SANITIZE_SPECIAL_CHARS);
            }
            else {
              print $this->Form->input($field, $args);
            }
          ?>
        </td>
      </tr>
      <?php endforeach; // $attrs ?>
      <tr>
        <td><span class="required"><?php print _txt('fd.req'); ?></span></td>
        <td><?php print $this->Form->submit($submit_label); ?></td>
      </tr>
    </tbody>
  </table>
</div>
