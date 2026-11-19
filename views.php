<?php
declare(strict_types=1);

function render_flash(?array $flash): string
{
    if ($flash === null) {
        return '';
    }
    $type = in_array($flash['type'] ?? '', ['success', 'error', 'warning'], true)
        ? $flash['type']
        : '';
    return '<div class="notice ' . e($type) . '" role="status">' .
        e($flash['message'] ?? '') . '</div>';
}

function render_auth_page(string $page, ?array $flash, string $resetToken = ''): void
{
    $register = $page === 'register';
    $title = match ($page) {
        'register' => 'Buat akun baru',
        'forgot' => 'Reset kata sandi',
        'reset' => 'Kata sandi baru',
        default => 'Masuk ke akun',
    };
    ?>
    <!doctype html>
    <html lang="id"><head>
      <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
      <meta name="theme-color" content="#0a1020"><title><?= e($title) ?> · RasaRaya</title>
      <link rel="stylesheet" href="style.css">
    </head><body>
      <main class="auth-layout">
        <aside class="auth-showcase">
          <a class="brand" href="index.php"><span class="brand-mark">R</span><span>RasaRaya</span></a>
          <div class="auth-showcase-copy">
            <span class="eyebrow">RasaRaya · PHP + MySQL</span>
            <h2>Rasa favorit,<br>lebih dekat.</h2>
            <p>Temukan pilihan menu, pesan dengan mudah, dan kelola usaha dalam satu tempat.</p>
          </div>
          <div class="auth-art" aria-hidden="true"><span>🍲</span><i></i><b></b></div>
          <small class="auth-showcase-footer">Belanja mudah · Kelola praktis</small>
        </aside>
        <section class="auth-panel">
          <div class="auth-card <?= $register ? 'auth-card-register' : '' ?>">
        <a class="brand auth-mobile-brand" href="index.php"><span class="brand-mark">R</span><span>RasaRaya</span></a>
        <span class="eyebrow">RasaRaya · PHP + MySQL</span>
        <h1><?= e($title) ?></h1>
        <p class="muted">
          <?php if ($register): ?>Isi data diri untuk membuat akun. Penjual menunggu persetujuan admin.
          <?php elseif ($page === 'forgot'): ?>Masukkan email akun untuk meminta tautan reset kata sandi.
          <?php elseif ($page === 'reset'): ?>Gunakan kata sandi baru minimal 8 karakter.
          <?php else: ?>Masuk dengan email dan kata sandi untuk melanjutkan.<?php endif; ?>
        </p>
        <?= render_flash($flash) ?>
        <?php if ($register): ?>
          <form class="register-form" method="post" action="index.php?page=register">
            <?= csrf_field() ?><input type="hidden" name="action" value="register">
            <div class="field"><label for="full_name">Nama lengkap</label><input id="full_name" name="full_name" autocomplete="name" required maxlength="120"></div>
            <div class="field"><label for="whatsapp">Nomor WhatsApp</label><input id="whatsapp" name="whatsapp" type="tel" autocomplete="tel" inputmode="tel" required maxlength="20" placeholder="081234567890"></div>
            <div class="field"><label for="email">Alamat email</label><input id="email" name="email" type="email" autocomplete="email" required maxlength="191"></div>
            <div class="field"><label for="password">Kata sandi</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required></div>
            <div class="field"><label for="role">Daftar sebagai</label><select id="role" name="role"><option value="USER_PEMBELI">Pembeli</option><option value="PENDING_PENJUAL">Penjual (menunggu persetujuan)</option></select></div>
            <div class="field" id="business-field"><label for="business_name">Nama usaha</label><input id="business_name" name="business_name" maxlength="120"></div>
            <button class="button full" type="submit">Daftar akun</button>
          </form>
          <script>
            const roleField = document.getElementById('role');
            const businessField = document.getElementById('business-field');
            const businessInput = document.getElementById('business_name');
            function updateBusinessField() {
              const isSeller = roleField.value === 'PENDING_PENJUAL';
              businessField.hidden = !isSeller;
              businessInput.required = isSeller;
            }
            roleField.addEventListener('change', updateBusinessField);
            updateBusinessField();
          </script>
          <div class="auth-switch">Sudah punya akun? <a href="<?= e(page_url('login')) ?>">Masuk</a></div>
        <?php elseif ($page === 'forgot'): ?>
          <form method="post" action="index.php?page=forgot">
            <?= csrf_field() ?><input type="hidden" name="action" value="forgot_password">
            <div class="field"><label for="email">Alamat email</label><input id="email" name="email" type="email" required maxlength="191"></div>
            <button class="button full">Kirim tautan reset</button>
          </form>
          <div class="auth-switch"><a href="<?= e(page_url('login')) ?>">Kembali ke masuk</a></div>
        <?php elseif ($page === 'reset'): ?>
          <form method="post" action="index.php?page=reset">
            <?= csrf_field() ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="token" value="<?= e($resetToken) ?>">
            <div class="field"><label for="password">Kata sandi baru</label><input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required></div>
            <button class="button full">Simpan kata sandi</button>
          </form>
          <div class="auth-switch"><a href="<?= e(page_url('login')) ?>">Kembali ke masuk</a></div>
        <?php else: ?>
          <form method="post" action="index.php?page=login">
            <?= csrf_field() ?><input type="hidden" name="action" value="login">
            <div class="field"><label for="email">Alamat email</label><input id="email" name="email" type="email" autocomplete="email" required maxlength="191"></div>
            <div class="field"><label for="password">Kata sandi</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
            <div class="field"><label for="role">Masuk sebagai</label><select id="role" name="role"><option value="USER_PEMBELI">Pembeli</option><option value="PENJUAL">Penjual / Admin</option></select></div>
            <button class="button full" type="submit">Masuk</button>
          </form>
          <div class="auth-switch"><a href="<?= e(page_url('forgot')) ?>">Lupa kata sandi?</a></div>
          <div class="auth-switch">Belum punya akun? <a href="<?= e(page_url('register')) ?>">Daftar sekarang</a></div>
        <?php endif; ?>
          </div>
        </section>
      </main>
    </body></html>
    <?php
}

