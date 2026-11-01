
CREATE DATABASE IF NOT EXISTS ballot_live CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ballot_live;

CREATE TABLE users (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(120)  NOT NULL,
  email          VARCHAR(190)  NOT NULL,
  password_hash  VARCHAR(255)  NOT NULL,
  avatar_url     VARCHAR(255)  NULL,
  provider       VARCHAR(32)   NOT NULL DEFAULT 'local',
  notify_results TINYINT(1)    NOT NULL DEFAULT 1,
  notify_closing TINYINT(1)    NOT NULL DEFAULT 1,
  notify_news    TINYINT(1)    NOT NULL DEFAULT 0,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE polls (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  author_id      INT UNSIGNED  NOT NULL,
  slug           VARCHAR(190)  NOT NULL,
  code           VARCHAR(16)   NOT NULL,
  title          VARCHAR(160)  NOT NULL,
  question       TEXT          NOT NULL,
  description    TEXT          NULL,
  category       VARCHAR(48)   NOT NULL DEFAULT 'community',
  mode           ENUM('single','multiple') NOT NULL DEFAULT 'single',
  status         ENUM('draft','open','closed') NOT NULL DEFAULT 'open',
  require_signin TINYINT(1)    NOT NULL DEFAULT 0,
  show_results   TINYINT(1)    NOT NULL DEFAULT 1,
  max_choices    TINYINT UNSIGNED NOT NULL DEFAULT 3,
  minutes_open   INT UNSIGNED  NOT NULL DEFAULT 10080,
  closes_at      DATETIME      NULL,
  views          INT UNSIGNED  NOT NULL DEFAULT 0,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_polls_slug (slug),
  KEY idx_polls_author (author_id),
  KEY idx_polls_status (status),
  CONSTRAINT fk_polls_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE poll_options (
  id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  poll_id  INT UNSIGNED  NOT NULL,
  label    VARCHAR(180)  NOT NULL,
  position TINYINT UNSIGNED NOT NULL DEFAULT 0,
  KEY idx_options_poll (poll_id),
  CONSTRAINT fk_options_poll FOREIGN KEY (poll_id) REFERENCES polls (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE votes (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  poll_id    INT UNSIGNED NOT NULL,
  option_id  INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NULL,
  voter_hash CHAR(32)     NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vote_user  (poll_id, user_id),
  UNIQUE KEY uq_vote_hash  (poll_id, voter_hash),
  KEY idx_votes_option (option_id),
  CONSTRAINT fk_votes_poll   FOREIGN KEY (poll_id)   REFERENCES polls (id)         ON DELETE CASCADE,
  CONSTRAINT fk_votes_option FOREIGN KEY (option_id) REFERENCES poll_options (id)  ON DELETE CASCADE,
  CONSTRAINT fk_votes_user   FOREIGN KEY (user_id)   REFERENCES users (id)         ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE password_resets (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  token_hash CHAR(64)     NOT NULL,
  expires_at DATETIME     NOT NULL,
  used_at    DATETIME     NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reset_token (token_hash),
  KEY idx_reset_user (user_id),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE contacts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(120) NOT NULL,
  email      VARCHAR(190) NOT NULL,
  topic      VARCHAR(60)  NOT NULL DEFAULT 'general',
  message    TEXT         NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE activity_log (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  action     VARCHAR(64)  NOT NULL,
  meta       VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_activity_user (user_id, created_at),
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;
