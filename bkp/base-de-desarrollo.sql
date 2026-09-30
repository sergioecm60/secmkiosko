-- ==================================================
-- Kiosco â€” respaldo de la base de datos
-- Generado: 30/09/2026 16:14:56
-- Base: kiosco (8.3.33 / MySQL 8.4.3)
-- Para restaurar: mysql -u root kiosco < este_archivo.sql
-- ==================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------
-- Tabla: caja_cierre_metodos
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `caja_cierre_metodos`;
CREATE TABLE `caja_cierre_metodos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `caja_id` int unsigned NOT NULL,
  `medio_pago_id` smallint unsigned NOT NULL,
  `esperado` decimal(12,2) NOT NULL DEFAULT '0.00',
  `declarado` decimal(12,2) NOT NULL DEFAULT '0.00',
  `diferencia` decimal(12,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `ix_caja` (`caja_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (sin registros)

-- ---------------------------------------------------------
-- Tabla: cajas
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `cajas`;
CREATE TABLE `cajas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int unsigned NOT NULL,
  `abierta_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `monto_inicial` decimal(12,2) NOT NULL DEFAULT '0.00',
  `cerrada_en` datetime DEFAULT NULL,
  `efectivo_esperado` decimal(12,2) DEFAULT NULL,
  `efectivo_declarado` decimal(12,2) DEFAULT NULL,
  `total_esperado` decimal(12,2) DEFAULT NULL,
  `total_declarado` decimal(12,2) DEFAULT NULL,
  `diferencia` decimal(12,2) DEFAULT NULL,
  `motivo` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `abierta` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `ix_usuario` (`usuario_id`),
  KEY `ix_abierta` (`abierta`),
  KEY `ix_abierta_en` (`abierta_en`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (sin registros)

-- ---------------------------------------------------------
-- Tabla: categorias
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `categorias`;
CREATE TABLE `categorias` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `orden` smallint NOT NULL DEFAULT '0',
  `cocina` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nombre` (`nombre`),
  KEY `ix_activo` (`activo`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categorias` VALUES
('1','Almacen','1','0','0'),
('5','Venta libre','1','0','0'),
('25','Comidas y Tragos','1','0','1');

-- ---------------------------------------------------------
-- Tabla: comanda_atajos
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `comanda_atajos`;
CREATE TABLE `comanda_atajos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `seccion` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Comidas',
  `etiqueta` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `producto_id` int unsigned DEFAULT NULL,
  `formato_unidad` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `texto` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detalle` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden` smallint NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `ix_activo` (`activo`),
  KEY `ix_orden` (`orden`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `comanda_atajos` VALUES
('1','Comidas','1 hamburguesa',NULL,NULL,'Hamburguesa completa','con lechuga, tomate y queso','1','1'),
('2','Comidas','1 pancho',NULL,NULL,'Pancho','hamburguesa completa','2','1'),
('3','Comidas','1 miga',NULL,NULL,'SÃ¡ndwich de miga',NULL,'3','1'),
('4','Comidas','1 milanesa',NULL,NULL,'SÃ¡ndwich de milanesa',NULL,'4','1'),
('5','Comidas','1 pollo',NULL,NULL,'Pollo con papas',NULL,'5','1'),
('6','Bebidas','1 cerveza',NULL,NULL,'Cerveza',NULL,'1','1'),
('7','Bebidas','1 coc 1L',NULL,NULL,'Coca Cola','botella de 1 litro','2','1'),
('8','Bebidas','1 coc 1.5',NULL,NULL,'Coca Cola','botella de litro y medio','3','1'),
('9','Bebidas','1 coc 2.25',NULL,NULL,'Coca Cola','botella de 2 litros y cuarto','4','1'),
('10','Tragos','1 vino',NULL,NULL,'Vaso de vino',NULL,'1','1'),
('11','Tragos','1 gin',NULL,NULL,'Gin',NULL,'2','1'),
('12','Tragos','1 pina',NULL,NULL,'PiÃ±a colada',NULL,'3','1'),
('13','Tragos','1 gancia',NULL,NULL,'Gancia fernet',NULL,'4','1'),
('14','Tragos','1 mesclado',NULL,NULL,'Trago mesclado',NULL,'5','1');

-- ---------------------------------------------------------
-- Tabla: comanda_items
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `comanda_items`;
CREATE TABLE `comanda_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `comanda_id` int unsigned NOT NULL,
  `producto_id` int unsigned DEFAULT NULL,
  `cantidad` decimal(12,2) NOT NULL DEFAULT '1.00',
  `texto` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detalle` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `es_producto` tinyint(1) NOT NULL DEFAULT '0',
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `creado` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_comanda` (`comanda_id`),
  CONSTRAINT `fk_ci_comanda` FOREIGN KEY (`comanda_id`) REFERENCES `comandas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (sin registros)

-- ---------------------------------------------------------
-- Tabla: comandas
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `comandas`;
CREATE TABLE `comandas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `venta_id` int unsigned DEFAULT NULL,
  `folio` int unsigned DEFAULT NULL,
  `cliente` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zona_id` smallint unsigned DEFAULT NULL,
  `zona_nombre` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'delivery',
  `lugar` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notas` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `envio` decimal(12,2) NOT NULL DEFAULT '0.00',
  `usuario` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cerrado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_venta` (`venta_id`),
  KEY `ix_estado` (`estado`),
  KEY `ix_creado` (`creado`),
  KEY `ix_cliente` (`cliente`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (sin registros)

-- ---------------------------------------------------------
-- Tabla: config
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `config`;
CREATE TABLE `config` (
  `clave` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `config` VALUES
('direccion',''),
('esquema_version','10'),
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
-- Tabla: contadores
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `contadores`;
CREATE TABLE `contadores` (
  `nombre` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `contadores` VALUES
('folio','0');

-- ---------------------------------------------------------
-- Tabla: medios_pago
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `medios_pago`;
CREATE TABLE `medios_pago` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `icono` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `tipo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `producto_id` int unsigned DEFAULT NULL,
  `producto_nombre` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` decimal(12,2) NOT NULL DEFAULT '0.00',
  `stock_anterior` decimal(12,2) NOT NULL DEFAULT '0.00',
  `stock_actual` decimal(12,2) NOT NULL DEFAULT '0.00',
  `referencia` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proveedor_id` int unsigned DEFAULT NULL,
  `documento` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nota` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_fecha` (`fecha`),
  KEY `ix_producto` (`producto_id`),
  KEY `ix_tipo` (`tipo`)
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `movimientos` VALUES
('47','2026-09-30 11:57:28','venta','52','Gaseosa Coca Cola 1.5L','-2.00','36.00','34.00','Venta folio 1','Prueba API (prueba_api)',NULL,NULL,NULL),
('48','2026-09-30 11:57:28','venta','51','Arroz Gallo Oro Parboil 1kg','-1.00','12.00','11.00','Venta folio 1','Prueba API (prueba_api)',NULL,NULL,NULL),
('49','2026-09-30 11:57:28','anulacion','52','Gaseosa Coca Cola 1.5L','2.00','34.00','36.00','Anulación venta folio 1','Prueba API (prueba_api)',NULL,NULL,NULL),
('50','2026-09-30 11:57:28','anulacion','51','Arroz Gallo Oro Parboil 1kg','1.00','11.00','12.00','Anulación venta folio 1','Prueba API (prueba_api)',NULL,NULL,NULL),
('51','2026-09-30 11:59:42','venta','52','Gaseosa Coca Cola 1.5L','-2.00','36.00','34.00','Venta folio 2','Prueba API (prueba_api)',NULL,NULL,NULL),
('52','2026-09-30 11:59:42','venta','51','Arroz Gallo Oro Parboil 1kg','-1.00','12.00','11.00','Venta folio 2','Prueba API (prueba_api)',NULL,NULL,NULL),
('53','2026-09-30 11:59:42','anulacion','52','Gaseosa Coca Cola 1.5L','2.00','34.00','36.00','Anulación venta folio 2','Prueba API (prueba_api)',NULL,NULL,NULL),
('54','2026-09-30 11:59:42','anulacion','51','Arroz Gallo Oro Parboil 1kg','1.00','11.00','12.00','Anulación venta folio 2','Prueba API (prueba_api)',NULL,NULL,NULL),
('55','2026-09-30 16:08:52','alta','97','Bizcocho Salado Don Satur 200 g','0.00','0.00','0.00','Alta de producto','Administrador (admin)',NULL,NULL,NULL),
('56','2026-09-30 16:08:52','alta','98','Pasta de Maní Natural Maní King 485 g','0.00','0.00','0.00','Alta de producto','Administrador (admin)',NULL,NULL,NULL),
('57','2026-09-30 16:08:52','alta','99','Yogur natural sin azúcar agregada Tregar 280 gramos','0.00','0.00','0.00','Alta de producto','Administrador (admin)',NULL,NULL,NULL),
('58','2026-09-30 16:08:52','alta','100','Pan Blanco LACTAL Lactal 315','0.00','0.00','0.00','Alta de producto','Administrador (admin)',NULL,NULL,NULL),
('59','2026-09-30 16:08:52','alta','101','Yogurisimo sabor Natural La Serenísima 300 g','0.00','0.00','0.00','Alta de producto','Administrador (admin)',NULL,NULL,NULL),
('60','2026-09-30 16:08:52','alta','102','9 de Oro clásicos Molino Cañuelas 200 g','0.00','0.00','0.00','Alta de producto','Administrador (admin)',NULL,NULL,NULL),
('61','2026-09-30 16:08:52','alta','103','Leche Protein La Serenísima 1 l','0.00','0.00','0.00','Alta de producto','Administrador (admin)',NULL,NULL,NULL),
('62','2026-09-30 16:08:52','alta','104','Polenta instantánea Arcor 490 g','0.00','0.00','0.00','Alta de producto','Administrador (admin)',NULL,NULL,NULL),
('63','2026-09-30 16:08:52','alta','105','Finlandia light La Serenísima 290 g','0.00','0.00','0.00','Alta de producto','Administrador (admin)',NULL,NULL,NULL),
('64','2026-09-30 16:08:52','alta','106','Mayonesa Clásica Rica y Cremosa Hellmann\'s 475 gr','0.00','0.00','0.00','Alta de producto','Administrador (admin)',NULL,NULL,NULL),
('65','2026-09-30 16:08:52','alta','107','Dulce de leche estilo colonial La Serenísima 400 g','0.00','0.00','0.00','Alta de producto','Administrador (admin)',NULL,NULL,NULL),
('66','2026-09-30 16:08:52','alta','108','Leche Zero Lactosa La Serenísima 1 l','0.00','0.00','0.00','Alta de producto','Administrador (admin)',NULL,NULL,NULL),
('67','2026-09-30 16:08:52','alta','109','Criollitas x3 Bagley 300 gr','0.00','0.00','0.00','Alta de producto','Administrador (admin)',NULL,NULL,NULL),
('68','2026-09-30 16:08:52','alta','110','Agua eco de los andes 500 ml','0.00','0.00','0.00','Alta de producto','Administrador (admin)',NULL,NULL,NULL),
('69','2026-09-30 16:08:52','alta','111','Yerba Unión 500 g','0.00','0.00','0.00','Alta de producto','Administrador (admin)',NULL,NULL,NULL),
('70','2026-09-30 16:08:53','alta','112','Azúcar Clásica Ledesma 1 kg','0.00','0.00','0.00','Alta de producto','Administrador (admin)',NULL,NULL,NULL),
('71','2026-09-30 16:08:53','alta','113','Finlandia + Liv La Serenísima 290 g','0.00','0.00','0.00','Alta de producto','Administrador (admin)',NULL,NULL,NULL),
('72','2026-09-30 16:08:53','alta','114','Chocolinas Original Bagley 250 gr','0.00','0.00','0.00','Alta de producto','Administrador (admin)',NULL,NULL,NULL),
('73','2026-09-30 16:08:53','alta','115','Pan Blanco LACTAL familiar 460','0.00','0.00','0.00','Alta de producto','Administrador (admin)',NULL,NULL,NULL),
('74','2026-09-30 16:08:53','alta','116','Yogurisimo Griego Natural La Serenísima 300 g','0.00','0.00','0.00','Alta de producto','Administrador (admin)',NULL,NULL,NULL),
('75','2026-09-30 16:08:53','alta','117','Coca Cola Original 2,25 L','0.00','0.00','0.00','Alta de producto','Administrador (admin)',NULL,NULL,NULL);

-- ---------------------------------------------------------
-- Tabla: productos
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `productos`;
CREATE TABLE `productos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `categoria` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT '0.00',
  `stock` decimal(12,2) NOT NULL DEFAULT '0.00',
  `minimo` decimal(12,2) NOT NULL DEFAULT '0.00',
  `unidad` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pieza',
  `foto` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `costo` decimal(10,2) NOT NULL DEFAULT '0.00',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `proveedor_id` int unsigned DEFAULT NULL,
  `unidad_compra` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `factor_compra` decimal(12,3) NOT NULL DEFAULT '1.000',
  `precio_compra` decimal(12,2) DEFAULT NULL,
  `presets` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sin_stock` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `ix_codigo` (`codigo`),
  KEY `ix_categoria` (`categoria`),
  KEY `ix_nombre` (`nombre`),
  KEY `ix_activo` (`activo`)
) ENGINE=InnoDB AUTO_INCREMENT=119 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `productos` VALUES
('49','Galletitas Traviata Kesitas 96g','7790040143944','Almacen','850.00','24.00','8.00','paquete',NULL,'1','2026-09-29 15:34:39','550.00','Codigo confirmado en el Maestro SEPA','1',NULL,'1.000',NULL,NULL,'0'),
('50','Galletitas Traviata Crackers Rex 96g','7790040143937','Almacen','800.00','18.00','6.00','paquete',NULL,'1','2026-09-29 15:34:39','520.00','Codigo con digito verificador correcto; el maestro no tiene esta variante Rex','1',NULL,'1.000',NULL,NULL,'0'),
('51','Arroz Gallo Oro Parboil 1kg','7790070411716','Almacen','2400.00','12.00','4.00','paquete',NULL,'1','2026-09-29 15:34:39','1650.00','Codigo corregido: el del usuario (7790070410120) tenia mal el digito verificador','1',NULL,'1.000',NULL,NULL,'0'),
('52','Gaseosa Coca Cola 1.5L','7790895000430','Almacen','3500.00','36.00','12.00','botella',NULL,'1','2026-09-29 15:34:39','2300.00','Codigo corregido: el del usuario (7790895000860) tenia mal el digito verificador','1',NULL,'1.000',NULL,NULL,'0'),
('53','Fernet Branca 750ml','7790290001193','Almacen','9500.00','6.00','2.00','botella',NULL,'1','2026-09-29 15:34:39','6300.00','Codigo corregido y de prefijo: el del usuario (7791293000018) no era de Branca','1',NULL,'1.000',NULL,NULL,'0'),
('54','Galletitas Chocolinas 170g','7790040929906','Almacen','1100.00','30.00','10.00','paquete',NULL,'1','2026-09-29 15:34:39','720.00','Codigo del maestro para la presentacion de 170g','1',NULL,'1.000',NULL,NULL,'0'),
('55','Aceite Girasol y Oliva Natura 900ml',NULL,'Almacen','3200.00','9.00','3.00','botella',NULL,'1','2026-09-29 15:34:39','0.00','Codigo EAN-13 verificado. Precio y stock de referencia: cargar los reales del local.','1',NULL,'1.000',NULL,NULL,'0'),
('56','Caramelos Butter Toffees Dulce de Leche','7790040010604','Almacen','2900.00','15.00','5.00','paquete',NULL,'1','2026-09-29 15:34:39','1900.00','Codigo con digito verificador correcto','1',NULL,'1.000',NULL,NULL,'0'),
('57','Alfajor Guolis Dulce de Leche','7790189000108','Almacen','1500.00','40.00','12.00','pieza',NULL,'1','2026-09-29 15:34:39','980.00','Codigo con digito verificador correcto; no figura en el Maestro SEPA','1',NULL,'1.000',NULL,NULL,'0'),
('58','Agua Mineral Villavicencio Sin Gas 1.5L','7790315000439','Almacen','2200.00','48.00','16.00','botella',NULL,'1','2026-09-29 15:34:39','1450.00','Codigo corregido: el del usuario (7791230000010) tenia mal el digito verificador','1',NULL,'1.000',NULL,NULL,'0'),
('73','Hamburguesa completa','2000000000732','Comidas y Tragos','4500.00','0.00','0.00','porción',NULL,'1','2026-09-30 11:07:34','0.00','Código interno de cocina, prefijo 20.',NULL,NULL,'1.000',NULL,NULL,'1'),
('74','Pancho','2000000000749','Comidas y Tragos','4200.00','0.00','0.00','porción',NULL,'1','2026-09-30 11:07:34','0.00','Código interno de cocina, prefijo 20.',NULL,NULL,'1.000',NULL,NULL,'1'),
('75','Sándwich de miga','2000000000756','Comidas y Tragos','3800.00','0.00','0.00','porción',NULL,'1','2026-09-30 11:07:34','0.00','Código interno de cocina, prefijo 20.',NULL,NULL,'1.000',NULL,NULL,'1'),
('76','Sándwich de milanesa','2000000000763','Comidas y Tragos','5200.00','0.00','0.00','porción',NULL,'1','2026-09-30 11:07:34','0.00','Código interno de cocina, prefijo 20.',NULL,NULL,'1.000',NULL,NULL,'1'),
('77','Pollo con papas','2000000000770','Comidas y Tragos','6000.00','0.00','0.00','porción',NULL,'1','2026-09-30 11:07:34','0.00','Código interno de cocina, prefijo 20.',NULL,NULL,'1.000',NULL,NULL,'1'),
('78','Gaseosa Coca Cola 1 L',NULL,'Almacen','3200.00','12.00','4.00','botella',NULL,'1','2026-09-30 11:07:34','0.00','PRECIO DE REFERENCIA. Nuestra: entra por compra de proveedor y descuenta stock al vender. Si la mandás a la comanda, igual descuenta: el tilde solo decide si va al papel de la cocina.',NULL,NULL,'1.000',NULL,NULL,'0'),
('80','Gaseosa Coca Cola 2.25 L',NULL,'Almacen','5800.00','12.00','4.00','botella',NULL,'1','2026-09-30 11:07:34','0.00','PRECIO DE REFERENCIA. Nuestra: entra por compra de proveedor y descuenta stock al vender. Si la mandás a la comanda, igual descuenta: el tilde solo decide si va al papel de la cocina.',NULL,NULL,'1.000',NULL,NULL,'0'),
('81','Cerveza en botella',NULL,'Almacen','2800.00','24.00','6.00','botella',NULL,'1','2026-09-30 11:07:34','0.00','PRECIO DE REFERENCIA. Nuestra: entra por compra de proveedor y descuenta stock al vender.',NULL,NULL,'1.000',NULL,NULL,'0'),
('82','Vaso de vino','2000000000824','Comidas y Tragos','3500.00','0.00','0.00','vaso',NULL,'1','2026-09-30 11:07:34','0.00','Código interno de cocina, prefijo 20.',NULL,NULL,'1.000',NULL,NULL,'1'),
('83','Trago de gin','2000000000831','Comidas y Tragos','4800.00','0.00','0.00','vaso',NULL,'1','2026-09-30 11:07:34','0.00','Código interno de cocina, prefijo 20.',NULL,NULL,'1.000',NULL,NULL,'1'),
('84','Piña colada','2000000000848','Comidas y Tragos','5200.00','0.00','0.00','vaso',NULL,'1','2026-09-30 11:07:34','0.00','Código interno de cocina, prefijo 20.',NULL,NULL,'1.000',NULL,NULL,'1'),
('85','Trago de Gancia con Fernet','2000000000855','Comidas y Tragos','4500.00','0.00','0.00','vaso',NULL,'1','2026-09-30 11:07:34','0.00','Código interno de cocina, prefijo 20.',NULL,NULL,'1.000',NULL,NULL,'1'),
('86','Trago mesclado','2000000000862','Comidas y Tragos','4000.00','0.00','0.00','vaso',NULL,'1','2026-09-30 11:07:34','0.00','Código interno de cocina, prefijo 20.',NULL,NULL,'1.000',NULL,NULL,'1'),
('87','Fernet 1/2 litro',NULL,'Almacen','3500.00','6.00','2.00','botella',NULL,'1','2026-09-30 11:17:21','0.00','PRECIO DE REFERENCIA. Nuestra: entra por compra de proveedor y descuenta stock al vender.',NULL,NULL,'1.000',NULL,NULL,'0'),
('89','Fernet 1 litro',NULL,'Almacen','6200.00','4.00','2.00','botella',NULL,'1','2026-09-30 11:17:21','0.00','PRECIO DE REFERENCIA. Nuestra: entra por compra de proveedor y descuenta stock al vender.',NULL,NULL,'1.000',NULL,NULL,'0'),
('90','Cerveza en lata',NULL,'Almacen','2600.00','36.00','12.00','lata',NULL,'1','2026-09-30 11:26:10','0.00','PRECIO DE REFERENCIA. Nuestra: entra por compra de proveedor y descuenta stock al vender.',NULL,NULL,'1.000',NULL,NULL,'0'),
('91','Vino en botella',NULL,'Almacen','4500.00','12.00','4.00','botella',NULL,'1','2026-09-30 11:26:10','0.00','PRECIO DE REFERENCIA. Nuestra: entra por compra de proveedor y descuenta stock al vender.',NULL,NULL,'1.000',NULL,NULL,'0'),
('92','Caja de vino',NULL,'Almacen','42000.00','3.00','1.00','caja',NULL,'1','2026-09-30 11:26:10','0.00','PRECIO DE REFERENCIA. Nuestra: entra por compra de proveedor y descuenta stock al vender.',NULL,NULL,'1.000',NULL,NULL,'0'),
('93','Gin en botella 900 cc',NULL,'Almacen','8500.00','6.00','2.00','botella',NULL,'1','2026-09-30 11:26:10','0.00','PRECIO DE REFERENCIA. Nuestra: entra por compra de proveedor y descuenta stock al vender.',NULL,NULL,'1.000',NULL,NULL,'0'),
('94','Pizza','2000000000947','Comidas y Tragos','6500.00','0.00','0.00','porción',NULL,'1','2026-09-30 11:26:10','0.00','Código interno de cocina, prefijo 20.',NULL,NULL,'1.000',NULL,NULL,'1'),
('95','Balón de cerveza','2000000000954','Comidas y Tragos','3000.00','0.00','0.00','balón',NULL,'1','2026-09-30 11:26:10','0.00','Código interno de cocina, prefijo 20.',NULL,NULL,'1.000',NULL,NULL,'1'),
('96','Trago destornillador','2000000000961','Comidas y Tragos','5200.00','0.00','0.00','vaso',NULL,'1','2026-09-30 11:26:10','0.00','Código interno de cocina, prefijo 20.',NULL,NULL,'1.000',NULL,NULL,'1'),
('97','Bizcocho Salado Don Satur 200 g','7795735000328','Almacen','0.00','0.00','0.00','pieza',NULL,'1','2026-09-30 16:08:52','0.00',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('98','Pasta de Maní Natural Maní King 485 g','7798151952332','Almacen','0.00','0.00','0.00','pieza',NULL,'1','2026-09-30 16:08:52','0.00',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('99','Yogur natural sin azúcar agregada Tregar 280 gramos','7793913013993','Almacen','0.00','0.00','0.00','pieza',NULL,'1','2026-09-30 16:08:52','0.00',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('100','Pan Blanco LACTAL Lactal 315','7793890258769','Almacen','0.00','0.00','0.00','pieza',NULL,'1','2026-09-30 16:08:52','0.00',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('101','Yogurisimo sabor Natural La Serenísima 300 g','7791337006355','Almacen','0.00','0.00','0.00','pieza',NULL,'1','2026-09-30 16:08:52','0.00',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('102','9 de Oro clásicos Molino Cañuelas 200 g','7792200000159','Almacen','0.00','0.00','0.00','pieza',NULL,'1','2026-09-30 16:08:52','0.00',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('103','Leche Protein La Serenísima 1 l','7790742358608','Almacen','0.00','0.00','0.00','pieza',NULL,'1','2026-09-30 16:08:52','0.00',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('104','Polenta instantánea Arcor 490 g','7790580138738','Almacen','0.00','0.00','0.00','pieza',NULL,'1','2026-09-30 16:08:52','0.00',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('105','Finlandia light La Serenísima 290 g','7790742373304','Almacen','0.00','0.00','0.00','pieza',NULL,'1','2026-09-30 16:08:52','0.00',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('106','Mayonesa Clásica Rica y Cremosa Hellmann\'s 475 gr','7794000006072','Almacen','0.00','0.00','0.00','pieza',NULL,'1','2026-09-30 16:08:52','0.00',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('107','Dulce de leche estilo colonial La Serenísima 400 g','7790742625205','Almacen','0.00','0.00','0.00','pieza',NULL,'1','2026-09-30 16:08:52','0.00',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('108','Leche Zero Lactosa La Serenísima 1 l','7790742333605','Almacen','0.00','0.00','0.00','pieza',NULL,'1','2026-09-30 16:08:52','0.00',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('109','Criollitas x3 Bagley 300 gr','7790040377806','Almacen','0.00','0.00','0.00','pieza',NULL,'1','2026-09-30 16:08:52','0.00',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('110','Agua eco de los andes 500 ml','7792799000011','Almacen','0.00','0.00','0.00','pieza',NULL,'1','2026-09-30 16:08:52','0.00',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('111','Yerba Unión 500 g','7790387014624','Almacen','0.00','0.00','0.00','pieza',NULL,'1','2026-09-30 16:08:52','0.00',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('112','Azúcar Clásica Ledesma 1 kg','7792540260138','Almacen','0.00','0.00','0.00','pieza',NULL,'1','2026-09-30 16:08:53','0.00',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('113','Finlandia + Liv La Serenísima 290 g','7790742324108','Almacen','0.00','0.00','0.00','pieza',NULL,'1','2026-09-30 16:08:53','0.00',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('114','Chocolinas Original Bagley 250 gr','7790040143234','Almacen','0.00','0.00','0.00','pieza',NULL,'1','2026-09-30 16:08:53','0.00',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('115','Pan Blanco LACTAL familiar 460','7793890258752','Almacen','0.00','0.00','0.00','pieza',NULL,'1','2026-09-30 16:08:53','0.00',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('116','Yogurisimo Griego Natural La Serenísima 300 g','7791337010017','Almacen','0.00','0.00','0.00','pieza',NULL,'1','2026-09-30 16:08:53','0.00',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('117','Coca Cola Original 2,25 L','7790895000997','Almacen','0.00','0.00','0.00','pieza',NULL,'1','2026-09-30 16:08:53','0.00',NULL,NULL,NULL,'1.000',NULL,NULL,'0');

-- ---------------------------------------------------------
-- Tabla: productos_formatos
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `productos_formatos`;
CREATE TABLE `productos_formatos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `producto_id` int unsigned NOT NULL,
  `ambito` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'venta',
  `unidad` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `factor` decimal(12,4) NOT NULL DEFAULT '1.0000',
  `precio` decimal(12,2) DEFAULT NULL,
  `margen` decimal(8,2) DEFAULT NULL,
  `predet` tinyint(1) NOT NULL DEFAULT '0',
  `creado` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_prod_ambito` (`producto_id`,`ambito`),
  KEY `ix_prod` (`producto_id`)
) ENGINE=InnoDB AUTO_INCREMENT=177 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `productos_formatos` VALUES
('121','49','venta','paquete','1.0000','850.00',NULL,'1','2026-09-29 15:51:13'),
('122','49','venta','2x1','2.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('123','49','venta','3x2','3.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('124','49','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('125','49','venta','docena','12.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('126','50','venta','paquete','1.0000','800.00',NULL,'1','2026-09-29 15:51:13'),
('127','50','venta','2x1','2.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('128','50','venta','3x2','3.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('129','50','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('130','50','venta','docena','12.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('131','51','venta','paquete','1.0000','2400.00',NULL,'1','2026-09-29 15:51:13'),
('132','51','venta','2x1','2.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('133','51','venta','3x2','3.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('134','51','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('135','51','venta','docena','12.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('136','52','venta','botella','1.0000','3500.00',NULL,'1','2026-09-29 15:51:13'),
('137','52','venta','2x1','2.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('138','52','venta','3x2','3.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('139','52','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('140','52','venta','docena','12.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('141','53','venta','botella','1.0000','9500.00',NULL,'1','2026-09-29 15:51:13'),
('142','53','venta','2x1','2.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('143','53','venta','3x2','3.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('144','53','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('145','53','venta','docena','12.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('146','54','venta','paquete','1.0000','1100.00',NULL,'1','2026-09-29 15:51:13'),
('147','54','venta','2x1','2.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('148','54','venta','3x2','3.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('149','54','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('150','54','venta','docena','12.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('156','56','venta','paquete','1.0000','2900.00',NULL,'1','2026-09-29 15:51:13'),
('157','56','venta','2x1','2.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('158','56','venta','3x2','3.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('159','56','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('160','56','venta','docena','12.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('161','57','venta','pieza','1.0000','1500.00',NULL,'1','2026-09-29 15:51:13'),
('162','57','venta','2x1','2.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('163','57','venta','3x2','3.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('164','57','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('165','57','venta','docena','12.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('166','58','venta','botella','1.0000','2200.00',NULL,'1','2026-09-29 15:51:13'),
('167','58','venta','2x1','2.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('168','58','venta','3x2','3.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('169','58','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('170','58','venta','docena','12.0000',NULL,NULL,'0','2026-09-29 15:51:13'),
('171','55','venta','botella','1.0000','3200.00',NULL,'1','2026-09-30 11:31:45'),
('172','55','venta','2x1','2.0000',NULL,NULL,'0','2026-09-30 11:31:45'),
('173','55','venta','3x2','3.0000',NULL,NULL,'0','2026-09-30 11:31:45'),
('174','55','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-30 11:31:45'),
('175','55','venta','docena','12.0000',NULL,NULL,'0','2026-09-30 11:31:45'),
('176','55','venta','kg','1.0000',NULL,NULL,'0','2026-09-30 11:31:45');

-- ---------------------------------------------------------
-- Tabla: proveedores
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `proveedores`;
CREATE TABLE `proveedores` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `proveedores` VALUES
('1','Distribuidora del Sur','11 5555-1234','ventas@dsur.com','Entrega los martes','1','2026-09-25 17:14:15');

-- ---------------------------------------------------------
-- Tabla: usuarios
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `usuario` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `clave` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vendedor',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `debe_cambiar_clave` tinyint(1) NOT NULL DEFAULT '0',
  `creado` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_ingreso` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario` (`usuario`),
  KEY `ix_rol` (`rol`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `usuarios` VALUES
('1','admin','Administrador','$2y$10$FXEQq5k9oH8A8TaYMni.MueTLqhzZTiv7EPfu9VRjzV3LWDTK0eU6','admin','1','0','2026-09-29 13:39:59','2026-09-30 16:08:52');

-- ---------------------------------------------------------
-- Tabla: venta_items
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `venta_items`;
CREATE TABLE `venta_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `venta_id` int unsigned NOT NULL,
  `producto_id` int unsigned DEFAULT NULL,
  `nombre` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cantidad` decimal(12,2) NOT NULL DEFAULT '0.00',
  `importe` decimal(12,2) NOT NULL DEFAULT '0.00',
  `costo_unitario` decimal(10,2) NOT NULL DEFAULT '0.00',
  `formato_id` int unsigned DEFAULT NULL,
  `formato_unidad` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `formato_cantidad` decimal(12,3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_venta` (`venta_id`),
  KEY `ix_producto` (`producto_id`),
  CONSTRAINT `fk_item_venta` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (sin registros)

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
  `metodo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Efectivo',
  `recibido` decimal(12,2) DEFAULT NULL,
  `vuelto` decimal(12,2) DEFAULT NULL,
  `referencia` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nota` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `anulada` tinyint(1) NOT NULL DEFAULT '0',
  `anulada_en` datetime DEFAULT NULL,
  `motivo` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proveedor_id` int unsigned DEFAULT NULL,
  `caja_id` int unsigned DEFAULT NULL,
  `medio_pago_id` smallint unsigned DEFAULT NULL,
  `anulada_por` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `envio` decimal(12,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_folio` (`folio`),
  KEY `ix_fecha` (`fecha`),
  KEY `ix_anulada` (`anulada`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (sin registros)

-- ---------------------------------------------------------
-- Tabla: zonas
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `zonas`;
CREATE TABLE `zonas` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `costo` decimal(12,2) NOT NULL DEFAULT '0.00',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `orden` smallint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `ix_activo` (`activo`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `zonas` VALUES
('1','Centro','0.00','1','1'),
('2','Barrio Norte','500.00','1','2'),
('3','Barrio Sur','800.00','1','3');

SET FOREIGN_KEY_CHECKS = 1;
-- Total de registros respaldados: 171
