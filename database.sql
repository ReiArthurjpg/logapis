-- ===================================================================
-- Estrutura do Banco de Dados - Sistema LOGMOV
-- Tabela de Usuários com suporte a Autenticação e Recuperação de Senha
-- ===================================================================

CREATE DATABASE IF NOT EXISTS `logmov_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `logmov_db`;

DROP TABLE IF EXISTS `usuarios`;

CREATE TABLE `usuarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `senha` VARCHAR(255) NOT NULL,
  `tipo` ENUM('admin', 'empresa', 'motoboy') NOT NULL DEFAULT 'admin',
  `status` ENUM('ativo', 'inativo', 'pendente') NOT NULL DEFAULT 'ativo',
  `reset_token` VARCHAR(255) DEFAULT NULL,
  `reset_expires_at` DATETIME DEFAULT NULL,
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_usuarios_email` (`email`),
  INDEX `idx_usuarios_reset_token` (`reset_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- Tabela de Blacklist de Tokens JWT Invalidados (Logout)
-- ===================================================================
CREATE TABLE IF NOT EXISTS `token_blacklist` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `token_hash` VARCHAR(64) NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_token_hash` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- Tabela de Empresas Parceiras
-- ===================================================================
CREATE TABLE IF NOT EXISTS `empresas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `responsavel` VARCHAR(150) NOT NULL,
  `empresa` VARCHAR(150) NOT NULL,
  `cnpj` VARCHAR(20) NOT NULL UNIQUE,
  `whatsapp` VARCHAR(20) DEFAULT NULL,
  `cidade_uf` VARCHAR(100) DEFAULT NULL,
  `endereco` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('Aprovado', 'Pendente', 'Suspenso') NOT NULL DEFAULT 'Aprovado',
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_empresas_cnpj` (`cnpj`),
  INDEX `idx_empresas_status` (`status`),
  INDEX `idx_empresas_busca` (`empresa`, `responsavel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
