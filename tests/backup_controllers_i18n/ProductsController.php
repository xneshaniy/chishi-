<?php
final class ProductsController {
    public static function index(): void {
        $q = inp('q'); $cat = intv(inp('category_id')); $kind = inp('kind');
        $where = ['1=1']; $params = [];
        if ($q !== '') { $where[] = '(p.sku LIKE ? OR p.name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
        if ($cat) { $where[] = 'p.category_id = ?'; $params[] = $cat; }
        if ($kind !== '') { $where[] = 'p.kind = ?'; $params[] = $kind; }
        $sql = "SELECT p.*, c.name AS category, u.short AS unit_short,
                       COALESCE((SELECT SUM(qty) FROM stock_balances sb WHERE sb.product_id = p.id),0) AS on_hand
                FROM products p LEFT JOIN product_categories c ON c.id = p.category_id LEFT JOIN units u ON u.id = p.unit_id
                WHERE " . implode(' AND ', $where) . " ORDER BY p.name";
        $p = paginate($sql, $params, intv(inp('page', 1)), 20);
        view('products/index', ['p' => $p, 'q' => $q, 'cat' => $cat, 'kind' => $kind,
            'categories' => db_all('SELECT * FROM product_categories ORDER BY name'),
            'title' => 'Products']);
    }

    public static function newProduct(): void { self::saveProduct(null); }
    public static function editProduct(): void { self::saveProduct(intv(inp('id'))); }

    private static function saveProduct(?int $id): void {
        $product = $id ? db_one('SELECT * FROM products WHERE id = ?', [$id]) : null;
        if ($id && !$product) { flash('error', __('Product not found.')); redirect('products'); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $data = [
                'sku' => strtoupper(inp('sku')), 'name' => inp('name'),
                'category_id' => intv(inp('category_id')) ?: null,
                'unit_id' => intv(inp('unit_id')) ?: null,
                'kind' => in_array(inp('kind'), ['finished','raw','packaging'], true) ? inp('kind') : 'finished',
                'pack_size' => inp('pack_size'),
                'purchase_price' => round(num(inp('purchase_price')), 2),
                'sale_price' => round(num(inp('sale_price')), 2),
                'issue_rate' => round(num(inp('issue_rate')), 2),
                'min_price' => round(num(inp('min_price')), 2),
                'tax_rate' => round(num(inp('tax_rate')), 2),
                'reorder_level' => round(num(inp('reorder_level')), 3),
                'has_expiry' => inp('has_expiry') ? 1 : 0,
                'is_active' => isset($_POST['is_active']) ? (int)(bool)$_POST['is_active'] : 1,
            ];
            $errors = [];
            if ($data['sku'] === '' || $data['name'] === '') $errors[] = 'SKU and name are required.';
            if (db_val('SELECT 1 FROM products WHERE sku = ? AND id != ?', [$data['sku'], $id ?? 0])) $errors[] = 'SKU already exists.';
            // Raw materials and packaging are always tracked in KG base; units cannot differ
            if (in_array($data['kind'], ['raw', 'packaging'], true)) {
                $kgId = (int)db_val("SELECT id FROM units WHERE short = 'KG'");
                if ($kgId) $data['unit_id'] = $kgId;
            }
            // Don't silently re-base units on a product that already has stock movements
            if ($id && db_val('SELECT COUNT(*) FROM stock_movements WHERE product_id = ?', [$id]) > 0) {
                $storedUnit = (int)($product['unit_id'] ?? 0);
                if ($storedUnit !== (int)$data['unit_id']) $errors[] = 'This product already has stock movements — its unit cannot be changed. Create a new product instead.';
            }
            if ($errors) { flash('error', implode(' ', $errors)); redirect($id ? 'products/' . $id . '/edit' : 'products/new'); }
        // Packaging config (FG products only, stored in product_packaging)
        $packPieces = inp('pack_pieces');
        $bundlePacks = inp('bundle_packs');
        $packetBundles = inp('packet_bundles');
        $isFinished = $data['kind'] === 'finished';
        $isNullish = fn($v) => $v === '' || $v === false;
        $packPayload = [
            'pack_pieces' => $isNullish($packPieces) ? null : max(0, round(num($packPieces), 3)),
            'bundle_packs' => $isNullish($bundlePacks) ? null : max(0, round(num($bundlePacks), 3)),
            'packet_bundles' => $isNullish($packetBundles) ? null : max(0, round(num($packetBundles), 3)),
        ];
        $savePackaging = function (int $pid) use ($isFinished, $packPayload) {
            $exists = db_val('SELECT 1 FROM product_packaging WHERE product_id = ?', [$pid]);
            if (!$isFinished) { if ($exists) db_exec('DELETE FROM product_packaging WHERE product_id = ?', [$pid]); return; }
            if ($exists) {
                db_exec('UPDATE product_packaging SET pack_pieces = ?, bundle_packs = ?, packet_bundles = ?, updated_at = ? WHERE product_id = ?',
                    [$packPayload['pack_pieces'], $packPayload['bundle_packs'], $packPayload['packet_bundles'], now_local(), $pid]);
            } else {
                db_insert('product_packaging', array_merge(['product_id' => $pid, 'updated_at' => now_local()], $packPayload));
            }
        };
        if ($id) {
                db_exec('UPDATE products SET sku=?, name=?, category_id=?, unit_id=?, kind=?, pack_size=?, purchase_price=?, sale_price=?, issue_rate=?, min_price=?, tax_rate=?, reorder_level=?, has_expiry=?, is_active=? WHERE id=?',
                    array_merge(array_values($data), [$id]));
                $savePackaging($id);
                audit('update', 'products', $id, 'Updated ' . $data['sku']);
                flash('success', __('Product updated.'));
            } else {
                $newId = db_insert('products', $data + ['created_at' => now_local()]);
                $savePackaging((int)$newId);
                audit('create', 'products', $newId, 'Created ' . $data['sku']);
                flash('success', __('Product created.'));
            }
            redirect('products');
        }
        // Auto-suggest next SKU for new products
        $packaging = $product ? db_one('SELECT * FROM product_packaging WHERE product_id = ?', [(int)$product['id']]) : null;
        view('products/form', ['product' => $product,
            'suggestedSku' => 'CTC-' . str_pad((string)((int)db_val('SELECT MAX(id) FROM products') + 1), 4, '0', STR_PAD_LEFT),
            'categories' => db_all('SELECT * FROM product_categories ORDER BY name'),
            'units' => db_all('SELECT * FROM units ORDER BY name'),
            'packaging' => $packaging,
            'defaults' => UnitConverter::packagingDefaults(),
            'title' => $product ? 'Edit Product' : 'New Product']);
    }

    public static function categories(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $name = inp('name'); $kind = inp('kind') ?: 'finished';
            if ($name === '') { flash('error', __('Category name required.')); redirect('products/categories'); }
            db_insert('product_categories', ['name' => $name, 'kind' => $kind]);
            audit('create', 'product_categories', null, $name);
            flash('success', __('Category added.'));
            redirect('products/categories');
        }
        view('products/categories', ['rows' => db_all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count FROM product_categories c ORDER BY c.name'), 'title' => 'Product Categories']);
    }
}