function nav_items(array $user): array
{
    if ($user['role'] === 'ADMIN_PENJUAL') {
        return [
            'dashboard' => 'Beranda', 'orders' => 'Pesanan', 'products' => 'Produk',
            'accounts' => 'Akun Terdaftar', 'sellers' => 'Persetujuan Penjual',
            'finance' => 'Keuangan', 'settings' => 'Pengaturan',
        ];
    }
    if (is_seller($user)) {
        return ['dashboard' => 'Beranda', 'orders' => 'Pesanan', 'products' => 'Produk',
            'finance' => 'Keuangan', 'settings' => 'Pengaturan'];
    }
    return ['catalog' => 'Katalog', 'cart' => 'Keranjang', 'orders' => 'Pesanan'];
}

function render_navigation(array $user, string $page, bool $mobile = false): string
{
    $items = nav_items($user);
    $html = '<nav class="' . ($mobile ? 'mobile-nav' : 'nav-list') . '">';
    foreach ($items as $key => $label) {
        $html .= '<a class="' . ($page === $key ? 'active' : '') . '" href="' .
            e(page_url($key)) . '">' . e($label) . '</a>';
    }
    return $html . '</nav>';
}

function render_application(
    string $page,
    array $user,
    ?array $flash,
    array $data,
    ?string $whatsappUrl = null
): void
{
    $titles = [
        'catalog' => 'Katalog', 'cart' => 'Keranjang', 'orders' => 'Pesanan',
        'dashboard' => 'Beranda', 'products' => 'Produk', 'accounts' => 'Akun Terdaftar',
        'sellers' => 'Persetujuan Penjual', 'finance' => 'Keuangan', 'settings' => 'Pengaturan',
    ];
    $roleLabel = match ($user['role']) {
        'ADMIN_PENJUAL' => 'Admin', 'PENJUAL' => 'Penjual', default => 'Pembeli',
    };
    ?>
    <!doctype html><html lang="id"><head>
      <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
      <meta name="theme-color" content="#0a1020"><title><?= e($titles[$page] ?? 'RasaRaya') ?> · RasaRaya</title>
      <link rel="stylesheet" href="style.css">
    </head><body><div class="app-layout">
      <aside class="sidebar">
        <a class="brand" href="<?= e(page_url(is_seller($user) ? 'dashboard' : 'catalog')) ?>"><span class="brand-mark">R</span><span>RasaRaya</span></a>
        <span class="nav-label">Menu utama</span><?= render_navigation($user, $page) ?>
        <div class="sidebar-bottom">
          <form method="post" action="index.php"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button class="button quiet full" type="submit">Keluar</button></form>
          <div class="user-chip"><strong><?= e($user['full_name']) ?></strong><small><?= e($roleLabel) ?> · <?= e($user['email']) ?></small></div>
        </div>
      </aside>
      <main class="main-area"><header class="topbar"><strong><?= e($titles[$page] ?? 'RasaRaya') ?></strong><small>PHP · MySQL</small></header>
        <section class="content">
          <?= render_flash($flash) ?>
          <?php if ($whatsappUrl !== null): ?><div class="notice success">Pesanan tersimpan. <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer">Lanjutkan ke WhatsApp</a> untuk menghubungi penjual.</div><?php endif; ?>
          <?php render_app_content($page, $user, $data); ?>
        </section>
      </main>
      <?= render_navigation($user, $page, true) ?>
    </div></body></html>
    <?php
}

