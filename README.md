autht2factor
==========

Two-step authentication module for simpleSAMLphp using questions and answers.

 * Configure it by adding an entry to config/authsources.php such as this:
 *
 *      'auth2factor' => array(
 *       	'auth2factor:auth2factor',
 *
 *        	'db.dsn' => 'mysql:host=db.example.com;port=3306;dbname=idpauth2factor',
 *       	'db.username' => 'simplesaml',
 *       	'db.password' => 'password',
 *          'db.answers_salt' => 'secretsalt',
 *			'mainAuthSource' => 'ldap',
 *			'uidField' => 'uid'
 *        ),
