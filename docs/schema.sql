-- The module will make an attempt to create this schema --
-- This file is for reference only --

--
-- Table structure for table `ssp_answers`
--

CREATE TABLE IF NOT EXISTS `ssp_answers` (
`answer_id` int(11) NOT NULL,
  `user_question_id` int(11) NOT NULL,
  `answer_hash` varchar(128) NOT NULL,
  `answer_salt` varchar(15) NOT NULL,
  `question_id` int(11) NOT NULL,
  `uid` varchar(60) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `ssp_questions`
--

CREATE TABLE IF NOT EXISTS `ssp_questions` (
`question_id` int(11) NOT NULL,
  `question_text` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `ssp_user_2factor`
--

CREATE TABLE IF NOT EXISTS `ssp_user_2factor` (
  `uid` varchar(60) NOT NULL,
  `challenge_type` enum('question','sms','mail','ssl') NOT NULL,
  `last_code` varchar(10) DEFAULT NULL,
  `last_code_stamp` timestamp NULL DEFAULT NULL,
  `login_count` int(11) NOT NULL,
  `answer_count` int(11) NOT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `ssp_user_questions`
--

CREATE TABLE IF NOT EXISTS `ssp_user_questions` (
`user_question_id` int(11) NOT NULL,
  `uid` varchar(60) NOT NULL,
  `user_question` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ssp_answers`
--
ALTER TABLE `ssp_answers`
 ADD PRIMARY KEY (`answer_id`);

--
-- Indexes for table `ssp_questions`
--
ALTER TABLE `ssp_questions`
 ADD PRIMARY KEY (`question_id`);

--
-- Indexes for table `ssp_user_2factor`
--
ALTER TABLE `ssp_user_2factor`
 ADD PRIMARY KEY (`uid`), ADD UNIQUE KEY `uid` (`uid`);

--
-- Indexes for table `ssp_user_questions`
--
ALTER TABLE `ssp_user_questions`
 ADD PRIMARY KEY (`user_question_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ssp_answers`
--
ALTER TABLE `ssp_answers`
MODIFY `answer_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `ssp_questions`
--
ALTER TABLE `ssp_questions`
MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `ssp_user_questions`
--
ALTER TABLE `ssp_user_questions`
MODIFY `user_question_id` int(11) NOT NULL AUTO_INCREMENT;