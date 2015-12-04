<?php

/**
 * LDAP authentication source.
 *
 * See the ldap-entry in config-templates/authsources.php for information about
 * configuration of this authentication source.
 *
 * This class is based on www/auth/login.php.
 *
 * @package simpleSAMLphp
 * @version $Id$
 */
class sspmod_auth2factor_Auth_Source_LDAP extends sspmod_ldap_Auth_Source_LDAP {


	/**
	 * Attempt to log in using the given username and password.
	 *
	 * @param string $username  The username the user wrote.
	 * @param string $password  The password the user wrote.
	 * param array $sasl_arg  Associative array of SASL options
	 * @return array  Associative array with the users attributes.
	 */
	protected function login($username, $password, array $sasl_args = NULL) {
		assert('is_string($username)');
		assert('is_string($password)');

		$qaLogin = SimpleSAML_Auth_Source::getById('auth2factor');

		if ($qaLogin->isLocked($username)) {
			throw new SimpleSAML_Error_Error('ACCOUNTLOCKED');
		}

		$result = $this->ldapConfig->login($username, $password, $sasl_args);

		// make sure login counter is zero!
		$qaLogin->resetFailedLoginAttempts($uid);

		if (!$result) {
			// increment failed login attempts
			$qaLogin->failedLoginAttempt($username);
		}

		return $result;
	}

}


?>