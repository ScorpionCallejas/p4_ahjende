-- phpMyAdmin SQL Dump
-- version 4.5.1
-- http://www.phpmyadmin.net
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 23-07-2025 a las 03:20:40
-- Versión del servidor: 10.1.13-MariaDB
-- Versión de PHP: 5.6.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `database_eventos`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `eventos`
--

CREATE TABLE `eventos` (
  `id_eve` int(11) NOT NULL,
  `nom_eve` varchar(100) NOT NULL,
  `fec_ini` datetime NOT NULL,
  `fec_fin` datetime NOT NULL,
  `tip_eve` enum('Administrativo','Admisiones','Academico') NOT NULL,
  `est_eve` enum('Pendiente','Completado') NOT NULL DEFAULT 'Pendiente',
  `fec_eve` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id_eje` int(11) NOT NULL,
  `id_pla` int(11) NOT NULL,
  `nom_eje` varchar(100) DEFAULT NULL,
  `nom_pla` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Volcado de datos para la tabla `eventos`
--

INSERT INTO `eventos` (`id_eve`, `nom_eve`, `fec_ini`, `fec_fin`, `tip_eve`, `est_eve`, `fec_eve`, `id_eje`, `id_pla`, `nom_eje`, `nom_pla`) VALUES
(1, 'Prueba 1', '2025-07-21 00:00:00', '2025-07-24 00:00:00', 'Administrativo', 'Completado', '2025-07-21 23:44:31', 1, 1, 'Raul Callejas', 'Ecatepec'),
(2, 'Prueba 2', '2025-07-18 00:00:00', '2025-07-22 00:00:00', 'Admisiones', 'Pendiente', '2025-07-21 23:56:00', 2, 2, 'Erick Valenzuela', 'Naucalpan'),
(3, 'Prueba 3', '2025-07-31 00:00:00', '2025-08-01 00:00:00', 'Academico', 'Pendiente', '2025-07-22 00:07:33', 3, 3, 'Vanessa Martinez', 'San Luis Potosi'),
(4, 'Prueba 4', '2025-06-29 00:00:00', '2025-07-03 00:00:00', 'Administrativo', 'Completado', '2025-07-22 00:08:19', 4, 4, 'Mariana Prado', 'Queretaro'),
(5, 'Prueba 5', '2025-06-29 00:00:00', '2025-07-03 00:00:00', 'Academico', 'Pendiente', '2025-07-22 00:08:39', 4, 4, 'Mariana Prado', 'Queretaro'),
(10, 'Prueba 6', '2025-07-05 00:00:00', '2025-07-06 00:00:00', 'Admisiones', 'Completado', '2025-07-22 23:32:37', 1, 1, 'Raul Callejas', 'Ecatepec'),
(12, 'Prueba 10', '2025-08-13 00:00:00', '2025-08-15 00:00:00', 'Admisiones', 'Pendiente', '2025-07-23 00:55:13', 0, 0, 'Raul Callejas', 'Ecatepec');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `eventos`
--
ALTER TABLE `eventos`
  ADD PRIMARY KEY (`id_eve`),
  ADD KEY `fk_eje` (`id_eje`),
  ADD KEY `fk_pla` (`id_pla`),
  ADD KEY `fk_nom_eje` (`nom_eje`),
  ADD KEY `fk_nom_pla` (`nom_pla`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `eventos`
--
ALTER TABLE `eventos`
  MODIFY `id_eve` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `eventos`
--
ALTER TABLE `eventos`
  ADD CONSTRAINT `fk_nom_eje` FOREIGN KEY (`nom_eje`) REFERENCES `database_ejecutivos`.`ejecutivo` (`nom_eje`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_nom_pla` FOREIGN KEY (`nom_pla`) REFERENCES `database_plantel`.`plantel` (`nom_pla`) ON UPDATE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