function render_app_content(string $page, array $user, array $data): void
{
    match ($page) {
        'catalog' => render_catalog($data),
        'cart' => render_cart($data),
        'orders' => render_orders($user, $data),
        'dashboard' => render_dashboard($user, $data),
        'products' => render_products($data),
        'accounts' => render_accounts($data),
        'sellers' => render_sellers($data),
        'finance' => render_finance($data),
        'settings' => render_settings($data),
        default => print('<div class="panel empty">Halaman tidak ditemukan.</div>'),
    };
}

function render_catalog(array $data): void
{
    $products = $data['products'];
    ?>
    <div class="heading"><div><span class="eyebrow">Menu pilihan</span><h1>Katalog</h1><p>Pilih produk dan masukkan ke keranjang.</p></div></div>
    <?php if (!$products): ?><div class="panel empty">Belum ada produk tersedia.</div>
    <?php else: ?><div class="grid">
      <?php foreach ($products as $product): ?><article class="product-card">
        <div class="product-art"><img src="<?= e($product['image_path'] ?: 'assets/placeholder.svg') ?>" alt="<?= e($product['name']) ?>"></div>
        <div class="product-body"><span class="product-category"><?= e($product['category']) ?></span>
          <h3><?= e($product['name']) ?></h3><p class="product-description"><?= e($product['description']) ?></p>
          <div class="product-footer"><span class="price"><?= e(format_rupiah($product['price'])) ?></span>
            <form method="post" action="index.php?page=catalog"><?= csrf_field() ?><input type="hidden" name="action" value="cart_add"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
              <button class="button" type="submit" <?= $product['stock'] < 1 ? 'disabled' : '' ?>><?= $product['stock'] < 1 ? 'Habis' : '+ Keranjang' ?></button>
            </form>
          </div><small class="muted-text"><?= $product['stock'] > 0 ? 'Stok ' . (int) $product['stock'] . ' tersedia' : 'Stok habis' ?></small>
        </div>
      </article><?php endforeach; ?>
    </div><?php endif; ?>
    <?php
}

