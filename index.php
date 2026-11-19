<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function upload_product_photo(): ?string
{
    $upload = $_FILES['photo'] ?? null;
    if ($upload === null) {
        return null;
    }
    if (!is_array($upload) || !isset($upload['error'], $upload['size'], $upload['tmp_name']) ||
        !is_int($upload['error']) || !is_int($upload['size']) || !is_string($upload['tmp_name'])) {
        throw new InvalidArgumentException('Data foto produk tidak valid.');
    }
    if ($upload['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($upload['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Foto produk gagal diunggah. Silakan coba lagi.');
    }
    if ($upload['size'] < 1 || $upload['size'] > 5 * 1024 * 1024 ||
        !is_uploaded_file($upload['tmp_name'])) {
        throw new InvalidArgumentException('Foto harus berukuran maksimal 5 MB.');
    }

    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    $image = getimagesize($upload['tmp_name']);
    if (!is_string($mime) || !isset($types[$mime]) ||
        !is_array($image) || ($image['mime'] ?? '') !== $mime) {
        throw new InvalidArgumentException('Gunakan foto berformat JPG, PNG, atau WebP.');
    }

    $directory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder penyimpanan foto tidak dapat dibuat.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $types[$mime];
    if (!move_uploaded_file($upload['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('Foto produk tidak dapat disimpan.');
    }
    return 'uploads/products/' . $filename;
}

function delete_product_photo(?string $relativePath): bool
{
    if ($relativePath === null ||
        !preg_match('/\Auploads\/products\/[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $relativePath)) {
        return true;
    }
    $path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    return !is_file($path) || unlink($path);
}

function load_products(PDO $pdo, bool $includeCost): array
{
    $columns = $includeCost
        ? 'id, name, category, description, price, cost, stock, image_path'
        : 'id, name, category, description, price, stock, image_path';
    $products = $pdo->query("SELECT {$columns} FROM products ORDER BY name")->fetchAll();
    foreach ($products as &$product) {
        foreach (['id', 'price', 'stock'] as $key) {
            $product[$key] = (int) $product[$key];
        }
        if ($includeCost) {
            $product['cost'] = (int) $product['cost'];
        }
    }
    unset($product);
    return $products;
}

function load_cart(PDO $pdo, int $userId): array
{
    $statement = $pdo->prepare(
        'SELECT p.id, p.name, p.price, p.stock, p.image_path, ci.quantity
         FROM cart_items ci JOIN products p ON p.id = ci.product_id
         WHERE ci.user_id = ? ORDER BY p.name'
    );
    $statement->execute([$userId]);
    $items = $statement->fetchAll();
    foreach ($items as &$item) {
        foreach (['id', 'price', 'stock', 'quantity'] as $key) {
            $item[$key] = (int) $item[$key];
        }
    }
    unset($item);
    return $items;
}

function load_orders(PDO $pdo, array $user): array
{
    if (is_seller($user)) {
        $statement = $pdo->query(
            'SELECT o.*, u.full_name AS customer_name, u.email AS customer_email
             FROM orders o JOIN users u ON u.id = o.customer_id
             ORDER BY o.created_at DESC'
        );
    } else {
        $statement = $pdo->prepare(
            'SELECT o.*, u.full_name AS customer_name, u.email AS customer_email
             FROM orders o JOIN users u ON u.id = o.customer_id
             WHERE o.customer_id = ? ORDER BY o.created_at DESC'
        );
        $statement->execute([(int) $user['id']]);
    }
    $orders = $statement->fetchAll();
    $itemsStatement = $pdo->prepare(
        'SELECT product_name, quantity, requested_price FROM order_items
         WHERE order_id = ? ORDER BY id'
    );
    foreach ($orders as &$order) {
        $itemsStatement->execute([(int) $order['id']]);
        $order['items'] = $itemsStatement->fetchAll();
        foreach ($order['items'] as &$item) {
            $item['quantity'] = (int) $item['quantity'];
            $item['requested_price'] = (int) $item['requested_price'];
        }
        unset($item);
    }
    unset($order);
    return $orders;
}

function load_finance(PDO $pdo): array
{
    $sales = $pdo->query('SELECT * FROM sales ORDER BY created_at DESC')->fetchAll();
    $items = $pdo->query(
        'SELECT si.*, s.created_at AS sale_date
         FROM sale_items si JOIN sales s ON s.id = si.sale_id ORDER BY s.created_at DESC'
    )->fetchAll();
    $expenses = $pdo->query('SELECT * FROM expenses ORDER BY created_at DESC')->fetchAll();
    return ['sales' => $sales, 'sale_items' => $items, 'expenses' => $expenses];
}

function process_post(PDO $pdo, ?array $user): never
{
    verify_csrf();
    $action = form_value('action');
    try {
        if ($action === 'register') {
            $name = form_value('full_name');
            $phone = form_value('whatsapp');
            $email = strtolower(form_value('email'));
            $password = (string) ($_POST['password'] ?? '');
            $role = form_value('role');
            $business = form_value('business_name');
            $nameLength = preg_match_all('/./us', $name);
            $businessLength = preg_match_all('/./us', $business);
            $digits = preg_replace('/\D/', '', $phone);
            if ($nameLength === false || $nameLength < 1 || $nameLength > 120 ||
                !preg_match('/\A\+?[0-9][0-9\s().-]{6,19}\z/', $phone) ||
                strlen($digits) < 8 || strlen($digits) > 15 ||
                !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191 ||
                strlen($password) < 8 || strlen($password) > 4096 ||
                !in_array($role, ['USER_PEMBELI', 'PENDING_PENJUAL'], true) ||
                ($role === 'PENDING_PENJUAL' &&
                    ($businessLength === false || $businessLength < 1 || $businessLength > 120))) {
                throw new InvalidArgumentException('Periksa kembali data registrasi. Kata sandi minimal 8 karakter.');
            }
            $insert = $pdo->prepare(
                'INSERT INTO users (full_name, whatsapp, business_name, email, password_hash, role)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                $name, $phone, $role === 'PENDING_PENJUAL' ? $business : '',
                $email, password_hash($password, PASSWORD_DEFAULT), $role,
            ]);
            if ($role === 'PENDING_PENJUAL') {
                set_flash('success', 'Permohonan penjual terkirim dan menunggu persetujuan admin.');
                redirect_to('login');
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $pdo->lastInsertId();
            redirect_to('catalog');
        }

        if ($action === 'login') {
            $email = strtolower(form_value('email'));
            $password = (string) ($_POST['password'] ?? '');
            $role = form_value('role');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '' ||
                !in_array($role, ['USER_PEMBELI', 'PENJUAL'], true)) {
                throw new InvalidArgumentException('Masukkan email, kata sandi, dan jenis akun yang valid.');
            }
            $statement = $pdo->prepare('SELECT id, password_hash, role FROM users WHERE email = ?');
            $statement->execute([$email]);
            $account = $statement->fetch();
            if (!$account || !password_verify($password, $account['password_hash'])) {
                throw new RuntimeException('Email atau kata sandi salah.');
            }
            if ($account['role'] === 'PENDING_PENJUAL') {
                throw new RuntimeException('Pendaftaran penjual masih menunggu persetujuan admin.');
            }
            $isSeller = in_array($account['role'], ['PENJUAL', 'ADMIN_PENJUAL'], true);
            if (($role === 'PENJUAL') !== $isSeller) {
                throw new RuntimeException('Pilih jenis akun yang sesuai dengan akun Anda.');
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $account['id'];
            redirect_to($isSeller ? 'dashboard' : 'catalog');
        }

        if ($action === 'forgot_password') {
            $email = strtolower(form_value('email'));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Masukkan alamat email yang valid.');
            }
            $find = $pdo->prepare('SELECT id, email FROM users WHERE email = ?');
            $find->execute([$email]);
            $account = $find->fetch();
            if ($account) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expiresAt = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');
                $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')
                    ->execute([(int) $account['id']]);
                $pdo->prepare(
                    'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
                )->execute([(int) $account['id'], $tokenHash, $expiresAt]);

                $from = config_value('MAIL_FROM', 'no-reply@localhost') ?: 'no-reply@localhost';
                if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('MAIL_FROM tidak berisi alamat email yang valid.');
                }
                $subject = '=?UTF-8?B?' . base64_encode('Reset kata sandi RasaRaya') . '?=';
                $resetUrl = app_url(['page' => 'reset', 'token' => $token]);
                $message = "Halo,\n\nGunakan tautan berikut untuk membuat kata sandi baru (berlaku 1 jam):\n{$resetUrl}\n\nJika Anda tidak meminta reset, abaikan email ini.";
                $sent = mail(
                    $account['email'],
                    $subject,
                    $message,
                    "From: {$from}\r\nContent-Type: text/plain; charset=UTF-8"
                );
                if (!$sent) {
                    error_log('RasaRaya password reset email could not be sent.');
                }
            }
            set_flash('success', 'Jika email terdaftar, tautan reset akan dikirim. Periksa kotak masuk Anda.');
            redirect_to('forgot');
        }

        if ($action === 'reset_password') {
            $token = form_value('token');
            $password = (string) ($_POST['password'] ?? '');
            if (!preg_match('/\A[a-f0-9]{64}\z/', $token) || strlen($password) < 8 ||
                strlen($password) > 4096) {
                throw new InvalidArgumentException('Tautan reset tidak valid atau kata sandi kurang dari 8 karakter.');
            }
            $pdo->beginTransaction();
            $find = $pdo->prepare(
                'SELECT id, user_id FROM password_resets
                 WHERE token_hash = ? AND expires_at > NOW() FOR UPDATE'
            );
            $find->execute([hash('sha256', $token)]);
            $reset = $find->fetch();
            if (!$reset) {
                throw new RuntimeException('Tautan reset tidak valid atau sudah kedaluwarsa.');
            }
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), (int) $reset['user_id']]);
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')
                ->execute([(int) $reset['user_id']]);
            $pdo->commit();
            set_flash('success', 'Kata sandi berhasil diubah. Silakan masuk.');
            redirect_to('login');
        }

        if ($action === 'logout') {
            $_SESSION = [];
            session_regenerate_id(true);
            redirect_to('login');
        }

        if ($user === null) {
            throw new RuntimeException('Silakan masuk terlebih dahulu.');
        }
        $userId = (int) $user['id'];

        if ($action === 'cart_add') {
            if (is_seller($user)) {
                throw new RuntimeException('Akun penjual tidak dapat membuat pesanan.');
            }
            $productId = post_integer('product_id', 1);
            $check = $pdo->prepare('SELECT id FROM products WHERE id = ? AND stock > 0');
            $check->execute([$productId]);
            if (!$check->fetch()) {
                throw new RuntimeException('Produk tidak tersedia.');
            }
            $statement = $pdo->prepare(
                'INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE quantity = quantity + 1'
            );
            $statement->execute([$userId, $productId]);
            set_flash('success', 'Produk ditambahkan ke keranjang.');
            redirect_to('catalog');
        }

        if ($action === 'cart_quantity') {
            $productId = post_integer('product_id', 1);
            $quantity = post_integer('quantity', 0);
            if ($quantity === 0) {
                $statement = $pdo->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
                $statement->execute([$userId, $productId]);
            } else {
                $statement = $pdo->prepare(
                    'UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?'
                );
                $statement->execute([$quantity, $userId, $productId]);
            }
            redirect_to('cart');
        }

        if ($action === 'checkout') {
            if (is_seller($user)) {
                throw new RuntimeException('Akun penjual tidak dapat membuat pesanan.');
            }
            $pdo->beginTransaction();
            $cart = $pdo->prepare(
                'SELECT p.id, p.name, p.price, ci.quantity FROM cart_items ci
                 JOIN products p ON p.id = ci.product_id WHERE ci.user_id = ? FOR UPDATE'
            );
            $cart->execute([$userId]);
            $items = $cart->fetchAll();
            if (!$items) {
                throw new RuntimeException('Keranjang masih kosong.');
            }
            $insertOrder = $pdo->prepare(
                'INSERT INTO orders (customer_id, customer_name, status) VALUES (?, ?, ?)'
            );
            $insertOrder->execute([$userId, $user['full_name'], 'MENUNGGU_KONFIRMASI']);
            $orderId = (int) $pdo->lastInsertId();
            $insertItem = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, quantity, requested_price)
                 VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($items as $item) {
                $insertItem->execute([
                    $orderId, (int) $item['id'], $item['name'],
                    (int) $item['quantity'], (int) $item['price'],
                ]);
            }
            $pdo->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$userId]);
            $pdo->commit();
            $storePhone = preg_replace(
                '/\D+/',
                '',
                (string) $pdo->query('SELECT whatsapp FROM store_settings WHERE id = 1')->fetchColumn()
            );
            if (strlen($storePhone) >= 9 && strlen($storePhone) <= 15) {
                $lines = ['Halo, saya ingin mengajukan pesanan #' . $orderId . ':'];
                foreach ($items as $item) {
                    $lines[] = '- ' . $item['name'] . ' x' . (int) $item['quantity'] .
                        ' = ' . format_rupiah((int) $item['price'] * (int) $item['quantity']);
                }
                $lines[] = 'Mohon konfirmasi harga dan ketersediaan.';
                $_SESSION['whatsapp_url'] = 'https://wa.me/' . $storePhone .
                    '?text=' . rawurlencode(implode("\n", $lines));
            }
            set_flash('success', 'Pesanan tersimpan dan menunggu konfirmasi penjual.');
            redirect_to('orders');
        }

        if (in_array($action, ['save_product', 'delete_product', 'resolve_order', 'approve_seller',
            'add_expense', 'delete_expense', 'save_settings'], true) && !is_seller($user)) {
            throw new RuntimeException('Tindakan ini hanya tersedia untuk penjual atau admin.');
        }

        if ($action === 'save_product') {
            $idValue = form_value('id');
            $productId = $idValue === '' ? null : post_integer('id', 1);
            $name = form_value('name');
            $category = form_value('category');
            $description = form_value('description');
            $price = post_integer('price', 1);
            $cost = post_integer('cost', 0);
            $stock = post_integer('stock', 0);
            if ($name === '' || strlen($name) > 60 || $category === '' ||
                strlen($category) > 30 || strlen($description) > 160) {
                throw new InvalidArgumentException('Periksa kembali data produk.');
            }
            $oldImage = null;
            if ($productId !== null) {
                $find = $pdo->prepare('SELECT image_path FROM products WHERE id = ?');
                $find->execute([$productId]);
                $existing = $find->fetch();
                if (!$existing) {
                    throw new RuntimeException('Produk tidak ditemukan.');
                }
                $oldImage = $existing['image_path'];
            }
            $newImage = upload_product_photo();
            if ($newImage === null && $oldImage === null) {
                throw new InvalidArgumentException('Tambahkan foto produk terlebih dahulu.');
            }
            $imagePath = $newImage ?? $oldImage;
            try {
                if ($productId === null) {
                    $statement = $pdo->prepare(
                        'INSERT INTO products (name, category, description, price, cost, stock, image_path)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $statement->execute([$name, $category, $description, $price, $cost, $stock, $imagePath]);
                } else {
                    $statement = $pdo->prepare(
                        'UPDATE products SET name = ?, category = ?, description = ?, price = ?,
                         cost = ?, stock = ?, image_path = ? WHERE id = ?'
                    );
                    $statement->execute([
                        $name, $category, $description, $price, $cost, $stock, $imagePath, $productId,
                    ]);
                }
            } catch (Throwable $error) {
                if ($newImage !== null && !delete_product_photo($newImage)) {
                    error_log('RasaRaya failed to clean up an unused product photo.');
                }
                throw $error;
            }
            if ($newImage !== null && $oldImage !== null && !delete_product_photo($oldImage)) {
                set_flash('warning', 'Produk diperbarui, tetapi foto lama tidak dapat dihapus.');
                redirect_to('products');
            }
            set_flash('success', $productId === null ? 'Produk berhasil ditambahkan.' : 'Produk berhasil diperbarui.');
            redirect_to('products');
        }

        if ($action === 'delete_product') {
            $productId = post_integer('product_id', 1);
            $check = $pdo->prepare(
                'SELECT EXISTS(SELECT 1 FROM sale_items WHERE product_id = ?) AS has_sales,
                 EXISTS(SELECT 1 FROM order_items oi JOIN orders o ON o.id = oi.order_id
                 WHERE oi.product_id = ? AND o.status = "MENUNGGU_KONFIRMASI") AS has_pending'
            );
            $check->execute([$productId, $productId]);
            $state = $check->fetch();
            if ($state['has_sales'] || $state['has_pending']) {
                throw new RuntimeException('Produk yang sudah terjual atau ada di pesanan aktif tidak dapat dihapus.');
            }
            $find = $pdo->prepare('SELECT image_path FROM products WHERE id = ?');
            $find->execute([$productId]);
            $product = $find->fetch();
            if (!$product) {
                throw new RuntimeException('Produk tidak ditemukan.');
            }
            $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$productId]);
            if (!delete_product_photo($product['image_path'])) {
                set_flash('warning', 'Produk dihapus, tetapi foto produk tidak dapat dihapus.');
            } else {
                set_flash('success', 'Produk berhasil dihapus.');
            }
            redirect_to('products');
        }

        if ($action === 'resolve_order') {
            $orderId = post_integer('order_id', 1);
            $status = form_value('status');
            if ($status === 'DITOLAK') {
                $statement = $pdo->prepare(
                    'UPDATE orders SET status = ? WHERE id = ? AND status = ?'
                );
                $statement->execute(['DITOLAK', $orderId, 'MENUNGGU_KONFIRMASI']);
                if ($statement->rowCount() !== 1) {
                    throw new RuntimeException('Pesanan sudah diproses atau tidak ditemukan.');
                }
                set_flash('success', 'Pesanan ditolak.');
                redirect_to('orders');
            }
            if ($status !== 'SELESAI') {
                throw new InvalidArgumentException('Status pesanan tidak valid.');
            }
            $pdo->beginTransaction();
            $orderQuery = $pdo->prepare(
                'SELECT id, customer_id FROM orders WHERE id = ? AND status = ? FOR UPDATE'
            );
            $orderQuery->execute([$orderId, 'MENUNGGU_KONFIRMASI']);
            $order = $orderQuery->fetch();
            if (!$order) {
                throw new RuntimeException('Pesanan sudah diproses atau tidak ditemukan.');
            }
            $linesQuery = $pdo->prepare(
                'SELECT oi.product_id, oi.product_name, oi.quantity, p.name, p.price, p.cost, p.stock
                 FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id
                 WHERE oi.order_id = ? FOR UPDATE'
            );
            $linesQuery->execute([$orderId]);
            $lines = $linesQuery->fetchAll();
            if (!$lines) {
                throw new RuntimeException('Pesanan tidak memiliki item.');
            }
            $total = 0;
            foreach ($lines as $line) {
                if ($line['product_id'] === null || $line['name'] === null ||
                    (int) $line['stock'] < (int) $line['quantity']) {
                    throw new RuntimeException('Produk pesanan tidak tersedia atau stok tidak mencukupi.');
                }
                $total += (int) $line['price'] * (int) $line['quantity'];
            }
            $pdo->prepare('INSERT INTO sales (order_id, customer_id, total) VALUES (?, ?, ?)')
                ->execute([$orderId, (int) $order['customer_id'], $total]);
            $saleId = (int) $pdo->lastInsertId();
            $updateStock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
            $insertSaleItem = $pdo->prepare(
                'INSERT INTO sale_items (sale_id, product_id, product_name, price, cost, quantity)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($lines as $line) {
                $updateStock->execute([(int) $line['quantity'], (int) $line['product_id']]);
                $insertSaleItem->execute([
                    $saleId, (int) $line['product_id'], $line['name'], (int) $line['price'],
                    (int) $line['cost'], (int) $line['quantity'],
                ]);
            }
            $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')
                ->execute(['SELESAI', $orderId]);
            $pdo->commit();
            set_flash('success', 'Pesanan selesai; stok dan penjualan diperbarui.');
            redirect_to('orders');
        }

        if ($action === 'approve_seller' && $user['role'] === 'ADMIN_PENJUAL') {
            $statement = $pdo->prepare(
                'UPDATE users SET role = ? WHERE id = ? AND role = ?'
            );
            $statement->execute(['PENJUAL', post_integer('seller_id', 1), 'PENDING_PENJUAL']);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('Permohonan penjual tidak ditemukan atau sudah diproses.');
            }
            set_flash('success', 'Akun penjual berhasil disetujui.');
            redirect_to(form_value('return_page') === 'accounts' ? 'accounts' : 'sellers');
        }

        if ($action === 'add_expense') {
            $name = form_value('name');
            $amount = post_integer('amount', 1);
            if ($name === '' || strlen($name) > 70) {
                throw new InvalidArgumentException('Masukkan nama biaya maksimal 70 karakter.');
            }
            $pdo->prepare('INSERT INTO expenses (created_by, name, amount) VALUES (?, ?, ?)')
                ->execute([$userId, $name, $amount]);
            set_flash('success', 'Biaya operasional dicatat.');
            redirect_to('finance');
        }

        if ($action === 'delete_expense') {
            $pdo->prepare('DELETE FROM expenses WHERE id = ?')->execute([post_integer('expense_id', 1)]);
            set_flash('success', 'Catatan biaya dihapus.');
            redirect_to('finance');
        }

        if ($action === 'save_settings') {
            $phone = preg_replace('/\D+/', '', form_value('whatsapp'));
            if ($phone !== '' && (strlen($phone) < 9 || strlen($phone) > 15)) {
                throw new InvalidArgumentException('Nomor WhatsApp harus 9–15 digit.');
            }
            $pdo->prepare(
                'INSERT INTO store_settings (id, whatsapp) VALUES (1, ?)
                 ON DUPLICATE KEY UPDATE whatsapp = VALUES(whatsapp)'
            )->execute([$phone]);
            set_flash('success', 'Nomor WhatsApp toko disimpan.');
            redirect_to('settings');
        }

        throw new RuntimeException('Tindakan tidak dikenal atau Anda tidak memiliki izin.');
    } catch (PDOException | RuntimeException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('RasaRaya MySQL action failed: ' . $error->getMessage());
        $message = $error->getCode() === '23000'
            ? 'Email tersebut sudah terdaftar atau data terkait tidak valid.'
            : 'Data tidak dapat disimpan. Periksa database lalu coba lagi.';
        set_flash('error', $message);
    } catch (InvalidArgumentException | RuntimeException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('error', $error->getMessage());
    }
    $returnPage = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : '';
    if ($user === null && in_array($returnPage, ['login', 'register', 'forgot', 'reset'], true)) {
        redirect_to($returnPage, $returnPage === 'reset' ? ['token' => form_value('token')] : []);
    }
    redirect_to($user === null ? 'login' : (is_seller($user) ? 'dashboard' : 'catalog'));
}

