INSERT INTO `ea_service_categories`(`id`, `name`, `description`) VALUES 
  (1,'Stora Huset','Rum i stora huset'),
  (2,'Privat','Rum i de privata husen'),
  (3,'Hela gården','Boka hela gården');

UPDATE `ea_services` SET 
  `id_service_categories`=1
WHERE Id > 8 AND Id < 19;

UPDATE `ea_services` SET 
  `id_service_categories`=2
WHERE Id < 9;

UPDATE `ea_services` SET 
  `id_service_categories`=3
WHERE Id > 18