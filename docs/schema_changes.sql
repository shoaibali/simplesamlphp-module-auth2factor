/* 29th November 2015 */
ALTER TABLE `ssp_user_2factor` CHANGE `challenge_type` `challenge_type` ENUM('question','sms','mail','ssl');

/* 30th November 2015 */
CREATE TABLE IF NOT EXISTS `ssp_user_questions` (
`user_question_id` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `user_question` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `ssp_user_questions`
 ADD PRIMARY KEY (`user_question_id`), ADD KEY `user_question_id` (`user_question_id`);

ALTER TABLE `ssp_user_questions`
MODIFY `user_question_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `ssp_answers`  ADD `user_question_id` INT NOT NULL  AFTER `question_id`,  ADD   INDEX  (`user_question_id`) ;
ALTER TABLE `ssp_user_questions` CHANGE `uid` `uid` VARCHAR(11) NOT NULL;

ALTER TABLE `ssp_user_2factor` ADD `login_count` INT NOT NULL AFTER `last_code_stamp`, ADD `answer_count` INT NOT NULL AFTER `login_count`, ADD `locked` BOOLEAN NOT NULL DEFAULT FALSE AFTER `answer_count`;