try {
    $pdo = db();
} catch (PDOException $error) {
    error_log('RasaRaya database connection failed: ' . $error->getMessage());
    http_response_code(503);
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><title>Database belum siap</title>';
    echo '<link rel="stylesheet" href="style.css"><body><main class="auth-layout"><section class="auth-card">';
    echo '<h1>Database belum tersambung</h1><p>Pastikan MySQL aktif, impor schema.sql, lalu periksa konfigurasi DB di README.</p>';
    echo '</section></main></body></html>';
    exit;
}

$user = current_user($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    process_post($pdo, $user);
}
$page = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : null;
$flash = take_flash();
$authPages = ['login', 'register', 'forgot', 'reset'];
if ($user === null) {
    $page = in_array($page, $authPages, true) ? $page : 'login';
    $resetToken = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';
    if ($page === 'reset') {
        $validToken = preg_match('/\A[a-f0-9]{64}\z/', $resetToken) === 1;
        if ($validToken) {
            $check = $pdo->prepare(
                'SELECT id FROM password_resets WHERE token_hash = ? AND expires_at > NOW()'
            );
            $check->execute([hash('sha256', $resetToken)]);
            $validToken = (bool) $check->fetch();
        }
        if (!$validToken) {
            set_flash('error', 'Tautan reset tidak valid atau sudah kedaluwarsa.');
            redirect_to('forgot');
        }
    }
    require __DIR__ . '/views.php';
    render_auth_page($page, $flash, $resetToken);
    exit;
}

