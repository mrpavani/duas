<?php
// ==========================================================================
// DUÁS - LEITURA DE CONFIGURAÇÕES DA LOJA (cadastradas na área /admin)
// Meios de pagamento e formas de entrega ativos, para uso no checkout.
// ==========================================================================

require_once __DIR__ . '/db.php';

/**
 * Meios de pagamento ativos, ordenados por sort_order.
 * @return array<int,array<string,mixed>> cada item já com `credentials` decodificado
 */
function get_active_payment_methods(): array
{
    $rows = db()->query(
        'SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY sort_order, label'
    )->fetchAll();

    foreach ($rows as &$row) {
        $row['credentials'] = json_decode((string) ($row['credentials'] ?? ''), true) ?: [];
    }
    return $rows;
}

/**
 * Formas de entrega ativas, ordenadas por sort_order.
 * @return array<int,array<string,mixed>> cada item já com `settings` decodificado
 */
function get_active_shipping_methods(): array
{
    $rows = db()->query(
        'SELECT * FROM shipping_methods WHERE is_active = 1 ORDER BY sort_order, label'
    )->fetchAll();

    foreach ($rows as &$row) {
        $row['settings'] = json_decode((string) ($row['settings'] ?? ''), true) ?: [];
    }
    return $rows;
}

/**
 * Calcula o valor de uma forma de entrega para um subtotal.
 * Regras: free_above zera o frete; senão usa flat_rate; senão retorna null (calcular via API).
 */
function shipping_cost_for(array $method, float $subtotal): ?float
{
    if ($method['free_above'] !== null && $subtotal >= (float) $method['free_above']) {
        return 0.0;
    }
    return $method['flat_rate'] !== null ? (float) $method['flat_rate'] : null;
}

// --------------------------------------------------------------------------
// Configurações chave/valor (tabela site_settings, geridas em /admin)
// --------------------------------------------------------------------------
function get_setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll() as $r) {
            $cache[$r['setting_key']] = $r['setting_value'];
        }
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

function set_setting(string $key, ?string $value): void
{
    db()->prepare(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    )->execute([$key, $value]);
}
