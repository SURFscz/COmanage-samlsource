# COmanage-samlsource
SAML value OrgIdentitySource Plugin
===================================
This is a plugin for the [COManage Registry](https://www.internet2.edu/products-services/trust-identity/comanage/) application as provided and maintained by the [Internet2](https://www.internet2.edu/) foundation.

This project has the following deployment goals:
- create an OrgIdentitySource (OIS) plugin to read out SAML attributes from the environment


COManage EnvSource Plugin
====================================
COManage comes with a configurable EnvSource OIS Plugin that allows CO administrators to read environment variables and import them into OrgIdentity objects during enrollment. This plugin goes a long way into incorporating the attributes passed by `mod_auth_mellon` and `shibboleth` authentication platforms during user authentication. However, there are some issues with this systematic that are solved by this OIS plugin:
- the EnvSource plugin has a limited set of attributes that each can contain 1 environment variable
- SAML attributes have a specific meaning that can be easily matched beforehand to the right OrgIdentity value. 
  Having to specify the various names explicitely in EnvSource is unnecessary and can only lead to errors if 
  done unattentively
- SAML can potentially send a lot of additional information, like extra identifiers, SSH keys, etc., that are 
  currently not available in EnvSource
- The REMOTE_USER attribute as configured in the back-end should be automatically linked as login identifier

Configuration
=============
Configuration of the SamlSource plugin allows two settings:
- a text prefix that is inserted by the authentication framework in front of the attribute names
- the attribute name (without prefix) of the REMOTE_USER attribute selected in the back-end

Fixed Configurations
====================
Because the configuration of this plugin is closely linked to the configuration of the authentication layer on the platform, the configuration settings can also be set using an application wide configuration file. In this case, the variables are still visible on the SamlSource configuration page, but cannot be adjusted. Create a configuration file called ```samlsource.php``` in the application configuration with the following contents:
```
<?php
$config=array(
  'samlsource' => array(
    'var_prefix'  => '<platform prefix>',
    'sorid' => '<platform REMOTE_USER variable>'
    )
  );
```

Common Name and Display Name
============================
A significant number of IdPs only pass along ``commonName`` or ``displayName`` instead of separate values for ``givenName`` and ``familyName``. The SamlSource plugin has support for splitting those attribute values into a proper ``Name`` object, although the algorithm is fairly straightforward:
- name attributes are split on spaces
- all preceding items that contain a dot at the end with a length of at least 2 characters are considered Honorifics
- all items containing a dot with only one character are considered initials and added to ``givenName`` if the first, or to ``middleName`` for any successive initial
- the first non-dot containing item is considered a ``givenName``, if no preceding initial was found
- all dot-containing items after the ``givenName`` are considered ``middleName``
- all non-dot containing items after ``givenName`` are considered ``familyName``
- all dot-containing items after ``familyName`` at the end are considered ``suffix``
- if a name ends with a comma, all following items are considered ``suffix``

There are several often occurring real world cases that are not properly parsed this way, but as this is very culture specific, a proper implementation lies out of scope of this plugin. A more proper solution would be for COmanage to support ``displayName`` itself. [CO-689](https://bugs.internet2.edu/jira/browse/CO-689)

Affiliation
===========
COmanage stores an affiliation field on the OrgIdentity. This is a single value attribute, but SAML allows it to be multi-valued (which is, in real life, an often occurring situation as well). The SamlSource plugin checks the SAML values and picks the last attribute which contains a value that matches the allowed affiliation values of COmanage. [CO-1611](https://bugs.internet2.edu/jira/browse/CO-1611)

Conflicting attributes
======================
IdPs will often use various different attributes (commonName, displayName) in various notations (URN, OID) to pass along the same attribute. The SamlSource plugin will interpret the attribute itself and consume its content according to the attribute definition, no matter which version or notation is used.

If both ``commonName``, ``displayName`` and ``givenName`` are passed, the SamlSource plugin will create a new ``Name`` object for each of these names. After parsing all attributes, all names are 'generated' and duplicate names are removed. In case an IdP supplies both split values ``givenName`` (or ``gn``) and ``surName`` (or ``sn``), those values are retained in preference over the combined values in ``displayName``, ``commonName`` or ``cn`` (which need to be split according to the above detailed algorithm, which has obvious flaws).

Identifiers
===========
The SamlSource plugin will try to create new identifiers, addresses, telephone numbers, etc. for all attributes carrying relevant content. However, before creating new objects a quick check is executed to determine if similar values have not already been created. This allows mixing both URN and OID type attribute names that duplicate the effective attributes. 

If the SAML attribute matches the configured platform identifier and has no special identifier case, SamlSource will create a login identifier for this value.of type 'sorid'.

Special cases at the moment are:
- ```urn:mace:dir:attribute-def:userid``` -> UID
- ```urn:mace:dir:attribute-def:uid``` -> UID
- ```urn:mace:dir:attribute-def:uniqueidentifier``` -> UID
- ```urn:mace:dir:attribute-def:edupersontargetedid``` -> ePTID
- ```urn:mace:dir:attribute-def:edupersonprincipalname``` -> ePPN

OID and short-name cases for the attributes are implemented as well.

Setup
=====
The OIS plugin must be installed in the `local/Plugin` directory of the COManage installation. Optionally, you can install it in the `app/AvailablePlugins` directory and link to it from the `local/Plugin` directory.

After installation, run the Cake database update script as per the COManage instructions:
```
app/Console/cake database
```
You can now select the SamlSource plugin for your OrgIdentitySources and subsequently link it to Enrollment.


Tests
=====
This plugin does not currently come with unit tests.

Disclaimer
==========
This plugin is provided AS-IS without any claims whatsoever to its functionality. The code is based largely on COManage Registry code, distributed under the [Apache License 2.0](http://www.apache.org/licenses/LICENSE-2.0).
