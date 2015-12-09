autht2factor
==========
Two-step authentication module for simpleSAMLphp using secret questions and answers, Email based token or SSL client verification certificate to bypass 2nd step.

- User has ability to switch between 2nd step.
- User has ability to reset questions.
- User has ability to resend mail code.
- Supports account locking feature.
- Supports ability to mix and match user defined and pre-defined secret question and answers.
- Supports SSL client certificate to bypass 2nd step.

Demonstration
==============
Below is a demonstration of what this module can do, this is using exampleauth module in SimpleSAMLphp.
The theme used in the demonstration is also available here https://github.com/shoaibali/simplesamlphp-module-theme2factor

![auth2factor simplesamlphp module demonstration](https://github.com/shoaibali/simplesamlphp-module-auth2factor/blob/master/docs/sso.gif?raw=true "SimpleSAMLphp module auth2factor demonstration")

Configure it by adding an entry to config/authsources.php such as this:
 
 ```
       'auth2factor' => array(
         'auth2factor:auth2factor',
         'db.dsn' => 'mysql:host=db.example.com;port=3306;dbname=idpauth2factor',
         'db.username' => 'simplesaml',
         'db.password' => 'password',
         'mainAuthSource' => 'ldap', // works with example-auth as well
         'uidField' => 'uid',
         'mailField' => 'email',
         'post_logout_url' => 'http://google.com', // URL to redirect to on logout. Optional
         'minAnswerLength' => 10, // Minimum answer length. Defaults to 0
         'minQuestionLength' => 10, // Minimum answer length. Defaults to 0
         'singleUseCodeLength' => 10, // Minimum answer length. Defaults to 8
         'initSecretQuestions' => array('Question 1', 'Question 2', 'Question 3'), // Optional - Initialise the db with secret questions
         'maxCodeAge' => 60 * 5, // Maximum age for a one time code. Defaults to 5 minutes
         'ssl.clientVerify' => false, // turned off by default, if turned on then other 2nd step verifications are bypassed
         'maxFailLogin' => 5, // maximum amount of failed logins before locking the account
         'mail' => array('host' => 'ssl://smtp.gmail.com',
                         'port' => '465',
                         'from' => 'cloudfiles.notifications@mydomain.com',
                         'subject' => '**TEST**', // This will be added before Code = XYZ
                         'body' => '', // This will be added before Code = XYZ
                         'username' => 'cloudfiles.notifications@mydomain.com',
                         'password' => 'CHANGEME',
                         'debug' => false,
                        )
         ),
```
