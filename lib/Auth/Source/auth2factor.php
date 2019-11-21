<?php

namespace SimpleSAML\Module\auth2factor\Auth\Source;

/**
 * @author Shoaib Ali, Catalyst IT
 *
 * 2 Step authentication module.
 *
 * Configure it by adding an entry to config/authsources.php such as this:
 *
 *      'auth2factor' => array(
 *        'auth2factor:auth2factor',
 *        'db.dsn' => 'mysql:host=db.example.com;port=3306;dbname=idpauth2factor',
 *        'db.username' => 'simplesaml',
 *        'db.password' => 'password',
 *        'mainAuthSource' => 'ldap',
 *        'uidField' => 'uid',
 *        'emailField' => 'email',
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
 *
 * @package simpleSAMLphp
 * @version $Id$
 */

class auth2factor extends \SimpleSAML\Auth\Source {

  /**
   * The string used to identify our step.
   */
  const STEPID = '\SimpleSAML\Module\auth2factor\Auth\Source\auth2factor.state';

  /**
   * Default minimum length of secret answer required. Can be overridden in the config
   */
  const ANSWERMINCHARLENGTH = 0;

  /**
   * Default minimum length of secret answer required. Can be overridden in the config
   */
  const QUESTIONMINCHARLENGTH = 0;


  /**
   * Length of an SMS/Mail single use code
   */
  const SINGLEUSECODELENGTH = 8;

  /**
   * The key of the AuthId field in the state.
   */
  // const AUTHID = 'sspmod_auth2factor_Auth_Source_auth2factor.AuthId';


  /**
   * The string used to identify our states.
   */
  const STAGEID = '\SimpleSAML\Module\auth2factor\Auth\Source\auth2factor.state';

  /**
   * The key of the AuthId field in the state.
   */
  const AUTHID = '\SimpleSAML\Module\auth2factor\Auth\Source\auth2factor.AuthId';


    /**
     *   sstc-saml-loa-authncontext-profile-draft.odt
    */

  const TFAAUTHNCONTEXTCLASSREF = 'urn:oasis:names:tc:SAML:2.0:post:ac:classes:nist-800-63:3';

    /**
     * 2 Factor type constants
     */
    const FACTOR_MAIL = 'mail';
    const FACTOR_SMS = 'sms';
    const FACTOR_QUESTION = 'question';
    const FACTOR_SSL = 'ssl';

    /**
     * Default maximum code age
     */

    const MAXCODEAGE = 300; //60 * 5

    const SALT_SPACE = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const NO_CONFUSION_SPACE = '23456789abcdefghikmnpqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private $db_dsn;
    private $db_name;
    private $db_username;
    private $db_password;
    private $site_salt;
    private $logoutURL;
    private $dbh;
    private $minAnswerLength;
    private $minQuestionLength;
    private $singleUseCodeLength;
    private $maxCodeAge;
    private $mail;
    private $maxFailLogin;
    private $ssl_clientVerify;
    private $notificationEmail;

    public $tfa_authencontextclassref;


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

    if (array_key_exists('db.dsn', $config)) {
      $this->db_dsn = $config['db.dsn'];
    }
    if (array_key_exists('db.name', $config)) {
      $this->db_name = $config['db.name'];
    } else {
      // look in DSN maybe its in there?
      if (isset($this->db_dsn)) {
        $dsnArray = [];
        foreach(explode(";", $this->db_dsn) as $key => $value) {
          $res = explode("=", $value);
          $dsnArray[$res[0]] = $res[1];
        }
        $this->db_name = $dsnArray['dbname'];
      }
    }
    if (array_key_exists('db.username', $config)) {
      $this->db_username = $config['db.username'];
    }
    if (array_key_exists('db.password', $config)) {
      $this->db_password = $config['db.password'];
    }
    if (array_key_exists('post_logout_url', $config)) {
       $this->logoutURL = $config['post_logout_url'];
    } else {
       $this->logoutURL = '/logout';
    }
    if (array_key_exists('minAnswerLength', $config)) {
       $this->minAnswerLength = (int) $config['minAnswerLength'];
    } else {
       $this->minAnserLength = self::ANSWERMINCHARLENGTH;
    }
    if (array_key_exists('minQuestionLength', $config)) {
       $this->minQuestionLength = (int) $config['minQuestionLength'];
    } else {
       $this->minQuestionLength = self::QUESTIONMINCHARLENGTH;
    }
    if (array_key_exists('singleUseCodeLength', $config)) {
        $this->singleUseCodeLength = (int) $config['singleUseCodeLength'];
    } else {
        $this->singleUseCodeLength = self::SINGLEUSECODELENGTH;
    }
    if (array_key_exists('maxFailLogin', $config)) {
      $this->maxFailLogin = (int) $config['maxFailLogin'];
    }
    if (array_key_exists('notificationEmail', $config)) {
      $this->notificationEmail = $config['notificationEmail'];
    }

