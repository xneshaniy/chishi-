<?php
final class VehiclesController {
    public static function index(): void {
        $rows = db_all("SELECT v.*,
            (SELECT COALESCE(SUM(cost),0) FROM vehicle_fuel_logs f WHERE f.vehicle_id = v.id) AS fuel_total,
            (SELECT COALESCE(SUM(cost),0) FROM vehicle_maintenance m WHERE m.vehicle_id = v.id) AS maint_total,
            (SELECT COALESCE(SUM(liters),0) FROM vehicle_fuel_logs f WHERE f.vehicle_id = v.id) AS fuel_liters
            FROM vehicles v ORDER BY v.reg_no");
        view('vehicles/index', ['rows' => $rows, 'title' => 'Vehicles']);
    }

    public static function vehicleNew(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            $reg = strtoupper(inp('reg_no'));
            if ($reg === '') { flash('error', __('Registration number required.')); redirect('vehicles/new'); }
            try {
                db_insert('vehicles', ['reg_no' => $reg, 'make' => inp('make'), 'model' => inp('model'), 'year' => intv(inp('year')) ?: null, 'driver_name' => inp('driver_name'), 'is_active' => 1]);
                audit('create', 'vehicles', null, $reg);
                flash('success', __('Vehicle added.'));
                redirect('vehicles');
            } catch (PDOException $e) { flash('error', __('Registration number already exists.')); redirect('vehicles/new'); }
        }
        view('vehicles/new', ['title' => 'New Vehicle']);
    }

    public static function vehicleView(): void {
        $id = intv(inp('id'));
        $v = db_one('SELECT * FROM vehicles WHERE id = ?', [$id]);
        if (!$v) { flash('error', __('Vehicle not found.')); redirect('vehicles'); }
        $fuel = db_all('SELECT * FROM vehicle_fuel_logs WHERE vehicle_id = ? ORDER BY date DESC, id DESC LIMIT 50', [$id]);
        $maint = db_all('SELECT * FROM vehicle_maintenance WHERE vehicle_id = ? ORDER BY date DESC, id DESC LIMIT 50', [$id]);
        $totals = db_one('SELECT (SELECT COALESCE(SUM(cost),0) FROM vehicle_fuel_logs WHERE vehicle_id = ?) AS fuel, (SELECT COALESCE(SUM(liters),0) FROM vehicle_fuel_logs WHERE vehicle_id = ?) AS liters, (SELECT COALESCE(SUM(cost),0) FROM vehicle_maintenance WHERE vehicle_id = ?) AS maint', [$id, $id, $id]);
        view('vehicles/view', ['v' => $v, 'fuel' => $fuel, 'maint' => $maint, 'totals' => $totals, 'title' => 'Vehicle — ' . $v['reg_no']]);
    }

    public static function fuelNew(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        $cost = round(num(inp('cost')), 2); $liters = round(num(inp('liters')), 2);
        if ($cost < 0 || $liters <= 0) { flash('error', __('Liters and cost are required.')); redirect('vehicles/' . $id); }
        db_insert('vehicle_fuel_logs', ['vehicle_id' => $id, 'date' => inp('date', today()), 'liters' => $liters, 'cost' => $cost, 'mileage' => intv(inp('mileage')) ?: null, 'station' => inp('station')]);
        audit('create', 'vehicle_fuel_logs', $id, "Fuel: $liters L / Rs $cost");
        flash('success', __('Fuel log added.'));
        redirect('vehicles/' . $id);
    }

    public static function maintenanceNew(): void {
        require_post(); csrf_verify();
        $id = intv(inp('id'));
        $desc = inp('description'); $cost = round(num(inp('cost')), 2);
        if ($desc === '' || $cost < 0) { flash('error', __('Description and cost required.')); redirect('vehicles/' . $id); }
        db_insert('vehicle_maintenance', ['vehicle_id' => $id, 'date' => inp('date', today()), 'description' => $desc, 'vendor' => inp('vendor'), 'cost' => $cost]);
        audit('create', 'vehicle_maintenance', $id, "Maintenance: $desc / Rs $cost");
        flash('success', __('Maintenance recorded.'));
        redirect('vehicles/' . $id);
    }
}