function render_cart(array $data): void
{
    $items = $data['cart'];
    $total = array_sum(array_map(static fn(array $item): int => $item['price'] * $item['quantity'], $items));
    ?>
    <div class="heading"><div><span class="eyebrow">Pesanan kamu</span><h1>Keranjang</h1><p>Penjual akan mengonfirmasi harga dan stok sebelum pesanan selesai.</p></div></div>
    <?php if (!$items): ?><div class="panel empty">Keranjang masih kosong. <a href="<?= e(page_url('catalog')) ?>">Lihat katalog</a></div>
    <?php else: ?><section class="panel"><div class="panel-body">
      <?php foreach ($items as $item): ?><div class="cart-row">
        <img class="cart-art" src="<?= e($item['image_path'] ?: 'assets/placeholder.svg') ?>" alt="">
        <div><strong><?= e($item['name']) ?></strong><div class="muted-text"><?= e(format_rupiah($item['price'])) ?> / item</div></div>
        <div class="quantity">
          <form method="post" action="index.php?page=cart"><?= csrf_field() ?><input type="hidden" name="action" value="cart_quantity"><input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="quantity" value="<?= max(0, $item['quantity'] - 1) ?>"><button aria-label="Kurangi">−</button></form>
          <strong><?= $item['quantity'] ?></strong>
          <form method="post" action="index.php?page=cart"><?= csrf_field() ?><input type="hidden" name="action" value="cart_quantity"><input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="quantity" value="<?= $item['quantity'] + 1 ?>"><button aria-label="Tambah">+</button></form>
        </div><strong class="price"><?= e(format_rupiah($item['price'] * $item['quantity'])) ?></strong>
      </div><?php endforeach; ?>
      <div class="summary-line total"><span>Perkiraan total</span><strong><?= e(format_rupiah($total)) ?></strong></div>
      <form method="post" action="index.php?page=cart"><?= csrf_field() ?><input type="hidden" name="action" value="checkout"><button class="button" type="submit">Kirim pesanan</button></form>
    </div></section><?php endif; ?>
    <?php
}

function render_orders(array $user, array $data): void
{
    ?>
    <div class="heading"><div><span class="eyebrow">Riwayat transaksi</span><h1>Pesanan</h1><p><?= is_seller($user) ? 'Tinjau dan proses permintaan pesanan pelanggan.' : 'Pantau status pesanan Anda.' ?></p></div></div>
    <?php if (!$data['orders']): ?><div class="panel empty">Belum ada pesanan.</div>
    <?php else: ?><div class="panel table-wrap"><table><thead><tr><th>Tanggal</th><th><?= is_seller($user) ? 'Pembeli' : 'Nomor' ?></th><th>Item</th><th>Status</th><?php if (is_seller($user)): ?><th>Aksi</th><?php endif; ?></tr></thead><tbody>
      <?php foreach ($data['orders'] as $order): ?><tr>
        <td><?= e(date('d M Y H:i', strtotime($order['created_at']))) ?></td>
        <td><?= is_seller($user) ? e($order['customer_name'] . ' · ' . $order['customer_email']) : '#' . (int) $order['id'] ?></td>
        <td><?php foreach ($order['items'] as $item): ?><?= e($item['product_name']) ?> × <?= $item['quantity'] ?><br><?php endforeach; ?></td>
        <td><span class="badge <?= $order['status'] === 'DITOLAK' ? 'bad' : '' ?>"><?= e(str_replace('_', ' ', $order['status'])) ?></span></td>
        <?php if (is_seller($user)): ?><td><?php if ($order['status'] === 'MENUNGGU_KONFIRMASI'): ?>
          <div class="row-actions">
            <form method="post" action="index.php?page=orders"><?= csrf_field() ?><input type="hidden" name="action" value="resolve_order"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><input type="hidden" name="status" value="SELESAI"><button class="button" type="submit">Selesaikan</button></form>
            <form method="post" action="index.php?page=orders"><?= csrf_field() ?><input type="hidden" name="action" value="resolve_order"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><input type="hidden" name="status" value="DITOLAK"><button class="button danger" type="submit">Tolak</button></form>
          </div><?php else: ?>—<?php endif; ?></td><?php endif; ?>
      </tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
    <?php
}

