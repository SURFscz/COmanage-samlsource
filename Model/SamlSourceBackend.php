<?php
/**
 * COmanage Registry SAML OrgIdentitySource Backend Model
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

App::uses("OrgIdentitySourceBackend", "Model");

class SamlSourceBackend extends OrgIdentitySourceBackend {
  public $name = "SamlSourceBackend";

  /**
   * Generate the set of attributes for the IdentitySource that can be used to map
   * to group memberships. The returned array should be of the form key => label,
   * where key is meaningful to the IdentitySource (eg: a number or a field name)
   * and label is the localized string to be displayed to the user. Backends should
   * only return a non-empty array if they wish to take advantage of the automatic
   * group mapping service.
   *
   * @since  COmanage Registry vTODO
   * @return Array As specified
   */

  public function groupableAttributes() {
    return array();
  }

  /**
   * Obtain all available records in the IdentitySource, as a list of unique keys
   * (ie: suitable for passing to retrieve()).
   *
   * @since  COmanage Registry vTODO
   * @return Array Array of unique keys
   * @throws DomainException If the backend does not support this type of requests
   */

  public function inventory() {
    throw new DomainException(_txt('in.ois.noinventory'));
  }

  /**
   * Read and store a value in the settings array
   *
   * @since  COmanage Registry vTODO
   * @param  Array List of attributes and values
   * @param  String Key to check
   * @param  String Value to add
   * @param  String Prefix to check on key
   * @param  Int    Prefix length
   * @return Array List of attributes and values
   */

  protected function addKey($result, $key, $value, $prefix, $prefix_length) {
    if(!strncasecmp($key,$prefix,$prefix_length) && strlen($key)>$prefix_length) {
      // do we have a postfix in the way of '_<index>' to distinguish multiple values
      $matches=array();
      if(preg_match('/^'.$prefix.'(.*)_[0-9]+$/i', $key, $matches)) {
        $key = $matches[1];
        if(!isset($result[$key])) $result[$key]=array();

        // filter out duplicate values
        if(!in_array($value, $result[$key])) {
          $result[$key][]=$value;
        }
      }
    }
    return $result;
  }

  /**
   * Read all environment variables that start with the indicated prefix
   *
   * @since  COmanage Registry vTODO
   * @return Array List of attributes and values
   */
  protected function readConfig() {
    $retval=array();
    $prefix = empty($this->pluginCfg['saml_var_prefix']) ? '' : $this->pluginCfg['saml_var_prefix'];
    $prefix_length=strlen($prefix);
    foreach($_ENV as $key=>$value) {
      $this->devLog('testing value '.$key.' from _ENV');
      $retval = $this->addKey($retval, $key, $value, $prefix, $prefix_length);
    }
    foreach($_SERVER as $key=>$value) {
      $this->devLog('testing value '.$key.' from _SERVER');
      $retval = $this->addKey($retval, $key, $value, $prefix, $prefix_length);
    }
    $this->devLog('returning '.json_encode($retval,JSON_PRETTY_PRINT));
    return $retval;
  }

  /**
   * Split a commonName in a givenName, middle name etc
   *
   * @since  COmanage Registry vTODO
   * @param  String commonName
   * @return Array Name object
   */
  protected function splitName($cn) {
    $this->devLog('splitting name '.$cn);
    $name=array(
      'honorific' => '',
      'given' => '',
      'middle' => '',
      'family' => '',
      'suffix' => '',
      'type'=> NameEnum::Official
    );

    // explode based on spaces. Names that
    // are separated with dashes or dots are
    // considered part of the same structure
    $values=mb_split('\s',$cn);

    // We expect cases like:
    // Dr. Pete W. Johnson Phd.   --> 'Dr.', 'Pete'. 'W.', 'Johnson','Phd.'
    // Carola Kennedy             --> '', 'Carola', '', 'Kennedy', ''
    // Pete H. Johnson, MD        --> '', 'Pete', 'H.', 'Johnson', 'MD'
    // Juan Bolivar di Perez      --> '', 'Juan', '', 'Bolivar di Perez',''
    // Pieter van der Veer        --> '', 'Pieter', '', 'van der Veer', ''
    //
    // Expected error cases:
    // John Frank Peterson        --> '','John','','Frank Peterson',''

    $index=0;
    $namefound=false;
    $lastnamefound=false;
    $forcesuffix=false;
    while($index < sizeof($values)) {
      $part = $values[$index];
      $index += 1;

      if(mb_strlen($part)) {
        $hasdot = mb_strlen($part) > 1 && mb_substr($part,mb_strlen($part)-1,1) == '.';

        // Case: Pete Johnson, Phd.
        $hascomma = mb_strlen($part) > 1 && mb_substr($part,mb_strlen($part)-1,1) == ',';
        if($hascomma) {
          // remove the comma. The next items are forced as suffix
          $part = mb_substr($part,0, mb_strlen($part)-1);
        }
        // any two-or-larger letter combination that ends
        // with a dot is considered a honorific, if we did not
        // find a name yet
        // If it is only 1 letter and a dot, it is considered an initial,
        // although there are initials with 2 letters, like 'Ae.'
        if(!$namefound && mb_strlen($part)>3 && $hasdot) {
          $name['honorific'] .= (mb_strlen($name['honorific']) ? ' ': '') . $part;
        }
        else {
          $namefound = true;

          if(!mb_strlen($name['given'])) {
            $name['given'] = $part;
          }
          else if($hasdot && !$lastnamefound) {
            $name['middle'] = (mb_strlen($name['middle'])>0 ? ' ': '') . $part;
          }
          else if(($hasdot || $forcesuffix) && $lastnamefound) {
            $name['suffix'] .= (mb_strlen($name['suffix'])>0 ? ' ' : '') . $part;
          }
          else {
            $lastnamefound = true;
            $name['family'] .= (mb_strlen($name['family'])>0 ? ' ' : '') . $part;
            if($hascomma) $forcesuffix=true;
          }
        }
      }
    }

    // Case: 'P. Jones'
    if(!mb_strlen($name['given']) && mb_strlen($name['honorific'])) {
      $name['given'] = $name['honorific'];
      $name['honorific'] = '';
    }

    // Case: 'Smith' or 'John'
    // We need at least a given name for the data model
    if(!mb_strlen($name['given']) && mb_strlen($name['family'])) {
      $name['given'] = $name['family'];
      $name['family'] = '';
    }
    $this->devLog('Returning name '.json_encode($name));
    return $name;
  }

  /**
   * Normalize the names we generated based on the remote values
   *
   * @since  COmanage Registry vTODO
   * @param  Array OrgData
   * @return Array OrgData
   */
  protected function normalizeNames($orgdata, $sorid) {
    $this->devLog('normalizing names using '.json_encode($orgdata) ." and ".json_encode($sorid));
    // see if we have an empty 'name[0]', but other names set based
    // on commonName or displayName
    if(generateCn($orgdata['Name'][0]) == '') {
      if(sizeof($orgdata['Name']) > 1) {
        // we can use a different name
        unset($orgdata['Name'][0]);
      }
      // else we create a given name below
    }

    if(sizeof($orgdata['Name']) == 0) {
      // We need a Name in order to save an OrgIdentity, but we may not get one since
      // some IdPs don't release meaningful attributes. So we create default values.
      $orgdata['Name'][0]['type'] = NameEnum::Official;
      $orgdata['Name'][0]['given'] = $sorid;
    }

    // given name is a required field
    // Loop over the names until we are sure the first name is valid
    while(sizeof($orgdata['Name']) > 0 && empty($orgdata['Name'][0]['given'])) {
      if(!empty($orgdata['Name'][0]['family'])) {
        // move family name to given name to satisfy the data model

        $orgdata['Name'][0]['given'] = $orgdata['Name'][0]['family'];
        $orgdata['Name'][0]['family'] = '';
      }
      else {
        if(sizeof($orgdata['Name']) > 1) {
          // we have different names, we can discard this incomplete name
          unset($orgdata['Name'][0]);
        }
        else if(!empty($sorid)) {
          // The only thing we can guarantee is SORID
          $this->devLog('creating new given name based on sorid '.json_encode($sorid));
          $orgdata['Name'][0]['given'] = $sorid;
        }
        else {
          // Populate a default given name in case it's required.
          $orgdata['Name'][0]['given'] = _txt('pl.samlsource.name.unknown');
        }
      }
    }

    // finally see if we have duplicate values, which can be due to
    // having values for commonName, displayName and individual fields
    // givenName, surname, etc.
    $names=array();
    $newnamelist=array();
    foreach($orgdata['Name'] as $name) {
      $nm = generateCn($name);
      $this->devLog("testing name '$nm'");
      if(!in_array($nm,$names)) {
        $newnamelist[]=$name;
        $names[]=$nm;
      }
      else $this->devLog('removing duplicate value');
    }
    $orgdata['Name']=$newnamelist;

    return $orgdata;
  }

  /**
   * Convert a search result into an Org Identity.
   *
   * @since  COmanage Registry vTODO
   * @param  Array $result File Search Result
   * @return Array Org Identity and related models, in the usual format
   */

  protected function resultToOrgIdentity($result) {
    // check which attribute is used as REMOTE_USER value
    $sorid_attr = $this->pluginCfg['saml_sorid'];

    $orgdata = array();
    $orgdata['OrgIdentity'] = array();
    $orgdata['Name'] = array(array("type"=>NameEnum::Official));
    $orgdata['EmailAddress'] = array();
    $orgdata['Address'] = array(array());
    $orgdata['Identifier']=array();
    $orgdata['TelephoneNumber'] = array();
    $orgdata['SshKey']=array();

    foreach($result as $key => $values) {
      if(!is_array($values)) $values=array($values);
      foreach($values as $value) {
        $this->devLog('determining attribute '.$key. ' = ' .$value);
        $identifier_added = false;

        switch(strtolower($key)) {
        case 'email':
        case 'urn:mace:dir:attribute-def:email':
        case 'emailaddress':
        case 'urn:mace:dir:attribute-def:emailaddress':
        case 'pkcs9email':
        case 'urn:mace:dir:attribute-def:pkcs9email':
        case 'urn:oid:1.2.840.113549.1.9.1':
        case 'mail':
        case 'urn:mace:dir:attribute-def:mail':
        case 'rfc822mailbox':
        case 'urn:mace:dir:attribute-def:rfc822mailbox':
        case 'urn:oid:0.9.2342.19200300.100.1.3':
          $this->devLog('this is an email address');
          $mails=array();
          foreach($orgdata['EmailAddress'] as $ma) if(isset($ma['mail'])) $mails[]=$ma['mail'];
          $this->devLog('testing for '.$value.' in '.json_encode($mails));
          if(!in_array($value,$mails)) {
            $this->devLog('email address not found yet, adding new address');
            $mail=array(
              'mail' => $value,
              'type' => EmailAddressEnum::Official,
              'verified' => true
            );
            $orgdata['EmailAddress'][] = $mail;
          }
          break;

        case 'userid':
        case 'urn:mace:dir:attribute-def:userid':
        case 'uid':
        case 'urn:mace:dir:attribute-def:uid':
        case 'urn:oid:0.9.2342.19200300.100.1.1':
        case 'uniqueidentifier':
        case 'urn:mace:dir:attribute-def:uniqueidentifier':
        case 'urn:oid:0.9.2342.19200300.100.1.44':
          $this->devLog('this is a UID identifier');
          $ids=array();
          foreach($orgdata['Identifier'] as $id) {
            if($id['type'] == IdentifierEnum::UID) {
              $ids[]=$id['identifier'];
            }
          }
          $this->devLog('testing for '.$value.' in '.json_encode($ids));
          if(!in_array($value,$ids)) {
            $this->devLog('Creating new UID identifier, checking if '.$sorid_attr.' == '.$key);
            $orgdata['Identifier'][] = array(
              'identifier' => $value,
              'login'      => ($sorid_attr == $key) ? true : false,
              'status'     => StatusEnum::Active,
              'type'       => IdentifierEnum::UID
            );
            $identifier_added = true;
          }
          break;

        case 'roomnumber':
        case 'urn:mace:dir:attribute-def:roomnumber':
        case 'urn:oid:0.9.2342.19200300.100.1.6':
          $this->devLog('room number');
          $orgdata['Address'][0]['room']=$value;
          break;

        case 'hometelephonenumber':
        case 'urn:mace:dir:attribute-def:hometelephonenumber':
        case 'homephone':
        case 'urn:mace:dir:attribute-def:homephone':
        case 'urn:oid:0.9.2342.19200300.100.1.20':
          $this->devLog('telephoneNumber');
          $tels=array();
          foreach($orgdata['TelephoneNumber'] as $tn) {
            if($tn['type'] == ContactEnum::Home) {
              $tels[]=$tn['number'];
            }
          }
          $this->devLog('testing for '.$value.' in '.json_encode($tels));
          if(!in_array($value,$tels)) {
            $tn=array(
              'number' => $value,
              'type' => ContactEnum::Home
            );
            $orgdata['TelephoneNumber'][] = $tn;
          }
          break;

        case 'othermailbox':
        case 'urn:mace:dir:attribute-def:othermailbox':
        case 'urn:oid:0.9.2342.19200300.100.1.22':
          $this->devLog('other mail box');
          $mails=array();
          foreach($orgdata['EmailAddress'] as $ma) $mails[]=$ma['mail'];
          $this->devLog('testing for '.$value.' in '.json_encode($mails));
          if(!in_array($value,$mails)) {
            $mail=array(
              'mail' => $value,
              'type' => EmailAddressEnum::Recovery,
              'verified' => true
            );
            $orgdata['EmailAddress'][] = $mail;
          }
          break;

        case 'homepostaladdress':
        case 'urn:mace:dir:attribute-def:homepostaladdress':
        case 'urn:oid:0.9.2342.19200300.100.1.39':
          $this->devLog('homepostaladdress');
          $addrs=array();
          foreach($orgdata['Address'] as $addr) {
            if($addr['type'] == ContactEnum::Home) {
              $addrs[]=$ma['street'];
            }
          }
          if(!in_array($value,$addrs)) {
            $addr=array(
              'street' => $value,
              'type' => ContactEnum::Home
            );
            $orgdata['Address'][] = $addr;
          }
          break;

        case 'mobile':
        case 'urn:mace:dir:attribute-def:mobile':
        case 'mobiletelephonenumber':
        case 'urn:mace:dir:attribute-def:mobiletelephonenumber':
        case 'urn:oid:0.9.2342.19200300.100.1.41':
          $this->devLog('mobile telephonenumber');
          $tels=array();
          foreach($orgdata['TelephoneNumber'] as $tn) {
            if($tn['type'] == ContactEnum::Mobile) {
              $tels[]=$tn['number'];
            }
          }
          if(!in_array($value,$tels)) {
            $tn=array(
              'number' => $value,
              'type' => ContactEnum::Mobile
            );
            $orgdata['TelephoneNumber'][] = $tn;
          }
          break;

        case 'pager':
        case 'urn:mace:dir:attribute-def:pager':
        case 'pagertelephonenumber':
        case 'urn:mace:dir:attribute-def:pagertelephonenumber':
        case 'urn:oid:0.9.2342.19200300.100.1.42':
          // skip pager numbers
          break;

        case 'friendlycountryname':
        case 'urn:mace:dir:attribute-def:friendlycountryname':
        case 'co':
        case 'urn:mace:dir:attribute-def:co':
        case 'urn:oid:0.9.2342.19200300.100.1.43':
        case 'c':
        case 'urn:mace:dir:attribute-def:c':
        case 'countryname':
        case 'urn:mace:dir:attribute-def:countryname':
        case 'urn:oid:2.5.4.6':
        case 'schaccountryofresidence':
        case 'urn:mace:terena.org:attribute-def:schaccountryofresidence':
        case 'urn:oid:1.3.6.1.4.1.25178.1.2.11':
          $this->devLog('country');
          $orgdata['Address'][0]['country']=$value;
          break;

        case 'cn':
        case 'urn:mace:dir:attribute-def:cn':
        case 'commonname':
        case 'urn:mace:dir:attribute-def:commonname':
        case 'urn:oid:2.5.4.3':
          $this->devLog('commonName');
          $names=array();
          foreach($orgdata['Name'] as $nm) {
            $names[]=generateCn($nm);
          }
          $this->devLog('testing for '.$value.' in '.json_encode($names));
          if(!in_array($value, $names)) {
            $this->devLog('splitting name');
            $orgdata['Name'][]=$this->splitName($value);
          }
          break;

        case 'sn':
        case 'urn:mace:dir:attribute-def:sn':
        case 'surname':
        case 'urn:mace:dir:attribute-def:surname':
        case 'urn:oid:2.5.4.4':
          $orgdata['Name'][0]['family']=$value;
          break;

        case 'localityname':
        case 'urn:mace:dir:attribute-def:localityname':
        case 'l':
        case 'urn:mace:dir:attribute-def:l':
        case 'urn:oid:2.5.4.7':
          $orgdata['Address'][0]['locality']=$value;
          break;

        case 'st':
        case 'urn:mace:dir:attribute-def:st':
        case 'stateorprovincename':
        case 'urn:mace:dir:attribute-def:stateorprovincename':
        case 'urn:oid:2.5.4.8':
          $orgdata['Address'][0]['state']=$value;
          break;

        case 'street':
        case 'urn:mace:dir:attribute-def:street':
        case 'streetaddress':
        case 'urn:mace:dir:attribute-def:streetaddress':
        case 'urn:oid:2.5.4.9':
          $orgdata['Address'][0]['street']=$value;
          break;

        case 'o':
        case 'urn:mace:dir:attribute-def:o':
        case 'organizationname':
        case 'urn:mace:dir:attribute-def:organizationname':
        case 'urn:oid:2.5.4.10':
        case 'schachomeorganization':
        case 'urn:mace:terena.org:attribute-def:schachomeorganization':
        case 'urn:oid:1.3.6.1.4.1.25178.1.2.9':
          $orgdata['OrgIdentity']['o'] = $value;
          break;

        case 'ou':
        case 'urn:mace:dir:attribute-def:ou':
        case 'organizationalunitname':
        case 'urn:mace:dir:attribute-def:organizationalunitname':
        case 'urn:oid:2.5.4.11':
          $orgdata['OrgIdentity']['ou'] = $value;
          break;

        case 'personaltitle':
        case 'urn:mace:dir:attribute-def:personaltitle':
        case 'urn:oid:0.9.2342.19200300.100.1.40':
        case 'title':
        case 'urn:mace:dir:attribute-def:title':
        case 'urn:oid:2.5.4.12':
        case 'schacpersonaltitle':
        case 'urn:mace:terena.org:attribute-def:schacpersonaltitle':
        case 'urn:oid:1.3.6.1.4.1.25178.1.2.8':
          $orgdata['OrgIdentity']['title'] = $value;
          break;

        case 'postaladdress':
        case 'urn:mace:dir:attribute-def:postaladdress':
        case 'urn:oid:2.5.4.16':
          $orgdata['Address'][0]['street']=$value;
          break;

        case 'postalcode':
        case 'urn:mace:dir:attribute-def:postalcode':
        case 'urn:oid:2.5.4.17':
          $orgdata['Address'][0]['postal_code']=$value;
          break;

        case 'postofficebox':
        case 'urn:mace:dir:attribute-def:postofficebox':
        case 'urn:oid:2.5.4.18':
          $orgdata['Address'][0]['street']=$value;
          $orgdata['Address'][0]['type']=ContactEnum::Postal;
          break;

        case 'telephonenumber':
        case 'urn:mace:dir:attribute-def:telephonenumber':
        case 'urn:oid:2.5.4.20':
          $tels=array();
          foreach($orgdata['TelephoneNumber'] as $tn) {
            if($tn['type'] == ContactEnum::Office) {
              $tels[]=$tn['number'];
            }
          }
          if(!in_array($value,$tels)) {
            $tn=array(
              'number' => $value,
              'type' => ContactEnum::Office
            );
            $orgdata['TelephoneNumber'][] = $tn;
          }
          break;

        case 'fax':
        case 'urn:mace:dir:attribute-def:fax':
        case 'facsimiletelephonenumber':
        case 'urn:mace:dir:attribute-def:facsimiletelephonenumber':
        case 'urn:oid:2.5.4.23':
          $tels=array();
          foreach($orgdata['TelephoneNumber'] as $tn) {
            if($tn['type'] == ContactEnum::Fax) {
              $tels[]=$tn['number'];
            }
          }
          if(!in_array($value,$tels)) {
            $tn=array(
              'number' => $value,
              'type' => ContactEnum::Fax
            );
            $orgdata['TelephoneNumber'][] = $tn;
          }
          break;

        case 'userpassword':
        case 'urn:mace:dir:attribute-def:userpassword':
        case 'urn:oid:2.5.4.35':
          // TODO: if we are going to support this, use the 3.2 Password object
          break;

        case 'givenname':
        case 'urn:mace:dir:attribute-def:givenname':
        case 'gn':
        case 'urn:mace:dir:attribute-def:gn':
        case 'urn:oid:2.5.4.42':
          $orgdata['Name'][0]['given']=$value;
          break;

        case 'initials':
        case 'urn:mace:dir:attribute-def:initials':
        case 'urn:oid:2.5.4.43':
          // TODO: we could use this to split a commonName correctly
          break;

        case 'generationqualifier':
        case 'urn:mace:dir:attribute-def:generationqualifier':
        case 'urn:oid:2.5.4.44':
          $orgdata['Name'][0]['suffix']=$value;
          break;

        case 'displayname':
        case 'urn:mace:dir:attribute-def:displayname':
        case 'urn:oid:2.16.840.1.113730.3.1.241':
          $names=array();
          foreach($orgdata['Name'] as $nm) {
            $names[]=generateCn($nm);
          }
          if(!in_array($value, $names)) {
            $orgdata['Name'][]=$this->splitName($value);
          }https://comanage.scz-vm.net/registry/co_invites/reply/df860857d369c2a134bc0a1718dea3509109acfb
          break;

        case 'employeetype':
        case 'urn:mace:dir:attribute-def:employeetype':
        case 'urn:oid:2.16.840.1.113730.3.1.4':
        case 'edupersonaffiliation':
        case 'urn:mace:dir:attribute-def:edupersonaffiliation':
        case 'urn:oid:1.3.6.1.4.1.5923.1.1.1.1':
        case 'edupersonprimaryaffiliation':
        case 'urn:mace:dir:attribute-def:edupersonprimaryaffiliation':
        case 'urn:oid:1.3.6.1.4.1.5923.1.1.1.5':
        case 'edupersonscopedaffiliation':
        case 'urn:mace:dir:attribute-def:edupersonscopedaffiliation':
        case 'urn:oid:1.3.6.1.4.1.5923.1.1.1.9':
          // TODO: support multi-valued fields
          if(in_array(strtolower($value), array(AffiliationEnum::Faculty,
                                    AffiliationEnum::Student,
                                    AffiliationEnum::Staff,
                                    AffiliationEnum::Alum,
                                    AffiliationEnum::Member,
                                    AffiliationEnum::Affiliate,
                                    AffiliationEnum::Employee,
                                    AffiliationEnum::LibraryWalkIn))) {
            $orgdata['OrgIdentity']['affiliation'] = strtolower($value);
          }
          break;

        case 'preferredlanguage':
        case 'urn:mace:dir:attribute-def:preferredlanguage':
        case 'urn:oid:2.16.840.1.113730.3.1.39':
          $orgdata['Name'][0]['language']=$value;
          break;

        case 'edupersonnickname':
        case 'urn:mace:dir:attribute-def:edupersonnickname':
        case 'urn:oid:1.3.6.1.4.1.5923.1.1.1.2':
          $names=array();
          foreach($orgdata['Name'] as $nm) {
            $names[]=generateCn($nm);
          }
          if(!in_array($value, $names)) {
            $nm=$this->splitName($value);
            $nm['type'] = NameEnum::Preferred;
            $orgdata['Name'][] = $nm;
          }
          break;

        case 'edupersonentitlement':
        case 'urn:mace:dir:attribute-def:edupersonentitlement':
        case 'urn:oid:1.3.6.1.4.1.5923.1.1.1.7':
          // TODO: can we link this to servers/services?
          break;

        case 'edupersonprincipalname':
        case 'urn:mace:dir:attribute-def:edupersonprincipalname':
        case 'urn:oid:1.3.6.1.4.1.5923.1.1.1.6':
          $ids=array();
          foreach($orgdata['Identifier'] as $id) {
            if($id['type'] == IdentifierEnum::ePPN) {
              $ids[]=$id['identifier'];
            }
          }
          if(!in_array($value,$ids)) {
            $orgdata['Identifier'][] = array(
              'identifier' => $value,
              'login'      => ($sorid_attr == $key) ? true : false,
              'status'     => StatusEnum::Active,
              'type'       => IdentifierEnum::ePPN
            );
            $identifier_added = true;
          }
          break;

        case 'edupersontargetedid':
        case 'urn:mace:dir:attribute-def:edupersontargetedid':
        case 'urn:oid:1.3.6.1.4.1.5923.1.1.1.10':
          $ids=array();
          foreach($orgdata['Identifier'] as $id) {
            if($id['type'] == IdentifierEnum::ePTID) {
              $ids[]=$id['identifier'];
            }
          }
          if(!in_array($value,$ids)) {
            $orgdata['Identifier'][] = array(
              'identifier' => $value,
              'login'      => ($sorid_attr == $key) ? true : false,
              'status'     => StatusEnum::Active,
              'type'       => IdentifierEnum::ePTID
            );
            $identifier_added = true;
          }
          break;

        case 'sshpublickey':
        case 'urn:oid:1.3.6.1.4.1.24552.1.1.1.13':
          $values=explode(" ",trim($value));
          $type=SshKeyTypeEnum::RSA;
          $comment="";
          global $ssh_ti;
          if(sizeof($values)>1) {
            $type=$values[0];
            $value=$values[1];
            if(sizeof($values)>2) {
              $comment=$values[2];
            }
            $ssh_it = array_flip($ssh_ti);
            if(isset($ssh_it[$type])) {
              $type=$ssh_it[$type];
            }
            else {
              // TODO: determine key type based on value
              $type=SshKeyTypeEnum::RSA;
            }
          }

          $ids=array();
          foreach($orgdata['SshKey'] as $id) {
            if($id['type'] == $type) {
              $ids[]=$id['identifier'];
            }
          }
          if(!in_array($value,$ids)) {
            $orgdata['SshKey'][] = array(
              'skey'     => $value,
              'comment'  => $comment,
              'type'     => $type
            );
          }
          break;


        case 'labeleduri':
        case 'urn:mace:dir:attribute-def:labeleduri':
        case 'urn:oid:1.3.6.1.4.1.250.1.57':
          $urls=array();
          foreach($orgdata['Url'] as $url) {
            $urls[]=$url['content'];
          }
          if(!in_array($value,$urls)) {
            $orgdata['Url'][] = array(
              'content' => $value,
              'type'       => UrlEnum::Official
            );
          }
          break;

        case 'schacpersonaluniquecode':
        case 'urn:mace:terena.org:attribute-def:schacpersonaluniquecode':
        case 'urn:oid:1.3.6.1.4.1.25178.1.2.14':
          // TODO: create identifier for this
          break;

        case 'schacpersonaluniqueid':
        case 'urn:mace:terena.org:attribute-def:schacpersonaluniqueid':
        case 'urn:oid:1.3.6.1.4.1.25178.1.2.15':
          // TODO: create identifier for this
          break;

        default:

          // If this specific attribute is used as login identifier, create a new identifier
          // If the SORID is an existing SAML identifier type, we will have set the login
          // attribute on that identifier somewhere above already. This case statement
          // is only for the situation that the SORID is not one of our known SAML attributes.
          if($key == $sorid_attr) {
            // Add a new identifier of type UID
            // We would want to create a new identifier type, but that is not supported through
            // Extended types for OrgIdentities
            $orgdata['Identifier'][] = array(
              'identifier' => $value,
              'login'      => true,
              'status'     => StatusEnum::Active,
              'type'       => IdentifierEnum::SORID
            );
          }
          break;
        }
      }
    }

    $orgdata = $this->normalizeNames($orgdata, $result[$sorid_attr][0]);
    // we make the first name we have 'primary' (because a primary name
    // is required)
    if(sizeof($orgdata['Name']) > 0) {
      $orgdata['Name'][0]['primary_name'] = true;
    }

    // remove empty objects for Address
    // We start with an empty object to allow individual address field
    // values to be stored, but if none of those are given, the
    // object remains empty
    if(empty($orgdata['Address'][0])) {
      unset($orgdata['Address'][0]);
    }
    else if(!isset($orgdata['Address'][0]['type'])) {
      $orgdata['Address'][0]['type'] =  ContactEnum::Office;
      // make sure we have a value for the street field, which cannot be left empty...
      if(empty($orgdata['Address'][0]['street'])) {
        $orgdata['Address'][0]['street'] = _txt('pl.samlsource.street.unknown');
      }
    }

    $this->devLog('returning orgidentity '.json_encode($orgdata,JSON_PRETTY_PRINT));
    return $orgdata;
  }

  /**
   * Convert a raw result, as from eg retrieve(), into an array of attributes that
   * can be used for group mapping.
   *
   * @since  COmanage Registry vTODO
   * @param  String $raw Raw record, as obtained via retrieve()
   * @return Array Array, where keys are attribute names and values are lists (arrays) of attributes
   */

  public function resultToGroups($raw) {
    return array();
  }

  /**
   * Retrieve a single record from the IdentitySource. The return array consists
   * of two entries: 'raw', a string containing the raw record as returned by the
   * IdentitySource backend, and 'orgidentity', the data in OrgIdentity format.
   *
   * @since  COmanage Registry v2.0.0
   * @param  String $id Unique key to identify record
   * @return Array As specified
   * @throws InvalidArgumentException if not found
   * @throws OverflowException if more than one match
   * @throws RuntimeException on backend specific errors
   */

  public function retrieve($id) {
    // We need to implement retrieve so OrgIdentitySource::createOrgIdentity()
    // can call it to obtain the org identity. Since we operate on environment
    // variables, we "retrieve" the record from the environment. However, to
    // avoid confusion when an admin is trying to "retrieve" the current record,
    // we throw an error if $id doesn't match $ENV.
    $this->devLog('samlsourcebackend::retrieve of '.json_encode($id));
    $prefix = $this->pluginCfg['saml_var_prefix'];
    $sorid_attr = $this->pluginCfg['saml_sorid'];

    $sorid = getenv($prefix.$sorid_attr);
    $this->devLog('sorid '.$sorid_attr.' is '.json_encode($sorid));

    if(!$sorid) {
      throw new RuntimeException(_txt('er.samlsource.sorid', array($prefix.$sorid_attr)));
    }

    if($sorid != $id) {
      throw new RuntimeException(_txt('er.samlsource.sorid.mismatch', array($prefix.$sorid_attr)));
    }

    // Note the controller must $use this for it to be available, apparently
    $SamlSource = ClassRegistry::init("SamlSource.SamlSource");

    $values = $this->readConfig();
    $this->devLog('read values: '.json_encode($values));
    $ret = array();
    $ret['raw'] = json_encode($values);
    $ret['orgidentity'] = $this->resultToOrgIdentity($values);
    $this->devLog('end of samlsourcebackend::retrieve');
    return $ret;
  }

  /**
   * Perform a search against the IdentitySource. The returned array should be of
   * the form uniqueId => attributes, where uniqueId is a persistent identifier
   * to obtain the same record and attributes represent an OrgIdentity, including
   * related models.
   *
   * @since  COmanage Registry v3.1.0
   * @param  Array $attributes Array in key/value format, where key is the same as returned by searchAttributes()
   * @return Array Array of search results, as specified
   */

  public function search($attributes) {
    return array();
  }

  /**
   * Generate the set of searchable attributes for the IdentitySource.
   * The returned array should be of the form key => label, where key is meaningful
   * to the IdentitySource (eg: a number or a field name) and label is the localized
   * string to be displayed to the user.
   *
   * @since  COmanage Registry v3.1.0
   * @return Array As specified
   */

  public function searchableAttributes() {
    return array();
  }

  private function devLog($txt) {
    //CakeLog::write('debug',$txt);
  }
}
