<?php
/**
 * @author Shoaib Ali, Catalyst IT
 * @package simpleSAMLphp
 * @version $Id$
 */

$as = \SimpleSAML\Configuration::getConfig('authsources.php')->getValue('auth2factor');

// Get session object
$session = \SimpleSAML\Session::getSessionFromRequest();


$authStateId = $_REQUEST['AuthState'];

//$state = \SimpleSAML\Auth\State::loadState($_GET['StateId'], 'consent:request');
$state = \SimpleSAML\Auth\State::loadState($authStateId, \SimpleSAML\Module\auth2factor\Auth\Source\auth2factor::STAGEID);



// Get the auth source so we can retrieve the URL we are ment to redirect to
//$qaLogin = SimpleSAML_Auth_Source::getById('auth2factor');

//$source = \SimpleSAML\Auth\Source::getById($as['mainAuthSource']);
// $source = \SimpleSAML\Auth\Source::getById($state[\SimpleSAML\Module\auth2factor\Auth\Source\auth2factor::AUTHID]);
$source = \SimpleSAML\Auth\Source::getById('auth2factor');

if ($source === null) {
    throw new \Exception(
        'Could not find authentication source with id ' . $as['mainAuthSource']
    );
}

// Trigger logout for the main auth source
if ($session->isValid( $as['mainAuthSource'] )) {
   $source->logout();
}

assert(false);