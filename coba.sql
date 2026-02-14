-- =============================================
-- phpMyAdmin SQL Dump
-- Version: 5.2.1
-- https://www.phpmyadmin.net/
-- 
-- Host: 127.0.0.1
-- Generation Time: Feb 06, 2026 at 06:00 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12
-- =============================================

-- =============================================
-- SETTING AWAL
-- =============================================
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- =============================================
-- SET CHARACTER SET
-- =============================================
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- =============================================
-- CREATE DATABASE
-- =============================================
CREATE DATABASE IF NOT EXISTS `peminjaman_buku`;
USE `peminjaman_buku`;

-- =============================================
-- TABLE: anggota (Tabel Anggota Perpustakaan)
-- =============================================
CREATE TABLE `anggota` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `kode_anggota` VARCHAR(20) NOT NULL,
  `nama_anggota` VARCHAR(100) NOT NULL,
  `jenis_kelamin` ENUM('L', 'P') DEFAULT NULL,
  `tempat_lahir` VARCHAR(50) DEFAULT NULL,
  `tanggal_lahir` DATE DEFAULT NULL,
  `alamat` TEXT DEFAULT NULL,
  `telepon` VARCHAR(15) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `pekerjaan` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('aktif', 'nonaktif', 'diblokir') DEFAULT 'aktif',
  `tanggal_daftar` DATE DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_anggota` (`kode_anggota`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- TABLE: aturan (Tabel Aturan Peminjaman)
-- =============================================
CREATE TABLE `aturan` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `max_pinjaman` INT(2) NOT NULL DEFAULT 3,
  `max_hari` INT(3) NOT NULL DEFAULT 7,
  `denda_per_hari` DECIMAL(10,2) NOT NULL DEFAULT 5000.00,
  `max_keterlambatan` INT(3) NOT NULL DEFAULT 30,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- TABLE: buku (Tabel Katalog Buku)
-- =============================================
CREATE TABLE `buku` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `kode_buku` VARCHAR(20) NOT NULL,
  `isbn` VARCHAR(20) DEFAULT NULL,
  `judul_buku` VARCHAR(255) NOT NULL,
  `pengarang` VARCHAR(255) NOT NULL,
  `penerbit` VARCHAR(255) NOT NULL,
  `tahun_terbit` YEAR(4) NOT NULL,
  `kategori` VARCHAR(100) NOT NULL,
  `bahasa` VARCHAR(50) DEFAULT 'Indonesia',
  `halaman` INT(5) DEFAULT NULL,
  `sinopsis` TEXT DEFAULT NULL,
  `stok_total` INT(5) NOT NULL DEFAULT 1,
  `stok_tersedia` INT(5) NOT NULL DEFAULT 1,
  `status` ENUM('tersedia', 'dipinjam', 'hilang', 'rusak') DEFAULT 'tersedia',
  `lokasi_rak` VARCHAR(50) DEFAULT NULL,
  `cover` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_buku` (`kode_buku`),
  UNIQUE KEY `isbn` (`isbn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- TABLE: peminjaman (Tabel Header Peminjaman)
-- =============================================
CREATE TABLE `peminjaman` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `kode_peminjaman` VARCHAR(20) NOT NULL,
  `nama_anggota` VARCHAR(100) NOT NULL,
  `tanggal_pinjam` DATE NOT NULL,
  `tanggal_harus_kembali` DATE NOT NULL,
  `status` ENUM('dipinjam', 'terlambat', 'dikembalikan', 'sebagian_dikembalikan') DEFAULT 'dipinjam',
  `judul_buku` VARCHAR(255) NOT NULL,
  `cover` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_peminjaman` (`kode_peminjaman`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- TABLE: peminjaman_detail (Tabel Detail Buku yang Dipinjam)
-- =============================================
CREATE TABLE `peminjaman_detail` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `peminjaman_id` INT(11) NOT NULL,
  `buku_id` INT(11) NOT NULL,
  `judul_buku` VARCHAR(255) NOT NULL,
  `cover` VARCHAR(255) DEFAULT NULL,
  `qty` INT(11) DEFAULT 1,
  `status_buku` ENUM('dipinjam', 'dikembalikan') DEFAULT 'dipinjam',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `pengarang` VARCHAR(255) DEFAULT NULL,
  `kode_buku` INT(255) NOT NULL,
  `total_buku` INT(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_peminjaman_id` (`peminjaman_id`),
  KEY `idx_buku_id` (`buku_id`),
  CONSTRAINT `peminjaman_detail_ibfk_1` FOREIGN KEY (`peminjaman_id`) REFERENCES `peminjaman` (`id`) ON DELETE CASCADE,
  CONSTRAINT `peminjaman_detail_ibfk_2` FOREIGN KEY (`buku_id`) REFERENCES `buku` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- TABLE: pengembalian (Tabel Header Pengembalian)
-- =============================================
CREATE TABLE `pengembalian` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `kode_pengembalian` VARCHAR(50) NOT NULL,
  `peminjaman_id` INT(11) NOT NULL,
  `tanggal_kembali` DATE NOT NULL,
  `jumlah_hari_terlambat` INT(4) DEFAULT 0,
  `denda` DECIMAL(10,2) DEFAULT 0.00,
  `kondisi_buku` ENUM('baik', 'rusak_ringan', 'rusak_berat', 'hilang') DEFAULT 'baik',
  `keterangan` TEXT DEFAULT NULL,
  `petugas_id` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `cover` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_pengembalian` (`kode_pengembalian`),
  UNIQUE KEY `idx_kode_unique` (`kode_pengembalian`),
  KEY `petugas_id` (`petugas_id`),
  KEY `peminjaman_id` (`peminjaman_id`),
  CONSTRAINT `pengembalian_ibfk_1` FOREIGN KEY (`peminjaman_id`) REFERENCES `peminjaman` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pengembalian_ibfk_2` FOREIGN KEY (`petugas_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- TABLE: pengembalian_detail (Tabel Detail Buku yang Dikembalikan)
-- =============================================
CREATE TABLE `pengembalian_detail` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `pengembalian_id` INT(11) NOT NULL,
  `peminjaman_detail_id` INT(11) NOT NULL,
  `kondisi_buku` ENUM('baik', 'rusak_ringan', 'rusak_berat', 'hilang') DEFAULT 'baik',
  `denda` DECIMAL(10,2) DEFAULT 0.00,
  `cover` VARCHAR(255) DEFAULT NULL,
  `keterangan` TEXT NOT NULL,
  `jumlah_hari_terlambat` INT(4) NOT NULL,
  `tanggal_dikembalikan` DATE DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pengembalian_id` (`pengembalian_id`),
  KEY `peminjaman_detail_id` (`peminjaman_detail_id`),
  CONSTRAINT `pengembalian_detail_ibfk_1` FOREIGN KEY (`pengembalian_id`) REFERENCES `pengembalian` (`id`),
  CONSTRAINT `pengembalian_detail_ibfk_2` FOREIGN KEY (`peminjaman_detail_id`) REFERENCES `peminjaman_detail` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- TABLE: users (Tabel Pengguna Sistem)
-- =============================================
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `nama_lengkap` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'pengguna') DEFAULT 'pengguna',
  `status` ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================
-- RESET CHARACTER SET
-- =============================================
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;