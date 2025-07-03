-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 03, 2025 at 01:28 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cms_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`) VALUES
(1, 'Sagar', 'Sagar8169@');

-- --------------------------------------------------------

--
-- Table structure for table `authors`
--

CREATE TABLE `authors` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `theme_preference` enum('light','dark') DEFAULT 'light',
  `password` varchar(255) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `role` varchar(20) DEFAULT 'author'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `authors`
--

INSERT INTO `authors` (`id`, `name`, `username`, `email`, `bio`, `photo`, `created_at`, `theme_preference`, `password`, `profile_picture`, `last_login`, `role`) VALUES
(1, 'Sagar', 'admin123', 'sagarmiishra8169@gmail.com', 'sagar author', NULL, '2025-07-01 17:10:11', 'light', '$2y$10$mox8EDwc8rkKzoAJGOhrbOHtc2dGjDfy2IxCB2.vc2.ALX.JbBizG', NULL, NULL, 'admin'),
(11, 'Prakash', 'Prakash8169', 'prakash@gmail.com', NULL, '1751486247_320a65a3-81d0-4a16-9b5d-56c217581776.png', '2025-07-02 19:33:43', 'light', '$2y$10$QMVN/GYy5vPLE2uE130bk.bmJUos0LtTzL.nBVYoBAoz8K9pvxKmy', NULL, NULL, 'author');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `created_at`, `description`) VALUES
(7, 'technology', NULL, '2025-07-02 19:54:57', ''),
(10, 'entertainment', 'entertainment', '2025-07-02 22:47:46', '');

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `author_name` varchar(255) NOT NULL,
  `author_email` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `status` enum('pending','approved') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`id`, `post_id`, `author_name`, `author_email`, `content`, `status`, `created_at`) VALUES
(12, 7, 'kamlesh', 'kamlesh@gmail.com', 'good article', 'approved', '2025-07-03 03:41:48'),
(15, 7, 'Prakash', 'prakash@gmail.com', 'badhiya hai', 'approved', '2025-07-03 15:37:09');

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `content` text DEFAULT NULL,
  `status` enum('draft','published') NOT NULL DEFAULT 'draft',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `author_id` int(11) DEFAULT 1,
  `excerpt` text DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `page_order` int(11) DEFAULT 0,
  `show_in_menu` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pages`
--

