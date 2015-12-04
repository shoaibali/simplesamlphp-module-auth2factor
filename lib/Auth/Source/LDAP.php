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
class sspmod_auth2factor_Auth_Source_LDAP extends sspmod_core_Auth_UserPassBase {

	/**
	 * A LDAP configuration object.
	 */
	private $ldapConfig;


	/**
	 * Constructor for this authentication source.
	 *
	 * @param array $info  Information about this authentication source.
	 * @param array $config  Configuration.
	 */
	public function __construct($info, $config) {
		assert('is_array($info)');
		assert('is_array($config)');

		/* Call the parent constructor first, as required by the interface. */
		parent::__construct($info, $config);

		$this->ldapConfig = new sspmod_ldap_ConfigHelper($config,
			'Authentication source ' . var_export($this->authId, TRUE));
	}

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
			// TODO pass in $result as that will contain the attributes
			$qaLogin->failedLoginAttempt($username,'login_count');
		}

		return $result;
	}

}


?>