    if (array_key_exists('ssl.clientVerify', $config)) {
      $this->ssl_clientVerify = (bool) $config['ssl.clientVerify'];
    } else {
      $this->ssl_clientVerify = false;
    }
    if (array_key_exists('maxCodeAge', $config)) {
        $this->maxCodeAge = $config['maxCodeAge'];
    } else {
        $this->maxCodeAge = self::MAXCODEAGE;
    }
    $globalConfig = \SimpleSAML\Configuration::getInstance();

    if ($globalConfig->hasValue('secretsalt')) {
        $this->site_salt = $globalConfig->getValue('secretsalt');
    } else {
      /* This is probably redundant, as SimpleSAMLPHP will not let you run without a salt */
      die('Auth2factor: secretsalt not set in config.php! You should set this immediately!');
    }

    $this->tfa_authencontextclassref = self::TFAAUTHNCONTEXTCLASSREF;
    
    //try {

      $this->dbh = new \PDO($this->db_dsn, $this->db_username, $this->db_password);
      $this->dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
      // $this->dbh->exec("CREATE DATABASE IF NOT EXISTS ".$this->db_name." CHARACTER SET utf8 COLLATION utf8_unicode_ci;");

    //} catch (PDOException $e){


    //}


      //die('DB error' . $e->getMessage());
        if ($this->createDatabase($this->db_name)) {
          // doing all these make sense if we have a database!

          $this->createTables();
          // pre-defined secret questions in config
          if (array_key_exists('initSecretQuestions', $config)){
            $this->initQuestions($config['initSecretQuestions']);
          }
      }

    if (array_key_exists('mail', $config)){
        $this->mail =  $config['mail'];
    }

  }

  public function getLogoutURL() {
        return $this->logoutURL;
  }

  public function getMinAnswerLength() {
        return $this->minAnswerLength;
  }

  public function getMinQuestionLength() {
        return $this->minQuestionLength;
  }

