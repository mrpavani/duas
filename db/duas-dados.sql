-- MySQL dump 10.13  Distrib 8.0.46, for Win64 (x86_64)
--
-- Host: localhost    Database: duas_db
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `admin_users`
--

LOCK TABLES `admin_users` WRITE;
/*!40000 ALTER TABLE `admin_users` DISABLE KEYS */;
INSERT INTO `admin_users` (`id`, `name`, `email`, `password_hash`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES (1,'Administrador Duás','admin@duasmoda.com.br','$2y$12$YRahiegitYN9Trx8o65FfuNZPvtBuDfUJVhdtIlo3kFrdZzFgCV3G',1,'2026-09-09 13:57:58','2026-09-09 14:48:34','2026-09-09 16:57:58'),(4,'Administrador Duas','admin@duasporll.com.br','$2y$12$q4DXo/6z3XdoH7QDb4UMfO989Y3HBuxS7BzmqQ2r04s9rwpystARm',1,'2026-09-29 14:31:02','2026-09-10 16:06:46','2026-09-29 17:31:02');
/*!40000 ALTER TABLE `admin_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `blog_posts`
--

LOCK TABLES `blog_posts` WRITE;
/*!40000 ALTER TABLE `blog_posts` DISABLE KEYS */;
/*!40000 ALTER TABLE `blog_posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `cart_items`
--

LOCK TABLES `cart_items` WRITE;
/*!40000 ALTER TABLE `cart_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `cart_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` (`id`, `name`, `sort_order`, `created_at`) VALUES (1,'Blusas',0,'2026-10-05 18:31:02'),(2,'Calças',0,'2026-10-05 18:31:02'),(3,'Conjuntos',0,'2026-10-05 18:31:02'),(4,'Regatas',0,'2026-10-05 18:31:02'),(5,'Saias',0,'2026-10-05 18:31:02'),(6,'Tops',0,'2026-10-05 18:31:02'),(7,'Vestidos',0,'2026-10-05 18:31:02');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `contact_messages`
--

LOCK TABLES `contact_messages` WRITE;
/*!40000 ALTER TABLE `contact_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `free_shipping_rules`
--

LOCK TABLES `free_shipping_rules` WRITE;
/*!40000 ALTER TABLE `free_shipping_rules` DISABLE KEYS */;
INSERT INTO `free_shipping_rules` (`id`, `label`, `min_subtotal`, `starts_at`, `ends_at`, `is_active`, `created_at`, `updated_at`) VALUES (1,'Frete grátis padrão',800.00,NULL,NULL,1,'2026-09-09 15:03:43','2026-09-09 15:03:43');
/*!40000 ALTER TABLE `free_shipping_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `newsletter_subscribers`
--

LOCK TABLES `newsletter_subscribers` WRITE;
/*!40000 ALTER TABLE `newsletter_subscribers` DISABLE KEYS */;
/*!40000 ALTER TABLE `newsletter_subscribers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `order_events`
--

LOCK TABLES `order_events` WRITE;
/*!40000 ALTER TABLE `order_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `payment_methods`
--

LOCK TABLES `payment_methods` WRITE;
/*!40000 ALTER TABLE `payment_methods` DISABLE KEYS */;
INSERT INTO `payment_methods` (`id`, `provider`, `label`, `environment`, `credentials`, `instructions`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (1,'mercado_pago','Mercado Pago','sandbox','{\"public_key\": \"TEST-2af2fea7-c36a-481a-a34f-9628ef07aad3\", \"access_token\": \"TEST-4289836793001885-090914-1ff8d047d192bde6d0b8815294a8ae96-93940985\"}','Cartão de crédito, Pix e boleto processados via Mercado Pago.',1,0,'2026-09-09 14:48:34','2026-09-10 20:06:17');
/*!40000 ALTER TABLE `payment_methods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `product_images`
--

LOCK TABLES `product_images` WRITE;
/*!40000 ALTER TABLE `product_images` DISABLE KEYS */;
INSERT INTO `product_images` (`id`, `product_id`, `image_url`, `position`) VALUES (1,1,'uploads/products/image1.JPG',0),(2,2,'uploads/products/image2.JPG',0),(3,3,'uploads/products/image3.JPG',0),(4,4,'uploads/products/image4.JPG',0),(5,5,'uploads/products/image5.JPG',0),(6,6,'uploads/products/image6.JPG',0),(7,7,'uploads/products/image7.JPG',0),(8,8,'uploads/products/image8.JPG',0),(9,9,'uploads/products/image9.JPG',0),(10,10,'uploads/products/image10.JPG',0),(11,11,'uploads/products/image11.jpg',0),(12,12,'uploads/products/image12.JPG',0),(13,13,'uploads/products/image13.JPG',0),(14,14,'uploads/products/image14.JPG',0),(15,15,'uploads/products/image15.JPG',0),(16,16,'uploads/products/image16.JPG',0),(17,17,'uploads/products/image17.JPG',0),(18,18,'uploads/products/image18.JPG',0),(19,19,'uploads/products/image19.JPG',0),(20,20,'uploads/products/image20.JPG',0),(21,21,'uploads/products/image21.jpg',0),(22,22,'uploads/products/image22.JPG',0);
/*!40000 ALTER TABLE `product_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `product_sizes`
--

LOCK TABLES `product_sizes` WRITE;
/*!40000 ALTER TABLE `product_sizes` DISABLE KEYS */;
INSERT INTO `product_sizes` (`id`, `product_id`, `size`, `stock`, `position`) VALUES (1,1,'PP',2,0),(2,1,'P',1,1),(3,2,'PP',1,0),(4,2,'P',1,1),(5,2,'M',2,2),(6,2,'G',1,3),(7,3,'P',3,0),(8,4,'PP',3,0),(9,4,'P',1,1),(10,4,'M',2,2),(11,5,'PP',1,0),(12,5,'P',2,1),(13,6,'PP',2,0),(14,6,'P',1,1),(15,6,'M',2,2),(16,7,'PP',2,0),(17,7,'G',1,1),(18,8,'PP',1,0),(19,8,'M',1,1),(20,9,'P',2,0),(21,9,'M',2,1),(22,10,'P',2,0),(23,11,'M',1,0),(24,12,'PP',1,0),(25,12,'P',1,1),(26,13,'P',1,0),(27,13,'M',1,1),(28,14,'PP',2,0),(29,15,'PP',2,0),(30,15,'P',2,1),(31,15,'M',2,2),(32,16,'P',2,0),(33,16,'M',2,1),(34,17,'PP',2,0),(35,18,'PP',1,0),(36,18,'M',2,1),(37,19,'PP',1,0),(38,19,'P',2,1),(39,19,'M',2,2),(40,20,'PP',1,0),(41,20,'P',2,1),(42,21,'PP',2,0),(43,21,'P',2,1),(44,22,'P',2,0);
/*!40000 ALTER TABLE `product_sizes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` (`id`, `name`, `slug`, `category`, `description`, `composition`, `care_instructions`, `instagram_url`, `measurements`, `price`, `sale_price`, `sale_starts_at`, `sale_ends_at`, `is_new_release`, `is_active`, `created_at`) VALUES (1,'Conjunto 10pm','conjunto-10pm','Conjuntos','O Conjunto 10pm é confeccionado em tecido vinil, trazendo um acabamento brilhante e um visual moderno. Composto por jaqueta cropped e short de cintura alta, o conjunto valoriza a silhueta com uma modelagem sofisticada e cheia de personalidade. A jaqueta possui gola alta, mangas longas, ombros estruturados e fechamento frontal por zíper, enquanto o short oferece um caimento confortável e elegante. Perfeito para festas, eventos e produções fashionistas, é uma peça versátil para quem busca um look marcante e contemporâneo.','Tecido vinil com acabamento brilhante.','Lavar à mão em água fria. Não torcer. Secar à sombra.',NULL,'{\"note\": \"A modelo veste PP. Cor: Azul Claro.\", \"rows\": {\"G\": {\"busto\": \"94 cm\", \"cintura\": \"83 cm\", \"quadril\": \"106 cm\", \"comprimento\": \"\"}, \"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"100 cm\", \"comprimento\": \"\"}, \"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"\"}}}',1399.90,NULL,NULL,NULL,1,1,'2026-09-29 18:41:54'),(2,'Vestido Martini','vestido-martini','Vestidos','O Vestido Martini é curto, em paetê grande com efeito perolado num tom off white que reflete a luz a cada movimento. O contraste é o que encanta: gola alta e frente comportada dão lugar a costas totalmente nuas, num decote profundo que revela na medida certa. Peça-desejo para as ocasiões que pedem protagonismo — réveillon, aniversários, festas e noites em que você quer ser a atração. Deixe o vestido falar por si: finalize com sandália de tira fina e brincos discretos para o brilho do paetê ser a estrela.','Paetê perolado de alto brilho com forro confortável.','Limpeza especializada a seco ou lavagem manual suave.',NULL,'{\"note\": \"A modelo veste PP. Cor: Bege / Off White.\", \"rows\": {\"G\": {\"busto\": \"94 cm\", \"cintura\": \"83 cm\", \"quadril\": \"106 cm\", \"comprimento\": \"42\"}, \"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"100 cm\", \"comprimento\": \"40\"}, \"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"38\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"36\"}}}',649.90,NULL,NULL,NULL,1,1,'2026-09-29 18:41:54'),(3,'Vestido Champagne','vestido-champagne','Vestidos','O Vestido Champagne é longo, em malha lurex metalizada num tom marrom profundo que reflete a luz com sofisticação. O decote cowl drapeado cai fluido sobre o colo, as alças finas frente-única deixam as costas em evidência, e a fenda na barra libera o movimento uma peça que veste como líquido. Feito para as noites em que você quer elegância com atitude: jantares, festas e eventos que pedem um look de impacto sem esforço. Finalize com sandália de tira fina e acessórios minimalistas para o brilho do tecido ser protagonista.','Malha lurex metalizada com caimento líquido.','Para preservar a beleza da peça, vista e guarde conforme recebida. Manter amarrado original sem desfazê-lo. Evitar contato com anéis e superfícies ásperas.',NULL,'{\"note\": \"A modelo veste P. Cor: Bege / Marrom Metalizado.\", \"rows\": {\"G\": {\"busto\": \"94 cm\", \"cintura\": \"83 cm\", \"quadril\": \"106 cm\", \"comprimento\": \"42\"}, \"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"100 cm\", \"comprimento\": \"40\"}, \"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"38\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"36\"}}}',1349.90,NULL,NULL,NULL,1,1,'2026-09-29 18:41:54'),(4,'Vestido Merlot','vestido-merlot','Vestidos','Modelo curto, tomara que caia, confeccionado em crepe musseline leve e com caimento impecável. Possui camadas de babados que criam movimento e volume na peça, deixando o visual moderno e super elegante.','Crepe musseline fluido e leve.','Lavar à mão ou a seco profissionalmente. Secar na horizontal à sombra.',NULL,'{\"note\": \"A modelo veste PP. Cor: Vermelho.\", \"rows\": {\"G\": {\"busto\": \"94 cm\", \"cintura\": \"83 cm\", \"quadril\": \"106 cm\", \"comprimento\": \"\"}, \"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"100 cm\", \"comprimento\": \"\"}, \"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"\"}}}',749.90,NULL,NULL,NULL,1,1,'2026-09-29 18:41:54'),(5,'Vestido Amour - Vermelho','vestido-amour-vermelho','Vestidos','Vestido curto tomara que caia com modelagem moderna e elegante na cor vermelho. Confeccionado em tecido estruturado, possui caimento impecável que valoriza a silhueta com sofisticação. O design minimalista e ajustado ao corpo traz um visual versátil e atemporal, perfeito para composições elegantes em festas, eventos e ocasiões especiais.','Tecido estruturado nobre encorpado.','Lavar à mão em temperatura ambiente. Não alvejar.',NULL,'{\"note\": \"A modelo veste PP. Cor: Vermelho.\", \"rows\": {\"G\": {\"busto\": \"94 cm\", \"cintura\": \"83 cm\", \"quadril\": \"106 cm\", \"comprimento\": \"\"}, \"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"100 cm\", \"comprimento\": \"\"}, \"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"\"}}}',399.90,NULL,NULL,NULL,1,1,'2026-09-29 18:41:54'),(6,'Vestido Amour - Preto','vestido-amour-preto','Vestidos','Vestido curto tomara que caia com modelagem moderna e elegante na cor preto. Confeccionado em tecido estruturado, possui caimento impecável que valoriza a silhueta com sofisticação. O design minimalista e ajustado ao corpo traz um visual versátil e atemporal, perfeito para composições elegantes em festas, eventos e ocasiões especiais.','Tecido estruturado nobre encorpado.','Lavar à mão em temperatura ambiente. Não alvejar.',NULL,'{\"note\": \"A modelo veste PP. Cor: Preto.\", \"rows\": {\"G\": {\"busto\": \"94 cm\", \"cintura\": \"83 cm\", \"quadril\": \"106 cm\", \"comprimento\": \"\"}, \"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"100 cm\", \"comprimento\": \"\"}, \"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"\"}}}',399.90,NULL,NULL,NULL,1,1,'2026-09-29 18:41:54'),(7,'Conjunto Moonlight','conjunto-moonlight','Conjuntos','Conjunto em alfaiataria, moderno e sofisticado, ideal para produções fashionistas com elegância minimalista. O cropped de gola alta possui modelagem estruturada e mangas curtas, trazendo um toque contemporâneo e refinado. O comprimento mais curto valoriza a silhueta e cria um contraste equilibrado com a parte inferior. A mini saia em alfaiataria apresenta corte preciso e caimento impecável, com detalhe de faixa alongada lateral que adiciona movimento e personalidade ao look, elevando a proposta clássica com um design atual. Confeccionado em tecido de alfaiataria encorpado, o conjunto une estrutura, conforto e acabamento elegante.','Alfaiataria encorpada premium com forro macio.','Lavagem delicada à mão ou a seco profissional.',NULL,'{\"note\": \"A modelo veste PP. Cor: Branco.\", \"rows\": {\"G\": {\"busto\": \"94 cm\", \"cintura\": \"83 cm\", \"quadril\": \"106 cm\", \"comprimento\": \"\"}, \"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"100 cm\", \"comprimento\": \"\"}, \"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"\"}}}',749.90,NULL,NULL,NULL,1,1,'2026-09-29 18:41:54'),(8,'Vestido Noir','vestido-noir','Vestidos','Confeccionado em tecido crepe musseline fluido de alta qualidade, o Noir combina sensualidade sofisticada com elegância atemporal. O bustier estruturado com cut out frontal cria um recorte estratégico que valoriza o decote com ousadia calculada, enquanto as alças finas adicionam delicadeza e leveza ao visual. A modelagem sereia abraça cada curva do corpo com precisão, culminando em uma cauda generosa que transforma qualquer passagem em um momento cinematográfico. Do tapete vermelho à formatura, do baile de gala ao casamento — o Noir foi criado para ocasiões que merecem ser eternas.','Crepe musseline fluido de alta gramatura.','Apenas lavagem especializada a seco.',NULL,'{\"note\": \"A modelo veste PP. Cor: Preta.\", \"rows\": {\"G\": {\"busto\": \"94 cm\", \"cintura\": \"83 cm\", \"quadril\": \"106 cm\", \"comprimento\": \"\"}, \"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"100 cm\", \"comprimento\": \"\"}, \"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"\"}}}',649.90,NULL,NULL,NULL,1,1,'2026-09-29 18:41:54'),(9,'Calça Sunrise','calca-sunrise','Calças','Calça Sunrise em tom off white com modelagem sofisticada e corte impecável. Desenvolvida para valorizar a silhueta com leveza e movimento, proporcionando conforto e elegância tanto para o cotidiano quanto para ocasiões especiais.','Tecido creponado encorpado com caimento pesado.','Lavar à mão ou em ciclo suave. Secar à sombra.',NULL,'{\"note\": \"A modelo veste P. Cor: Off White.\", \"rows\": {\"M\": {\"busto\": \"\", \"cintura\": \"79 cm\", \"quadril\": \"100 cm\", \"comprimento\": \"\"}, \"P\": {\"busto\": \"\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"\"}}}',224.90,NULL,NULL,NULL,1,1,'2026-09-29 18:41:54'),(10,'Top Sunset - Amarelo Claro','top-sunset-amarelo-claro','Tops','Top Sunset na cor amarelo claro com modelagem minimalista e caimento confortável. Uma peça charmosa e fresca para combinações solares e descontraídas.','Algodão com elastano de toque suave.','Lavar com cores similares em água fria.',NULL,'{\"note\": \"A modelo veste P. Cor: Amarelo Claro.\", \"rows\": {\"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"\", \"comprimento\": \"\"}}}',49.90,NULL,NULL,NULL,1,1,'2026-09-29 18:41:54'),(11,'Top Sunset - Marrom','top-sunset-marrom','Tops','Top Sunset na cor marrom com corte moderno e ajuste ao corpo. Uma peça básica sofisticada que transita perfeitamente do dia para a noite.','Algodão com elastano de toque macio.','Lavar com cores similares em água fria.',NULL,'{\"note\": \"A modelo veste M. Cor: Marrom.\", \"rows\": {\"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"\", \"comprimento\": \"\"}}}',49.90,NULL,NULL,NULL,1,1,'2026-09-29 18:41:54'),(12,'Regata Lumi - Preta','regata-lumi-preta','Regatas','Regata Lumi em preto essencial com decote sutil e acabamento refinado. Perfeita para sobreposições contemporâneas com blazers ou saias fluidas.','Toque de seda com caimento fluido.','Lavar à mão com sabão neutro.',NULL,'{\"note\": \"A modelo veste PP. Cor: Preta.\", \"rows\": {\"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"\", \"comprimento\": \"\"}}}',149.90,NULL,NULL,NULL,1,1,'2026-09-29 18:41:54'),(13,'Regata Lumi - Branca','regata-lumi-branca','Regatas','Regata Lumi em branco puro com visual limpo e toque suave. Essencial em qualquer armário inteligente para compor looks elegantes e leves.','Toque de seda com caimento fluido.','Lavar à mão com sabão neutro.',NULL,'{\"note\": \"A modelo veste PP. Cor: Branca.\", \"rows\": {\"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"\", \"comprimento\": \"\"}, \"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"\", \"comprimento\": \"\"}}}',149.90,NULL,NULL,NULL,1,1,'2026-09-29 18:41:54'),(14,'Saia Midnight - Branca','saia-midnight-branca','Saias','Saia Midnight na cor branca com acabamento delicado e corte fluido. Proporciona movimento elegante e harmonia em composições monocromáticas.','Tecido creponado leve com forro.','Lavar delicadamente. Secar à sombra.',NULL,'{\"note\": \"A modelo veste PP. Cor: Branca.\", \"rows\": {\"PP\": {\"busto\": \"\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"\"}}}',229.90,NULL,NULL,NULL,1,1,'2026-09-29 18:41:54'),(15,'Saia Midnight - Preta','saia-midnight-preta','Saias','Saia Midnight na cor preta clássica com caimento envolvente e acabamento impecável. Uma peça versátil e atemporal indispensável para diversas ocasiões.','Tecido creponado leve com forro.','Lavar delicadamente. Secar à sombra.',NULL,'{\"note\": \"A modelo veste PP. Cor: Preta.\", \"rows\": {\"M\": {\"busto\": \"\", \"cintura\": \"79 cm\", \"quadril\": \"100 cm\", \"comprimento\": \"\"}, \"P\": {\"busto\": \"\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"\"}}}',229.90,NULL,NULL,NULL,1,1,'2026-09-29 18:41:54'),(16,'Blusa Clair - Off White','blusa-clair-off-white','Blusas','Blusa Clair em tom off white sofisticado, com modelagem requintada e linhas elegantes. Transmite personalidade e refinamento em todas as produções.','Tecido nobre de toque macio e caimento fluido.','Lavar à mão ou a seco.',NULL,'{\"note\": \"A modelo veste PP e P. Cor: Off White.\", \"rows\": {\"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"\", \"comprimento\": \"\"}, \"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"\", \"comprimento\": \"\"}}}',359.90,NULL,NULL,NULL,1,1,'2026-09-29 18:41:54'),(17,'Blusa Clair - Marrom','blusa-clair-marrom','Blusas','Blusa Clair na cor marrom profunda com modelagem sofisticada e acabamento de alta alfaiataria. Elegância incomparável com toque aconchegante.','Tecido nobre de toque macio e caimento fluido.','Lavar à mão ou a seco.',NULL,'{\"note\": \"A modelo veste PP e P. Cor: Marrom.\", \"rows\": {\"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"\", \"comprimento\": \"\"}}}',359.90,NULL,NULL,NULL,1,1,'2026-09-29 18:41:54'),(18,'Blusa Aurora - Off White','blusa-aurora-off-white','Blusas','Blusa Aurora em tom off white com modelagem confortável e gola elegante. Uma peça contemporânea pensada para enriquecer qualquer produção.','Fibras nobres respiráveis com elastano.','Lavar delicadamente em água fria.',NULL,'{\"note\": \"A modelo veste PP. Cor: Off White.\", \"rows\": {\"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"\", \"comprimento\": \"\"}}}',179.90,NULL,NULL,NULL,1,1,'2026-09-29 18:41:54'),(19,'Blusa Aurora - Verde','blusa-aurora-verde','Blusas','Blusa Aurora em verde refinado com corte preciso e ajuste harmônico. Perfeita para quem valoriza elegância contemporânea com um ponto de cor exclusivo.','Fibras nobres respiráveis com elastano.','Lavar delicadamente em água fria.',NULL,'{\"note\": \"A modelo veste PP. Cor: Verde.\", \"rows\": {\"M\": {\"busto\": \"90 cm\", \"cintura\": \"79 cm\", \"quadril\": \"\", \"comprimento\": \"\"}, \"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"\", \"comprimento\": \"\"}}}',179.90,NULL,NULL,NULL,1,1,'2026-09-29 18:41:54'),(20,'Vestido Latte','vestido-latte','Vestidos','Vestido Latte na marcante tonalidade marrom, com linhas fluidas e silhueta feminina bem marcada. Peça autoral sofisticada perfeita para ocasiões especiais.','Tecido acetinado nobre e encorpado.','Limpeza especializada a seco.',NULL,'{\"note\": \"A modelo veste PP. Cor: Marrom.\", \"rows\": {\"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"\"}}}',669.90,NULL,NULL,NULL,1,1,'2026-09-29 18:41:54'),(21,'Vestido Bonjour','vestido-bonjour','Vestidos','Vestido Bonjour em tom off white estonteante com modelagem de alta costura e acabamento impecável. Criado para momentos inesquecíveis com presença inigualável.','Tecido acetinado estruturado com forro nobre.','Limpeza a seco profissional recomendada.',NULL,'{\"note\": \"A modelo veste PP. Cor: Off White.\", \"rows\": {\"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"94 cm\", \"comprimento\": \"\"}, \"PP\": {\"busto\": \"82 cm\", \"cintura\": \"70 cm\", \"quadril\": \"90 cm\", \"comprimento\": \"\"}}}',979.90,NULL,NULL,NULL,1,1,'2026-09-29 18:41:54'),(22,'Top Dusk','top-dusk','Tops','Top Dusk em renda preta com desenho delicado e caimento primoroso. Versátil para compor desde propostas refinadas até produções marcantes de fim de noite.','Renda delicada macia com elastano.','Lavagem manual suave em água fria.',NULL,'{\"note\": \"A modelo veste P. Cor: Preto de renda.\", \"rows\": {\"P\": {\"busto\": \"86 cm\", \"cintura\": \"74 cm\", \"quadril\": \"\", \"comprimento\": \"\"}}}',59.90,NULL,NULL,NULL,1,1,'2026-09-29 18:41:54');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `promotions`
--

LOCK TABLES `promotions` WRITE;
/*!40000 ALTER TABLE `promotions` DISABLE KEYS */;
INSERT INTO `promotions` (`id`, `name`, `code`, `discount_type`, `discount_value`, `min_subtotal`, `starts_at`, `ends_at`, `usage_limit`, `used_count`, `is_active`, `created_at`, `updated_at`) VALUES (1,'Cupom de boas-vindas','DUAS10','percent',10.00,0.00,NULL,NULL,NULL,0,1,'2026-09-09 15:03:43','2026-09-09 15:03:43');
/*!40000 ALTER TABLE `promotions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `shipping_methods`
--

LOCK TABLES `shipping_methods` WRITE;
/*!40000 ALTER TABLE `shipping_methods` DISABLE KEYS */;
INSERT INTO `shipping_methods` (`id`, `carrier`, `label`, `service_code`, `settings`, `flat_rate`, `free_above`, `estimated_days_min`, `estimated_days_max`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (1,'correios','Correios PAC','03298','{\"origin_cep\": \"01310100\", \"contract_code\": \"\", \"contract_password\": \"\"}',NULL,800.00,5,9,1,0,'2026-09-09 14:48:34','2026-09-09 14:48:34'),(2,'correios','Correios SEDEX','03220','{\"origin_cep\": \"01310100\", \"contract_code\": \"\", \"contract_password\": \"\"}',24.90,NULL,2,4,1,1,'2026-09-09 14:48:34','2026-09-09 14:48:34');
/*!40000 ALTER TABLE `shipping_methods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `site_settings`
--

LOCK TABLES `site_settings` WRITE;
/*!40000 ALTER TABLE `site_settings` DISABLE KEYS */;
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('announcement_active','1','2026-09-09 16:45:34'),('announcement_text','COLEÇÃO PRIMAVERA/VERÃO • FRETE GRÁTIS EM COMPRAS ACIMA DE R$ 800,00 • ATÉ 6X SEM JUROS','2026-09-10 15:57:58');
/*!40000 ALTER TABLE `site_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `sizes`
--

LOCK TABLES `sizes` WRITE;
/*!40000 ALTER TABLE `sizes` DISABLE KEYS */;
INSERT INTO `sizes` (`id`, `code`, `name`, `category`, `busto_hint`, `cintura_hint`, `quadril_hint`, `comprimento_hint`, `is_active`, `sort_order`, `created_at`) VALUES (1,'PP','Extra Pequeno (34)','letra','80-84','62-66','90-94','110',1,10,'2026-09-29 17:10:40'),(2,'P','Pequeno (36/38)','letra','84-88','66-70','94-98','112',1,20,'2026-09-29 17:10:40'),(3,'M','Médio (40)','letra','90-94','72-76','100-104','113',1,30,'2026-09-29 17:10:40'),(4,'G','Grande (42)','letra','96-100','78-82','106-110','114',1,40,'2026-09-29 17:10:40'),(5,'GG','Extra Grande (44)','letra','102-106','84-88','112-116','115',1,50,'2026-09-29 17:10:40'),(6,'Extra G','Extra Grande Especial (46)','letra','108-112','90-94','118-122','116',1,60,'2026-09-29 17:10:40'),(7,'G1','Plus Size 48 (G1)','letra','114-118','96-100','124-128','117',0,70,'2026-09-29 17:10:40'),(8,'G2','Plus Size 50 (G2)','letra','120-124','102-106','130-134','118',0,80,'2026-09-29 17:10:40'),(9,'G3','Plus Size 52 (G3)','letra','126-130','108-112','136-140','119',0,90,'2026-09-29 17:10:40'),(10,'Único','Tamanho Único (U)','letra','86-96','68-78','96-106','113',0,100,'2026-09-29 17:10:40'),(11,'34','Tamanho 34 (PP)','numero','80-84','62-66','90-94','110',0,110,'2026-09-29 17:10:40'),(12,'36','Tamanho 36 (P)','numero','84-88','66-70','94-98','111',0,120,'2026-09-29 17:10:40'),(13,'38','Tamanho 38 (P/M)','numero','88-92','70-74','98-102','112',0,130,'2026-09-29 17:10:40'),(14,'40','Tamanho 40 (M)','numero','92-96','74-78','102-106','113',0,140,'2026-09-29 17:10:40'),(15,'42','Tamanho 42 (G)','numero','96-100','78-82','106-110','114',0,150,'2026-09-29 17:10:40'),(16,'44','Tamanho 44 (GG)','numero','102-106','84-88','112-116','115',0,160,'2026-09-29 17:10:40'),(17,'46','Tamanho 46 (Extra G)','numero','108-112','90-94','118-122','116',0,170,'2026-09-29 17:10:40'),(18,'48','Tamanho 48 (Plus)','numero','114-118','96-100','124-128','117',0,180,'2026-09-29 17:10:40'),(19,'50','Tamanho 50 (Plus)','numero','120-124','102-106','130-134','118',0,190,'2026-09-29 17:10:40'),(20,'52','Tamanho 52 (Plus)','numero','126-130','108-112','136-140','119',0,200,'2026-09-29 17:10:40');
/*!40000 ALTER TABLE `sizes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `stock_notifications`
--

LOCK TABLES `stock_notifications` WRITE;
/*!40000 ALTER TABLE `stock_notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `stock_notifications` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-06 10:50:55
