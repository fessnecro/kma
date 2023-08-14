CREATE DATABASE `db`;
USE `db`;

CREATE TABLE IF NOT EXISTS `url` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `url` TEXT,
    `length` INT,
    `created_at` int
);