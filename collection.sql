-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Vært: db
-- Genereringstid: 31. 10 2025 kl. 09:44:23
-- Serverversion: 8.0.43
-- PHP-version: 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `collection`
--

-- --------------------------------------------------------

--
-- Struktur-dump for tabellen `cards`
--

CREATE TABLE `cards` (
  `id` int NOT NULL,
  `name` varchar(50) NOT NULL,
  `power` int DEFAULT NULL,
  `defense` int DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `abilities` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Data dump for tabellen `cards`
--

INSERT INTO `cards` (`id`, `name`, `power`, `defense`, `description`, `abilities`) VALUES
(7, 'All-Out Assault', 0, 0, 'When this enchantment enters, if its your main phase, there is an additional combat phase after this phase followed by an additional main phase. When you next attack this turn, untap each creature you control.', '[\"Creatures you control get +1/+1 and have deathtouch.\"]'),
(8, 'Chittering Witch', 2, 2, 'When this creature enters, create a number of 1/1 black Rat crature tokens equal ot the number of opponents you have.', '[\"{1}{B}, Sacrifice a creature: Target creature get -2/-2 until end of turn.\"]'),
(9, 'Supernatural Stamina', 0, 0, 'Until end of turn, target crature gets +2/+0 and gains \"When this creature dies, return it to the the battefield tapped under its owner\'s control.\"', NULL),
(11, 'Oracle of Bones', 3, 1, 'When Oracle of Bones enters the battlefield, if tribute wasn\'t paid, you may cast an instant or sorcery from you hand without paying it\'s mana cost.', '[\"Haste\", \"Tribute 2\"]'),
(13, 'Mountain', NULL, NULL, NULL, NULL),
(14, 'Shattered Landscape', NULL, NULL, NULL, '[\"{T}: Add {1}\", \"{T}, Sacrifice this land: Search your library for a basic Mountain, Plains, or Swamp card, put it onto the battlefield tapped, then shuffle.\", \"Cycling {R}{W}{B} ({R}{W}{B}, Discard this card: Draw a card.)\"]'),
(15, 'Abrade', NULL, NULL, 'choose one -', '[\"Abrade deals 3 damage to target creature.\", \"Destroy target artifact.\"]'),
(16, 'Goldlust Triad', 4, 3, 'Whenever this creature dels combat damage to a player, create a Treasure token.', '[\"Flying\", \"Myriad\"]'),
(17, 'Sonic Shrieker', 4, 4, 'When this creature enters, it deals 2 damage to any target and you gain 2 life. If a player is dealt damage this way, they discard a card.', '[\"Flying\"]'),
(18, 'Comet Crawler', 2, 3, 'Whever this creature attacks, you may sacrifice another creature or artifact. If you do, this creature gets +2/+0 until end of turn.', '[\"Lifelink\"]');

-- --------------------------------------------------------

--
-- Struktur-dump for tabellen `card_mana`
--

CREATE TABLE `card_mana` (
  `card_id` int NOT NULL,
  `mana_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Data dump for tabellen `card_mana`
--

INSERT INTO `card_mana` (`card_id`, `mana_id`) VALUES
(7, 6),
(7, 6),
(7, 5),
(7, 2),
(7, 1),
(8, 6),
(8, 6),
(8, 6),
(8, 5),
(8, 2),
(8, 1),
(9, 1),
(11, 5),
(11, 5),
(11, 6),
(11, 6),
(15, 6),
(15, 5),
(16, 6),
(16, 6),
(16, 6),
(16, 6),
(16, 5),
(17, 6),
(17, 6),
(17, 5),
(17, 2),
(17, 1),
(18, 6),
(18, 6),
(18, 1);

-- --------------------------------------------------------

--
-- Struktur-dump for tabellen `card_type`
--

CREATE TABLE `card_type` (
  `card_id` int NOT NULL,
  `type_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Data dump for tabellen `card_type`
--

INSERT INTO `card_type` (`card_id`, `type_id`) VALUES
(8, 1),
(11, 1),
(16, 1),
(17, 1),
(18, 1),
(7, 2),
(9, 4),
(15, 4),
(13, 7),
(14, 7);

-- --------------------------------------------------------

--
-- Struktur-dump for tabellen `mana`
--

CREATE TABLE `mana` (
  `id` int NOT NULL,
  `color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Data dump for tabellen `mana`
--

INSERT INTO `mana` (`id`, `color`) VALUES
(1, 'black'),
(2, 'white'),
(3, 'green'),
(4, 'blue'),
(5, 'red'),
(6, 'colorless');

-- --------------------------------------------------------

--
-- Struktur-dump for tabellen `type`
--

CREATE TABLE `type` (
  `id` int NOT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Data dump for tabellen `type`
--

INSERT INTO `type` (`id`, `type`) VALUES
(1, 'creature'),
(2, 'enchantment'),
(3, 'sorcery'),
(4, 'instant'),
(5, 'planeswalker'),
(6, 'artifact'),
(7, 'land');

--
-- Begrænsninger for dumpede tabeller
--

--
-- Indeks for tabel `cards`
--
ALTER TABLE `cards`
  ADD PRIMARY KEY (`id`);

--
-- Indeks for tabel `card_mana`
--
ALTER TABLE `card_mana`
  ADD KEY `mana_id` (`mana_id`),
  ADD KEY `card_mana_ibfk_1` (`card_id`);

--
-- Indeks for tabel `card_type`
--
ALTER TABLE `card_type`
  ADD UNIQUE KEY `uniq_card_type` (`card_id`,`type_id`),
  ADD KEY `type_id` (`type_id`);

--
-- Indeks for tabel `mana`
--
ALTER TABLE `mana`
  ADD PRIMARY KEY (`id`);

--
-- Indeks for tabel `type`
--
ALTER TABLE `type`
  ADD PRIMARY KEY (`id`);

--
-- Brug ikke AUTO_INCREMENT for slettede tabeller
--

--
-- Tilføj AUTO_INCREMENT i tabel `cards`
--
ALTER TABLE `cards`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Tilføj AUTO_INCREMENT i tabel `mana`
--
ALTER TABLE `mana`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Tilføj AUTO_INCREMENT i tabel `type`
--
ALTER TABLE `type`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Begrænsninger for dumpede tabeller
--

--
-- Begrænsninger for tabel `card_mana`
--
ALTER TABLE `card_mana`
  ADD CONSTRAINT `card_mana_ibfk_1` FOREIGN KEY (`card_id`) REFERENCES `cards` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  ADD CONSTRAINT `card_mana_ibfk_2` FOREIGN KEY (`mana_id`) REFERENCES `mana` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Begrænsninger for tabel `card_type`
--
ALTER TABLE `card_type`
  ADD CONSTRAINT `card_type_ibfk_1` FOREIGN KEY (`card_id`) REFERENCES `cards` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  ADD CONSTRAINT `card_type_ibfk_2` FOREIGN KEY (`type_id`) REFERENCES `type` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