INSERT INTO `pages` (`id`, `title`, `slug`, `created_at`, `content`, `status`, `updated_at`, `author_id`, `excerpt`, `meta_description`, `page_order`, `show_in_menu`) VALUES
(3, 'About Us', 'about-us', '2025-07-01 14:27:38', '<p>We are a <strong>real estate tech platform</strong> helping users buy, sell, and rent properties easily. Our goal is to simplify property searches with reliable listings and modern technology. Whether you\'re a buyer, seller, or agent, <i>we’re here to support your journey</i>.</p>', 'published', '2025-07-03 15:24:48', 1, '', '', 1, 1),
(4, 'Contact Us', 'contact-us', '2025-07-01 14:28:11', '<p>If you have any questions or need support, feel free to contact us. You can reach us by email at support@yourwebsite.com or call us at +91-9876543210. We\'re happy to help.</p>', 'published', '2025-07-03 15:24:48', 1, '', '', 2, 1),
(5, 'Privacy Policy', 'privacy-policy', '2025-07-01 14:28:52', '<p>We respect your privacy. Any personal data you provide (like name or email) is safe with us. We never sell or share your details. We may use cookies to improve your experience on our site.</p>', 'published', '2025-07-03 15:24:48', 1, '', '', 3, 1),
(6, 'Disclaimer', 'disclaimer', '2025-07-01 14:29:06', '<p>All information on this website is provided for general purposes. We try to keep everything accurate, but we cannot guarantee it. Please verify property details directly with the seller or agent.</p>', 'published', '2025-07-03 02:44:17', 1, '', '', 4, 1),
(7, 'DMCA', 'dmca', '2025-07-01 14:29:19', '<p>If you believe your copyrighted content has been used on our site without permission, please email us at dmca@yourwebsite.com. We will review your request and take action if necessary.</p>', 'published', '2025-07-03 02:44:37', 1, '', '', 5, 1),
(8, 'Terms & Conditions', 'terms-conditions', '2025-07-01 14:29:35', '<p>By using our website, you agree to follow our rules. You must not misuse the platform. We have the right to suspend or remove users who break the terms. Always follow laws when using the site.</p>', 'published', '2025-07-03 02:44:45', 1, '', '', 6, 1);

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `tags` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('published','draft') DEFAULT 'published',
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `author_id` int(11) DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `excerpt` text DEFAULT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  `view_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `title`, `slug`, `category`, `tags`, `image`, `status`, `content`, `created_at`, `author_id`, `views`, `excerpt`, `meta_description`, `updated_at`, `category_id`, `view_date`) VALUES
(1, 'Welcome to my blog', 'welcome-to-my-blog', NULL, NULL, '1751370989_product5.jpg', 'published', 'This is the first post in my custom PHP CMS.', '2025-07-01 11:06:00', 1, 0, NULL, NULL, '2025-07-02 15:04:41', NULL, NULL),
(2, 'India\'s Sigachi factory fire death toll rises to 39; cause still unknown', 'india-s-sigachi-factory-fire-death-toll-rises-to-39-cause-still-unknown', NULL, NULL, '1751370966_product55.png', 'published', '\"I came out (of the plant) to use the restroom and heard a loud blast. It sounded like a bomb blast. I came out and saw fire. A part of the fire also spread towards me. I jumped the wall and escaped,\" Chandan Gound, 32, who has been working at the factory for six months, told Reuters by phone.\r\n\"Many of them (those inside) managed to escape, but a large number were trapped and could not come out,\" Gound added.\r\nSigachi, which makes microcrystalline cellulose (MCC), caters to clients in the pharma, food, cosmetic and specialty chemicals sectors in countries ranging from the U.S. to Australia.\r\nMCC\'s compressibility, binding properties, and ability to boost drug release make it a vital ingredient in pharmaceutical manufacturing. It is also used to prevent the formation of lumps in food products, to maintain texture of cosmetic products, and as a fat substitute in low-calorie foods.', '2025-07-01 11:06:50', 1, 0, NULL, NULL, '2025-07-02 15:04:41', NULL, NULL),
(3, 'Indian Navy receives last foreign-built warship INS Tamal:Armed with BrahMos missiles; will keep Karachi port under watch', 'indian-navy-receives-last-foreign-built-warship-ins-tamal-armed-with-brahmos-missiles-will-keep-karachi-port-under-watch', NULL, NULL, '1751370954_product2.jpg', 'published', 'A new warship – INS Tamal – will be inducted into the Indian Navy today in Kaliningrad, Russia. Named after Indra’s sword, the frigate carries BrahMos cruise missiles with a 450 km range, underwater torpedoes, and a gun system that fires 5,000 rounds per minute. Weighing around 4,000 tonnes, the stealth ship is undetectable by enemy radar.\r\n\r\nWhat makes the Navy’s newest warrior INS Tamal special, how it strengthens India’s grip on Pakistan, and why it’s called the last foreign-built warship – all are explained below.\r\n\r\nWhat is INS Tamal?\r\n\r\nINS Tamal is a modern stealth frigate – a warship designed to evade radar detection. It was built at the Yantar Shipyard with inputs from Indian Navy experts and Russia’s Severnoye Design Bureau. Though foreign-built, 26% of its components are Indian.\r\n\r\nThe vessel will be formally inducted into the Navy on 1 July at a ceremony in Kaliningrad, Russia. Over 250 Indian sailors of the commissioning crew arrived in St Petersburg in February 2025. The ship underwent three months of trials to test its weapons, systems, and sensors.', '2025-07-01 11:31:04', 1, 1, NULL, NULL, '2025-07-03 14:22:02', NULL, NULL),
(4, 'How did Shubhanshu Shukla spend 5 days on ISS?:Why did he carry tomato-brinjal seeds to space; what are the 7 experiments he’s conducting?', 'how-did-shubhanshu-shukla-spend-5-days-on-iss-why-did-he-carry-tomato-brinjal-seeds-to-space-what-are-the-7-experiments-he-s-conducting', NULL, NULL, '1751370946_product55.png', 'published', 'At the age of 42, actor Shefali Jariwala died suddenly from a suspected cardiac arrest. Police found signs of self-medication, and reports suggest she had been taking anti-ageing pills and glutathione injections to improve her skin.\r\n\r\nOn the day of her death, Shefali was also fasting, which may have triggered a bad reaction in her body.\r\n\r\nSome experts believe she may have suffered an allergic shock that caused her heart to stop.\r\n\r\nDeputy Commissioner of Police (Zone 9), Dixit Gedam, said,', '2025-07-01 11:31:56', 1, 1, NULL, NULL, '2025-07-03 12:18:30', NULL, NULL),
(5, 'Police deployed, then how was meat found near temple\':Locals deny violence; CM Himanta alleges plot to seize Dhubri', 'police-deployed-then-how-was-meat-found-near-temple-locals-deny-violence-cm-himanta-alleges-plot-to-seize-dhubri', 'entertainment', '', '1751370937_product55.png', 'published', '<p>After meat was found near a temple on Eid, tension rose in Dhubri, Assam. The incident repeated the next day despite police presence, leaving locals fearful. Many avoid going out, and children are scared to attend school. Violence began on Bakrid, 7 June, after an alleged beef was found near a Hanuman temple. Residents say there were no riots, only rumours. Assam CM Himanta Biswa Sarma blamed a ‘new beef mafia’ and the group ‘Nabin Bangla’ for inciting unrest with posters about merging Dhubri with Bangladesh. He visited the area twice and ordered shoot-at-sight action.</p>', '2025-07-01 11:32:32', 11, 49, '', '', '2025-07-03 12:47:15', NULL, NULL),
(6, 'Hasan Ali’s $8 billion scandal alleges involvement of 3 ex-CMs:From scrap dealer to scandal magnet; was he the boss or just a small crook?', 'hasan-ali-s-8-billion-scandal-alleges-involvement-of-3-ex-cms-from-scrap-dealer-to-scandal-magnet-was-he-the-boss-or-just-a-small-crook', NULL, NULL, '1751370926_vegbiryani.png', 'published', 'Hasan Ali Khan began his life as a small-time businessman in Hyderabad. He started off as a scrap dealer and later ran a car rental service.\r\n\r\nIn the 1980s and 90s, he became involved in horse racing, earning the nickname “Hyderabad Ka Ghodewala”.\r\n\r\nDespite his modest profile, Ali was allegedly involved in several fraud cases, including cheating banks and conning people with fake promises of foreign currency deals.\r\n\r\nIn 2000, he shifted to Pune. This move marked the beginning of what would become one of India’s most dramatic financial scandals.', '2025-07-01 11:33:05', 1, 0, NULL, NULL, '2025-07-02 15:04:41', NULL, NULL),
(7, 'India’s Space Ambitions Reach New Heights', 'india-s-space-ambitions-reach-new-heights', 'entertainment', 'dummy,News Tag', '1751467328_rd.jpg', 'published', '<p>&nbsp;</p><h2>India\'s Space Ambitions Reach New Heights: A New Era of Exploration and Innovation</h2><p>India\'s space program, spearheaded by the Indian Space Research Organisation (ISRO), is rapidly ascending to new frontiers, showcasing a blend of indigenous innovation, cost-effectiveness, and a growing emphasis on international collaboration and private sector participation.</p><p>1 Once a nascent player, India is now a formidable force in the global space arena, pushing the boundaries of exploration and leveraging space technology for national development and commercial gain.</p><p>2.Recent Triumphs: Solidifying India\'s Space Prowess</p><p>The past few years have been marked by significant milestones that underscore India\'s expanding capabilities:</p><ul><li><strong>Chandrayaan-3\'s Lunar South Pole Landing (2023):</strong> This historic achievement made India the fourth nation to soft-land on the Moon and the first to do so near the unexplored lunar south pole.3 The mission demonstrated India\'s precision landing capabilities and gathered crucial data, paving the way for future lunar endeavors.</li><li><p>4. <strong>Aditya-L1 Solar Mission (2024):</strong> India\'s first dedicated solar observatory successfully reached its halo orbit around the Sun-Earth Lagrangian Point 1 (L1), providing an uninterrupted view of the Sun.</p><p>5. This mission places India in an elite group of nations studying solar phenomena, crucial for understanding space weather and its impact on Earth.</p><p>6. <strong>Space Docking Experiment (SpaDeX) (2024):</strong> ISRO successfully conducted its first space docking experiment, a critical technology for future space station construction and orbital servicing.7 This achievement places India among a handful of countries capable of independent space docking.8</p></li><li><strong>Reusable Launch Vehicle (RLV) Tests (2024):</strong> Multiple successful RLV Landing Experiments (RLV-LEX-02 and RLV-LEX-03) demonstrated India\'s commitment to developing reusable rocket technology.9 This advancement promises to significantly reduce launch costs, making space more accessible for a wider range of missions.10</li><li><strong>XPoSat Mission (2024):</strong> The X-ray Polarimeter Satellite (XPoSat) launch in early 2024 positioned India as the second country globally to have a dedicated observatory studying astronomical phenomena like black holes and neutron stars.11</li><li><strong>INSAT-3DS Weather Satellite (2024):</strong> This successful launch enhanced India\'s weather forecasting, environmental monitoring, and disaster relief capabilities, showcasing ISRO\'s continued focus on practical applications of space technology.12</li><li><p><strong>Indian Astronaut on ISS (2025):</strong> Group Captain Shubhanshu Shukla\'s participation in the Axiom-04 mission to the International Space Station (ISS) marks a crucial step in India\'s human spaceflight program.13 This collaboration provides invaluable training and operational experience, directly benefiting the upcoming Gaganyaan missions.</p><p>&nbsp;</p></li></ul><h3>Ambitious Future Missions: Charting a Course for Deeper Space and Human Presence</h3><p>India\'s ambition extends far beyond Earth\'s orbit, with a packed roadmap of groundbreaking missions:</p><ul><li><strong>Gaganyaan Program (First uncrewed test flight expected 2025, crewed mission by 2026):</strong> This flagship human spaceflight program aims to send Indian astronauts into Low Earth Orbit, making India the fourth nation to independently achieve human spaceflight.14 The ongoing test flights are rigorously validating crucial systems for crew safety.</li><li><strong>NISAR (NASA-ISRO Synthetic Aperture Radar) (2025):15</strong> A collaborative Earth observation mission with NASA, NISAR will provide high-resolution imagery for monitoring natural disasters, mapping the Earth\'s surface, and studying environmental changes with unprecedented detail.16</li><li><strong>Venus Orbiter Mission (Shukrayaan) (Expected 2025):</strong> India\'s first mission to Venus will study the planet\'s atmosphere and surface, contributing to our understanding of Venusian science and the evolution of planetary climates.</li><li><strong>Mars Orbiter Mission 2 (Mangalyaan-2) (Expected 2026):</strong> Building on the success of Mangalyaan-1, this mission will further explore Mars\' atmosphere, surface, and potential for past life with more sophisticated instruments.17</li><li><strong>Lunar Polar Exploration Mission (Expected 2026-2029):</strong> In collaboration with JAXA (Japan Aerospace Exploration Agency), this mission will explore the Moon\'s South Pole, focusing on water ice and lunar soil composition, with implications for future lunar settlements.18</li><li><strong>Chandrayaan-4 (Expected 2027):</strong> Following the success of its predecessors, Chandrayaan-4 is planned as a lunar sample-return mission, aiming to bring back lunar samples to Earth for in-depth analysis.19</li><li><strong>Bharatiya Antariksha Station (Indian Space Station) (Planned by 2035):</strong> India has ambitious plans to construct its own modular space station in Low Earth Orbit, marking a significant step towards sustained human presence in space.20</li></ul><h3>The Rise of India\'s Private Space Sector: A Catalyst for Growth</h3><p>A transformative shift in India\'s space landscape is the burgeoning private sector. Propelled by the Indian National Space Promotion and Authorization Centre (IN-SPACe) and the New Space Policy (2023), private players are increasingly contributing to the nation\'s space endeavors.21</p><p><strong>Innovation and Capacity Building:</strong> Companies like Skyroot Aerospace and Agnikul Cosmos are developing indigenous launch vehicles and advanced payloads, fostering innovation and reducing reliance on imports.22 Skyroot\'s Vikram-S in 2022 was a landmark as India\'s first private rocket launch.23</p><ul><li><strong>Commercialization of Space:</strong> NewSpace India Limited (NSIL), ISRO\'s commercial arm, is actively engaging with private firms for satellite launches and transponder leasing, opening up avenues for monetizing ISRO\'s technologies.24</li><li><strong>Increased Investment and Employment:</strong> The Indian space economy, valued at approximately $8.4 billion in 2024, is projected to reach $13 billion by 2025 and even $50 billion by 2028, driven by increased private participation. This growth is also generating significant direct and indirect employment opportunities.</li><li><strong>Global Collaboration:</strong> Indian private companies are increasingly seeking partnerships with international space agencies and corporations, facilitating knowledge sharing and expanding their global reach.25</li></ul><h3>Challenges and Opportunities</h3><p>Despite its impressive trajectory, India\'s space program faces challenges:</p><ul><li><strong>Funding and Investment:</strong> While private investment is growing, space projects require substantial capital and long incubation periods.26 Attracting sustained funding remains crucial.</li><li><strong>Regulatory Framework:</strong> The absence of a dedicated space law to govern private sector operations can lead to complexities. Streamlining regulations is vital for fostering a more agile and competitive environment.</li><li><strong>Skilled Workforce:</strong> While India boasts a large pool of engineers, nurturing specialized skills in advanced space technologies, particularly in the private sector, is essential.27</li><li><strong>Global Competition:</strong> As more nations and private entities enter the space race, India must continue to innovate and maintain its competitive edge in terms of cost-effectiveness and technological advancements.</li></ul><p>However, these challenges are dwarfed by the immense opportunities:</p><ul><li><strong>Economic Growth:</strong> The space sector\'s expansion offers significant economic benefits through manufacturing, service provision, and job creation.28</li><li><strong>Strategic Autonomy:</strong> Developing indigenous capabilities in launch vehicles, satellite manufacturing, and human spaceflight ensures India\'s strategic independence in accessing and utilizing space.29</li><li><strong>Scientific Discovery:</strong> India\'s planetary and deep-space missions are contributing invaluable data to global scientific understanding, expanding humanity\'s knowledge of the cosmos.30</li><li><strong>Societal Impact:</strong> Space applications in communication, navigation (NavIC), disaster management, weather forecasting, and remote sensing are directly benefiting millions of lives across India.31</li><li><strong>International Collaboration:</strong> India\'s proven capabilities make it an attractive partner for global space ventures, fostering scientific diplomacy and shared exploration goals.</li></ul><p>&nbsp;</p><h3>Conclusion</h3><p>India\'s space ambitions have undeniably reached new heights. With a history of remarkable achievements, an aggressive roadmap for future missions, and a rapidly expanding private sector, India is poised to play an even more significant role in shaping the future of space exploration. The nation\'s journey into the cosmos is not merely a testament to technological prowess but also a powerful symbol of its growing global influence and its commitment to harnessing the power of space for the betterment of humanity. As India continues to push the boundaries of what\'s possible, its star in the firmament of global space powers shines ever brighter.</p>', '2025-07-01 11:35:12', 11, 76, '', '', '2025-07-03 16:55:30', NULL, NULL),
(9, 'Top 10 Budget-Friendly Hill Stations to Visit This Summer', 'top-10-budget-friendly-hill-stations-to-visit-this-summer', 'entertainment', '', '1751383425_MANALI.jpg', 'published', '<p>As summer approaches, Indian travelers are searching for affordable and cool destinations. From Dharamshala in Himachal to Yercaud in Tamil Nadu, we list the top 10 scenic hill stations that won’t break your budget but promise unforgettable experiences.</p>', '2025-07-01 11:35:12', 1, 29, '', '', '2025-07-03 16:54:39', NULL, NULL),
(10, 'Electric Vehicles in India: Are We Ready?', 'electric-vehicles-in-india-are-we-ready', 'News Category', 'News Tag', '1751370802_daaltarkha.png', 'published', '<p>The EV wave has hit India, but are we really ready? While manufacturers are pushing electric scooters and cars, infrastructure challenges such as insufficient charging stations, range anxiety, and high prices remain key barriers. Here’s a deep dive into where India stands.</p>', '2025-07-01 11:35:12', 1, 5, '', '', '2025-07-03 16:55:03', NULL, NULL),
(11, 'The Rise of Regional OTT Platforms in India', 'the-rise-of-regional-ott-platforms-in-india', 'technology', '', '1751370811_f.jpg', 'published', '<p>OTT platforms in regional languages are booming. Platforms like Hoichoi (Bengali), Aha (Telugu), and Koode (Malayalam) are gaining massive popularity, offering original web series and films that connect with local audiences. We explore how they’re changing the entertainment landscape.</p>', '2025-07-01 11:35:12', 1, 3, '', '', '2025-07-03 16:46:01', NULL, NULL),
(12, 'NEP 2020: How Will It Change Indian Education?', 'nep-2020-how-will-it-change-indian-education', NULL, NULL, '1751370825_karaichicken.png', 'published', 'The New Education Policy 2020 promises a paradigm shift in the Indian education system. From a 5+3+3+4 model to skill-based learning and regional language instruction, the policy aims to make education holistic and flexible. But how practical are these reforms? We analyze.', '2025-07-01 11:35:12', 1, 4, NULL, NULL, '2025-07-03 16:44:16', NULL, NULL),
(13, 'Top Indian Startups to Watch in 2025', 'top-indian-startups-to-watch-in-2025', NULL, NULL, '1751370832_product9.jpg', 'published', 'India is now the third-largest startup ecosystem in the world. From fintech to edtech, startups like Zepto, Cred, and Skyroot are making global headlines. Here’s a curated list of the top 10 Indian startups to watch in 2025 based on funding, innovation, and impact.', '2025-07-01 11:35:12', 1, 0, NULL, NULL, '2025-07-02 15:04:41', NULL, NULL),
(14, '5G Rollout in India: What You Need to Know', '5g-rollout-in-india-what-you-need-to-know', NULL, NULL, '1751370846_1.webp', 'published', '5G technology is being rolled out in phases across major Indian cities. With faster speeds, low latency, and massive connectivity, 5G promises a new digital era. But what are the health, privacy, and economic implications of this shift? We explain in detail.', '2025-07-01 11:35:12', 1, 0, NULL, NULL, '2025-07-02 15:04:41', NULL, NULL),
(15, 'Yoga and Mental Health: A Scientific Perspective', 'yoga-and-mental-health-a-scientific-perspective', NULL, NULL, '1751370853_product60.png', 'published', 'Yoga is not just a physical exercise—it’s a mental and emotional wellness practice. Recent studies in Indian medical institutions have shown that regular yoga can reduce stress, anxiety, and even symptoms of depression. Here’s how to start your yoga journey for better mental health.', '2025-07-01 11:35:12', 1, 34, NULL, NULL, '2025-07-03 16:41:59', NULL, NULL),
(16, 'India’s Smart Cities: Dream or Reality?', 'india-s-smart-cities-dream-or-reality', NULL, NULL, '1751370916_Vohi Creation.png', 'published', 'Smart cities were launched to modernize urban infrastructure, traffic, water, and waste management. A decade later, some cities like Pune and Ahmedabad have shown progress, while others lag behind. We investigate whether India’s smart city dream is turning into reality.', '2025-07-01 11:35:12', 1, 0, NULL, NULL, '2025-07-02 15:04:41', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE `tags` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tags`
--

INSERT INTO `tags` (`id`, `name`, `slug`, `description`, `created_at`) VALUES
(2, 'News Tag', 'news-tag', '', '2025-07-01 20:28:44'),
(7, 'dummy', NULL, NULL, '2025-07-03 04:06:02');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `authors`
--
ALTER TABLE `authors`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `author_id` (`author_id`);

--
-- Indexes for table `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `authors`
--
ALTER TABLE `authors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `authors` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
