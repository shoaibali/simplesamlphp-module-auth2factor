<?php

/**
 * @author Shoaib Ali, Catalyst IT
 * @package simpleSAMLphp
 * @version $Id$
 */

$as = SimpleSAML_Configuration::getConfig('authsources.php')->getValue('auth2factor');

// Get session object
$session = SimpleSAML_Session::getInstance();

// Get the authetication state
$authStateId = $_REQUEST['AuthState'];
$state = SimpleSAML_Auth_State::loadState($authStateId,'auth2factor.step');

// Use 2 step authentication class
$qaLogin = SimpleSAML_Auth_Source::getById('auth2factor');

// Init template
$template = 'auth2factor:login.php';
$globalConfig = SimpleSAML_Configuration::getInstance();
$t = new SimpleSAML_XHTML_Template($globalConfig, $template);

$errorCode = NULL;

$questions = $qaLogin->getQuestions();

if(!$questions){
    $errorCode = 'EMPTYQUESTIONS';
    $t->data['todo'] = 'loginANSWER';
}

$t->data['questions'] = $questions;

//If user doesn't have session, force to use the main authentication method
if (!$session->isValid( $as['mainAuthSource'] )) {
    SimpleSAML_Auth_Default::initLogin( $as['mainAuthSource'], SimpleSAML_Utilities::selfURL());
}

$attributes = $session->getAttributes();
$state['Attributes'] = $attributes;


$uid = $attributes[ $as['uidField'] ][0];
$state['UserID'] = $uid;
$isRegistered = $qaLogin->isRegistered($uid);

$prefs = $qaLogin->get2FactorFromUID($uid);

/******************************
 *       NEW USERS
 ******************************/

if ( !$isRegistered ) {


    //If the user has not set his preference of 2 factor authentication, redirect to settings page
    if ( isset($_POST['answers']) && isset($_POST['questions']) ){
        // Save answers
        $answers = $_POST["answers"];
        $questions = $_POST["questions"];

        // verify answers are not duplicates
        if( (sizeof(array_unique($answers)) != sizeof($answers)) || (sizeof(array_unique($questions)) != sizeof($questions)) ){
            $errorCode = 'INVALIDQUESTIONANSWERS';
            $t->data['todo'] = 'selectanswers';
        } elseif (in_array(0, $questions) || sizeof($answers) < 3) {
            $errorCode = 'INCOMPLETEQUESTIONS';
            $t->data['todo'] = 'selectanswers';
        } else {
            $result = $qaLogin->registerAnswers($uid, $answers, $questions);
            if ( ! $result) {
                // Failed to register answers for some reason. This is probably because one or more answer is too short
                $errorCode = 'SHORTQUESTIONANSWERS';
                $t->data['todo'] = 'selectanswers';
            } else {
                // redirect user back to be quized
                SimpleSAML_Utilities::redirect(SimpleSAML_Utilities::selfURL(), array('AuthState' => $authStateId,
                                                                                      'Quesetions' => $_POST['questions'],
                                                                                      'Answers' => $_POST['answers']));
            }
        }

    } else {
        $t->data['todo'] = 'selectauthpref';

        // We are setting the preference of user for the first time
        if(isset($_POST["authpref"])) {
            $t->data['todo'] = 'selectanswers';

            switch ($_POST['authpref']) {

                case "qanda":
                    $qaLogin->set2Factor($uid, 'question');
                    $t->data['todo'] = 'selectanswers';
                    //SimpleSAML_Utilities::redirect(SimpleSAML_Utilities::selfURL(), array('AuthState' => $authStateId));
                    break;
                case "pin":
                    $qaLogin->set2Factor($uid, 'sms');
                    $t->data['todo'] = 'selectanswers';
                    //SimpleSAML_Utilities::redirect(SimpleSAML_Utilities::selfURL(), array('AuthState' => $authStateId));
                    break;
                default:
                    break;
            }

        }

        //$t->data['todo'] = 'selectanswers';
    }
}


/******************************
 *          EXISTING USERS
 ******************************/

if ( $isRegistered ){


// do this if it's questions


    // get a random question
    $random_question = $qaLogin->getRandomQuestion($uid);
    $t->data['random_question'] = array("question_text" => $random_question["question_text"],
                                        "question_id" => $random_question["question_id"]);

// do this if it's sms code
    // check age of code - regen if old or empty
    sprintf('%06d', mt_rand(0, 999999));


    $t->data['autofocus'] = 'answer';
    if ($prefs['challenge_type'] == 'question') {
        $t->data['todo'] = 'loginANSWER';
    } else {
        $t->data['todo'] = 'loginCode';
        if(!$qaLogin->hasMailCode($uid)) {
            $qaLogin->sendMailCode($uid);
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
                            SimpleSAML_Auth_Source::completeAuth($state);
                        } else {
                            $errorCode = 'WRONGANSWER';
                            $t->data['todo'] = 'loginANSWER';
                        }
                    }
                    else {
                        $loggedIn = $qaLogin->verifyChallenge($uid, $_POST['answer']);
                        if ($loggedIn){
                            $state['saml:AuthnContextClassRef'] = $qaLogin->tfa_authencontextclassref;
                            SimpleSAML_Auth_Source::completeAuth($state);
                        } else {
                            $errorCode = 'WRONGANSWER';
                            $t->data['todo'] = 'loginCode';
                        }
                    }
                }

                break;

            // Switch to Questions button pushed
            case $t->t('{auth2factor:login:switchtoq}'):
                //error_log('switchtoq');
                $qaLogin->set2Factor($uid, 'question');
                break;

            // Switch to SMS button pushed
            case $t->t('{auth2factor:login:switchtomail}'):
                //error_log('switchtosms');
                $qaLogin->set2Factor($uid, 'mail');
                break;

            default:
                break;
        }

//  } else {
//      $t->data['autofocus'] = 'answer';
//      $t->data['todo'] = 'loginANSWER';
    }
}

$t->data['stateparams'] = array('AuthState' => $authStateId);
$t->data['errorcode'] = $errorCode;
$t->data['minAnswerLength'] = $qaLogin->getMinAnswerLength();
// get the preferences agains as they may have changed above
$prefs = $qaLogin->get2FactorFromUID($uid);
$t->data['useSMS'] = false;

if (!$t->data['todo'] == 'selectauthpref') {
    if ($prefs['challenge_type'] == 'question') {
        $t->data['todo'] = 'loginANSWER';
    } else {
        $t->data['useSMS'] = true;
        $t->data['todo'] = 'loginCode';
        if(!$qaLogin->hasMailCode($uid)) {
            $qaLogin->sendMailCode($uid);
        }
    }
}
$t->show();

?>
