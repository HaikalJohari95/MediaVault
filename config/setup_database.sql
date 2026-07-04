-- ============================================================
--  MediaVault - Full Database Setup
--  Based on ERD Diagram
--  Database: GS05DB
-- ============================================================

CREATE DATABASE IF NOT EXISTS GS05DB;
USE GS05DB;

-- ============================================================
-- 1. USER_ACCOUNTS
-- ============================================================
CREATE TABLE IF NOT EXISTS user_accounts (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    email         VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    department    VARCHAR(100) DEFAULT NULL,
    access_role   ENUM('Admin', 'User', 'Viewer') NOT NULL DEFAULT 'User',
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 2. DEMOGRAPHIC_PARSING_STORE
-- ============================================================
CREATE TABLE IF NOT EXISTS demographic_parsing_store (
    record_id       INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT          NOT NULL,
    ic_number       VARCHAR(20)  NOT NULL,
    date_of_birth   DATE         NOT NULL,
    state_of_origin VARCHAR(100) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE
);

-- ============================================================
-- 3. MULTIMEDIA_FILES
-- ============================================================
CREATE TABLE IF NOT EXISTS multimedia_files (
    file_id          INT AUTO_INCREMENT PRIMARY KEY,
    file_name        VARCHAR(255) NOT NULL,
    file_type        ENUM('Document', 'Audio', 'Video') NOT NULL,
    size_kb          DECIMAL(10, 2) NOT NULL,
    upload_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    user_id          INT NOT NULL,
    tag_id           INT DEFAULT NULL,
    doc_id           INT DEFAULT NULL,
    feature_id       INT DEFAULT NULL,
    video_id         INT DEFAULT NULL,
    audio_id         INT DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE
);

-- ============================================================
-- 4. DOCUMENT_METADATA
-- ============================================================
CREATE TABLE IF NOT EXISTS document_metadata (
    doc_id         INT AUTO_INCREMENT PRIMARY KEY,
    file_id        INT NOT NULL,
    page_count     INT DEFAULT 0,
    word_count     INT DEFAULT 0,
    language       VARCHAR(50)  DEFAULT 'English',
    version_number VARCHAR(20)  DEFAULT '1.0',
    FOREIGN KEY (file_id) REFERENCES multimedia_files(file_id) ON DELETE CASCADE
);

-- ============================================================
-- 5. AUDIO_METADATA
-- ============================================================
CREATE TABLE IF NOT EXISTS audio_metadata (
    audio_id         INT AUTO_INCREMENT PRIMARY KEY,
    file_id          INT NOT NULL,
    duration_seconds INT DEFAULT 0,
    bitrate_kbps     INT DEFAULT 0,
    frequency_hz     INT DEFAULT 0,
    genre_tag        VARCHAR(100) DEFAULT NULL,
    FOREIGN KEY (file_id) REFERENCES multimedia_files(file_id) ON DELETE CASCADE
);

-- ============================================================
-- 6. VIDEO_METADATA
-- ============================================================
CREATE TABLE IF NOT EXISTS video_metadata (
    video_id         INT AUTO_INCREMENT PRIMARY KEY,
    file_id          INT NOT NULL,
    duration_seconds INT DEFAULT 0,
    resolution       VARCHAR(20)  DEFAULT NULL,
    frame_rate       DECIMAL(5,2) DEFAULT 0,
    codec            VARCHAR(50)  DEFAULT NULL,
    FOREIGN KEY (file_id) REFERENCES multimedia_files(file_id) ON DELETE CASCADE
);

-- ============================================================
-- 7. CONTENT_FEATURES
-- ============================================================
CREATE TABLE IF NOT EXISTS content_features (
    feature_id    INT AUTO_INCREMENT PRIMARY KEY,
    file_id       INT NOT NULL,
    feature_type  VARCHAR(100) NOT NULL,
    feature_value VARCHAR(255) NOT NULL,
    FOREIGN KEY (file_id) REFERENCES multimedia_files(file_id) ON DELETE CASCADE
);

-- ============================================================
-- 8. TAG_DICTIONARY
-- ============================================================
CREATE TABLE IF NOT EXISTS tag_dictionary (
    tag_id      INT AUTO_INCREMENT PRIMARY KEY,
    file_id     INT DEFAULT NULL,
    tag_name    VARCHAR(100) NOT NULL UNIQUE,
    usage_count INT DEFAULT 0,
    FOREIGN KEY (file_id) REFERENCES multimedia_files(file_id) ON DELETE SET NULL
);

-- ============================================================
-- 9. FILE_TAGS  (Many-to-Many junction)
-- ============================================================
CREATE TABLE IF NOT EXISTS file_tags (
    file_id INT NOT NULL,
    tag_id  INT NOT NULL,
    PRIMARY KEY (file_id, tag_id),
    FOREIGN KEY (file_id) REFERENCES multimedia_files(file_id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id)  REFERENCES tag_dictionary(tag_id)   ON DELETE CASCADE
);

-- ============================================================
-- 10. TRANSACTION_AUDIT_LOG
-- ============================================================
CREATE TABLE IF NOT EXISTS transaction_audit_log (
    log_id         INT AUTO_INCREMENT PRIMARY KEY,
    operation_type VARCHAR(20) NOT NULL,
    timestamp      DATETIME    DEFAULT CURRENT_TIMESTAMP,
    user_id        INT DEFAULT NULL,
    file_id        INT DEFAULT NULL,
    outcome        VARCHAR(50) NOT NULL DEFAULT 'SUCCESS',
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE SET NULL,
    FOREIGN KEY (file_id) REFERENCES multimedia_files(file_id) ON DELETE SET NULL
);

-- ============================================================
-- FK COLUMNS ON USER_ACCOUNTS (as shown in ERD)
-- ============================================================
ALTER TABLE user_accounts
    ADD COLUMN log_id    INT DEFAULT NULL,
    ADD COLUMN record_id INT DEFAULT NULL,
    ADD COLUMN file_id   INT DEFAULT NULL;

-- ============================================================
-- TRIGGERS: Auto-log file operations into audit log
-- ============================================================
DELIMITER $$

CREATE TRIGGER after_insert_file
AFTER INSERT ON multimedia_files
FOR EACH ROW
BEGIN
    INSERT INTO transaction_audit_log (operation_type, timestamp, user_id, file_id, outcome)
    VALUES ('INSERT', NOW(), NEW.user_id, NEW.file_id, 'SUCCESS');
END$$

CREATE TRIGGER after_delete_file
AFTER DELETE ON multimedia_files
FOR EACH ROW
BEGIN
    INSERT INTO transaction_audit_log (operation_type, timestamp, user_id, file_id, outcome)
    VALUES ('DELETE', NOW(), OLD.user_id, OLD.file_id, 'SUCCESS');
END$$

CREATE TRIGGER after_update_file
AFTER UPDATE ON multimedia_files
FOR EACH ROW
BEGIN
    INSERT INTO transaction_audit_log (operation_type, timestamp, user_id, file_id, outcome)
    VALUES ('UPDATE', NOW(), NEW.user_id, NEW.file_id, 'SUCCESS');
END$$

DELIMITER ;

-- ============================================================
-- DEFAULT ADMIN ACCOUNT
-- Username: admin | Password: Admin@1234
-- ============================================================
INSERT IGNORE INTO user_accounts (username, email, password_hash, department, access_role)
VALUES (
    'admin',
    'admin@mediavault.com',
    '$2y$12$eImiTXuWVxfM37uY4JANjOe5XwbMwNqWqZg0.bxXPMO8QIFqQV8Oi',
    'IT Department',
    'Admin'
);
