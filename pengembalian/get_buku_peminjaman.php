<?php
session_start();
require_once '../lib/functions.php';
require_once '../lib/auth.php';
require_once '../config/database.php';

requireAuth();
requireModuleAccess('pengembalian');

$peminjaman_id = (int)($_GET['peminjaman_id'] ?? 0);

if ($peminjaman_id > 0) {
    $query = "SELECT pd.id, pd.judul_buku, pd.kode_buku, pd.pengarang, pd.cover, b.stok_tersedia
              FROM peminjaman_detail pd
              LEFT JOIN buku b ON pd.buku_id = b.id
              WHERE pd.peminjaman_id = ? AND pd.status_buku = 'dipinjam'
              ORDER BY pd.judul_buku";
    
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $peminjaman_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th width="30">
                            <input type="checkbox" id="select-all-books" onchange="toggleAllBooks(this.checked)">
                        </th>
                        <th width="80">Cover</th>
                        <th>Judul Buku</th>
                        <th>Kode Buku</th>
                        <th>Pengarang</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($buku = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="buku_ids[]" value="<?= $buku['id'] ?>" 
                                   class="buku-checkbox" onchange="updateSelectedCount()">
                        </td>
                        <td>
                            <?php if (!empty($buku['cover'])): ?>
                                <img src="../uploads/cover/<?= htmlspecialchars($buku['cover']) ?>" 
                                     class="img-thumbnail" style="width: 60px; height: 80px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-light d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 80px;">
                                    <i class="fas fa-book text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($buku['judul_buku']) ?></td>
                        <td><?= htmlspecialchars($buku['kode_buku']) ?></td>
                        <td><?= htmlspecialchars($buku['pengarang'] ?? '-') ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <div class="alert alert-info mt-3">
            <i class="fas fa-info-circle me-2"></i>
            Centang buku yang akan dikembalikan. Anda dapat mengembalikan sebagian atau semua buku.
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Tidak ada buku yang sedang dipinjam untuk peminjaman ini.
        </div>
    <?php endif;
    
    mysqli_stmt_close($stmt);
} else {
    echo '<div class="alert alert-danger">ID peminjaman tidak valid.</div>';
}
?>