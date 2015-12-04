autht2factor
==========

Two-step authentication module for simpleSAMLphp using questions and answers and SMS/Email token

 * Configure it by adding an entry to config/authsources.php such as this:
 *
 *      'auth2factor' => array(
 *        'auth2factor:auth2factor',
 *        'db.dsn' => 'mysql:host=db.example.com;port=3306;dbname=idpauth2factor',
 *        'db.username' => 'simplesaml',
 *        'db.password' => 'password',
 *        'mainAuthSource' => 'ldap',
 *        'uidField' => 'uid',
 *        'mailField' => 'email',
 *        'post_logout_url' => 'http://google.com', // URL to redirect to on logout. Optional
 *        'minAnswerLength' => 10, // Minimum answer length. Defaults to 0
 *        'minQuestionLength' => 10, // Minimum answer length. Defaults to 0
 *        'singleUseCodeLength' => 10, // Minimum answer length. Defaults to 8
 *        'initSecretQuestions' => array('Question 1', 'Question 2', 'Question 3'), // Optional - Initialise the db with secret questions
 *        'maxCodeAge' => 60 * 5, // Maximum age for a one time code. Defaults to 5 minutes
 *        'ssl.clientVerify' => false, // turned off by default, if turned on then other 2nd step verifications are bypassed
 *        'maxFailLogin' => 5, // maximum amount of failed logins before locking the account
 *        'mail' => array('host' => 'ssl://smtp.gmail.com',
 *                        'port' => '465',
 *                        'from' => 'cloudfiles.notifications@mydomain.com',
 *                        'subject' => '**TEST**', // This will be added before Code = XYZ
 *                        'body' => '', // This will be added before Code = XYZ
 *                        'username' => 'cloudfiles.notifications@mydomain.com',
 *                        'password' => 'CHANGEME',
 *                        'debug' => false,
 *                       )
 *        ),