function render_stat(string $label, string $value, string $note): void
{
    ?><article class="stat"><small><?= e($label) ?></small><strong><?= e($value) ?></strong><small><?= e($note) ?></small></article><?php
}

function render_dashboard(array $user, array $data): void
{
    $sales = $data['finance']['sales'];
    $items = $data['finance']['sale_items'];
    $expenses = $data['finance']['expenses'];
    $revenue = array_sum(array_map(static fn(array $sale): int => (int) $sale['total'], $sales));
    $cost = array_sum(array_map(static fn(array $item): int => (int) $item['cost'] * (int) $item['quantity'], $items));
    $expenseTotal = array_sum(array_map(static fn(array $expense): int => (int) $expense['amount'], $expenses));
    ?>
    <div class="welcome"><div><h2>Selamat datang, <?= e($user['full_name']) ?></h2><p>Ringkasan aktivitas toko.</p></div><a class="button" href="<?= e(page_url('products')) ?>">＋ Tambah produk</a></div>
    <div class="stats">
      <?php render_stat('Penjualan', format_rupiah($revenue), count($sales) . ' transaksi'); ?>
      <?php render_stat('Modal produk', format_rupiah($cost), 'Harga modal produk terjual'); ?>
      <?php render_stat('Biaya operasional', format_rupiah($expenseTotal), count($expenses) . ' catatan'); ?>
      <?php render_stat('Laba bersih', format_rupiah($revenue - $cost - $expenseTotal), 'Penjualan − modal − biaya'); ?>
    </div>
    <div class="notice">Pesanan baru dan persetujuan penjual dapat dikelola melalui menu di samping.</div>
    <?php
}

function render_products(array $data): void
{
    $product = $data['edit_product'];
    ?>
    <div class="heading"><div><span class="eyebrow">Kelola katalog</span><h1>Produk</h1><p>Kelola foto, harga, deskripsi, dan stok produk.</p></div></div>
    <section class="panel"><div class="panel-header"><h2><?= $product ? 'Ubah produk' : 'Tambah produk' ?></h2><?php if ($product): ?><a href="<?= e(page_url('products')) ?>">Batal</a><?php endif; ?></div>
      <div class="panel-body"><form method="post" action="index.php?page=products" class="form-grid" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_product"><input type="hidden" name="id" value="<?= $product ? (int) $product['id'] : '' ?>">
        <div class="field"><label>Nama produk</label><input name="name" required maxlength="60" value="<?= e($product['name'] ?? '') ?>"></div>
        <div class="field"><label>Kategori</label><input name="category" required maxlength="30" value="<?= e($product['category'] ?? '') ?>"></div>
        <div class="field"><label>Harga jual (Rp)</label><input name="price" type="number" min="1" required value="<?= e($product['price'] ?? '') ?>"></div>
        <div class="field"><label>Harga modal (Rp)</label><input name="cost" type="number" min="0" required value="<?= e($product['cost'] ?? '') ?>"></div>
        <div class="field"><label>Stok</label><input name="stock" type="number" min="0" required value="<?= e($product['stock'] ?? 0) ?>"></div>
        <div class="field"><label for="photo">Foto produk</label><input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" <?= !$product || empty($product['image_path']) ? 'required' : '' ?>>
          <small class="muted-text">JPG, PNG, WebP · maksimal 5 MB.</small>
          <?php if ($product && !empty($product['image_path'])): ?><img class="photo-preview" src="<?= e($product['image_path']) ?>" alt="Foto produk saat ini"><?php endif; ?>
        </div>
        <div class="field wide"><label>Deskripsi</label><textarea name="description" maxlength="160"><?= e($product['description'] ?? '') ?></textarea></div>
        <div class="wide"><button class="button" type="submit">Simpan produk</button></div>
      </form></div>
    </section>
    <section class="panel"><div class="panel-header"><h2>Daftar produk (<?= count($data['products']) ?>)</h2></div>
      <?php if (!$data['products']): ?><div class="empty">Belum ada produk.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Foto</th><th>Nama</th><th>Kategori</th><th>Harga</th><th>Modal</th><th>Stok</th><th>Aksi</th></tr></thead><tbody>
        <?php foreach ($data['products'] as $item): ?><tr>
          <td><img class="product-thumb" src="<?= e($item['image_path'] ?: 'assets/placeholder.svg') ?>" alt=""></td><td><strong><?= e($item['name']) ?></strong></td><td><?= e($item['category']) ?></td><td><?= e(format_rupiah($item['price'])) ?></td><td><?= e(format_rupiah($item['cost'])) ?></td><td><?= (int) $item['stock'] ?></td>
          <td><div class="row-actions"><a class="button secondary" href="<?= e(page_url('products', ['edit' => $item['id']])) ?>">Ubah</a>
            <form method="post" action="index.php?page=products" onsubmit="return confirm('Hapus produk ini?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_product"><input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>"><button class="button danger">Hapus</button></form>
          </div></td>
        </tr><?php endforeach; ?>
      </tbody></table></div><?php endif; ?>
    </section>
    <?php
}

