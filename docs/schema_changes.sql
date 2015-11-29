/* 29th November 2015 */
ALTER TABLE `ssp_user_2factor` CHANGE `challenge_type` `challenge_type` ENUM('question','sms','mail','ssl');