$sellerPages = ['dashboard', 'orders', 'products', 'finance', 'settings'];
$buyerPages = ['catalog', 'cart', 'orders'];
$adminPages = ['sellers', 'accounts'];
$allowedPages = is_seller($user)
    ? array_merge($sellerPages, $user['role'] === 'ADMIN_PENJUAL' ? $adminPages : [])
    : $buyerPages;
if (!in_array($page, $allowedPages, true)) {
    $page = is_seller($user) ? 'dashboard' : 'catalog';
}

$data = [];
if (in_array($page, ['catalog', 'products'], true)) {
    $data['products'] = load_products($pdo, is_seller($user));
}
if ($page === 'products') {
    $data['edit_product'] = null;
    $editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
    if ($editId !== false && $editId !== null && $editId > 0) {
        $statement = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $statement->execute([$editId]);
        $data['edit_product'] = $statement->fetch() ?: null;
    }
}
if ($page === 'cart') {
    $data['cart'] = load_cart($pdo, (int) $user['id']);
}
if ($page === 'orders') {
    $data['orders'] = load_orders($pdo, $user);
}
if (in_array($page, ['dashboard', 'finance'], true)) {
    $data['finance'] = load_finance($pdo);
}
if ($page === 'sellers') {
    $data['pending_sellers'] = $pdo->query(
        'SELECT id, full_name, whatsapp, business_name, email, created_at
         FROM users WHERE role = "PENDING_PENJUAL" ORDER BY created_at'
    )->fetchAll();
}
if ($page === 'accounts') {
    $data['accounts'] = $pdo->query(
        'SELECT id, full_name, whatsapp, business_name, email, role, created_at
         FROM users ORDER BY created_at DESC'
    )->fetchAll();
    $data['account_counts'] = $pdo->query(
        'SELECT role, COUNT(*) AS total FROM users GROUP BY role'
    )->fetchAll();
}
if ($page === 'settings') {
    $data['settings'] = $pdo->query(
        'SELECT whatsapp FROM store_settings WHERE id = 1'
    )->fetch() ?: ['whatsapp' => ''];
}

require __DIR__ . '/views.php';
$whatsappUrl = $page === 'orders' && isset($_SESSION['whatsapp_url'])
    ? (string) $_SESSION['whatsapp_url']
    : null;
unset($_SESSION['whatsapp_url']);
render_application($page, $user, $flash, $data, $whatsappUrl);
