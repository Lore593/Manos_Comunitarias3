CREATE DATABASE IF NOT EXISTS manos_comunitarios
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE manos_comunitarios;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    post_type ENUM('ofrezco', 'necesito') NOT NULL,
    title VARCHAR(100) NOT NULL,
    category VARCHAR(60) NOT NULL,
    description VARCHAR(600) NOT NULL,
    location VARCHAR(160) NOT NULL,
    status ENUM('open', 'completed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_posts_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS post_responses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_response (post_id, user_id),
    CONSTRAINT fk_response_post FOREIGN KEY (post_id) REFERENCES posts(id),
    CONSTRAINT fk_response_user FOREIGN KEY (user_id) REFERENCES users(id)
);
