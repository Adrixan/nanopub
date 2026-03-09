-- NanoPub Database Schema
-- MySQL 8.0+ / MariaDB 10.5+
-- Charset: utf8mb4 for full Unicode support (including emojis)
-- Accounts table (users)
CREATE TABLE accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(30) NOT NULL,
    display_name VARCHAR(100),
    email VARCHAR(255),
    password_hash VARCHAR(255),
    bio TEXT,
    avatar_url VARCHAR(500),
    header_url VARCHAR(500),
    private_key TEXT,
    -- RSA private key for ActivityPub
    public_key TEXT,
    -- RSA public key for ActivityPub
    actor_url VARCHAR(500),
    -- ActivityPub actor URL
    inbox_url VARCHAR(500),
    -- ActivityPub inbox URL
    outbox_url VARCHAR(500),
    -- ActivityPub outbox URL
    followers_url VARCHAR(500),
    -- ActivityPub followers URL
    following_url VARCHAR(500),
    -- ActivityPub following URL
    is_local TINYINT(1) DEFAULT 1,
    is_locked TINYINT(1) DEFAULT 0,
    is_bot TINYINT(1) DEFAULT 0,
    is_suspended TINYINT(1) DEFAULT 0,
    is_admin TINYINT(1) DEFAULT 0,
    is_moderator TINYINT(1) DEFAULT 0,
    followers_count INT UNSIGNED DEFAULT 0,
    following_count INT UNSIGNED DEFAULT 0,
    statuses_count INT UNSIGNED DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_activity_at DATETIME,
    UNIQUE KEY uk_username (username),
    UNIQUE KEY uk_email (email),
    KEY idx_actor_url (actor_url(191)),
    KEY idx_created_at (created_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Statuses table (posts/toots)
CREATE TABLE statuses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    in_reply_to_id BIGINT UNSIGNED,
    -- Parent status for replies
    in_reply_to_account_id BIGINT UNSIGNED,
    reblog_of_id BIGINT UNSIGNED,
    -- Original status for boosts
    content TEXT,
    content_warning VARCHAR(200),
    visibility ENUM('public', 'unlisted', 'private', 'direct') DEFAULT 'public',
    `sensitive` TINYINT(1) DEFAULT 0,
    `language` VARCHAR(10),
    uri VARCHAR(500),
    -- ActivityPub URI
    url VARCHAR(500),
    -- Web URL
    local TINYINT(1) DEFAULT 1,
    favourites_count INT UNSIGNED DEFAULT 0,
    reblogs_count INT UNSIGNED DEFAULT 0,
    replies_count INT UNSIGNED DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_account_id (account_id),
    KEY idx_in_reply_to_id (in_reply_to_id),
    KEY idx_reblog_of_id (reblog_of_id),
    KEY idx_visibility_created (`visibility`, created_at),
    KEY idx_uri (uri(191)),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Media attachments
CREATE TABLE media_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    status_id BIGINT UNSIGNED,
    account_id BIGINT UNSIGNED NOT NULL,
    type ENUM('image', 'video', 'gifv', 'audio', 'unknown') DEFAULT 'unknown',
    url VARCHAR(500) NOT NULL,
    remote_url VARCHAR(500),
    preview_url VARCHAR(500),
    description TEXT,
    width INT UNSIGNED,
    height INT UNSIGNED,
    file_size INT UNSIGNED,
    mime_type VARCHAR(100),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_status_id (status_id),
    KEY idx_account_id (account_id),
    FOREIGN KEY (status_id) REFERENCES statuses(id) ON DELETE
    SET NULL,
        FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Follows
CREATE TABLE follows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    -- Follower
    target_account_id BIGINT UNSIGNED NOT NULL,
    -- Following
    uri VARCHAR(500),
    -- ActivityPub URI
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_follow (account_id, target_account_id),
    KEY idx_target_account_id (target_account_id),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (target_account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Follow requests (for locked accounts)
CREATE TABLE follow_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    target_account_id BIGINT UNSIGNED NOT NULL,
    uri VARCHAR(500),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_request (account_id, target_account_id),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (target_account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Likes (favourites)
CREATE TABLE likes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    status_id BIGINT UNSIGNED NOT NULL,
    uri VARCHAR(500),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_like (account_id, status_id),
    KEY idx_status_id (status_id),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (status_id) REFERENCES statuses(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Bookmarks
CREATE TABLE bookmarks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    status_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_bookmark (account_id, status_id),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (status_id) REFERENCES statuses(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Boosts (reblogs)
CREATE TABLE boosts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    status_id BIGINT UNSIGNED NOT NULL,
    uri VARCHAR(500),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_boost (account_id, status_id),
    KEY idx_status_id (status_id),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (status_id) REFERENCES statuses(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Notifications
CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    type ENUM(
        'follow',
        'follow_request',
        'mention',
        'reblog',
        'favourite',
        'poll',
        'status',
        'update'
    ) NOT NULL,
    from_account_id BIGINT UNSIGNED,
    status_id BIGINT UNSIGNED,
    read_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_account_id_created (account_id, created_at),
    KEY idx_account_id_read (account_id, read_at),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (from_account_id) REFERENCES accounts(id) ON DELETE
    SET NULL,
        FOREIGN KEY (status_id) REFERENCES statuses(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Instance configuration
CREATE TABLE instance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) NOT NULL,
    title VARCHAR(100),
    description TEXT,
    short_description TEXT,
    contact_email VARCHAR(255),
    admin_account_id BIGINT UNSIGNED,
    registrations_open TINYINT(1) DEFAULT 1,
    approval_required TINYINT(1) DEFAULT 0,
    max_toot_chars INT UNSIGNED DEFAULT 500,
    max_media_attachments INT UNSIGNED DEFAULT 4,
    max_image_size INT UNSIGNED DEFAULT 8388608,
    max_video_size INT UNSIGNED DEFAULT 41943040,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Domain blocks
CREATE TABLE domain_blocks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) NOT NULL,
    severity ENUM('silence', 'suspend') DEFAULT 'suspend',
    reason TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_domain (domain)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Reports
CREATE TABLE reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    -- Reporter
    target_account_id BIGINT UNSIGNED NOT NULL,
    -- Reported account
    status_ids TEXT,
    -- JSON array of status IDs
    comment TEXT,
    category ENUM('spam', 'violation', 'other') DEFAULT 'other',
    forwarded TINYINT(1) DEFAULT 0,
    action_taken_at DATETIME,
    action_taken_by_account_id BIGINT UNSIGNED,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_account_id (account_id),
    KEY idx_target_account_id (target_account_id),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (target_account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Activity queue (incoming federation)
CREATE TABLE activity_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activity_type VARCHAR(50) NOT NULL,
    activity_data JSON NOT NULL,
    actor VARCHAR(500) NOT NULL,
    priority TINYINT UNSIGNED DEFAULT 0,
    attempts TINYINT UNSIGNED DEFAULT 0,
    error TEXT,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME,
    KEY idx_status_priority (status, priority, created_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Delivery queue (outgoing federation)
CREATE TABLE delivery_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activity_id VARCHAR(100) NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    activity_data JSON NOT NULL,
    target_inbox VARCHAR(500) NOT NULL,
    signing_account_id BIGINT UNSIGNED NOT NULL,
    priority TINYINT UNSIGNED DEFAULT 0,
    attempts TINYINT UNSIGNED DEFAULT 0,
    error TEXT,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME,
    KEY idx_status_priority (status, priority, created_at),
    FOREIGN KEY (signing_account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Sessions table
CREATE TABLE sessions (
    id VARCHAR(128) PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    KEY idx_account_id (account_id),
    KEY idx_expires_at (expires_at),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- OAuth tokens (for API access)
CREATE TABLE oauth_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL,
    client_id VARCHAR(64),
    scopes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    last_used_at DATETIME,
    revoked TINYINT(1) DEFAULT 0,
    UNIQUE KEY uk_token (token),
    KEY idx_account_id (account_id),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Rate limits tracking
CREATE TABLE rate_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rate_limit_key VARCHAR(255) NOT NULL,
    request_count INT UNSIGNED DEFAULT 1,
    window_start DATETIME NOT NULL,
    last_request_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_key_window (rate_limit_key, window_start),
    KEY idx_window_start (window_start),
    KEY idx_last_request (last_request_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;