function render_accounts(array $data): void
{
    $counts = [];
    foreach ($data['account_counts'] as $row) {
        $counts[$row['role']] = (int) $row['total'];
    }
    ?>
    <div class="heading"><div><span class="eyebrow">Administrasi</span><h1>Akun terdaftar</h1><p>Pantau akun dan permohonan penjual.</p></div></div>
    <div class="stats">
      <?php render_stat('Total akun', (string) array_sum($counts), 'Seluruh akun'); ?>
      <?php render_stat('Pembeli', (string) ($counts['USER_PEMBELI'] ?? 0), 'Akun pembeli aktif'); ?>
      <?php render_stat('Penjual aktif', (string) ($counts['PENJUAL'] ?? 0), 'Sudah disetujui'); ?>
      <?php render_stat('Menunggu persetujuan', (string) ($counts['PENDING_PENJUAL'] ?? 0), 'Permohonan penjual'); ?>
    </div>
    <div class="panel table-wrap"><table><thead><tr><th>Nama</th><th>Usaha</th><th>WhatsApp</th><th>Email</th><th>Role</th><th>Tanggal</th><th>Aksi</th></tr></thead><tbody>
      <?php foreach ($data['accounts'] as $account): ?><tr>
        <td><?= e($account['full_name']) ?></td><td><?= e($account['business_name']) ?></td><td><?= e($account['whatsapp']) ?></td><td><?= e($account['email']) ?></td><td><?= e(str_replace('_', ' ', $account['role'])) ?></td><td><?= e(date('d M Y', strtotime($account['created_at']))) ?></td>
        <td><?php if ($account['role'] === 'PENDING_PENJUAL'): ?><form method="post" action="index.php?page=accounts"><?= csrf_field() ?><input type="hidden" name="action" value="approve_seller"><input type="hidden" name="return_page" value="accounts"><input type="hidden" name="seller_id" value="<?= (int) $account['id'] ?>"><button class="button">Setujui</button></form><?php else: ?>—<?php endif; ?></td>
      </tr><?php endforeach; ?>
    </tbody></table></div>
    <?php
}

