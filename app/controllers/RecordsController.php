<?php
/**
 * RecordsController — Dedicated Company Records & Archives hub (Owner & Admin only).
 * Handles departing staff/salesmen, keeps complete historical records,
 * and allows Owner & Admin to review dossier, restore, or safely delete.
 */
final class RecordsController {

    public static function index(): void {
        if (!is_owner_or_admin()) {
            http_response_code(403);
            view('error/403', ['perm' => 'records.view']);
            exit;
        }

        $tab = inp('tab', 'salesmen');
        if (!in_array($tab, ['salesmen', 'employees', 'masters'], true)) {
            $tab = 'salesmen';
        }

        $archivedSalesmenCount = (int)db_val('SELECT COUNT(*) FROM salesmen WHERE COALESCE(is_archived, 0) = 1');
        $archivedEmployeesCount = (int)db_val('SELECT COUNT(*) FROM employees WHERE COALESCE(is_archived, 0) = 1');
        $activeSalesmenCount = (int)db_val('SELECT COUNT(*) FROM salesmen WHERE is_active = 1 AND COALESCE(is_archived, 0) = 0');
        $activeEmployeesCount = (int)db_val('SELECT COUNT(*) FROM employees WHERE is_active = 1 AND COALESCE(is_archived, 0) = 0');

        $salesmen = [];
        $employees = [];
        $masters = [];

        if ($tab === 'salesmen') {
            $q = trim(inp('q'));
            $where = 'COALESCE(s.is_archived, 0) = 1';
            $params = [];
            if ($q !== '') {
                $where .= ' AND (s.code LIKE ? OR s.name LIKE ? OR s.phone LIKE ? OR s.cnic LIKE ? OR s.area LIKE ?)';
                $params = array_fill(0, 5, "%$q%");
            }
            $salesmen = db_all("SELECT s.*, w.name AS warehouse_name,
                (SELECT COUNT(*) FROM sales_invoices WHERE salesman_id = s.id) AS total_invoices,
                (SELECT COALESCE(SUM(total), 0) FROM sales_invoices WHERE salesman_id = s.id AND status = 'posted') AS total_sales_amount
                FROM salesmen s
                LEFT JOIN warehouses w ON w.id = s.warehouse_id
                WHERE $where
                ORDER BY s.left_date DESC, s.id DESC", $params);
        } elseif ($tab === 'employees') {
            $q = trim(inp('q'));
            $where = 'COALESCE(e.is_archived, 0) = 1';
            $params = [];
            if ($q !== '') {
                $where .= ' AND (e.code LIKE ? OR e.name LIKE ? OR e.phone LIKE ? OR e.cnic LIKE ? OR e.designation LIKE ? OR e.department LIKE ?)';
                $params = array_fill(0, 6, "%$q%");
            }
            $employees = db_all("SELECT e.*,
                (SELECT COALESCE(SUM(net_salary), 0) FROM payroll_payments WHERE employee_id = e.id) AS total_salary_paid
                FROM employees e
                WHERE $where
                ORDER BY e.left_date DESC, e.id DESC", $params);
        } elseif ($tab === 'masters') {
            $masters = Archive::summary();
        }

        view('records/index', [
            'tab' => $tab,
            'salesmen' => $salesmen,
            'employees' => $employees,
            'masters' => $masters,
            'archivedSalesmenCount' => $archivedSalesmenCount,
            'archivedEmployeesCount' => $archivedEmployeesCount,
            'activeSalesmenCount' => $activeSalesmenCount,
            'activeEmployeesCount' => $activeEmployeesCount,
            'title' => 'Company Records'
        ]);
    }

    /**
     * Mark a salesman or employee as left / deleted from active list.
     * Accessible only to Owner & Admin.
     */
    public static function markLeft(): void {
        require_post(); csrf_verify();
        if (!is_owner_or_admin()) {
            http_response_code(403);
            exit('Forbidden');
        }

        $type = inp('type');
        if (!in_array($type, ['salesmen', 'employees'], true)) {
            flash('error', __('Invalid record type.'));
            redirect('records');
        }

        $id = intv(inp('id'));
        $leftDate = inp('left_date') ?: today();
        $reason = trim((string)inp('reason'));
        if ($reason === '') {
            flash('error', __('A reason is required to remove this record.'));
            redirect($type === 'salesmen' ? 'salesmen/' . $id . '/profile' : 'employees/' . $id);
        }

        $table = $type;
        $row = db_one("SELECT * FROM `$table` WHERE id = ?", [$id]);
        if (!$row) {
            flash('error', __('Record not found.'));
            redirect($type);
        }

        $adminId = (int)($_SESSION['user_id'] ?? 0);
        Database::transaction(function () use ($table, $id, $leftDate, $reason, $adminId, $row) {
            db_exec("UPDATE `$table` SET is_archived = 1, is_active = 0, left_date = ?, departure_reason = ?, deactivated_at = ?, deactivated_by = ? WHERE id = ?", [
                $leftDate,
                $reason,
                now_local(),
                $adminId,
                $id
            ]);
            audit('left_company', $table, $id, "{$row['name']} marked as left on {$leftDate} by user #{$adminId} — {$reason}");
        });

        $typeLabel = $type === 'salesmen' ? __('Salesman') : __('Employee');
        flash('success', $typeLabel . ' ' . $row['name'] . __(' marked as left and moved to Records archive.'));
        redirect('records?tab=' . $type);
    }

    /**
     * Restore a departed salesman or employee back to active service.
     */
    public static function restore(): void {
        require_post(); csrf_verify();
        if (!is_owner_or_admin()) {
            http_response_code(403);
            exit('Forbidden');
        }

        $type = inp('type');
        if (!in_array($type, ['salesmen', 'employees'], true)) {
            flash('error', __('Invalid record type.'));
            redirect('records');
        }

        $id = intv(inp('id'));
        $table = $type;
        $row = db_one("SELECT * FROM `$table` WHERE id = ?", [$id]);
        if (!$row) {
            flash('error', __('Record not found.'));
            redirect('records?tab=' . $type);
        }

        $adminId = (int)($_SESSION['user_id'] ?? 0);
        $reason = trim((string)inp('reason')) ?: 'Rejoined company';

        Database::transaction(function () use ($table, $id, $reason, $adminId, $row) {
            db_exec("UPDATE `$table` SET is_archived = 0, is_active = 1, left_date = NULL, departure_reason = NULL, deactivated_at = NULL, deactivated_by = NULL WHERE id = ?", [$id]);
            audit('rejoined', $table, $id, "{$row['name']} restored to active service by user #{$adminId} — {$reason}");
        });

        $typeLabel = $type === 'salesmen' ? __('Salesman') : __('Employee');
        flash('success', $typeLabel . ' ' . $row['name'] . __(' restored to active service successfully.'));
        redirect($type === 'salesmen' ? 'salesmen/' . $id . '/profile' : 'employees/' . $id);
    }

    /**
     * Safely delete permanently — only allowed if no historical transaction references exist.
     */
    public static function delete(): void {
        require_post(); csrf_verify();
        if (!is_owner_or_admin()) {
            http_response_code(403);
            exit('Forbidden');
        }

        $type = inp('type');
        if (!in_array($type, ['salesmen', 'employees'], true)) {
            flash('error', __('Invalid record type.'));
            redirect('records');
        }

        $id = intv(inp('id'));
        $reason = trim((string)inp('reason'));
        if ($reason === '') {
            flash('error', __('A reason is required to delete.'));
            redirect('records?tab=' . $type);
        }

        try {
            Archive::delete($type, $id, (int)($_SESSION['user_id'] ?? 0), $reason);
            flash('success', __('Record deleted permanently.'));
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        redirect('records?tab=' . $type);
    }
}
