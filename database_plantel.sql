CREATE DATABASE IF NOT EXISTS database_plantel;
USE database_plantel;

CREATE TABLE plantel (
    id_pla INT(11) AUTO_INCREMENT PRIMARY KEY,
    nom_pla VARCHAR(255) NOT NULL
);