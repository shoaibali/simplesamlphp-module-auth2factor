<?php

/**
 * @author Shoaib Ali, Catalyst IT
 * @package simpleSAMLphp
 * @version $Id$
 */

// Retrieve the authentication state
if (!array_key_exists('AuthState', $_REQUEST)) {
    throw new \SimpleSAML\Error\BadRequest('Missing AuthState parameter.');
}

$as = \SimpleSAML\Configuration::getConfig('authsources.php')->getValue('auth2factor');

// Get session object
$session = \SimpleSAML\Session::getSessionFromRequest();

// Get the authetication state
$authStateId = $_REQUEST['AuthState'];

//$state = SimpleSAML_Auth_State::loadState($authStateId,'auth2factor.step');
//$state = \SimpleSAML\Auth\State::loadState($authStateId, 'auth2factor.step');
/** @var array $state */
$state = \SimpleSAML\Auth\State::loadState($authStateId, \SimpleSAML\Module\auth2factor\Auth\Source\auth2factor::STAGEID);

// Use 2 step authentication class
$qaLogin = \SimpleSAML\Auth\Source::getById('auth2factor');

// Init template
$template = 'auth2factor:login.php';
$globalConfig = \SimpleSAML\Configuration::getInstance();
$t = new \SimpleSAML\XHTML\Template($globalConfig, $template);

$errorCode = NULL;

$questions = $qaLogin->getQuestions();


if(!$questions){
    $errorCode = 'EMPTYQUESTIONS';
    $t->data['todo'] = 'loginANSWER';
}

$t->data['questions'] = $questions;


//If user doesn't have session, force to use the main authentication method
// if (!$session->isValid( $as['mainAuthSource'] )) {
//     SimpleSAML_Auth_Default::initLogin( $as['mainAuthSource'], SimpleSAML_Utilities::selfURL());
// }

if (!$session->isValid( $as['mainAuthSource'] )) {
//     SimpleSAML_Auth_Default::initLogin( $as['mainAuthSource'], SimpleSAML_Utilities::selfURL());
    $source = \SimpleSAML\Auth\Source::getById($as['mainAuthSource']);
    // $source = \SimpleSAML\Auth\Source::getById($state[\SimpleSAML\Module\auth2factor\Auth\Source\auth2factor::AUTHID]);
    if ($source === null) {
        throw new \Exception(
            'Could not find authentication source with id ' . $as['mainAuthSource']
        );
    }
    //'\SimpleSAML\Auth\DefaultAuth.id'

    $source->initLogin(\SimpleSAML\Utils\HTTP::getSelfURL());
}



// $attributes = $session->getAuthData($as['mainAuthSource'], 'Attributes');
$attributes = $session->getAuthData($as['mainAuthSource'], 'Attributes');
$state['Attributes'] = $attributes;


$uid = $attributes[ $as['uidField'] ][0]; // TODO check to see if this is set.
$email = isset( $attributes[ $as['emailField'] ][0] ) ? $attributes[ $as['emailField'] ][0] : $uid; 
$givenName = isset( $attributes['givenName'][0] ) ? $attributes['givenName'][0] : ""; 

$state['UserID'] = $uid;
$isRegistered = $qaLogin->isRegistered($uid);
$isSSLVerified = $qaLogin->hasValidCert($uid);
$accountLocked = $qaLogin->isLocked($uid);
$failedAttempts = $qaLogin->getFailedAttempts($uid);

$loginCount = (int) (!empty($failedAttempts))? $failedAttempts[0]['login_count'] : 0;
$answerCount = (int) (!empty($failedAttempts))? $failedAttempts[0]['answer_count'] : 0;
$failCount =  $loginCount + $answerCount;

// TODO this is bad, what if maxFailLogin is not set or is zero or is less than 3
$firstFailCount = $qaLogin->getmaxFailLogin() - 2;
$secondFailCount = $qaLogin->getmaxFailLogin() - 1;

$prefs = $qaLogin->get2FactorFromUID($uid);
$t->data['useSMS'] = true; // there is no SMS support this is misused for Email based code

// Check account is locked or not
if($accountLocked) {
    $errorCode = 'ACCOUNTLOCKED';
    $t->data['todo'] = 'loginCode';
    // destroy session and the user out
    $qaLogin->logout($state);
}

// if we are using SSL ceritificate to verify then we do not need 2-factor
if ($isSSLVerified && !$accountLocked) {
    $state['saml:AuthnContextClassRef'] = $qaLogin->tfa_authencontextclassref;
    \SimpleSAML\Auth\Source::completeAuth($state);
} else {
    // if SSL verification has failed make sure we fall back on question
    if (!$qaLogin->hasMailCode($uid)) {
        $qaLogin->set2Factor($uid, 'question');
    }
}