/**
 * Initialize login.
 *
 * This function saves the information about the login, and redirects to a
 * login page.
 *
 * @param array &$state  Information about the current authentication.
 */
  public function authenticate(&$state)
    {
    assert(is_array($state));

    /* We are going to need the authId in order to retrieve this authentication source later. */
    $state[self::AUTHID] = $this->authId;

    $id = \SimpleSAML\Auth\State::saveState($state, self::STAGEID);

    $url = \SimpleSAML\Module::getModuleURL('auth2factor/login.php');
    //\SimpleSAML\Utilities::redirect($url, ['AuthState' => $id]);
    \SimpleSAML\Utils\HTTP::redirectTrustedURL($url, ['AuthState' => $id]);
  
  }

  /**
   * Handle login request.
   *
   * This function is used by the login form (core/www/loginuserpass.php) when the user
   * enters a username and password. On success, it will not return. On wrong
   * username/password failure, it will return the error code. Other failures will throw an
   * exception.
   *
   * @param string $authStateId  The identifier of the authentication state.
   * @param string $otp  The one time password entered-
   * @return string|null  Error code in the case of an error.
   */
    // public static function handleLogin($authStateId, $otp)
    // {
    //     assert(is_string($authStateId));
    //     assert(is_string($otp));

    //     /* Retrieve the authentication state. */
    //     $state = \SimpleSAML\Auth\State::loadState($authStateId, self::STAGEID);

    //     /* Find authentication source. */
    //     assert(array_key_exists(self::AUTHID, $state));
    //     $source = \SimpleSAML\Auth\Source::getById($state[self::AUTHID]);
    //     if ($source === null) {
    //         throw new \Exception('Could not find authentication source with id '.$state[self::AUTHID]);
    //     }

    //     try {
    //         /* Attempt to log in. */
    //         $attributes = $source->login($otp);
    //     } catch (\SimpleSAML\Error\Error $e) {
    //         /* An error occurred during login. Check if it is because of the wrong
    //          * username/password - if it is, we pass that error up to the login form,
    //          * if not, we let the generic error handler deal with it.
    //          */
    //         if ($e->getErrorCode() === 'WRONGUSERPASS') {
    //             return 'WRONGUSERPASS';
    //         }

    //         /* Some other error occurred. Rethrow exception and let the generic error
    //          * handler deal with it.
    //          */
    //         throw $e;
    //     }

    //     $state['Attributes'] = $attributes;
    //     \SimpleSAML\Auth\Source::completeAuth($state);

    //     return null;
    // }

  public function logout(&$state) {
        assert('is_array($state)');
        $state[self::AUTHID] = $this->authId;

        $id = \SimpleSAML\Auth\State::saveState($state, self::STEPID);

        $url = \SimpleSAML\Module::getModuleURL('auth2factor/logout.php');
        \SimpleSAML\Utilities::redirect($url, ['AuthState' => $id]);
  }

  //Generate a random string of a given length. Used to produce the per-question salt
    private function generateRandomString($length=15, $characters=self::SALT_SPACE) {
      $randomString = '';
      for ($i = 0; $i < $length; $i++) {
          $randomString .= $characters[rand(0, strlen($characters) - 1)];
      }
      return $randomString;
  }

  private function createTables()
  {
      /* Create table to hold questions */
      $q = "CREATE TABLE IF NOT EXISTS ssp_questions (
                  question_id INT (11) NOT NULL AUTO_INCREMENT,
                  PRIMARY KEY(question_id),
                  question_text VARCHAR(255) NOT NULL
                 );";

      $result = $this->dbh->query($q);

      /* Create table to hold answers */
      $q = "CREATE TABLE IF NOT EXISTS ssp_answers (
              answer_id INT(11) NOT NULL AUTO_INCREMENT,
              PRIMARY KEY(answer_id),
              user_question_id INT(11) NOT NULL,
              answer_hash VARCHAR(128) NOT NULL,
              answer_salt VARCHAR(15) NOT NULL,
                  question_id INT(11) NOT NULL,
                  uid VARCHAR(60) NULL
             );";
      $result = $this->dbh->query($q);

      /* Create table to hold user preferences */
      // simple string concatenation is safe here because constants
      $q = "CREATE TABLE IF NOT EXISTS ssp_user_2factor (
              uid VARCHAR(60) NOT NULL,
              PRIMARY KEY(uid),
              challenge_type ENUM('".self::FACTOR_QUESTION."', '".self::FACTOR_SMS."', '".self::FACTOR_MAIL."', '".self::FACTOR_SSL."') NOT NULL,
              last_code VARCHAR(10) NULL,
              last_code_stamp TIMESTAMP NULL,
              login_count INT NOT NULL DEFAULT 0,
              answer_count INT NOT NULL DEFAULT 0,
              locked BOOLEAN NOT NULL DEFAULT FALSE,
              UNIQUE KEY uid (uid)
             );";
      $result = $this->dbh->query($q);

      /* Create table to hold user defined questions */
      $q = "CREATE TABLE IF NOT EXISTS ssp_user_questions (
              user_question_id INT(11) NOT NULL AUTO_INCREMENT,
              PRIMARY KEY(user_question_id),
              uid VARCHAR(60) NOT NULL,
              user_question VARCHAR(100) NULL
             );";
      $result = $this->dbh->query($q);

  }

    private function initQuestions($questions){
        // Not sure if this is the correct way to use assert
        if (assert('is_array($questions)')){
          // make sure table is empty
          if ($this->emptyTable("ssp_questions")) {
              foreach($questions as $q){
                  $q = "INSERT INTO ssp_questions(question_text) VALUES ('".addslashes($q)."');";
                  $this->dbh->query($q);
              }
          }
        }
    }

    private function emptyTable($table)
    {
        $q = "SELECT COUNT(*) as records_count FROM $table";
        $result = $this->dbh->query($q);
        $row = $result->fetch();
        $records_count =  $row["records_count"];
        return ($records_count == 0)? TRUE : FALSE;
    }

    private function createDatabase($database)
    {
        $q = "CREATE DATABASE IF NOT EXISTS $database";

        return (bool) ($this->dbh->query($q))? $this->dbh->query("USE $database") : false;
    }

    /**
     * This method determines if the user's answers are registered
     *
     * @param int $uid
     * @return bool
     */

  public function isRegistered($uid)
  {
        if (strlen($uid) > 0) {
            $q = $this->dbh->prepare("SELECT COUNT(*) as registered_count FROM ssp_answers WHERE uid=:uid");
            $result = $q->execute([':uid' => $uid]);
            $row = $q->fetch();
            $registered =  $row["registered_count"];
            if ($registered >= 3){
                \SimpleSAML\Logger::debug('User '.$uid.' is registered, '.$registered.' answers');
                return TRUE;
            } else {
                if ($this->hasRegisteredForMail($uid)) {
                  return TRUE;
                  \SimpleSAML\Logger::debug('User '.$uid.' is registered for PIN code');
                }
                \SimpleSAML\Logger::debug('User '.$uid.' is NOT registered, '.$registered.' answers');
                return FALSE;
            }
        } else {
            return FALSE;
        }
  }

    /**
     * Get user preferences from database
     *
     * @param int $uid
     * @return array
     */
    public function get2FactorFromUID($uid){
        $q = $this->dbh->prepare("SELECT * FROM ssp_user_2factor WHERE uid=:uid");
        $result = $q->execute([':uid' => $uid]);
        $rows = $q->fetchAll();
        if (empty($rows)){
            \SimpleSAML\Logger::debug('auth2factor: use has no default prefs');
            return ['uid' => $uid, 'challenge_type' => self::FACTOR_QUESTION];
        }
        \SimpleSAML\Logger::debug('auth2factor: using method '. $rows[0]['challenge_type']);
        return $rows[0];
    }

    /**
     * Saves user 2nd Factor preferences to database
     *
     * @param int $uid
     * @param string $type
     * @return bool
     */
    public function set2Factor($uid, $type, $code="") {
        $q = $this->dbh->prepare(
          "INSERT INTO ssp_user_2factor (uid, challenge_type, last_code, last_code_stamp)
            VALUES (:uid, :type, :code, NOW())
            ON DUPLICATE KEY UPDATE challenge_type=:type, last_code=:code, last_code_stamp=NOW();");
        // $uid can't be null
        $result = $q->execute([':uid' => $uid, ':type' => $type, ':code' => $code]);
        \SimpleSAML\Logger::debug('auth2factor: ' . $uid . ' set preferences: '. $type . ' code:' . $code);

        return $result;
    }

    public function sendQuestionResetEmail($attributes) {

        require_once('Mail.php');

        $auth = false;
        $username = '';
        $password = '';

        // only turn on authentication if we have username and password
        if(isset($this->mail["username"]) && isset($this->mail["password"])) {
          $auth = true;
          $username = $this->mail["username"];
          $password = $this->mail["password"];
        }

          $name = $attributes['givenName'][0];
          $email = $attributes['mail'][0];
          $subject = " Secret questions reset notification";
          $body = <<<EOD

Dear $name,

This is an email to let you know that your secret questions and answers have been reset.

If this is something you did not initiate, kindly report this incident.

Regards,

Security Team

EOD;

        if (isset($this->mail)) {
          $params = ["host" => $this->mail["host"],
                          "port" => $this->mail["port"],
                          "auth" => $auth,
                          "username" => $username,
                          "password" => $password,
                          "debug" => $this->mail["debug"],
                          ];

          $headers = [
                      "To" => $email,
                      "From" => $this->mail["from"],
                      "Subject" => $this->mail["subject"]  . $subject,
                    ];

          $mail = new Mail();

          $mail_factory = $mail->factory('smtp', $params); // Print the parameters you are using to the page
          $mail_factory->send($email, $headers, $body);


        } else {
          // fall back to normal mail function
          mail($email, $subject, $body);
        }
        \SimpleSAML\Logger::debug('auth2factor: sending notification email of question reset to '. $attributes['uid'][0]);
    }

    public function sendMailCode($uid, $email) {
        $code = $this->generateRandomString($this->singleUseCodeLength, self::NO_CONFUSION_SPACE);
        $this->set2Factor($uid, self::FACTOR_MAIL, $code);


        // sudo pear install mail
        // sudo pear install Net_SMTP
        require_once('Mail.php');

        $auth = false;
        $username = '';
        $password = '';

        // only turn on authentication if we have username and password
        if(isset($this->mail["username"]) && isset($this->mail["password"])) {
          $auth = true;
          $username = $this->mail["username"];
          $password = $this->mail["password"];
        }
        if (isset($this->mail)) {
          $params = [ "host" => $this->mail["host"],
                      "port" => $this->mail["port"],
                      "auth" => $auth,
                      "username" => $username,
                      "password" => $password,
                      "debug" => $this->mail["debug"],
                    ];

          $headers = [  "To" => $email,
                        "From" => $this->mail["from"],
                        "Subject" => $this->mail["subject"] . " Code = " . $code,
                    ];

          $mail = new Mail();

          $mail_factory = $mail->factory('smtp', $params); // Print the parameters you are using to the page
          $mail_factory->send($email, $headers, $this->mail["body"]);


        } else {
          // fall back to normal mail function
          mail($email, 'Code = '.$code, '');
        }

        \SimpleSAML\Logger::debug('auth2factor: sending '.self::FACTOR_MAIL.' code: '. $code);

    }

    public function hasRegisteredForMail($uid) {
      $q = $this->dbh->prepare("SELECT uid, challenge_type FROM ssp_user_2factor WHERE uid=:uid;");
      $result = $q->execute([':uid' => $uid]);
      $rows = $q->fetchAll();


      if (count($rows) > 0) {
        if ($rows[0]["challenge_type"] == self::FACTOR_QUESTION) {
          return false;
        }
        // if the user has no questions either, then also return true i.e not registered
        if (count($this->getAnswersFromUID($uid)) == 0){
          return true;
        }

        return true;
      }

      return false;
    }

    public function hasMailCode($uid) {
        $q = $this->dbh->prepare("SELECT uid, last_code, last_code_stamp FROM ssp_user_2factor WHERE uid=:uid AND challenge_type = 'mail' ORDER BY last_code_stamp DESC;");
        $result = $q->execute([':uid' => $uid]);
        $rows = $q->fetchAll();

        if (count($rows) == 0) {
            \SimpleSAML\Logger::debug('User '.$uid.' has no challenge');
            return false;
        } else if (count($rows) > 1) {
            \SimpleSAML\Logger::debug('User '.$uid.' has multiple prefs rows');
        }

        $age = date_diff(new DateTime(), date_create($rows[0]['last_code_stamp']));
        $age_s = $age->s;
        $age_s += $age->i * 60;
        $age_s += $age->h * 60 * 60;
        $age_s += $age->d * 60 * 60 * 24;
        $age_s += $age->m * 60 * 60 * 24 * 30; // Codes don't live for more than a few minutes, so this is an OK assumption
        $age_s += $age->y * 60 * 60 * 24 * 30 * 12;
        \SimpleSAML\Logger::debug('User code age '. $age_s);

        if (strlen($rows[0]['last_code']) != $this->singleUseCodeLength) {
            \SimpleSAML\Logger::debug('User '.$uid.' stored code is too short');
            return false;
        } else if ($age_s > $this->maxCodeAge) {
            \SimpleSAML\Logger::debug('User '.$uid.' stored code has expired');
            return false;
        } else {
            return true;
        }
    }

    public function getQuestions(){
        $q = "SELECT * FROM ssp_questions;";
        $result = $this->dbh->query($q);
        $row = $result->fetchAll();

        if (empty($row)){
            return false;
        }
        return $row;
    }

    public function getAnswersFromUID($uid)
    {
        $q = $this->dbh->prepare("SELECT * FROM ssp_answers WHERE uid=:uid");
        $result = $q->execute([':uid' => $uid]);
        $rows = $q->fetchAll();
        return $rows;
    }

    public function getRandomQuestion($uid){
        $preDefinedQuestionsQuery = $this->dbh->prepare("SELECT ssp_answers.question_id, ssp_questions.question_text FROM ssp_answers, ssp_questions WHERE ssp_answers.uid=:uid AND ssp_answers.question_id = ssp_questions.question_id;");
        $pdqqr = $preDefinedQuestionsQuery->execute([':uid' => $uid]);
        // also get any user defined questions
        // TODO this could just be done in 1 query above
        // I blame all these frameworks and ORMs I've been spoiled with (shoaib)
        $userDefinedQuestionsQuery = $this->dbh->prepare("SELECT ssp_user_questions.user_question_id as question_id, ssp_user_questions.user_question as question_text FROM ssp_user_questions, ssp_answers WHERE ssp_user_questions.user_question_id = ssp_answers.user_question_id AND ssp_user_questions.uid=:uid");
        $udqq = $userDefinedQuestionsQuery->execute([':uid' => $uid]);

        $pdqq = $preDefinedQuestionsQuery->fetchAll();
        $udqq = $userDefinedQuestionsQuery->fetchAll();

        $rows = array_merge($pdqq, $udqq);

        // array_rand is quicker then SQL ORDER BY RAND()
        $random_question = $rows[array_rand($rows)];
        // TODO this question needs to be made persistent
        // so that user is challenged for same random question
        return array_unique($random_question);
    }

    private function calculateAnswerHash($answer, $siteSalt, $answerSalt) {
      return hash('sha512', $siteSalt.$answerSalt.strtolower($answer));
    }

    /**
     * Saves user submitted answers in to database
     *
     * @param int $uid
     * @param array $answers
     * @param array $questions
     * @return bool
     */
    public function registerAnswers($uid,$answers, $questions) {

        // This check is probably not needed
        if (empty($answers) || empty($questions) || empty($uid)) return FALSE;
        $question_answers = array_combine($answers, $questions);

        foreach ($question_answers as $answer => $question) {
            // Check that the answer meets the length requirements
            if ( (strlen($answer) >= $this->minAnswerLength) && $question > 0) {
                $answer_salt = $this->generateRandomString();
                $answer_hash = $this->calculateAnswerHash($answer, $this->site_salt, $answer_salt);
                $q = $this->dbh->prepare("INSERT INTO ssp_answers (answer_salt, answer_hash, question_id, uid) VALUES (:answer_salt, :answer_hash, :question, :uid)");

                $result = $q->execute([':answer_salt' => $answer_salt,
                                       ':answer_hash' => $answer_hash,
                                       ':question' => $question,
                                       ':uid' => $uid]);
                \SimpleSAML\Logger::debug('auth2factor: ' . $uid . ' registered his answer: '. $answer . ' for question_id:' . $question);
                $result = TRUE;
            } else {
                $result = FALSE;
            }
        }

        return $result;
    }

    /**
     * Deletes all predefined and userdefined questions for that user
     *
     * @param int $uid
     * @return boolean
     */
    public function unregisterQuestions($uid) {
      $answers = $this->dbh->prepare("DELETE FROM ssp_answers WHERE uid=:uid");
      $questions = $this->dbh->prepare("DELETE FROM ssp_user_questions WHERE uid=:uid");

      $resetAnswers = $answers->execute([':uid' => $uid]);
      $resetQuestions = $questions->execute([':uid' => $uid]);

      if ($resetAnswers && $resetQuestions) {
        // make sure the preference is still set to questions!
        $this->set2Factor($uid, 'question');
        \SimpleSAML\Logger::debug('auth2factor: ' . $uid . ' has asked to reset their questions (including user defined)');
        return true;
      }

      return false;
    }

    /**
     * Saves user submitted questions/answers in to database
     *
     * @param int $uid
     * @param array $answers
     * @param array $questions
     * @return bool
     */
    public function registerCustomAnswers($uid,$answers, $questions) {

        // This check is probably not needed
        if (empty($answers) || empty($questions) || empty($uid)) return FALSE;

        $question_answers = array_combine($answers, $questions);

        foreach ($question_answers as $answer => $question) {
            // Check for user defined question
            if (!$this->isPredefinedQuestion($question)) {

              // Check that the answer meets the length requirements
              if ((strlen($answer) >= $this->minAnswerLength) && (strlen($question) >= $this->minQuestionLength)) {

                  // insert user defined question in to database
                  $insertCustomQuestion = $this->dbh->prepare("INSERT INTO ssp_user_questions (uid, user_question) VALUES (:uid, :user_question)");
                  $r = $insertCustomQuestion->execute([':uid' => $uid, ':user_question' => $question]);
                  $user_question_id = $this->dbh->lastInsertId();


                  $answer_salt = $this->generateRandomString();
                  $answer_hash = $this->calculateAnswerHash($answer, $this->site_salt, $answer_salt);
                  $q = $this->dbh->prepare("INSERT INTO ssp_answers (answer_salt, answer_hash, user_question_id, uid) VALUES (:answer_salt, :answer_hash, :user_question_id, :uid)");


                  $result = $q->execute([':answer_salt' => $answer_salt,
                                         ':answer_hash' => $answer_hash,
                                         ':user_question_id' => $user_question_id,
                                         ':uid' => $uid]);
                  \SimpleSAML\Logger::debug('auth2factor: ' . $uid . ' registered his answer: '. $answer . ' for custom_question_id:' . $user_question_id);
              } else {
                  $result = FALSE;
              }


            } else { // dealing with pre-defined questions below
              // Check that the answer meets the length requirements
              if ((strlen($answer) >= $this->minAnswerLength) && (int) $question > 0) {
                  $answer_salt = $this->generateRandomString();
                  $answer_hash = $this->calculateAnswerHash($answer, $this->site_salt, $answer_salt);
                  $q = $this->dbh->prepare("INSERT INTO ssp_answers (answer_salt, answer_hash, question_id, uid) VALUES (:answer_salt, :answer_hash, :question, :uid)");


                  $result = $q->execute([':answer_salt' => $answer_salt,
                                         ':answer_hash' => $answer_hash,
                                         ':question' => $question,
                                         ':uid' => $uid]);
                  \SimpleSAML\Logger::debug('auth2factor: ' . $uid . ' registered his answer: '. $answer . ' for question_id:' . $question);
              } else {
                  $result = FALSE;
              }
            }
        }

        return $result;
    }

    /**
     * Verify user submitted answer against their question
     *
     * @param int $uid
     * @param int $question_id
     * @param string $answer
     * @return bool
     */
    public function verifyAnswer($uid, $question_id, $answer){
        $answers = self::getAnswersFromUID($uid);
        $match = FALSE;

        foreach($answers as $a){
          if ($question_id == $a["question_id"] || $question_id == $a["user_question_id"]) {
                $answer_salt = $a['answer_salt'];
                $submitted_hash = $this->calculateAnswerHash($answer, $this->site_salt, $answer_salt);
                if ($submitted_hash == $a["answer_hash"]) {
                    $match = TRUE;
                    break;
                }
          }
        }
        return $match;
    }

    public function verifyChallenge($uid, $answer) {
        if ($this->hasMailCode($uid)) {
            $q = $this->dbh->prepare("SELECT uid, last_code, last_code_stamp FROM ssp_user_2factor WHERE uid=:uid ORDER BY last_code_stamp DESC;");
            $result = $q->execute([':uid' => $uid]);
            $rows = $q->fetchAll();

            if ($rows[0]['last_code'] === trim($answer)) {
                \SimpleSAML\Logger::debug('User '.$uid.' passed good code');
                $q = $this->dbh->prepare("UPDATE ssp_user_2factor SET last_code=NULL,last_code_stamp=NULL WHERE uid=:uid;");
                $result = $q->execute([':uid' => $uid]);
                return true;
            } else {
                \SimpleSAML\Logger::debug('User '.$uid.' passed bad code. "'.$rows[0]['last_code'].'" !== "'.$answer.'"');
                return false;
            }
        } else {
            \SimpleSAML\Logger::debug('User '.$uid.' does not have a code');
            return false;
        }
    }

    public function isInvalidCode($uid, $answer) {

      $q = $this->dbh->prepare("SELECT uid, last_code FROM ssp_user_2factor WHERE uid=:uid ORDER BY last_code_stamp DESC;");
      $result = $q->execute([':uid' => $uid]);
      $rows = $q->fetchAll();

      if ($rows[0]['last_code'] !== trim($answer)) {
          return true;
      }
      return false;
    }


    /**
     * Determines if the browser provided a valid SSL client certificate
     *
     * @return boolean True if the client cert is there and is valid
     */
    public function hasValidCert($uid) {
      // always return false if SSL client cert verification turned off
      return $this->ssl_clientVerify;

      if (!isset($_SERVER['SSL_CLIENT_M_SERIAL'])
          || !isset($_SERVER['SSL_CLIENT_V_END'])
          || !isset($_SERVER['SSL_CLIENT_VERIFY'])
          || $_SERVER['SSL_CLIENT_VERIFY'] !== 'SUCCESS'
          || !isset($_SERVER['SSL_CLIENT_I_DN'])
      ) {
          return false;
      }

      if ($_SERVER['SSL_CLIENT_V_REMAIN'] <= 0) {
          return false;
      }
      $this->set2Factor($uid, self::FACTOR_SSL);
      return true;
    }

    /**
     * Increments failed login attempt for
     * login, mail code and secret questions
     *
     * @param int $uid userid
     * @param  string $type Column name
     */
    public function failedLoginAttempt($uid, $type, $attributes = []) {
      // write it back to database the new count
      $q = $this->dbh->prepare("UPDATE ssp_user_2factor SET $type = $type + 1 WHERE uid=:uid LIMIT 1;");
      $result = $q->execute([':uid' => $uid]);
      \SimpleSAML\Logger::debug('User '.$uid.' failed login attempt with ' . $type);
      // lock the account!
      $failedAttempts = $this->getFailedAttempts($uid);

      if ($this->maxFailLogin == ((int)$failedAttempts[0]['login_count'] + (int) $failedAttempts[0]['answer_count'])) {
        \SimpleSAML\Logger::debug('User '.$uid.' has exceeded max failed login attempts of ' . $this->maxFailLogin);
        $this->lockAccount($uid);
        $this->emailAdministrators($attributes);
        $this->emailUser($attributes['uid'], $attributes['mail'], $attributes['name']);
      }
    }

    /**
     * Returns a count of failed attempts
     * using username/password or secret question or mailcode
     *
     * @param int $uid userid
     * @return  array $count
     */
    public function getFailedAttempts($uid) {

      $q = $this->dbh->prepare("SELECT login_count, answer_count FROM ssp_user_2factor WHERE uid=:uid LIMIT 1;");
      $result = $q->execute([':uid' => $uid]);
      $rows = $q->fetchAll();

      return $rows;
    }

   /**
     * Reset login attempts back to zero
     *
     * @param int $uid userid
     * @param  string $type Column name
     */
    public function resetFailedLoginAttempts($uid, $type = 'login_count') {
      $q = $this->dbh->prepare("UPDATE ssp_user_2factor SET $type = 0 WHERE uid=:uid LIMIT 1;");
      $result = $q->execute([':uid' => $uid]);
      \SimpleSAML\Logger::debug('User '.$uid.' reset login attempts back to zero');
    }

   /**
     * Checks if user account is locked or not
     *
     * @param int $uid userid
     * @param boolean locked
     */
    public function isLocked($uid) {
      $q = $this->dbh->prepare("SELECT locked FROM ssp_user_2factor WHERE uid=:uid LIMIT 1;");
      $result = $q->execute([':uid' => $uid]);
      $rows = $q->fetchAll();

      return (bool) (!empty($rows))? $rows[0]['locked'] : false;
    }

   /**
     * Returns configured maxFailedLogin count
     *
     * @return int maxFailLogin
     */
    public function getmaxFailLogin(){
      return (int) $this->maxFailLogin;
    }

   /**
     * Locks the user account and resets failed attempts to zero
     *
     * @param int $uid userid
     */
    private function lockAccount($uid) {
      $q = $this->dbh->prepare("UPDATE ssp_user_2factor SET locked = 1 WHERE uid=:uid LIMIT 1;");
      $result = $q->execute([':uid' => $uid]);

      $this->resetFailedLoginAttempts($uid, 'login_count');
      $this->resetFailedLoginAttempts($uid, 'answer_count');

      \SimpleSAML\Logger::debug('User '.$uid.' account is now locked');
    }

    private function emailAdministrators($attributes) {
        if (!empty($this->notificationEmail) && !empty($attributes)) {
          foreach($this->notificationEmail as $email) {

             require_once('Mail.php');

              $auth = false;
              $username = '';
              $password = '';

              // only turn on mail smtp authentication if we have username and password
              if(isset($this->mail["username"]) && isset($this->mail["password"])) {
                $auth = true;
                $username = $this->mail["username"];
                $password = $this->mail["password"];
              }

                $useremail = $attributes['mail'];
                $name = $attributes['name'];
                $id = $attributes['uid'];
                $subject = " Account locked notification";
                $body = <<<EOD
Dear Administrator,

This is an email to let you know that account of name: '$name' , id: '$id' with email address : '$useremail'  has been locked.

Yours truely,
SSO System

EOD;

              if (isset($this->mail)) {
                $params = [ "host" => $this->mail["host"],
                            "port" => $this->mail["port"],
                            "auth" => $auth,
                            "username" => $username,
                            "password" => $password,
                            "debug" => $this->mail["debug"],
                          ];

                $headers = [  "To" => $email,
                              "From" => $this->mail["from"],
                              "Subject" => $this->mail["subject"]  . $subject,
                          ];

                $mail = new Mail();

                $mail_factory = $mail->factory('smtp', $params); // Print the parameters you are using to the page
                $mail_factory->send($email, $headers, $body);


              } else {
                // fall back to normal mail function
                mail($email, $subject, $body);
              }

          }
        }
    }

    /* Email user of his/hesr account being locked */

    private function emailUser($uid, $email, $name) {
        if (isset($email)) {

             require_once('Mail.php');

              $auth = false;
              $username = '';
              $password = '';

              // only turn on mail smtp authentication if we have username and password
              if(isset($this->mail["username"]) && isset($this->mail["password"])) {
                $auth = true;
                $username = $this->mail["username"];
                $password = $this->mail["password"];
              }

                $subject = " Account locked notification";
                $body = <<<EOD
Dear $name,

This is an email to let you know that account '$uid' has been locked. Please contact system administrators.

Regards,

Security Team

EOD;

              if (isset($this->mail)) {
                $params = [ "host" => $this->mail["host"],
                            "port" => $this->mail["port"],
                            "auth" => $auth,
                            "username" => $username,
                            "password" => $password,
                            "debug" => $this->mail["debug"],
                          ];

                $headers = [  "To" => $email,
                              "From" => $this->mail["from"],
                              "Subject" => $this->mail["subject"]  . $subject,
                          ];

                $mail = new Mail();

                $mail_factory = $mail->factory('smtp', $params); // Print the parameters you are using to the page
                $mail_factory->send($email, $headers, $body);


              } else {
                // fall back to normal mail function
                mail($email, $subject, $body);
              }

        }
    }

    /**
     * Determines if it is a predefined question or not
     *
     * @param int $question Question ID to check if it exists or not
     * @return boolean True if the client cert is there and is valid
     */
    private function isPredefinedQuestion($question) {

      $q = $this->dbh->prepare("SELECT COUNT(*) as predefined_count FROM ssp_questions WHERE question_id = :question_id LIMIT 1;");

      $result = $q->execute([':question_id' => $question]);
      $rows = $q->fetchAll();
      $records_count =  $rows[0]["predefined_count"];
      return (bool) ($records_count > 0)? TRUE : FALSE;

    }


}

?>
