CREATE DATABASE IF NOT EXISTS vbo_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE vbo_db;

CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)                     NOT NULL UNIQUE,
    password   VARCHAR(255)                    NOT NULL,
    role       ENUM('entraineur', 'admin')     NOT NULL DEFAULT 'entraineur',
    prenom     VARCHAR(50)                     NOT NULL DEFAULT '',
    nom        VARCHAR(50)                     NOT NULL DEFAULT '',
    created_at TIMESTAMP                       DEFAULT CURRENT_TIMESTAMP
);