/******************************
 *       NEW USERS
 ******************************/

if (!$isRegistered && !$isSSLVerified) {

    //If the user has not set his preference of 2 factor authentication, redirect to settings page
    if ( isset($_POST['answers']) && isset($_POST['questions']) ){
        // Save answers
        $answers = (isset($_POST["answers"]))? $_POST["answers"] : [];
        $questions = (isset($_POST["questions"]))? $_POST["questions"] : [];
        $custom_questions = (isset($_POST["custom_questions"]))?  $_POST["custom_questions"] : [];

        foreach($custom_questions as $ck => $cv) {
            if (!empty($cv)) {
                $questions[$ck] = $cv;
            }
        }

        // verify answers are not duplicates
        if( (sizeof(array_unique($answers)) != sizeof($answers)) || (sizeof(array_unique($questions)) != sizeof($questions)) )
        {
            $errorCode = 'INVALIDQUESTIONANSWERS';
            $t->data['todo'] = 'selectanswers';
        } elseif (count($questions) < 3 || sizeof($answers) < 3) {
            $errorCode = 'INCOMPLETEQUESTIONS';
            $t->data['todo'] = 'selectanswers';
        } else {


            if (count(array_filter($custom_questions)) > 0) {
                // we are dealing with one or more userdefined questions
                $result = $qaLogin->registerCustomAnswers($uid, $answers, $questions);
            } else {
                $result = $qaLogin->registerAnswers($uid, $answers, $questions);
            }

            if (!$result) {
                // Failed to register answers for some reason. This is probably because one or more answer is too short
                $errorCode = 'SHORTQUESTIONANSWERS';
                $t->data['todo'] = 'selectanswers';
            } else {
                // redirect user back to be quized
                \SimpleSAML\Utilities::redirect(\SimpleSAML\Utilities::selfURL(), [ 'AuthState' => $authStateId,
                                                                                    'Quesetions' => $_POST['questions'],
                                                                                    'Answers' => $_POST['answers']]);
            }
        }

    } else {
        // We are setting the preference of user for the first time
        if(isset($_POST["authpref"])) {
            $t->data['todo'] = 'selectanswers';
            switch ($_POST['authpref']) {
                case "qanda":
                    $qaLogin->set2Factor($uid, 'question');
                    $t->data['todo'] = 'selectanswers';
                    $t->data['useSMS'] = false;
                    break;
                case "pin":
                    $qaLogin->set2Factor($uid, 'mail');
                    $qaLogin->sendMailCode($uid, $email);
                    $t->data['todo'] = 'loginCode';
                    break;
                default:
                    break;
            }

        } else {
            $t->data['todo'] = 'selectauthpref';
        }
    }
}


/******************************
 *          EXISTING USERS
 ******************************/