function render_sellers(array $data): void
{
    ?>
    <div class="heading"><div><span class="eyebrow">Verifikasi</span><h1>Persetujuan penjual</h1><p>Tinjau permohonan sebelum memberikan akses toko.</p></div></div>
    <?php if (!$data['pending_sellers']): ?><div class="panel empty">Tidak ada permohonan menunggu.</div>
    <?php else: ?><div class="panel table-wrap"><table><thead><tr><th>Nama</th><th>Usaha</th><th>WhatsApp</th><th>Email</th><th>Daftar</th><th>Aksi</th></tr></thead><tbody>
      <?php foreach ($data['pending_sellers'] as $seller): ?><tr><td><?= e($seller['full_name']) ?></td><td><?= e($seller['business_name']) ?></td><td><?= e($seller['whatsapp']) ?></td><td><?= e($seller['email']) ?></td><td><?= e(date('d M Y', strtotime($seller['created_at']))) ?></td>
        <td><form method="post" action="index.php?page=sellers"><?= csrf_field() ?><input type="hidden" name="action" value="approve_seller"><input type="hidden" name="seller_id" value="<?= (int) $seller['id'] ?>"><button class="button">Setujui</button></form></td></tr>
      <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
    <?php
}

function render_finance(array $data): void
{
    $finance = $data['finance'];
    $revenue = array_sum(array_map(static fn(array $sale): int => (int) $sale['total'], $finance['sales']));
    $cost = array_sum(array_map(static fn(array $item): int => (int) $item['cost'] * (int) $item['quantity'], $finance['sale_items']));
    $expenses = array_sum(array_map(static fn(array $item): int => (int) $item['amount'], $finance['expenses']));
    ?>
    <div class="heading"><div><span class="eyebrow">Laporan usaha</span><h1>Keuangan</h1><p>Ringkasan penjualan, modal, dan biaya.</p></div></div>
    <div class="stats">
      <?php render_stat('Pendapatan', format_rupiah($revenue), count($finance['sales']) . ' transaksi'); ?>
      <?php render_stat('Modal', format_rupiah($cost), 'Produk terjual'); ?>
      <?php render_stat('Biaya', format_rupiah($expenses), count($finance['expenses']) . ' catatan'); ?>
      <?php render_stat('Laba bersih', format_rupiah($revenue - $cost - $expenses), 'Pendapatan − modal − biaya'); ?>
    </div>
    <section class="panel"><div class="panel-header"><h2>Tambah biaya operasional</h2></div><div class="panel-body">
      <form method="post" action="index.php?page=finance" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="add_expense">
        <div class="field"><label>Catatan</label><input name="name" maxlength="70" required></div><div class="field"><label>Jumlah (Rp)</label><input name="amount" type="number" min="1" required></div><button class="button">Simpan</button>
      </form>
    </div></section>
    <section class="panel table-wrap"><table><thead><tr><th>Tanggal</th><th>Catatan</th><th>Jumlah</th><th>Aksi</th></tr></thead><tbody>
      <?php foreach ($finance['expenses'] as $expense): ?><tr><td><?= e(date('d M Y', strtotime($expense['created_at']))) ?></td><td><?= e($expense['name']) ?></td><td><?= e(format_rupiah($expense['amount'])) ?></td>
        <td><form method="post" action="index.php?page=finance"><?= csrf_field() ?><input type="hidden" name="action" value="delete_expense"><input type="hidden" name="expense_id" value="<?= (int) $expense['id'] ?>"><button class="button danger">Hapus</button></form></td></tr><?php endforeach; ?>
    </tbody></table></section>
    <?php
}

function render_settings(array $data): void
{
    ?>
    <div class="heading"><div><span class="eyebrow">Pengaturan toko</span><h1>Pengaturan</h1><p>Atur nomor WhatsApp yang digunakan pembeli.</p></div></div>
    <section class="panel"><div class="panel-body"><form method="post" action="index.php?page=settings">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_settings">
      <div class="field"><label for="whatsapp">Nomor WhatsApp toko</label><input id="whatsapp" name="whatsapp" type="tel" value="<?= e($data['settings']['whatsapp']) ?>" placeholder="6281234567890"></div>
      <button class="button">Simpan pengaturan</button>
    </form></div></section>
    <?php
}
