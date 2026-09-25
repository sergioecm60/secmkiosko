-- ==================================================
-- Kiosco — respaldo de la base de datos
-- Generado: 25/09/2026 17:41:06
-- Base: kiosco (8.3.33 / MySQL 8.4.3)
-- Para restaurar: mysql -u root kiosco < este_archivo.sql
-- ==================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------
-- Tabla: config
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `config`;
CREATE TABLE `config` (
  `clave` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `config` VALUES
('direccion',''),
('factura','no'),
('folio','1'),
('logo','K'),
('moneda','$'),
('negocio','Mi Kiosco'),
('pie','¡Gracias por su compra!'),
('pin',''),
('telefono',''),
('tema','claro');

-- ---------------------------------------------------------
-- Tabla: medios_pago
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `medios_pago`;
CREATE TABLE `medios_pago` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icono` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exige_referencia` tinyint(1) NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `orden` smallint NOT NULL DEFAULT '0',
  `es_efectivo` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `ix_activo` (`activo`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `medios_pago` VALUES
('1','Efectivo','💵','0','1','1','1'),
('2','Tarjeta','💳','1','1','2','0'),
('3','Transferencia','📱','1','1','3','0'),
('4','Mercado Pago','🅿️','1','1','4','0');

-- ---------------------------------------------------------
-- Tabla: movimientos
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `movimientos`;
CREATE TABLE `movimientos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `producto_id` int unsigned DEFAULT NULL,
  `producto_nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` decimal(12,2) NOT NULL DEFAULT '0.00',
  `stock_anterior` decimal(12,2) NOT NULL DEFAULT '0.00',
  `stock_actual` decimal(12,2) NOT NULL DEFAULT '0.00',
  `referencia` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proveedor_id` int unsigned DEFAULT NULL,
  `documento` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_fecha` (`fecha`),
  KEY `ix_producto` (`producto_id`),
  KEY `ix_tipo` (`tipo`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `movimientos` VALUES
('9','2026-09-25 13:53:31','sistema',NULL,'Mantenimiento','0.00','0.00','0.00','Borrado: kardex (0 movimientos previos)','Cajero',NULL,NULL),
('10','2026-09-25 13:53:44','entrada','20','Jabón de platos 500 ml','102.00','-91.00','11.00','Edición de producto','Cajero',NULL,NULL),
('11','2026-09-25 17:08:30','venta','1','Agua mineral 600 ml','-1.00','48.00','47.00','Venta folio 1','Cajero',NULL,NULL),
('12','2026-09-25 17:08:30','venta','4','Cerveza lata 355 ml','-3.00','24.00','21.00','Venta folio 1','Cajero',NULL,NULL),
('13','2026-09-25 17:08:30','venta','5','Leche entera 1 L','-1.00','20.00','19.00','Venta folio 1','Cajero',NULL,NULL),
('14','2026-09-25 17:14:28','entrada','1','Agua mineral 600 ml','24.00','47.00','71.00','Compra semanal','Cajero','1','REM-2026-0451'),
('15','2026-09-25 17:14:51','sistema',NULL,'Mantenimiento','0.00','0.00','0.00','Borrado: productos (6 movimientos previos)','Cajero',NULL,NULL),
('16','2026-09-25 17:14:51','sistema',NULL,'Mantenimiento','0.00','0.00','0.00','Borrado: ventas (7 movimientos previos)','Cajero',NULL,NULL),
('17','2026-09-25 17:14:52','alta',NULL,'Carga inicial de productos','0.00','0.00','0.00','instalacion',NULL,NULL,NULL),
('18','2026-09-25 17:15:05','venta','25','Agua mineral 600 ml','-6.00','48.00','42.00','Venta folio 1','Cajero',NULL,NULL),
('19','2026-09-25 17:15:05','venta','28','Cerveza lata 355 ml','-3.00','24.00','21.00','Venta folio 1','Cajero',NULL,NULL),
('20','2026-09-25 17:18:54','entrada','38','Aceite vegetal 1 L','10.00','12.00','22.00','Reposición mensual','Cajero','1','REM-2026-0999');

-- ---------------------------------------------------------
-- Tabla: productos
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `productos`;
CREATE TABLE `productos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `categoria` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT '0.00',
  `stock` decimal(12,2) NOT NULL DEFAULT '0.00',
  `minimo` decimal(12,2) NOT NULL DEFAULT '0.00',
  `unidad` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pieza',
  `foto` mediumtext COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `costo` decimal(10,2) NOT NULL DEFAULT '0.00',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `proveedor_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_codigo` (`codigo`),
  KEY `ix_categoria` (`categoria`),
  KEY `ix_nombre` (`nombre`),
  KEY `ix_activo` (`activo`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `productos` VALUES
('25','Agua mineral 600 ml','7501234567890','Bebidas','12.00','42.00','12.00','botella',NULL,'1','2026-09-25 17:14:52','7.10',NULL,NULL),
('26','Gaseosa de cola 600 ml','7501234567891','Bebidas','18.00','36.00','12.00','botella',NULL,'1','2026-09-25 17:14:52','10.80',NULL,NULL),
('27','Jugo de naranja 1 L','7501234567892','Bebidas','26.00','18.00','6.00','botella',NULL,'1','2026-09-25 17:14:52','16.40',NULL,NULL),
('28','Cerveza lata 355 ml','7501234567893','Bebidas','28.00','21.00','6.00','lata',NULL,'1','2026-09-25 17:14:52','18.90',NULL,NULL),
('29','Leche entera 1 L','7501234567894','Lácteos','24.00','20.00','8.00','botella',NULL,'1','2026-09-25 17:14:52','15.20',NULL,NULL),
('30','Yogur natural 500 g','7501234567895','Lácteos','28.00','14.00','6.00','pieza',NULL,'1','2026-09-25 17:14:52','17.60',NULL,NULL),
('31','Queso fresco 200 g','7501234567896','Lácteos','46.00','9.00','4.00','pieza',NULL,'1','2026-09-25 17:14:52','30.20',NULL,NULL),
('32','Huevos blancos (kg)','7501234567897','Abarrotes','48.00','15.00','5.00','kg',NULL,'1','2026-09-25 17:14:52','33.50',NULL,NULL),
('33','Pan de molde','7501234567898','Panadería','34.00','11.00','5.00','pieza',NULL,'1','2026-09-25 17:14:52','20.10',NULL,NULL),
('34','Galletas de avena x12','7501234567899','Botanas','38.00','22.00','8.00','paquete',NULL,'1','2026-09-25 17:14:52','24.70',NULL,NULL),
('35','Chocolate en barra','7501234567900','Botanas','16.00','40.00','12.00','pieza',NULL,'1','2026-09-25 17:14:52','9.40',NULL,NULL),
('36','Café soluble 250 g','7501234567901','Despensa','78.00','8.00','4.00','paquete',NULL,'1','2026-09-25 17:14:52','55.30',NULL,NULL),
('37','Cereal de maíz 500 g','7501234567902','Despensa','52.00','10.00','4.00','paquete',NULL,'1','2026-09-25 17:14:52','36.80',NULL,NULL),
('38','Aceite vegetal 1 L','7501234567903','Despensa','44.00','22.00','6.00','botella',NULL,'1','2026-09-25 17:14:52','29.50',NULL,NULL),
('39','Arroz grano largo 1 kg','7501234567904','Despensa','32.00','25.00','10.00','paquete',NULL,'1','2026-09-25 17:14:52','22.40',NULL,NULL),
('40','Poroto negro 500 g','7501234567905','Despensa','28.00','18.00','8.00','paquete',NULL,'1','2026-09-25 17:14:52','19.60',NULL,NULL),
('41','Azúcar 1 kg','7501234567906','Despensa','30.00','20.00','8.00','paquete',NULL,'1','2026-09-25 17:14:52','21.10',NULL,NULL),
('42','Fideos spaghetti 500 g','7501234567907','Despensa','26.00','16.00','8.00','paquete',NULL,'1','2026-09-25 17:14:52','17.90',NULL,NULL),
('43','Detergente líquido 1 L','7501234567908','Limpieza','68.00','7.00','4.00','botella',NULL,'1','2026-09-25 17:14:52','50.40',NULL,NULL),
('44','Jabón de platos 500 ml','7501234567909','Limpieza','36.00','11.00','5.00','botella',NULL,'1','2026-09-25 17:14:52','24.20',NULL,NULL),
('45','Papel higiénico x4','7501234567910','Higiene','58.00','14.00','6.00','paquete',NULL,'1','2026-09-25 17:14:52','42.30',NULL,NULL),
('46','Servilletas x100','7501234567911','Higiene','22.00','20.00','8.00','paquete',NULL,'1','2026-09-25 17:14:52','14.10',NULL,NULL),
('47','Bolsas para basura x20','7501234567912','Higiene','28.00','13.00','6.00','paquete',NULL,'1','2026-09-25 17:14:52','18.90',NULL,NULL),
('48','Pilas alcalinas x4','7501234567913','Varios','45.00','6.00','4.00','paquete',NULL,'1','2026-09-25 17:14:52','31.80',NULL,NULL);

-- ---------------------------------------------------------
-- Tabla: proveedores
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `proveedores`;
CREATE TABLE `proveedores` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `proveedores` VALUES
('1','Distribuidora del Sur','11 5555-1234','ventas@dsur.com','Entrega los martes','1','2026-09-25 17:14:15');

-- ---------------------------------------------------------
-- Tabla: venta_items
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `venta_items`;
CREATE TABLE `venta_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `venta_id` int unsigned NOT NULL,
  `producto_id` int unsigned DEFAULT NULL,
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cantidad` decimal(12,2) NOT NULL DEFAULT '0.00',
  `importe` decimal(12,2) NOT NULL DEFAULT '0.00',
  `costo_unitario` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `ix_venta` (`venta_id`),
  KEY `ix_producto` (`producto_id`),
  CONSTRAINT `fk_item_venta` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `venta_items` VALUES
('7','4','25','Agua mineral 600 ml','7501234567890','12.00','6.00','72.00','7.10'),
('8','4','28','Cerveza lata 355 ml','7501234567893','28.00','3.00','84.00','18.90');

-- ---------------------------------------------------------
-- Tabla: ventas
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `ventas`;
CREATE TABLE `ventas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `folio` int unsigned NOT NULL DEFAULT '1',
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `descuento` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `metodo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Efectivo',
  `recibido` decimal(12,2) DEFAULT NULL,
  `vuelto` decimal(12,2) DEFAULT NULL,
  `referencia` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nota` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `anulada` tinyint(1) NOT NULL DEFAULT '0',
  `anulada_en` datetime DEFAULT NULL,
  `motivo` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proveedor_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_folio` (`folio`),
  KEY `ix_fecha` (`fecha`),
  KEY `ix_anulada` (`anulada`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ventas` VALUES
('4','1','2026-09-25 17:15:05','156.00','0.00','156.00','Efectivo','200.00','44.00',NULL,NULL,'Cajero','0',NULL,NULL,NULL);

SET FOREIGN_KEY_CHECKS = 1;
-- Total de registros respaldados: 54