if ($isRegistered && !$isSSLVerified && !$accountLocked) {

    // do this if it's questions
    $t->data['autofocus'] = 'answer';
    if ($prefs['challenge_type'] == 'question' && (count($qaLogin->getAnswersFromUID($uid))>0)) {

        $t->data['todo'] = 'loginANSWER';
        $t->data['useSMS'] = false;

        // get a random question
        $random_question = $qaLogin->getRandomQuestion($uid);
        $t->data['random_question'] = array("question_text" => $random_question["question_text"],
                                        "question_id" => $random_question["question_id"]);

    } else {
        $t->data['todo'] = 'loginCode';
        if(!$qaLogin->hasMailCode($uid)) {
            $qaLogin->sendMailCode($uid, $email);
        }
    }

    if (isset( $_POST['submit'] )) {

        // if the form was submitted
        switch ($_POST['submit']) {
            // Next button pushed
            case $t->t('{auth2factor:login:next}'):

                // is this questions ?
                if ( isset( $_POST['answer'] ) ){
                    //Ask the user for answer to a randomly selected question
                    if ($prefs['challenge_type'] == 'question') {
                        $loggedIn = $qaLogin->verifyAnswer($uid, $_POST['question_id'], $_POST['answer']);
                        if ($loggedIn){
                            $state['saml:AuthnContextClassRef'] = $qaLogin->tfa_authencontextclassref;
                            $qaLogin->resetFailedLoginAttempts($uid, 'answer_count');
                            \SimpleSAML\Auth\Source::completeAuth($state);
                        } else {
                            $errorCode = 'WRONGANSWER';
                            // only increment if the account is not already locked
                            if (!$qaLogin->isLocked($uid)) {
                                $qaLogin->failedLoginAttempt($uid, 'answer_count', array(
                                                                                        'name' => $givenName,
                                                                                        'mail' => $email,
                                                                                        'uid' => $uid
                                                                                    )
                                );

                                if ($failCount == $firstFailCount){
                                    $errorCode = '2FAILEDATTEMPTWARNING';
                                }

                                if ($failCount == $secondFailCount) {
                                    $errorCode = '1FAILEDATTEMPTWARNING';
                                }

                            }

                            $t->data['todo'] = 'loginANSWER';
                        }
                    }
                    else {
                       $t->data['todo'] = 'loginCode';
                        // TODO don't need to verify an invalid code
                        $loggedIn = $qaLogin->verifyChallenge($uid, $_POST['answer']);

                        if ($loggedIn){
                          $state['saml:AuthnContextClassRef'] = $qaLogin->tfa_authencontextclassref;
                          $qaLogin->resetFailedLoginAttempts($uid, 'answer_count');
                          \SimpleSAML\Auth\Source::completeAuth($state);
                        } else {
                          $errorCode = 'CODEXPIRED';
                          if (!$qaLogin->isLocked($uid)) {
                            $qaLogin->failedLoginAttempt($uid, 'answer_count', array(
                                                                                'name' => $givenName,
                                                                                'mail' => $email,
                                                                                'uid' => $uid
                                                                                )
                            );

                            if ($failCount == $firstFailCount){
                                $errorCode = '2FAILEDATTEMPTWARNING';
                            }

                            if ($failCount == $secondFailCount) {
                                $errorCode = '1FAILEDATTEMPTWARNING';
                            }
                          }
                        }
                   }
                }

                break;

            // Switch to Questions button pushed
            case $t->t('{auth2factor:login:switchtoq}'):
                if(count($qaLogin->getAnswersFromUID($uid))) {
                    // get a random question
                    $random_question = $qaLogin->getRandomQuestion($uid);
                    $t->data['random_question'] = array(
                                                    "question_text" => $random_question["question_text"],
                                                    "question_id" => $random_question["question_id"]
                                                  );
                }

                $qaLogin->set2Factor($uid, 'question');

                $t->data['todo'] = 'loginANSWER';
                $qaLogin->set2Factor($uid, 'question');
                $t->data['useSMS'] = false;
                // check if the user has registered questions if not ask them to register
                if (!$qaLogin->isRegistered($uid)) {
                    $t->data['todo'] = 'selectanswers';
                }

                break;

            // Switch to Mail code button (link) pushed
            case $t->t('{auth2factor:login:switchtomail}'):
                $qaLogin->set2Factor($uid, 'mail');
                $qaLogin->sendMailCode($uid, $email);
                $t->data['todo'] = 'loginCode';
                $t->data['useSMS'] = true;
                break;

            // User asked to reset their question and answers
            case $t->t('{auth2factor:login:resetquestions}'):
                $qaLogin->set2Factor($uid, 'questions');
                $unregistered  = $qaLogin->unregisterQuestions($uid);
                if ($unregistered) {
                    $t->data['todo'] = 'selectanswers';
                    $t->data['useSMS'] = false;
                    $qaLogin->sendQuestionResetEmail($attributes);
                }

                break;

            case $t->t('{auth2factor:login:resend}'):
                $qaLogin->sendMailCode($uid, $email);
                $t->data['todo'] = 'loginCode';
                $t->data['useSMS'] = true;
                if (!$qaLogin->isLocked($uid)) {
                    $qaLogin->failedLoginAttempt($uid, 'answer_count', array(
                                                                            'name' => $givenName,
                                                                            'mail' => $email,
                                                                            'uid' => $uid
                                                                        )
                    );
                }
                break;
            default:
                break;
        }

    // } else {
    //      $t->data['autofocus'] = 'answer';
    //      $t->data['todo'] = 'loginANSWER';
    }
}

$t->data['stateparams'] = array('AuthState' => $authStateId);
$t->data['errorcode'] = $errorCode;
$t->data['minAnswerLength'] = $qaLogin->getMinAnswerLength();
$t->data['minQuestionLength'] = $qaLogin->getMinQuestionLength();

// get the preferences agains as they may have changed above
$prefs = $qaLogin->get2FactorFromUID($uid);

if (!$t->data['todo'] == 'selectauthpref') {
    if ($prefs['challenge_type'] == 'question') {
        $t->data['todo'] = 'loginANSWER';
    } else {
        $t->data['useSMS'] = true;
        $t->data['todo'] = 'loginCode';
        if(!$qaLogin->hasMailCode($uid)) {
            // TODO investigate this seems like a pointless condition never gets executed!
            $qaLogin->sendMailCode($uid, $email);
        }
    }
}

$t->show();

?>
