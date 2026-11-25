

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE patrimoine
CHARACTER SET utf8mb4
COLLATE utf8mb4_general_ci;

use patrimoine;

CREATE TABLE `categorie` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `categorie`
--

INSERT INTO `categorie` (`id`, `nom`, `description`) VALUES
(1, 'Monuments religieux', 'Mosquées, médersas et édifices religieux.'),
(2, 'Monuments historiques', 'Monuments témoignant des grandes périodes historiques.'),
(3, 'Sites archéologiques', 'Vestiges et sites issus des civilisations anciennes.'),
(4, 'Architecture traditionnelle', 'Bâtiments représentant le patrimoine architectural traditionnel.'),
(5, 'Monuments culturels', 'Lieux historiques et culturels importants.');

-- --------------------------------------------------------


CREATE TABLE `image` (
  `id` int(11) NOT NULL,
  `chemin` varchar(255) NOT NULL,
  `legende` varchar(200) DEFAULT NULL,
  `monument_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `image`
--

INSERT INTO `image` (`id`, `chemin`, `legende`, `monument_id`) VALUES
(10, 'images/monuments/1245e2a55bc76cec64fc0867afcf5be3.jpeg', 'Photo de présentation', 1),
(11, 'images/monuments/e025f67fda0cbea4f7024ef3044875cd.jpg', 'Mosquée Al Quaraouiyine', 1),
(12, 'images/monuments/e9903689d068922b07dd7bdc96eebfd1.jpg', 'Photo de présentation', 3),
(13, 'images/monuments/5565d1e80c40c79b6adc52761b39e21f.jpg', 'Photo de présentation', 16),
(14, 'images/monuments/12bf72315653ca93b1f1d809431c0ae7.jpg', 'Photo de présentation', 11),
(15, 'images/monuments/187e2b89bca1864d9de991f2c1eb4200.jpg', 'Photo de présentation', 15),
(16, 'images/monuments/9cf511bedc388d48f37ba1837ccb00fc.jpg', 'Photo de présentation', 14),
(17, 'images/monuments/d48a18ff5dd5f2754d6b2592156e3f99.jpg', 'Photo de présentation', 12),
(18, 'images/monuments/4d842c0c8dfc5abe193a310bce8e2950.jpg', 'Photo de présentation', 13),
(19, 'images/monuments/e92ab1195c62e3a400c4c40d4369f838.jpg', 'Photo de présentation', 10),
(20, 'images/monuments/ae89d357cfaaadd5d58823e7318d744c.jpg', 'Photo de présentation', 9),
(21, 'images/monuments/d42f41badea44514e79b00dfa07a3c74.jpg', 'Photo de présentation', 8),
(22, 'images/monuments/53f0330bdeef4e4bf601903fb303fc9d.jpg', 'Photo de présentation', 7),
(23, 'images/monuments/76d6d3f6103812c623e09b7067a71919.jpg', 'Photo de présentation', 6),
(24, 'images/monuments/164b3cba3c672aa7cabc3a2168eedec9.jpg', 'Photo de présentation', 5),
(25, 'images/monuments/37b50e39d49dec76a8d027e97a0b3727.jpg', 'Photo de présentation', 4),
(26, 'images/monuments/6d9a9a57d3a0efbb039a50798bea57d4.jpg', 'Photo de présentation', 2);

-- --------------------------------------------------------

--
-- Structure de la table `message`
--

CREATE TABLE `message` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `sujet` varchar(150) NOT NULL,
  `contenu` text NOT NULL,
  `lu` tinyint(1) NOT NULL DEFAULT 0,
  `date_envoi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `monument`
--

CREATE TABLE `monument` (
  `id` int(11) NOT NULL,
  `titre` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `categorie_id` int(11) NOT NULL,
  `ville_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `monument`
--

INSERT INTO `monument` (`id`, `titre`, `description`, `categorie_id`, `ville_id`) VALUES
(1, 'Mosquée Al Quaraouiyine', 'Fondée au IXe siècle à Fès, elle représente un monument majeur du patrimoine religieux et intellectuel marocain.', 1, 1),
(2, 'Médersa Bou Inania', 'Un chef-d œuvre de l architecture mérinide, célèbre pour ses zelliges et ses décorations sculptées.', 4, 1),
(3, 'Bab Boujloud', 'Porte monumentale emblématique de la médina de Fès, connue pour ses décorations bleues et vertes.', 2, 1),
(4, 'Mosquée Koutoubia', 'Monument emblématique de Marrakech, célèbre pour son minaret almohade.', 1, 2),
(5, 'Palais de la Bahia', 'Palais historique remarquable pour ses jardins, ses cours et son architecture traditionnelle.', 4, 2),
(6, 'Tour Hassan', 'Minaret historique de Rabat, vestige d une grande mosquée almohade.', 2, 3),
(7, 'Mausolée Mohammed V', 'Monument funéraire abritant les tombeaux de la famille royale marocaine.', 2, 3),
(8, 'Bab Mansour', 'Porte monumentale située à Meknès, remarquable par sa décoration architecturale.', 2, 4),
(9, 'Pyramides de Gizeh', 'Ensemble monumental de l Égypte antique comprenant notamment la pyramide de Khéops.', 3, 5),
(10, 'Grand Sphinx de Gizeh', 'Sculpture monumentale représentant un sphinx à tête humaine et corps de lion.', 3, 5),
(11, 'Mosquée Djingareyber', 'Ancienne mosquée de Tombouctou représentative de l architecture en terre.', 1, 6),
(12, 'Tour Eiffel', 'Monument métallique construit à Paris pour l Exposition universelle de 1889.', 2, 7),
(13, 'Alhambra', 'Ensemble palatial de Grenade témoignant de l architecture de la période nasride.', 4, 8),
(14, 'Colisée de Rome', 'Amphithéâtre antique emblématique de la civilisation romaine.', 3, 9),
(15, 'Sainte-Sophie', 'Monument historique d Istanbul ayant connu plusieurs fonctions religieuses.', 1, 10),
(16, 'Amphithéâtre d El Jem', 'Monument romain situé en Tunisie et classé au patrimoine mondial.', 3, 11);

-- --------------------------------------------------------

--
-- Structure de la table `pays`
--

CREATE TABLE `pays` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `code_iso` char(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `pays`
--

INSERT INTO `pays` (`id`, `nom`, `code_iso`) VALUES
(1, 'Maroc', 'MA'),
(2, 'Égypte', 'EG'),
(3, 'Mali', 'ML'),
(4, 'France', 'FR'),
(5, 'Espagne', 'ES'),
(6, 'Italie', 'IT'),
(7, 'Turquie', 'TR'),
(8, 'Tunisie', 'TN');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateur`
--

CREATE TABLE `utilisateur` (
  `id` int(11) NOT NULL,
  `login` varchar(50) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `role` enum('admin') NOT NULL DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `utilisateur`
--

INSERT INTO `utilisateur` (`id`, `login`, `mot_de_passe`, `role`) VALUES
(1, 'admin', '$2y$10$0nLHnXwJ2LCJWLlDQsWTOeKut5pHskRHD4SBybNOIygWmMr1cPCXK', 'admin');

-- --------------------------------------------------------

--
-- Structure de la table `ville`
--

CREATE TABLE `ville` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `pays_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `ville`
--

INSERT INTO `ville` (`id`, `nom`, `description`, `pays_id`) VALUES
(1, 'Fès', 'Ville impériale marocaine et centre historique.', 1),
(2, 'Marrakech', 'Ville historique connue pour sa médina.', 1),
(3, 'Rabat', 'Capitale du Maroc.', 1),
(4, 'Meknès', 'Ville impériale riche en patrimoine architectural.', 1),
(5, 'Le Caire', 'Capitale égyptienne au patrimoine millénaire.', 2),
(6, 'Tombouctou', 'Ancienne ville commerciale et intellectuelle.', 3),
(7, 'Paris', 'Capitale française connue pour ses monuments.', 4),
(8, 'Grenade', 'Ville espagnole marquée par l héritage andalou.', 5),
(9, 'Rome', 'Ville historique italienne.', 6),
(10, 'Istanbul', 'Ville historique située entre l Europe et l Asie.', 7),
(11, 'Tunis', 'Capitale tunisienne avec une médina historique.', 8);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `categorie`
--
ALTER TABLE `categorie`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nom` (`nom`);

--
-- Index pour la table `image`
--
ALTER TABLE `image`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_image_monument` (`monument_id`);

--
-- Index pour la table `message`
--
ALTER TABLE `message`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `monument`
--
ALTER TABLE `monument`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_monument_categorie` (`categorie_id`),
  ADD KEY `fk_monument_ville` (`ville_id`);

--
-- Index pour la table `pays`
--
ALTER TABLE `pays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nom` (`nom`),
  ADD UNIQUE KEY `code_iso` (`code_iso`);

--
-- Index pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login` (`login`);

--
-- Index pour la table `ville`
--
ALTER TABLE `ville`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ville` (`pays_id`,`nom`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `categorie`
--
ALTER TABLE `categorie`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `image`
--
ALTER TABLE `image`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT pour la table `message`
--
ALTER TABLE `message`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `monument`
--
ALTER TABLE `monument`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT pour la table `pays`
--
ALTER TABLE `pays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `ville`
--
ALTER TABLE `ville`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `image`
--
ALTER TABLE `image`
  ADD CONSTRAINT `fk_image_monument` FOREIGN KEY (`monument_id`) REFERENCES `monument` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `monument`
--
ALTER TABLE `monument`
  ADD CONSTRAINT `fk_monument_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categorie` (`id`),
  ADD CONSTRAINT `fk_monument_ville` FOREIGN KEY (`ville_id`) REFERENCES `ville` (`id`);

--
-- Contraintes pour la table `ville`
--
ALTER TABLE `ville`
  ADD CONSTRAINT `fk_ville_pays` FOREIGN KEY (`pays_id`) REFERENCES `pays` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
