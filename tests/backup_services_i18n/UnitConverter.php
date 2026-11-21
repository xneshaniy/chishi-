<?php
/**
 * Unit conversions — raw materials tracked in KG base; finished goods show packaging units.
 * Conversion factors and packaging defaults are configurable in Admin Settings (company_settings).
 */
final class UnitConverter {

    /** kg factor for a purchase unit short (TON/KG/MAN, case-insensitive). */
    public static function kgFactor(string $unitShort): float {
        $u = strtoupper(trim($unitShort));
        switch ($u) {
            case 'TON': return (float)setting('rm_unit_ton', '1000');
            case 'MAN': return (float)setting('rm_unit_man', '40');
            case 'KG':  return (float)setting('rm_unit_kg', '1');
            default:    return 1.0; // unknown unit codes fall back to 1 kg per unit (documented)
        }
    }

    /** Convert a quantity in purchase unit to KG base. */
    public static function toKg(float $qty, string $unitShort): float { return round($qty * self::kgFactor($unitShort), 3); }

    /** Convert KG to an equivalent quantity in target unit. */
    public static function fromKg(float $qtyKg, string $unitShort): float {
        $f = self::kgFactor($unitShort);
        return $f > 0 ? $qtyKg / $f : $qtyKg;
    }

    /** Format a KG quantity for display, e.g. "2,100.000 KG (2.100 TON · 52.500 MAN)". */
    public static function formatStock(float $qtyKg): string {
        $kg = number_format($qtyKg, 3);
        $ton = number_format(self::fromKg($qtyKg, 'TON'), 3);
        $man = number_format(self::fromKg($qtyKg, 'MAN'), 3);
        return $kg . ' KG (' . $ton . ' TON · ' . $man . ' MAN)';
    }

    /** Global packaging defaults (Admin Settings): pieces per pack, packs per bundle, bundles per packet. */
    public static function packagingDefaults(): array {
        $pack = (float)setting('packaging_pack_pieces', '12');
        $bundle = (float)setting('packaging_bundle_packs', '0');   // 0/blank = not configured
        $packet = (float)setting('packaging_packet_bundles', '0'); // 0/blank = not configured
        return [
            'pack_pieces' => $pack > 0 ? $pack : 0,
            'bundle_packs' => $bundle > 0 ? $bundle : 0,
            'packet_bundles' => $packet > 0 ? $packet : 0,
        ];
    }

    /**
     * Packaging conversion config for a product, resolved product-level over global defaults.
     * Returns ['pack_pieces'=>float|0, 'bundle_packs'=>float|0, 'packet_bundles'=>float|0]
     * A zero value means that conversion tier is not configured (display skipped).
     */
    public static function productPackaging(int $productId): array {
        $row = db_one('SELECT * FROM product_packaging WHERE product_id = ?', [$productId]);
        $defaults = self::packagingDefaults();
        $resolve = function ($value, $default) {
            if ($value === null || $value === '') return $default;  // inherit default
            $v = (float)$value;
            return $v > 0 ? $v : 0;                                // 0/negative = not configured
        };
        if (!$row) return $defaults;
        return [
            'pack_pieces'    => $resolve($row['pack_pieces'], $defaults['pack_pieces']),
            'bundle_packs'   => $resolve($row['bundle_packs'], $defaults['bundle_packs']),
            'packet_bundles' => $resolve($row['packet_bundles'], $defaults['packet_bundles']),
        ];
    }

    /**
     * Convert a base FG quantity (in the product's own stock unit —
     * pieces for PCS products — using exact configured conversions only).
     * Returns an ordered breakdown string like "144.000 Pc (12 Packs, 4.00 Bundle, 0.80 Packet)".
     */
    public static function packagingDisplay(float $qty, int $productId): string {
        $cfg = self::productPackaging($productId);
        $parts = [];
        if ($cfg['pack_pieces'] > 0 && $cfg['pack_pieces'] != 0) {
            $packs = $qty / $cfg['pack_pieces'];
            $parts[] = number_format($packs, 2) . ' Packs';
        }
        $packsFloat = $cfg['pack_pieces'] > 0 ? $qty / $cfg['pack_pieces'] : 0;
        if ($cfg['bundle_packs'] > 0 && $packsFloat > 0) {
            $bundles = $packsFloat / $cfg['bundle_packs'];
            $parts[] = number_format($bundles, 2) . ' Bundles';
        }
        $bundlesFloat = ($cfg['bundle_packs'] > 0 && $packsFloat > 0) ? $packsFloat / $cfg['bundle_packs'] : 0;
        if ($cfg['packet_bundles'] > 0 && $bundlesFloat > 0) {
            $packets = $bundlesFloat / $cfg['packet_bundles'];
            $parts[] = number_format($packets, 2) . ' Packets';
        }
        return $parts ? implode(' · ', $parts) : '—';
    }
}
