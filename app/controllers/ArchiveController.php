<?php
/** /admin/archives — Archive / restore / safely-delete master data (Owner/Admin only). */
final class ArchiveController {
    public static function index(): void {
        if (!is_owner_or_admin()) { http_response_code(403); exit('Forbidden'); }
        $featured = Archive::summary();
        view('archives/index', ['featured' => $featured, 'title' => 'Archives']);
    }

    public static function detail(): void {
        if (!is_owner_or_admin()) { http_response_code(403); exit('Forbidden'); }
        $table = inp('table');
        Archive::checkTable($table);
        $rows = Archive::archivedRows($table);
        // Resolve display name per table
        $label = Archive::allTables()[$table] ?? $table;
        view('archives/detail', ['table' => $table, 'label' => $label, 'rows' => $rows, 'title' => 'Archives — ' . $label]);
    }

    public static function archive(): void {
        require_post(); csrf_verify();
        if (!is_owner_or_admin()) { http_response_code(403); exit('Forbidden'); }
        $id = intv(inp('id'));
        $table = inp('table');
        $reason = trim(inp('reason'));
        try {
            Archive::archive($table, $id, (int)($_SESSION['user_id'] ?? 0), $reason);
            flash('success', __('Record archived.'));
        } catch (Throwable $e) { flash('error', $e->getMessage()); }
        redirect('admin/archives/' . $table);
    }

    public static function restore(): void {
        require_post(); csrf_verify();
        if (!is_owner_or_admin()) { http_response_code(403); exit('Forbidden'); }
        $id = intv(inp('id'));
        $table = inp('table');
        $reason = trim(inp('reason'));
        try {
            Archive::restore($table, $id, (int)($_SESSION['user_id'] ?? 0), $reason);
            flash('success', __('Record restored.'));
        } catch (Throwable $e) { flash('error', $e->getMessage()); }
        redirect('admin/archives/' . $table);
    }

    public static function delete(): void {
        require_post(); csrf_verify();
        if (!is_owner_or_admin()) { http_response_code(403); exit('Forbidden'); }
        $id = intv(inp('id'));
        $table = inp('table');
        $reason = trim(inp('reason'));
        try {
            Archive::delete($table, $id, (int)($_SESSION['user_id'] ?? 0), $reason);
            flash('success', __('Record deleted.'));
        } catch (Throwable $e) { flash('error', $e->getMessage()); }
        redirect('admin/archives/' . $table);
    }
}
