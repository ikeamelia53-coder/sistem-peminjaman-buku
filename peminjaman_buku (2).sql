-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 03, 2026 at 08:35 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `peminjaman_buku`
--

-- --------------------------------------------------------

--
-- Table structure for table `anggota`
--

CREATE TABLE `anggota` (
  `id` int(11) NOT NULL,
  `kode_anggota` varchar(20) NOT NULL,
  `nama_anggota` varchar(100) NOT NULL,
  `jenis_kelamin` enum('L','P') DEFAULT NULL,
  `tempat_lahir` varchar(50) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `telepon` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `pekerjaan` varchar(50) DEFAULT NULL,
  `status` enum('aktif','nonaktif','diblokir') DEFAULT 'aktif',
  `tanggal_daftar` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `anggota`
--

INSERT INTO `anggota` (`id`, `kode_anggota`, `nama_anggota`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `alamat`, `telepon`, `email`, `pekerjaan`, `status`, `tanggal_daftar`, `created_at`) VALUES
(1, 'AGT001', 'Ahmad Fauzi', 'L', 'Jakarta', '1995-03-15', 'Jl. Merdeka No. 123, Jakarta Pusat', '081234567890', 'ahmad@gmail.com', 'Mahasiswa', 'aktif', '2025-01-10', '2025-01-10 00:00:00'),
(2, 'AGT002', 'Siti Nurhaliza', 'P', 'Bandung', '1998-07-20', 'Jl. Asia Afrika No. 45, Bandung', '082345678901', 'siti@yahoo.com', 'Guru', 'aktif', '2025-01-12', '2025-01-12 00:00:00'),
(3, 'AGT003', 'Budi Santoso', 'L', 'Surabaya', '1993-11-05', 'Jl. Diponegoro No. 78, Surabaya', '083456789012', 'budi@email.com', 'Pegawai Swasta', 'aktif', '2025-01-15', '2025-01-15 00:00:00'),
(4, 'AGT004', 'Dewi Lestari', 'P', 'Yogyakarta', '1997-04-25', 'Jl. Malioboro No. 12, Yogyakarta', '084567890123', 'dewi@gmail.com', 'Dosen', 'aktif', '2025-01-18', '2025-01-18 00:00:00'),
(5, 'AGT005', 'Eko Prasetyo', 'L', 'Semarang', '1990-09-12', 'Jl. Gajah Mada No. 34, Semarang', '085678901234', 'eko@yahoo.com', 'Wiraswasta', 'aktif', '2025-01-20', '2025-01-20 00:00:00'),
(6, 'AGT006', 'Fitriani', 'P', 'Medan', '1996-12-30', 'Jl. Sudirman No. 56, Medan', '086789012345', 'fitri@email.com', 'Perawat', 'aktif', '2025-01-22', '2025-01-22 00:00:00'),
(7, 'AGT007', 'Guntur Wijaya', 'L', 'Makassar', '1994-06-18', 'Jl. Urip Sumoharjo No. 89, Makassar', '087890123456', 'guntur@gmail.com', 'Programmer', 'aktif', '2025-01-25', '2025-01-25 00:00:00'),
(8, 'AGT008', 'Hana Putri', 'P', 'Denpasar', '1999-02-14', 'Jl. Hayam Wuruk No. 23, Denpasar', '088901234567', 'hana@email.com', 'Pelajar', 'aktif', '2025-01-28', '2025-01-28 00:00:00'),
(9, 'AGT009', 'Irfan Maulana', 'L', 'Malang', '1992-08-08', 'Jl. Ijen No. 67, Malang', '089012345678', 'irfan@yahoo.com', 'Dokter', 'aktif', '2025-02-01', '2025-02-01 00:00:00'),
(10, 'AGT010', 'Julia Sari', 'P', 'Solo', '1995-05-19', 'Jl. Slamet Riyadi No. 90, Solo', '081112223333', 'julia@gmail.com', 'Apoteker', 'aktif', '2025-02-05', '2025-02-05 00:00:00'),
(11, 'AGT011', 'Kurniawan', 'L', 'Palembang', '1991-10-22', 'Jl. Jenderal Sudirman No. 45, Palembang', '081223334444', 'kurnia@email.com', 'Pengacara', 'aktif', '2025-02-10', '2025-02-10 00:00:00'),
(12, 'AGT012', 'Lina Marlina', 'P', 'Banjarmasin', '1998-03-07', 'Jl. Pangeran Antasari No. 12, Banjarmasin', '081334445555', 'lina@yahoo.com', 'Arsitek', 'aktif', '2025-02-15', '2025-02-15 00:00:00'),
(13, 'AGT013', 'Muhammad Ali', 'L', 'Padang', '1993-07-29', 'Jl. Pemuda No. 34, Padang', '081445556666', 'ali@gmail.com', 'Wartawan', 'aktif', '2025-02-20', '2025-02-20 00:00:00'),
(14, 'AGT014', 'Nina Widya', 'P', 'Manado', '1997-01-11', 'Jl. Sam Ratulangi No. 56, Manado', '081556667777', 'nina@email.com', 'Akuntan', 'aktif', '2025-02-25', '2025-02-25 00:00:00'),
(15, 'AGT015', 'Oki Setiawan', 'L', 'Balikpapan', '1994-04-16', 'Jl. MT Haryono No. 78, Balikpapan', '081667778888', 'oki@yahoo.com', 'Insinyur', 'aktif', '2025-03-01', '2025-03-01 00:00:00'),
(16, 'AGT016', 'Putri Ayu', 'P', 'Bogor', '1999-09-03', 'Jl. Suryakencana No. 90, Bogor', '081778889999', 'putri@gmail.com', 'Desainer', 'aktif', '2025-03-05', '2025-03-05 00:00:00'),
(17, 'AGT017', 'Rizki Pratama', 'L', 'Cirebon', '1996-12-27', 'Jl. Kartini No. 11, Cirebon', '081889990000', 'rizki@email.com', 'Konsultan', 'aktif', '2025-03-10', '2025-03-10 00:00:00'),
(18, 'AGT018', 'Sari Dewi', 'P', 'Tangerang', '1992-06-14', 'Jl. Benteng No. 22, Tangerang', '081990001111', 'sari@yahoo.com', 'Peneliti', 'aktif', '2025-03-15', '2025-03-15 00:00:00'),
(19, 'AGT019', 'Teguh Wijaya', 'L', 'Bekasi', '1990-11-08', 'Jl. Ahmad Yani No. 33, Bekasi', '082001112222', 'teguh@gmail.com', 'Manager', 'aktif', '2025-03-20', '2025-03-20 00:00:00'),
(20, 'AGT020', 'Umi Kulsum', 'P', 'Depok', '1995-02-19', 'Jl. Margonda No. 44, Depok', '082112223333', 'umi@email.com', 'Pustakawan', 'aktif', '2025-03-25', '2025-03-25 00:00:00'),
(21, 'AGT021', 'Sarah Kang', 'P', 'Cirebon', '2000-02-12', 'Jl. Anggrek No.06', '0895327832766', 'thisissarah12@gmail.com', 'Writer', 'aktif', '2026-02-01', '2026-02-01 09:33:00');

-- --------------------------------------------------------

--
-- Table structure for table `aturan`
--

CREATE TABLE `aturan` (
  `id` int(11) NOT NULL,
  `max_pinjaman` int(2) NOT NULL DEFAULT 3,
  `max_hari` int(3) NOT NULL DEFAULT 7,
  `denda_per_hari` decimal(10,2) NOT NULL DEFAULT 5000.00,
  `max_keterlambatan` int(3) NOT NULL DEFAULT 30,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `buku`
--

CREATE TABLE `buku` (
  `id` int(11) NOT NULL,
  `kode_buku` varchar(20) NOT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `judul_buku` varchar(255) NOT NULL,
  `pengarang` varchar(255) NOT NULL,
  `penerbit` varchar(255) NOT NULL,
  `tahun_terbit` year(4) NOT NULL,
  `kategori` varchar(100) NOT NULL,
  `bahasa` varchar(50) DEFAULT 'Indonesia',
  `halaman` int(5) DEFAULT NULL,
  `sinopsis` text DEFAULT NULL,
  `stok_total` int(5) NOT NULL DEFAULT 1,
  `stok_tersedia` int(5) NOT NULL DEFAULT 1,
  `status` enum('tersedia','dipinjam','hilang','rusak') DEFAULT 'tersedia',
  `lokasi_rak` varchar(50) DEFAULT NULL,
  `cover` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `buku`
--

INSERT INTO `buku` (`id`, `kode_buku`, `isbn`, `judul_buku`, `pengarang`, `penerbit`, `tahun_terbit`, `kategori`, `bahasa`, `halaman`, `sinopsis`, `stok_total`, `stok_tersedia`, `status`, `lokasi_rak`, `cover`, `created_at`) VALUES
(1, 'BK001', '9786020384390', 'Laskar Pelangi', 'Andrea Hirata', 'Bentang Pustaka', '2005', 'Fiksi', 'Indonesia', 529, '0', 10, 7, 'tersedia', 'RAK-A1', 'buku_20260119121600_1fb0a1e9.jpg', '2025-01-10 00:00:00'),
(2, 'BK002', '9789792254344', 'Bumi Manusia', 'Pramoedya Ananta Toer', 'Lentera Dipantara', '1980', 'Sejarah', 'Indonesia', 535, '0', 8, 5, 'tersedia', 'RAK-B2', 'buku_20260119121505_f07360ec.jpg', '2025-01-12 00:00:00'),
(3, 'BK003', '9789792229144', 'Perahu Kertas', 'Dee Lestari', 'Bentang Pustaka', '2009', 'Romantis', 'Indonesia', 444, '0', 12, 8, 'tersedia', 'RAK-C3', 'buku_20260119121345_c77924fa.jpg', '2025-01-15 00:00:00'),
(4, 'BK004', '9789793062792', 'Sang Pemimpi', 'Andrea Hirata', 'Bentang Pustaka', '2006', 'Fiksi', 'Indonesia', 292, '0', 9, 7, 'tersedia', 'RAK-A1', 'buku_20260119121244_6810ae2e.png', '2025-01-18 00:00:00'),
(5, 'BK005', '9786020324815', 'Negeri 5 Menara', 'Ahmad Fuadi', 'Gramedia Pustaka Utama', '2009', 'Inspiratif', 'Indonesia', 416, '0', 7, 4, 'tersedia', 'RAK-D4', 'buku_20260119121106_8d1de449.jpg', '2025-01-20 00:00:00'),
(6, 'BK006', '9786022918847', 'Ronggeng Dukuh Paruk', 'Ahmad Tohari', 'Gramedia Pustaka Utama', '1982', 'Sastra', 'Indonesia', 272, '0', 6, 3, 'tersedia', 'RAK-E5', 'buku_20260119120921_d4b93a25.jpg', '2025-01-22 00:00:00'),
(7, 'BK007', '9786020306149', 'Ayat-Ayat Cinta', 'Habiburrahman El Shirazy', 'Republika', '2004', 'Religi', 'Indonesia', 418, '0', 15, 11, 'tersedia', 'RAK-F6', 'buku_20260119120813_37322d23.webp', '2025-01-25 00:00:00'),
(8, 'BK008', '9786020331776', 'Cantik Itu Luka', 'Eka Kurniawan', 'Gramedia Pustaka Utama', '2002', 'Fiksi', 'Indonesia', 520, '0', 5, 2, 'tersedia', 'RAK-G7', 'buku_20260119120643_2225e773.jpg', '2025-01-28 00:00:00'),
(9, 'BK009', '9786024260695', 'Pulang', 'Leila S. Chudori', 'Kepustakaan Populer Gramedia', '2012', 'Sejarah', 'Indonesia', 488, '0', 8, 6, 'tersedia', 'RAK-B2', 'buku_20260119120540_d5180766.jpg', '2025-02-01 00:00:00'),
(10, 'BK010', '9786024246941', 'Laut Bercerita', 'Leila S. Chudori', 'Kepustakaan Populer Gramedia', '2017', 'Sejarah', 'Indonesia', 392, '0', 6, 3, 'tersedia', 'RAK-B2', 'buku_20260119120421_25494a11.jpg', '2025-02-05 00:00:00'),
(11, 'BK011', '9786020382167', 'Supernova: Kesatria, Putri, dan Bintang Jatuh', 'Dee Lestari', 'Bentang Pustaka', '2001', 'Fiksi Sains', 'Indonesia', 298, '0', 10, 5, 'tersedia', 'RAK-H8', 'buku_20260119120321_4c640d50.jpg', '2025-02-10 00:00:00'),
(12, 'BK012', '9786024261753', 'Gadis Kretek', 'Ratih Kumala', 'Gramedia Pustaka Utama', '2012', 'Sejarah', 'Indonesia', 328, '0', 7, 5, 'tersedia', 'RAK-B2', 'buku_20260119120043_70eedf9c.jpg', '2025-02-15 00:00:00'),
(13, 'BK013', '9789797808520', 'Critical Eleven', 'Ika Natassa', 'Gramedia Pustaka Utama', '2013', 'Romantis', 'Indonesia', 376, '0', 9, 6, 'tersedia', 'RAK-C3', 'buku_20260119115945_36c2face.jpg', '2025-02-20 00:00:00'),
(14, 'BK014', '9786020327281', 'Marmut Merah Jambu', 'Raditya Dika', 'Gagas Media', '2010', 'Komedi', 'Indonesia', 240, '0', 11, 9, 'tersedia', 'RAK-I9', 'buku_20260119115803_6a0fdf2a.jpg', '2025-02-25 00:00:00'),
(15, 'BK015', '9786023755184', 'Sepotong Hati yang Baru', 'Tere Liye', 'Republika', '2014', 'Inspiratif', 'Indonesia', 252, '0', 8, 4, 'tersedia', 'RAK-D4', 'buku_20260119115658_6578969c.jpg', '2025-03-01 00:00:00'),
(16, 'BK016', '9786024251198', 'Harry Potter dan Batu Bertuah', 'J.K. Rowling', 'Gramedia Pustaka Utama', '1997', 'Fantasi', 'Indonesia', 309, '0', 20, 16, 'tersedia', 'RAK-J10', 'buku_20260119115546_66b583fa.jpg', '2025-03-05 00:00:00'),
(17, 'BK017', '9786020312408', 'Harry Potter dan Kamar Rahasia', 'J.K. Rowling', 'Gramedia Pustaka Utama', '1998', 'Fantasi', 'Indonesia', 341, '0', 18, 13, 'tersedia', 'RAK-J10', 'buku_20260119115350_d1b246f8.jpg', '2025-03-10 00:00:00'),
(18, 'BK018', '9789792270016', 'Harry Potter dan Tawanan Azkaban', 'J.K. Rowling', 'Gramedia Pustaka Utama', '1999', 'Fantasi', 'Indonesia', 435, '0', 16, 11, 'tersedia', 'RAK-J10', 'buku_20260119115048_f30a59c7.jpg', '2025-03-15 00:00:00'),
(19, 'BK019', '9786020389944', 'The Da Vinci Code', 'Dan Brown', 'Gramedia Pustaka Utama', '2003', 'Misteri', 'Indonesia', 489, '0', 14, 8, 'tersedia', 'RAK-K11', 'buku_20260119114848_08b753d3.jpg', '2025-03-20 00:00:00'),
(20, 'BK020', '9786020314822', 'Angels & Demons', 'Dan Brown', 'Gramedia Pustaka Utama', '2000', 'Misteri', 'Indonesia', 572, '0', 12, 7, 'tersedia', 'RAK-K11', 'buku_20260119114717_cf9031c4.jpg', '2025-03-25 00:00:00'),
(21, 'BK-021', '9786020384391', 'The Five People You Meet in Heaven', 'Mitch Albom', 'DoubleDay', '2001', 'Klasik', 'Indonesia', 207, 'Menceritakan tentang Eddie yang meninggal dan bertemu 5 orang di perjalanannya menuju surga', 50, 35, 'dipinjam', 'RAK-A1', 'buku_20260201210452_e620451f.jpg', '2026-01-19 04:02:00');

-- --------------------------------------------------------

--
-- Table structure for table `peminjaman`
--

CREATE TABLE `peminjaman` (
  `id` int(11) NOT NULL,
  `kode_peminjaman` varchar(20) NOT NULL,
  `nama_anggota` varchar(100) NOT NULL,
  `tanggal_pinjam` date NOT NULL,
  `tanggal_harus_kembali` date NOT NULL,
  `status` enum('dipinjam','dikembalikan','terlambat','hilang','rusak') DEFAULT 'dipinjam',
  `judul_buku` varchar(255) NOT NULL,
  `cover` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `peminjaman`
--

INSERT INTO `peminjaman` (`id`, `kode_peminjaman`, `nama_anggota`, `tanggal_pinjam`, `tanggal_harus_kembali`, `status`, `judul_buku`, `cover`) VALUES
(4, 'PJM004', 'Dewi Lestari', '2026-01-08', '2026-01-15', 'dikembalikan', '', 'buku_20260119115048_f30a59c7.jpg'),
(6, 'PJM006', 'Ahmad Fauzi', '2026-01-10', '2026-01-17', 'dikembalikan', '', ''),
(8, 'PJM008', 'Budi Santoso', '2026-01-12', '2026-01-19', 'dikembalikan', '', ''),
(31, 'PJM11', 'Siti Nurhaliza', '2026-02-01', '2026-02-08', 'dikembalikan', '', ''),
(34, 'PJM12', 'Sarah Kang', '2026-02-07', '2026-02-07', 'dikembalikan', '', 'buku_20260119120321_4c640d50.jpg'),
(36, 'PJM308', 'Eko Prasetyo', '2026-02-02', '2026-02-09', 'dipinjam', '', ''),
(37, 'PJM309', 'Oki Setiawan', '2026-02-03', '2026-02-10', 'dipinjam', '', 'buku_20260119121106_8d1de449.jpg'),
(44, 'PJM310', 'Nina Widya', '2026-02-03', '2026-02-10', 'dipinjam', '', 'buku_20260119121600_1fb0a1e9.jpg'),
(49, '0', 'Sari Dewi', '2026-02-03', '2026-02-10', 'dipinjam', '', 'buku_20260119120643_2225e773.jpg'),
(50, 'PJM311', 'Umi Kulsum', '2026-02-03', '2026-02-10', 'dipinjam', '', 'buku_20260119120921_d4b93a25.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `peminjaman_detail`
--

CREATE TABLE `peminjaman_detail` (
  `id` int(11) NOT NULL,
  `peminjaman_id` int(11) NOT NULL,
  `buku_id` int(11) NOT NULL,
  `judul_buku` varchar(255) NOT NULL,
  `cover` varchar(255) DEFAULT NULL,
  `qty` int(11) DEFAULT 1,
  `status_buku` enum('dipinjam','dikembalikan') DEFAULT 'dipinjam',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `pengarang` varchar(255) DEFAULT NULL,
  `kode_buku` int(255) NOT NULL,
  `total_buku` int(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `peminjaman_detail`
--

INSERT INTO `peminjaman_detail` (`id`, `peminjaman_id`, `buku_id`, `judul_buku`, `cover`, `qty`, `status_buku`, `created_at`, `pengarang`, `kode_buku`, `total_buku`) VALUES
(7, 31, 2, 'Bumi Manusia', 'buku_20260119121505_f07360ec.jpg', 1, 'dipinjam', '2026-02-02 11:03:53', 'Pramoedya Ananta Toer', 0, 0),
(8, 8, 13, 'Critical Eleven', 'buku_20260119115945_36c2face.jpg', 1, 'dipinjam', '2026-02-02 11:04:21', 'Ika Natassa', 0, 0),
(9, 8, 7, 'Ayat-Ayat Cinta', 'buku_20260119120813_37322d23.webp', 1, 'dipinjam', '2026-02-02 11:04:21', 'Habiburrahman El Shirazy', 0, 0),
(10, 8, 20, 'Angels & Demons', 'buku_20260119114717_cf9031c4.jpg', 1, 'dipinjam', '2026-02-02 11:04:21', 'Dan Brown', 0, 0),
(11, 6, 17, 'Harry Potter dan Kamar Rahasia', 'buku_20260119115350_d1b246f8.jpg', 1, 'dipinjam', '2026-02-02 11:05:23', 'J.K. Rowling', 0, 0),
(13, 36, 19, 'The Da Vinci Code', 'buku_20260119114848_08b753d3.jpg', 1, 'dipinjam', '2026-02-02 14:21:32', 'Dan Brown', 0, 0),
(14, 36, 18, 'Harry Potter dan Tawanan Azkaban', 'buku_20260119115048_f30a59c7.jpg', 1, 'dikembalikan', '2026-02-02 14:21:32', 'J.K. Rowling', 0, 0),
(17, 4, 18, 'Harry Potter dan Tawanan Azkaban', 'buku_20260119115048_f30a59c7.jpg', 1, 'dipinjam', '2026-02-02 18:45:00', 'J.K. Rowling', 0, 0),
(18, 4, 10, 'Laut Bercerita', 'buku_20260119120421_25494a11.jpg', 1, 'dipinjam', '2026-02-02 18:45:00', 'Leila S. Chudori', 0, 0),
(19, 37, 5, 'Negeri 5 Menara', 'buku_20260119121106_8d1de449.jpg', 1, 'dikembalikan', '2026-02-02 19:22:01', 'Ahmad Fuadi', 0, 0),
(20, 37, 3, 'Perahu Kertas', 'buku_20260119121345_c77924fa.jpg', 1, 'dipinjam', '2026-02-02 19:22:01', 'Dee Lestari', 0, 0),
(31, 44, 1, 'Laskar Pelangi', 'buku_20260119121600_1fb0a1e9.jpg', 1, 'dipinjam', '2026-02-02 19:45:22', 'Andrea Hirata', 0, 0),
(34, 49, 8, 'Cantik Itu Luka', 'buku_20260119120643_2225e773.jpg', 1, 'dipinjam', '2026-02-02 23:33:58', 'Eka Kurniawan', 0, 0),
(35, 50, 6, 'Ronggeng Dukuh Paruk', 'buku_20260119120921_d4b93a25.jpg', 1, 'dipinjam', '2026-02-02 23:48:03', 'Ahmad Tohari', 0, 0),
(40, 34, 11, 'Supernova: Kesatria, Putri, dan Bintang Jatuh', 'buku_20260119120321_4c640d50.jpg', 1, 'dipinjam', '2026-02-03 07:30:40', 'Dee Lestari', 0, 0),
(41, 34, 10, 'Laut Bercerita', 'buku_20260119120421_25494a11.jpg', 1, 'dipinjam', '2026-02-03 07:30:40', 'Leila S. Chudori', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `pengembalian`
--

CREATE TABLE `pengembalian` (
  `id` int(11) NOT NULL,
  `kode_pengembalian` varchar(50) NOT NULL,
  `peminjaman_id` int(11) NOT NULL,
  `tanggal_kembali` date NOT NULL,
  `jumlah_hari_terlambat` int(4) DEFAULT 0,
  `denda` decimal(10,2) DEFAULT 0.00,
  `kondisi_buku` enum('baik','rusak_ringan','rusak_berat','hilang') DEFAULT 'baik',
  `keterangan` text DEFAULT NULL,
  `petugas_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cover` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pengembalian`
--

INSERT INTO `pengembalian` (`id`, `kode_pengembalian`, `peminjaman_id`, `tanggal_kembali`, `jumlah_hari_terlambat`, `denda`, `kondisi_buku`, `keterangan`, `petugas_id`, `created_at`, `cover`) VALUES
(4, 'KMB004', 6, '2026-01-20', 3, 15000.00, 'baik', 'Buku dalam kondisi baik', 3, '2026-01-20 11:00:00', '1769908844_697eaa6c7ab56.jpg'),
(12, 'KMB20260201939', 31, '2026-02-01', 0, 0.00, 'baik', 'buku dikembalikan dengan kondisi yang baik', 1, '2026-02-01 09:15:28', 'cover_1769937328_697f19b070b1a.jpg'),
(62, 'KMB2026020314145182', 36, '2026-02-03', 0, 0.00, 'baik', '', 1, '2026-02-03 07:18:00', '');

-- --------------------------------------------------------

--
-- Table structure for table `pengembalian_detail`
--

CREATE TABLE `pengembalian_detail` (
  `id` int(11) NOT NULL,
  `pengembalian_id` int(11) NOT NULL,
  `peminjaman_detail_id` int(11) NOT NULL,
  `kondisi_buku` enum('baik','rusak_ringan','rusak_berat','hilang') DEFAULT 'baik',
  `denda_buku` decimal(10,2) DEFAULT 0.00,
  `cover` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pengembalian_detail`
--

INSERT INTO `pengembalian_detail` (`id`, `pengembalian_id`, `peminjaman_detail_id`, `kondisi_buku`, `denda_buku`, `cover`) VALUES
(11, 62, 14, 'baik', 0.00, 'buku_20260119115048_f30a59c7.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','petugas') DEFAULT 'petugas',
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `email`, `role`, `status`, `created_at`) VALUES
(1, 'admin', '$2a$12$62bib0pHgZoTeQ.ODqnxheEPcN9l.aC1RYmB8c0IseMcVs35mDNtm', 'Administrator Sistem', 'admin@perpustakaan.com', 'admin', 'aktif', '2026-01-01 00:00:00'),
(2, 'petugas1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Siti Rahayu', 'siti@perpustakaan.com', 'petugas', 'aktif', '2026-01-02 00:00:00'),
(3, 'petugas2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Budi Santoso', 'budi@perpustakaan.com', 'petugas', 'aktif', '2026-01-03 00:00:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `anggota`
--
ALTER TABLE `anggota`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_anggota` (`kode_anggota`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `aturan`
--
ALTER TABLE `aturan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `buku`
--
ALTER TABLE `buku`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_buku` (`kode_buku`),
  ADD UNIQUE KEY `isbn` (`isbn`);

--
-- Indexes for table `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_peminjaman` (`kode_peminjaman`);

--
-- Indexes for table `peminjaman_detail`
--
ALTER TABLE `peminjaman_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_peminjaman_id` (`peminjaman_id`),
  ADD KEY `idx_buku_id` (`buku_id`);

--
-- Indexes for table `pengembalian`
--
ALTER TABLE `pengembalian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_pengembalian` (`kode_pengembalian`),
  ADD UNIQUE KEY `peminjaman_id` (`peminjaman_id`),
  ADD UNIQUE KEY `idx_kode_unique` (`kode_pengembalian`),
  ADD KEY `petugas_id` (`petugas_id`);

--
-- Indexes for table `pengembalian_detail`
--
ALTER TABLE `pengembalian_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pengembalian_id` (`pengembalian_id`),
  ADD KEY `peminjaman_detail_id` (`peminjaman_detail_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `anggota`
--
ALTER TABLE `anggota`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `aturan`
--
ALTER TABLE `aturan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `buku`
--
ALTER TABLE `buku`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `peminjaman`
--
ALTER TABLE `peminjaman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `peminjaman_detail`
--
ALTER TABLE `peminjaman_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `pengembalian`
--
ALTER TABLE `pengembalian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `pengembalian_detail`
--
ALTER TABLE `pengembalian_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `peminjaman_detail`
--
ALTER TABLE `peminjaman_detail`
  ADD CONSTRAINT `peminjaman_detail_ibfk_1` FOREIGN KEY (`peminjaman_id`) REFERENCES `peminjaman` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `peminjaman_detail_ibfk_2` FOREIGN KEY (`buku_id`) REFERENCES `buku` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pengembalian`
--
ALTER TABLE `pengembalian`
  ADD CONSTRAINT `pengembalian_ibfk_1` FOREIGN KEY (`peminjaman_id`) REFERENCES `peminjaman` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pengembalian_ibfk_2` FOREIGN KEY (`petugas_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `pengembalian_detail`
--
ALTER TABLE `pengembalian_detail`
  ADD CONSTRAINT `pengembalian_detail_ibfk_1` FOREIGN KEY (`pengembalian_id`) REFERENCES `pengembalian` (`id`),
  ADD CONSTRAINT `pengembalian_detail_ibfk_2` FOREIGN KEY (`peminjaman_detail_id`) REFERENCES `peminjaman_detail` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
