-- ==================================================
-- Kiosco — respaldo de la base de datos
-- Generado: 28/09/2026 19:37:01
-- Base: kiosco (8.3.16 / MySQL 8.4.3)
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
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `cajas` VALUES
('21','1','2026-09-26 23:24:39','10000.00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'1');

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
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `comanda_atajos` VALUES
('1','Comidas','1 hamburguesa',NULL,NULL,'Hamburguesa completa','con lechuga, tomate y queso','1','1'),
('2','Comidas','1 pancho',NULL,NULL,'Pancho','hamburguesa completa','2','1'),
('3','Comidas','1 miga',NULL,NULL,'Sándwich de miga',NULL,'3','1'),
('4','Comidas','1 milanesa',NULL,NULL,'Sándwich de milanesa',NULL,'4','1'),
('5','Comidas','1 pollo',NULL,NULL,'Pollo con papas',NULL,'5','1'),
('6','Bebidas','1 cerveza',NULL,NULL,'Cerveza',NULL,'1','1'),
('7','Bebidas','1 coc 1L',NULL,NULL,'Coca Cola','botella de 1 litro','2','1'),
('8','Bebidas','1 coc 1.5',NULL,NULL,'Coca Cola','botella de litro y medio','3','1'),
('9','Bebidas','1 coc 2.25',NULL,NULL,'Coca Cola','botella de 2 litros y cuarto','4','1'),
('10','Tragos','1 vino',NULL,NULL,'Vaso de vino',NULL,'1','1'),
('11','Tragos','1 gin',NULL,NULL,'Gin',NULL,'2','1'),
('12','Tragos','1 pina',NULL,NULL,'Piña colada',NULL,'3','1'),
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `usuario` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cerrado_en` datetime DEFAULT NULL,
  `envio` decimal(12,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `ix_venta` (`venta_id`),
  KEY `ix_estado` (`estado`),
  KEY `ix_creado` (`creado`),
  KEY `ix_cliente` (`cliente`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (sin registros)

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
('esquema_version','7'),
('factura','no'),
('logo','K'),
('moneda','$'),
('negocio','Mi Kiosco'),
('pie','¡Gracias por su compra!'),
('pin',''),
('telefono',''),
('tema','ocuro');

-- ---------------------------------------------------------
-- Tabla: medios_pago
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `medios_pago`;
CREATE TABLE `medios_pago` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icono` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exige_referencia` tinyint(1) NOT NULL DEFAULT '0',
  `es_efectivo` tinyint(1) NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `orden` smallint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `ix_activo` (`activo`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `medios_pago` VALUES
('1','Efectivo','💵','0','1','1','1'),
('2','Tarjeta','💳','1','0','1','2'),
('3','Transferencia','📱','1','0','1','3'),
('4','Mercado Pago','🅿️','1','0','1','4');

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
  `nota` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_fecha` (`fecha`),
  KEY `ix_producto` (`producto_id`),
  KEY `ix_tipo` (`tipo`)
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (sin registros)

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
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `productos` VALUES
('1','Agua mineral 600 ml',NULL,'Bebidas','0.00','48.00','12.00','botella',NULL,'1','2026-09-26 20:17:53','7.10',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('2','Gaseosa de cola 600 ml','7501234567891','Bebidas','18.00','48.00','12.00','botella',NULL,'1','2026-09-26 20:17:53','10.80',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('3','Jugo de naranja 1 L','7501234567892','Bebidas','26.00','18.00','6.00','botella',NULL,'1','2026-09-26 20:17:53','16.40',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('4','Cerveza lata 355 ml','7501234567893','Bebidas','28.00','24.00','6.00','lata',NULL,'1','2026-09-26 20:17:53','18.90',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('5','Leche entera 1 L','7501234567894','Lácteos','24.00','20.00','8.00','botella',NULL,'1','2026-09-26 20:17:53','15.20',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('6','Yogur natural 500 g','7501234567895','Lácteos','28.00','14.00','6.00','pieza',NULL,'1','2026-09-26 20:17:53','17.60',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('9','Pan de molde','7501234567898','Panadería','34.00','11.00','5.00','pieza',NULL,'1','2026-09-26 20:17:53','20.10',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('10','Galletas de avena x12','7501234567899','Botanas','38.00','22.00','8.00','paquete',NULL,'1','2026-09-26 20:17:53','24.70',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('11','Chocolate en barra','7501234567900','Botanas','16.00','40.00','12.00','pieza',NULL,'1','2026-09-26 20:17:53','9.40',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('12','Café soluble 250 g','7501234567901','Despensa','78.00','8.00','4.00','paquete',NULL,'1','2026-09-26 20:17:53','55.30',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('13','Cereal de maíz 500 g','7501234567902','Despensa','52.00','10.00','4.00','paquete',NULL,'1','2026-09-26 20:17:53','36.80',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('14','Aceite vegetal 1 L','7501234567903','Despensa','44.00','10.00','6.00','botella',NULL,'1','2026-09-26 20:17:53','31.60',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('15','Arroz grano largo 1 kg','7501234567904','Despensa','32.00','25.00','10.00','paquete',NULL,'1','2026-09-26 20:17:53','22.40',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('16','Poroto negro 500 g','7501234567905','Despensa','28.00','18.00','8.00','paquete',NULL,'1','2026-09-26 20:17:53','19.60',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('17','Azúcar 1 kg','7501234567906','Despensa','30.00','20.00','8.00','paquete',NULL,'1','2026-09-26 20:17:53','21.10',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('18','Fideos spaghetti 500 g','7501234567907','Despensa','26.00','16.00','8.00','paquete',NULL,'1','2026-09-26 20:17:53','17.90',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('19','Detergente líquido 1 L','7501234567908','Limpieza','68.00','7.00','4.00','botella',NULL,'1','2026-09-26 20:17:53','50.40',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('20','Jabón de platos 500 ml','7501234567909','Limpieza','36.00','11.00','5.00','botella',NULL,'1','2026-09-26 20:17:53','24.20',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('21','Papel higiénico x4','7501234567910','Higiene','58.00','14.00','6.00','paquete',NULL,'1','2026-09-26 20:17:53','42.30',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('22','Servilletas x100','7501234567911','Higiene','22.00','20.00','8.00','paquete',NULL,'1','2026-09-26 20:17:53','14.10',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('23','Bolsas para basura x20','7501234567912','Higiene','28.00','13.00','6.00','paquete',NULL,'1','2026-09-26 20:17:53','18.90',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('24','Pilas alcalinas x4','7501234567913','Varios','45.00','6.00','4.00','paquete',NULL,'1','2026-09-26 20:17:53','31.80',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('25','Manteca 200 g','7501234567914','Lácteos','32.00','14.00','6.00','pieza',NULL,'1','2026-09-26 20:17:53','21.60',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('26','Harina 000 x1 kg','7501234567915','Panadería','30.00','18.00','8.00','paquete',NULL,'1','2026-09-26 20:17:53','19.40',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('27','Afre instantáneo 500 g','7501234567916','Despensa','68.00','6.00','3.00','paquete',NULL,'1','2026-09-26 20:17:53','48.90',NULL,NULL,NULL,'1.000',NULL,NULL,'0'),
('29','Jamón cocido en barra','','Almacén','18500.00','4.50','0.50','kg',NULL,'1','2026-09-26 20:40:00','12500.00',NULL,NULL,'barra','1.000',NULL,'0.1,0.2,0.25,0.5,1','0'),
('30','Salame tipo Milano','','Almacén','21000.00','3.00','0.50','kg',NULL,'1','2026-09-26 20:40:00','14500.00',NULL,NULL,'barra','1.000',NULL,'0.1,0.2,0.25,0.5,1','0'),
('31','Salchichón primavera','','Almacén','16800.00','5.00','0.50','kg',NULL,'1','2026-09-26 20:40:00','11200.00',NULL,NULL,'barra','1.000',NULL,'0.1,0.2,0.25,0.5,1','0'),
('32','Matambre con queso','','Almacén','19800.00','4.00','0.50','kg',NULL,'1','2026-09-26 20:40:00','13400.00',NULL,NULL,'barra','1.000',NULL,'0.1,0.2,0.25,0.5,1','0'),
('33','Queso muzzarella en barra','','Almacén','15500.00','6.00','0.50','kg',NULL,'1','2026-09-26 20:40:00','10200.00',NULL,NULL,'barra','1.000',NULL,'0.1,0.2,0.25,0.5,1','0'),
('34','Queso fresco','','Almacén','9800.00','2.00','0.30','kg',NULL,'1','2026-09-26 20:40:00','6400.00',NULL,NULL,'barra','0.500',NULL,'0.1,0.2,0.25,0.5','0'),
('35','Aceitunas verdes en salmuera','','Almacén','8900.00','8.00','1.00','kg',NULL,'1','2026-09-26 20:40:00','5900.00',NULL,NULL,'bidón','5.000',NULL,'0.1,0.2,0.25,0.5,1','0'),
('36','Huevos',NULL,'Lacteos','250.00','570.00','60.00','unidad',NULL,'1','2026-09-26 20:40:00','166.67',NULL,'1','cajón','570.000','78000.00','1,6,12,18,30','0'),
('37','Pan flautín a granel','','Panadería','6500.00','20.00','5.00','kg',NULL,'1','2026-09-26 20:40:00','4200.00',NULL,NULL,'bolsa','10.000','42000.00','0.25,0.5,1','0'),
('38','Pan de mesa a granel','','Panadería','6000.00','15.00','5.00','kg',NULL,'1','2026-09-26 20:40:00','3900.00',NULL,NULL,'bolsa','10.000','39000.00','0.25,0.5,1','0'),
('39','Facturas dulces','','Panadería','1500.00','36.00','12.00','unidad',NULL,'1','2026-09-26 20:40:00','950.00',NULL,NULL,'docena','12.000','11400.00','1,2,3,4,5,6,12','0'),
('40','Vigilantes (bollo de queso)','','Panadería','1300.00','24.00','12.00','unidad',NULL,'1','2026-09-26 20:40:00','820.00',NULL,NULL,'docena','12.000','9840.00','1,2,3,4,6,12','0'),
('41','Jabón líquido bidón','','Limpieza','9800.00','20.00','5.00','litro',NULL,'1','2026-09-26 20:40:00','6400.00',NULL,NULL,'bidón','5.000','32000.00','0.5,1,2,5','0'),
('42','Suavizante ropa bidón','','Limpieza','11500.00','20.00','5.00','litro',NULL,'1','2026-09-26 20:40:00','7600.00',NULL,NULL,'bidón','5.000','38000.00','0.5,1,2,5','0'),
('43','Detergente concentrado bidón','','Limpieza','12500.00','20.00','5.00','litro',NULL,'1','2026-09-26 20:40:00','8300.00',NULL,NULL,'bidón','5.000','41500.00','0.5,1,2,5','0'),
('44','Lavandina bidón','','Limpieza','4200.00','25.00','5.00','litro',NULL,'1','2026-09-26 20:40:00','2600.00',NULL,NULL,'bidón','5.000','13000.00','0.5,1,2,5','0'),
('45','Cloro bidón','','Limpieza','3900.00','20.00','5.00','litro',NULL,'1','2026-09-26 20:40:00','2400.00',NULL,NULL,'bidón','5.000','12000.00','0.5,1,2,5','0'),
('46','Detergente Cif 150 ml','','Limpieza','1450.00','24.00','6.00','pieza',NULL,'1','2026-09-26 20:40:00','980.00',NULL,NULL,'caja','12.000','11760.00','1,2,3','0'),
('47','Detergente Cif 340 ml','','Limpieza','2100.00','24.00','6.00','pieza',NULL,'1','2026-09-26 20:40:00','1420.00',NULL,NULL,'caja','12.000','17040.00','1,2,3','0'),
('48','Detergente Cif 600 ml','','Limpieza','3250.00','12.00','6.00','pieza',NULL,'1','2026-09-26 20:40:00','2180.00',NULL,NULL,'caja','12.000','26160.00','1,2,3','0'),
('49','Detergente Cif 900 ml','','Limpieza','4500.00','12.00','6.00','pieza',NULL,'1','2026-09-26 20:40:00','3050.00',NULL,NULL,'caja','12.000','36600.00','1,2,3','0'),
('50','Lavandina Ayudín 900 ml','','Limpieza','2350.00','24.00','6.00','pieza',NULL,'1','2026-09-26 20:40:00','1580.00',NULL,NULL,'caja','12.000','18960.00','1,2,3','0'),
('51','Pure de tomate 500 ml',NULL,'Almacen','1083.33','0.00','0.00','unidad',NULL,'1','2026-09-26 22:00:12','833.33',NULL,'1',NULL,'1.000',NULL,NULL,'0');

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
) ENGINE=InnoDB AUTO_INCREMENT=272 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `productos_formatos` VALUES
('1','1','venta','botella','1.0000','12.00',NULL,'1','2026-09-26 21:52:55'),
('2','1','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('3','1','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('4','1','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('5','1','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('6','2','venta','botella','1.0000','18.00',NULL,'1','2026-09-26 21:52:55'),
('7','2','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('8','2','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('9','2','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('10','2','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('11','3','venta','botella','1.0000','26.00',NULL,'1','2026-09-26 21:52:55'),
('12','3','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('13','3','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('14','3','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('15','3','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('16','4','venta','lata','1.0000','28.00',NULL,'1','2026-09-26 21:52:55'),
('17','4','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('18','4','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('19','4','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('20','4','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('21','5','venta','botella','1.0000','24.00',NULL,'1','2026-09-26 21:52:55'),
('22','5','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('23','5','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('24','5','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('25','5','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('26','6','venta','pieza','1.0000','28.00',NULL,'1','2026-09-26 21:52:55'),
('27','6','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('28','6','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('29','6','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('30','6','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('31','9','venta','pieza','1.0000','34.00',NULL,'1','2026-09-26 21:52:55'),
('32','9','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('33','9','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('34','9','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('35','9','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('36','10','venta','paquete','1.0000','38.00',NULL,'1','2026-09-26 21:52:55'),
('37','10','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('38','10','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('39','10','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('40','10','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('41','11','venta','pieza','1.0000','16.00',NULL,'1','2026-09-26 21:52:55'),
('42','11','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('43','11','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('44','11','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('45','11','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('46','12','venta','paquete','1.0000','78.00',NULL,'1','2026-09-26 21:52:55'),
('47','12','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('48','12','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('49','12','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('50','12','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('51','13','venta','paquete','1.0000','52.00',NULL,'1','2026-09-26 21:52:55'),
('52','13','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('53','13','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('54','13','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('55','13','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('56','14','venta','botella','1.0000','44.00',NULL,'1','2026-09-26 21:52:55'),
('57','14','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('58','14','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('59','14','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('60','14','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('61','15','venta','paquete','1.0000','32.00',NULL,'1','2026-09-26 21:52:55'),
('62','15','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('63','15','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('64','15','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('65','15','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('66','16','venta','paquete','1.0000','28.00',NULL,'1','2026-09-26 21:52:55'),
('67','16','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('68','16','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('69','16','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('70','16','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('71','17','venta','paquete','1.0000','30.00',NULL,'1','2026-09-26 21:52:55'),
('72','17','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('73','17','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('74','17','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('75','17','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('76','18','venta','paquete','1.0000','26.00',NULL,'1','2026-09-26 21:52:55'),
('77','18','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('78','18','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('79','18','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('80','18','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('81','19','venta','botella','1.0000','68.00',NULL,'1','2026-09-26 21:52:55'),
('82','19','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('83','19','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('84','19','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('85','19','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('86','20','venta','botella','1.0000','36.00',NULL,'1','2026-09-26 21:52:55'),
('87','20','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('88','20','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('89','20','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('90','20','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('91','21','venta','paquete','1.0000','58.00',NULL,'1','2026-09-26 21:52:55'),
('92','21','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('93','21','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('94','21','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('95','21','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('96','22','venta','paquete','1.0000','22.00',NULL,'1','2026-09-26 21:52:55'),
('97','22','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('98','22','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('99','22','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('100','22','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('101','23','venta','paquete','1.0000','28.00',NULL,'1','2026-09-26 21:52:55'),
('102','23','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('103','23','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('104','23','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('105','23','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('106','24','venta','paquete','1.0000','45.00',NULL,'1','2026-09-26 21:52:55'),
('107','24','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('108','24','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('109','24','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('110','24','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('111','25','venta','pieza','1.0000','32.00',NULL,'1','2026-09-26 21:52:55'),
('112','25','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('113','25','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('114','25','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('115','25','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('116','26','venta','paquete','1.0000','30.00',NULL,'1','2026-09-26 21:52:55'),
('117','26','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('118','26','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('119','26','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('120','26','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('121','27','venta','paquete','1.0000','68.00',NULL,'1','2026-09-26 21:52:55'),
('122','27','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('123','27','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('124','27','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('125','27','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('126','29','compra','barra','1.0000',NULL,NULL,'1','2026-09-26 21:52:55'),
('127','29','venta','kg','1.0000','18500.00',NULL,'1','2026-09-26 21:52:55'),
('128','29','venta','100 g','0.1000',NULL,NULL,'0','2026-09-26 21:52:55'),
('129','29','venta','200 g','0.2000',NULL,NULL,'0','2026-09-26 21:52:55'),
('130','29','venta','250 g','0.2500',NULL,NULL,'0','2026-09-26 21:52:55'),
('131','29','venta','500 g','0.5000',NULL,NULL,'0','2026-09-26 21:52:55'),
('132','30','compra','barra','1.0000',NULL,NULL,'1','2026-09-26 21:52:55'),
('133','30','venta','kg','1.0000','21000.00',NULL,'1','2026-09-26 21:52:55'),
('134','30','venta','100 g','0.1000',NULL,NULL,'0','2026-09-26 21:52:55'),
('135','30','venta','200 g','0.2000',NULL,NULL,'0','2026-09-26 21:52:55'),
('136','30','venta','250 g','0.2500',NULL,NULL,'0','2026-09-26 21:52:55'),
('137','30','venta','500 g','0.5000',NULL,NULL,'0','2026-09-26 21:52:55'),
('138','31','compra','barra','1.0000',NULL,NULL,'1','2026-09-26 21:52:55'),
('139','31','venta','kg','1.0000','16800.00',NULL,'1','2026-09-26 21:52:55'),
('140','31','venta','100 g','0.1000',NULL,NULL,'0','2026-09-26 21:52:55'),
('141','31','venta','200 g','0.2000',NULL,NULL,'0','2026-09-26 21:52:55'),
('142','31','venta','250 g','0.2500',NULL,NULL,'0','2026-09-26 21:52:55'),
('143','31','venta','500 g','0.5000',NULL,NULL,'0','2026-09-26 21:52:55'),
('144','32','compra','barra','1.0000',NULL,NULL,'1','2026-09-26 21:52:55'),
('145','32','venta','kg','1.0000','19800.00',NULL,'1','2026-09-26 21:52:55'),
('146','32','venta','100 g','0.1000',NULL,NULL,'0','2026-09-26 21:52:55'),
('147','32','venta','200 g','0.2000',NULL,NULL,'0','2026-09-26 21:52:55'),
('148','32','venta','250 g','0.2500',NULL,NULL,'0','2026-09-26 21:52:55'),
('149','32','venta','500 g','0.5000',NULL,NULL,'0','2026-09-26 21:52:55'),
('150','33','compra','barra','1.0000',NULL,NULL,'1','2026-09-26 21:52:55'),
('151','33','venta','kg','1.0000','15500.00',NULL,'1','2026-09-26 21:52:55'),
('152','33','venta','100 g','0.1000',NULL,NULL,'0','2026-09-26 21:52:55'),
('153','33','venta','200 g','0.2000',NULL,NULL,'0','2026-09-26 21:52:55'),
('154','33','venta','250 g','0.2500',NULL,NULL,'0','2026-09-26 21:52:55'),
('155','33','venta','500 g','0.5000',NULL,NULL,'0','2026-09-26 21:52:55'),
('156','34','compra','barra','0.5000',NULL,NULL,'1','2026-09-26 21:52:55'),
('157','34','venta','kg','1.0000','9800.00',NULL,'1','2026-09-26 21:52:55'),
('158','34','venta','100 g','0.1000',NULL,NULL,'0','2026-09-26 21:52:55'),
('159','34','venta','200 g','0.2000',NULL,NULL,'0','2026-09-26 21:52:55'),
('160','34','venta','250 g','0.2500',NULL,NULL,'0','2026-09-26 21:52:55'),
('161','34','venta','500 g','0.5000',NULL,NULL,'0','2026-09-26 21:52:55'),
('162','35','compra','bidón','5.0000',NULL,NULL,'1','2026-09-26 21:52:55'),
('163','35','venta','kg','1.0000','8900.00',NULL,'1','2026-09-26 21:52:55'),
('164','35','venta','100 g','0.1000',NULL,NULL,'0','2026-09-26 21:52:55'),
('165','35','venta','200 g','0.2000',NULL,NULL,'0','2026-09-26 21:52:55'),
('166','35','venta','250 g','0.2500',NULL,NULL,'0','2026-09-26 21:52:55'),
('167','35','venta','500 g','0.5000',NULL,NULL,'0','2026-09-26 21:52:55'),
('174','37','compra','bolsa','10.0000','42000.00',NULL,'1','2026-09-26 21:52:55'),
('175','37','venta','kg','1.0000','6500.00',NULL,'1','2026-09-26 21:52:55'),
('176','37','venta','250 g','0.2500',NULL,NULL,'0','2026-09-26 21:52:55'),
('177','37','venta','500 g','0.5000',NULL,NULL,'0','2026-09-26 21:52:55'),
('178','38','compra','bolsa','10.0000','39000.00',NULL,'1','2026-09-26 21:52:55'),
('179','38','venta','kg','1.0000','6000.00',NULL,'1','2026-09-26 21:52:55'),
('180','38','venta','250 g','0.2500',NULL,NULL,'0','2026-09-26 21:52:55'),
('181','38','venta','500 g','0.5000',NULL,NULL,'0','2026-09-26 21:52:55'),
('182','39','compra','docena','12.0000','11400.00',NULL,'1','2026-09-26 21:52:55'),
('183','39','venta','unidad','1.0000','1500.00',NULL,'1','2026-09-26 21:52:55'),
('184','39','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('185','39','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('186','39','venta','4 u','4.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('187','39','venta','5 u','5.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('188','39','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('189','39','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('190','40','compra','docena','12.0000','9840.00',NULL,'1','2026-09-26 21:52:55'),
('191','40','venta','unidad','1.0000','1300.00',NULL,'1','2026-09-26 21:52:55'),
('192','40','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('193','40','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('194','40','venta','4 u','4.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('195','40','venta','6 unidades','6.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('196','40','venta','docena','12.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('197','41','compra','bidón','5.0000','32000.00',NULL,'1','2026-09-26 21:52:55'),
('198','41','venta','litro','1.0000','9800.00',NULL,'1','2026-09-26 21:52:55'),
('199','41','venta','500 mL','0.5000',NULL,NULL,'0','2026-09-26 21:52:55'),
('200','41','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('201','41','venta','5 u','5.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('202','42','compra','bidón','5.0000','38000.00',NULL,'1','2026-09-26 21:52:55'),
('203','42','venta','litro','1.0000','11500.00',NULL,'1','2026-09-26 21:52:55'),
('204','42','venta','500 mL','0.5000',NULL,NULL,'0','2026-09-26 21:52:55'),
('205','42','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('206','42','venta','5 u','5.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('207','43','compra','bidón','5.0000','41500.00',NULL,'1','2026-09-26 21:52:55'),
('208','43','venta','litro','1.0000','12500.00',NULL,'1','2026-09-26 21:52:55'),
('209','43','venta','500 mL','0.5000',NULL,NULL,'0','2026-09-26 21:52:55'),
('210','43','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('211','43','venta','5 u','5.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('212','44','compra','bidón','5.0000','13000.00',NULL,'1','2026-09-26 21:52:55'),
('213','44','venta','litro','1.0000','4200.00',NULL,'1','2026-09-26 21:52:55'),
('214','44','venta','500 mL','0.5000',NULL,NULL,'0','2026-09-26 21:52:55'),
('215','44','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('216','44','venta','5 u','5.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('217','45','compra','bidón','5.0000','12000.00',NULL,'1','2026-09-26 21:52:55'),
('218','45','venta','litro','1.0000','3900.00',NULL,'1','2026-09-26 21:52:55'),
('219','45','venta','500 mL','0.5000',NULL,NULL,'0','2026-09-26 21:52:55'),
('220','45','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('221','45','venta','5 u','5.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('222','46','compra','caja','12.0000','11760.00',NULL,'1','2026-09-26 21:52:55'),
('223','46','venta','pieza','1.0000','1450.00',NULL,'1','2026-09-26 21:52:55'),
('224','46','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('225','46','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('226','47','compra','caja','12.0000','17040.00',NULL,'1','2026-09-26 21:52:55'),
('227','47','venta','pieza','1.0000','2100.00',NULL,'1','2026-09-26 21:52:55'),
('228','47','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('229','47','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('230','48','compra','caja','12.0000','26160.00',NULL,'1','2026-09-26 21:52:55'),
('231','48','venta','pieza','1.0000','3250.00',NULL,'1','2026-09-26 21:52:55'),
('232','48','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('233','48','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('234','49','compra','caja','12.0000','36600.00',NULL,'1','2026-09-26 21:52:55'),
('235','49','venta','pieza','1.0000','4500.00',NULL,'1','2026-09-26 21:52:55'),
('236','49','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('237','49','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('238','50','compra','caja','12.0000','18960.00',NULL,'1','2026-09-26 21:52:55'),
('239','50','venta','pieza','1.0000','2350.00',NULL,'1','2026-09-26 21:52:55'),
('240','50','venta','2x1','2.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('241','50','venta','3x2','3.0000',NULL,NULL,'0','2026-09-26 21:52:55'),
('265','36','compra','maple','30.0000','5000.00',NULL,'1','2026-09-26 22:19:17'),
('266','36','venta','unidad','1.0000','250.00',NULL,'1','2026-09-26 22:19:17'),
('267','36','venta','6 unidades','6.0000','1500.00',NULL,'0','2026-09-26 22:19:17'),
('268','36','venta','docena','12.0000','2500.00',NULL,'0','2026-09-26 22:19:17'),
('269','51','compra','caja x24','24.0000','20000.00',NULL,'1','2026-09-26 22:19:17'),
('270','51','venta','unidad','1.0000',NULL,'30.00','1','2026-09-26 22:19:17'),
('271','51','venta','pack x6','6.0000',NULL,'30.00','0','2026-09-26 22:19:17');

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
('1','Distribuidora del Centro',NULL,NULL,'Proveedor de ejemplo del sistema.','1','2026-09-26 22:19:05');

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
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `usuarios` VALUES
('1','admin','Administrador','$2y$10$d7YZnz.InOQjbJr73q.3ouDHIKUp3d3WVRLOHgD9Ye9/5Ebn66emi','admin','1',1,'2026-09-26 21:20:13','2026-09-27 13:15:08');

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
  `formato_id` int unsigned DEFAULT NULL,
  `formato_unidad` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `formato_cantidad` decimal(12,3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_venta` (`venta_id`),
  KEY `ix_producto` (`producto_id`),
  CONSTRAINT `fk_item_venta` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `caja_id` int unsigned DEFAULT NULL,
  `medio_pago_id` smallint unsigned DEFAULT NULL,
  `anulada_por` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `envio` decimal(12,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_folio` (`folio`),
  KEY `ix_fecha` (`fecha`),
  KEY `ix_anulada` (`anulada`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `zonas` VALUES
('1','Centro','0.00','1','1'),
('2','Barrio Norte','500.00','1','2'),
('3','Barrio Sur','800.00','1','3');

SET FOREIGN_KEY_CHECKS = 1;
-- Total de registros respaldados: 324
