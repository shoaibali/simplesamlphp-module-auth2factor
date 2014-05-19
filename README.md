authtqstep
==========

Two-step authentication module for simpleSAMLphp using questions and answers.

 * Configure it by adding an entry to config/authsources.php such as this:
 *
 *      'authqstep' => array(
 *       	'authqstep:authqstep',
 *
 *        	'db.dsn' => 'mysql:host=db.example.com;port=3306;dbname=idpauthqstep',
 *       	'db.username' => 'simplesaml',
 *       	'db.password' => 'password',
 *          'db.answers_salt' => 'secretsalt',
 *			'mainAuthSource' => 'ldap',
 *			'uidField' => 'uid'
 *        ),
