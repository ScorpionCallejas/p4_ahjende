CREATE DATABASE IF NOT EXISTS database_ejecutivos;
USE database_ejecutivos;

CREATE TABLE ejecutivo (
    id_eje INT(11) AUTO_INCREMENT PRIMARY KEY,
    nom_eje VARCHAR(255) NOT NULL
);