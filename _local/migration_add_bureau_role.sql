-- Migration : ajout du rôle 'bureau'
-- À exécuter une seule fois sur la base de données existante

USE vbo_db;

ALTER TABLE users
    MODIFY COLUMN role ENUM('entraineur', 'admin', 'arbitre', 'bureau') NOT NULL DEFAULT 'entraineur';
