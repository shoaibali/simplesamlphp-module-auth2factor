<?php

$this->includeAtTemplateBase('includes/header.php');
$this->data['header'] = $this->t('{auth2factor:login:authentication}');

?>

<link rel="stylesheet" href="keyboard.css" />
<script src="jquery.min.js"></script>
<script src="jquery-ui.min.js"></script>
<script type="text/javascript" src="jquery.keyboard.min.js"></script>
<!-- Only activate the virtual keyboard if we are doing questions -->
<?php if ( !$this->data['useSMS'] ) : ?>
    <script type="text/javascript">
    $(document).ready(function () {
    $("input[type='text']").keyboard({
        autoAccept: true,
        layout: 'custom',
        lockInput: false,
        customLayout: {
        'default': ['0 1 2 3 4 5 6 7 8 9', 'a b c d e f g h i j k l m', 'n o p q r s t u v w x y z','{accept} {space} {cancel}']
        }
    });
    $("input[type='text']").getkeyboard().reveal();
    });
    </script>
<?php endif; ?>


<?php if ($this->data['errorcode'] !== NULL) :?>
    <div style="border-left: 1px solid #e8e8e8; border-bottom: 1px solid #e8e8e8; background: #f5f5f5">
    <img src="/<?php echo $this->data['baseurlpath']; ?>resources/icons/experience/gtk-dialog-error.48x48.png" style="float: left; margin: 15px " />
    <h2><?php echo $this->t('{login:error_header}'); ?></h2>
    <p><b><?php echo $this->t('{auth2factor:errors:title_' . $this->data['errorcode'] . '}'); ?></b></p>
    <p><?php echo $this->t('{auth2factor:errors:descr_' . $this->data['errorcode'] . '}'); ?></p>
    </div>
<?php endif; ?>


<form action="?" method="post" name="f" id="form">
    <?php if ( $this->data['todo'] == 'selectauthpref' ) : ?>

        As second factor authentication what would you like to use
        <br/>
        <ul>
            <li><input type="radio" name="authpref" value="qanda" checked="checked"> Secret question &amp; answers?</li>
            <li><input type="radio" name="authpref" value="pin">OTP (One Time Password) sent via Email?</li>
        </ul>
    <p>For future login attempts, above selected preference will be applied. Option to switch different second step authentication will be available</p>

    <input class="submitbutton" type="submit" tabindex="2" name="submit" value="<?php echo $this->t('{auth2factor:login:next}')?>" />


    <?php elseif ( $this->data['todo'] == 'selectanswers' ) : ?>
    <h2><?php echo $this->t('{auth2factor:login:2step_title}')?></h2>
    <div class="loginbox">
        <p class="logintitle"><?php echo $this->t('{auth2factor:login:chooseANSWERS}')?></p>
            <p>


            <?php
            if(!empty($this->data['questions'])) {
                for($i=1;$i <=3; $i++){
                    $answer_value = "";
                    $question_value = "";
                    if(isset($_POST["answers"]) && isset($_POST["questions"])){
                        $answer_value = $_POST["answers"][$i-1];
                        $selected_qid = $_POST["questions"][$i-1];
                        $question_value = $_POST["questions"][$i-1];
                    }
                     echo '<select id="question_'. $i .'" class="form-control small questions" name="questions[]" required="requred">';
                        echo '<option value="0">--- select question ---</option>';

                    foreach($this->data['questions'] as $question => $q) {
                        $selected = '';
                        if(isset($_POST["questions"])){
                            if($selected_qid == $q["question_id"]){
                                $selected = " selected";
                            }
                        }
                        echo ('<option value="'.$q['question_id'].'"'. $selected . '>'.$q['question_text'].'</option>');
                    }
                    echo '<option class="custom" value="question_'.$i.'">Write your own question ...</option>';
                    echo '</select>';

                    echo '<input style="display: none;" autocomplete="off" autocorrect="off" autocapitalize="off" class="form-control small question_'.$i.'" placeholder="Question" name="custom_questions[]" value="'.$question_value. '" type="text" pattern=".{'.$this->data['minQuestionLength'].',}"';
                    echo ' title="Question must be at least '.$this->data['minQuestionLength'].' characters long" required="requred">';

                    echo 'Answer: <input name="answers[]" value="" type="text" pattern=".{'.$this->data['minAnswerLength'].',}"';
                    echo 'title="Answers must be at least '.$this->data['minAnswerLength'].' characters long" required="requred">';
                    echo '<br/><br/>';
                }
            }
            ?>
        <?php if ( $this->data['minAnswerLength'] > 0 ) : ?>
            <p>Answers must be at least <?php echo $this->data['minAnswerLength'] ?> characters long</p>
        <?php endif; ?>
            <input class="submitbutton" type="submit" tabindex="2" name="submit" value="<?php echo $this->t('{auth2factor:login:next}')?>" />
            </p>
    </div>

    <?php elseif ( $this->data['todo'] == 'loginANSWER' ) : ?>
    <h2><?php echo $this->t('{auth2factor:login:2step_login}')?></h2>
    <div class="loginbox">
        <p class="logintitle">
        <?php echo ($this->t('{auth2factor:login:verficiationanswer}') )?>
        <br/>
        <strong><?php echo ($this->data["random_question"]["question_text"]); ?>?</strong>
        <br/>
        <input type="hidden" value="<?php echo $this->data['random_question']['question_id'];?>" name="question_id">
        <input id="answer" class="yubifield" type="text" tabindex="1" name="answer" />
        <input id="submit" class="submitbutton" type="submit" tabindex="2" name="submit" value="<?php echo $this->t('{auth2factor:login:next}')?>"/>
        <input class="submitbutton" type="submit" tabindex="3" name="submit" value="<?php echo $this->t('{auth2factor:login:switchtomail}')?>" />
        <input id="resetquestions" class="submitbutton" type="submit" tabindex="4" name="submit" value="<?php echo $this->t('{auth2factor:login:resetquestions}')?>"/>
        </p>
    </div>
    <?php elseif ( $this->data['todo'] == 'loginCode' ) : ?>
    <div class="loginbox">
        <p class="logintitle">
        <?php echo ($this->t('{auth2factor:login:entermailcode}'))?>
        <br/>
        <strong><?php echo ($this->t('{auth2factor:login:mailcode}')); ?>?</strong>
        <br/>
        <input id="answer" class="yubifield" type="text" tabindex="1" name="answer" />
        <input id="submit" class="submitbutton" type="submit" tabindex="2" name="submit" value="<?php echo $this->t('{auth2factor:login:next}')?>"/>
        <input class="submitbutton" type="submit" tabindex="3" name="submit" value="<?php echo $this->t('{auth2factor:login:switchtoq}')?>" />
        <input id="resent" class="submitbutton" type="submit" tabindex="4" name="submit" value="<?php echo $this->t('{auth2factor:login:resend}')?>"/>
        </p>
    </div>
    <?php endif ; ?>

    <?php
    foreach ($this->data['stateparams'] as $name => $value) {
    echo('<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '" />');
    }
    ?>

</form>

<script type="text/javascript">
    $( ".questions" ).change(function() {
         $('.'+$(this).attr('id')).prop('required',false);
        $('.'+$(this).attr('id')).hide();
        if ($(this).find('option:selected').attr('class') == 'custom') {
            $('.'+$(this).val()).show();
            $('.'+$(this).val()).prop('required',true);;
        }
    });
</script>

<div id="keyboard"></div>

<?php
$this->includeAtTemplateBase('includes/footer.php');
?>
