<?php
/**
 * COmanage Registry SamlSource Plugin Language File
 *
 * Author licenses this file to you under the Apache License, Version 2.0
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
 * @link          http://www.surfnet.nl
 * @package       registry-plugin
 * @since         2018-03-01
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

global $cm_lang, $cm_texts;

// When localizing, the number in format specifications (eg: %1$s) indicates the argument
// position as passed to _txt.  This can be used to process the arguments in
// a different order than they were passed.

$cm_saml_source_texts['en_US'] = array(
  // Titles, per-controller
  'ct.saml_sources.1'  => 'SAML Organizational Identity Source',
  'ct.saml_sources.pl' => 'SAML Organizational Identity Sources',

  // Error messages
  'er.samlsource.sorid'          => 'Identifier (SORID) variable "%1$s" not set',
  'er.samlsource.sorid.mismatch' => 'Requested ID does not match %1$s; SamlSource does not support general retrieve operations',
  'er.samlsource.token'          => 'Token error',

  // Labels
  'pl.samlsource.prefix'         => 'Prefix',
  'pl.samlsource.prefix.desc'    => 'Defines the prefix used for SAML variables set in the environment',
  'pl.samlsource.identifier'     => 'Identifier',
  'pl.samlsource.identifier.desc'=> 'Defines the identifier configured as REMOTE_USER for login',
  'pl.samlsource.name.unknown'   => 'Nomen Nescio',
  'pl.samlsource.street.unknown' => 'Unknown'
);
