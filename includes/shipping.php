<?php
// ==========================================================================
// DUÁS - CÁLCULO DE FRETE
// Para cada forma de entrega ativa (admin > Formas de entrega) devolve valor e prazo
// para um CEP de destino:
//   1. frete grátis (campanha vigente ou "grátis acima de")  -> R$ 0,00
//   2. valor fixo cadastrado                                  -> flat_rate
//   3. Correios (API CWS) com as credenciais da forma de entrega
//   4. estimativa por região (quando a API não está configurada ou falha)
// ==========================================================================

require_once __DIR__ . '/settings.php';

const SHIPPING_ITEM_GRAMS = 400;   // peso médio de uma peça embalada
const SHIPPING_BOX_CM     = [30, 25, 10]; // comprimento, largura, altura da caixa

function shipping_digits(string $v): string
{
    return preg_replace('/\D+/', '', $v);
}

/** Peso em gramas dos itens do carrinho (mínimo 300 g). */
function shipping_weight_grams(int $qty): int
{
    return max(300, $qty * SHIPPING_ITEM_GRAMS);
}

/** Chamada HTTP simples (cURL). @return array{status:int,body:?array} */
function shipping_http(string $method, string $url, array $headers = [], ?string $body = null): array
{
    if (!function_exists('curl_init')) return ['status' => 0, 'body' => null];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $headers),
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => $raw === false ? null : (json_decode((string) $raw, true) ?: null)];
}

/** Token da API dos Correios (CWS), guardado em cache até perto de expirar. */
function shipping_correios_token(array $set): ?string
{
    $user = trim((string) ($set['contract_code'] ?? ''));
    $pass = trim((string) ($set['contract_password'] ?? ''));
    $card = trim((string) ($set['postcard'] ?? ''));
    if ($user === '' || $pass === '' || $card === '') return null;

    $cache = sys_get_temp_dir() . '/duas_correios_' . md5($user . $card) . '.json';
    if (is_file($cache)) {
        $c = json_decode((string) @file_get_contents($cache), true);
        if (!empty($c['token']) && ($c['exp'] ?? 0) > time() + 120) return $c['token'];
    }

    $r = shipping_http(
        'POST',
        'https://api.correios.com.br/token/v1/autentica/cartaopostagem',
        ['Content-Type: application/json', 'Authorization: Basic ' . base64_encode($user . ':' . $pass)],
        json_encode(['numero' => $card])
    );
    $token = $r['body']['token'] ?? null;
    if (!$token) return null;

    $exp = isset($r['body']['expiraEm']) ? (int) strtotime((string) $r['body']['expiraEm']) : time() + 3000;
    @file_put_contents($cache, json_encode(['token' => $token, 'exp' => $exp]));
    return $token;
}

/** Preço e prazo reais pela API dos Correios. @return array{cost:float,days:?int}|null */
function shipping_correios_quote(array $method, string $cep): ?array
{
    $set    = $method['settings'] ?? [];
    $code   = trim((string) ($method['service_code'] ?? ''));
    $origin = shipping_digits((string) ($set['origin_cep'] ?? ''));
    if ($code === '' || strlen($origin) !== 8) return null;

    $token = shipping_correios_token($set);
    if (!$token) return null;
    $auth = ['Authorization: Bearer ' . $token];

    [$len, $wid, $hei] = SHIPPING_BOX_CM;
    $q = http_build_query([
        'cepOrigem' => $origin, 'cepDestino' => $cep,
        'psObjeto' => (int) ($method['_weight'] ?? 500), 'tpObjeto' => 2,
        'comprimento' => $len, 'largura' => $wid, 'altura' => $hei,
    ]);
    $price = shipping_http('GET', "https://api.correios.com.br/preco/v1/nacional/{$code}?{$q}", $auth);
    if ($price['status'] !== 200 || empty($price['body']['pcFinal'])) return null;
    $cost = (float) str_replace(',', '.', str_replace('.', '', (string) $price['body']['pcFinal']));

    $days = null;
    $term = shipping_http('GET', "https://api.correios.com.br/prazo/v1/nacional/{$code}?" . http_build_query(['cepOrigem' => $origin, 'cepDestino' => $cep]), $auth);
    if ($term['status'] === 200 && isset($term['body']['prazoEntrega'])) $days = (int) $term['body']['prazoEntrega'];

    return ['cost' => round($cost, 2), 'days' => $days];
}

/**
 * Estimativa por região (1º dígito do CEP) usada quando não há API configurada.
 * @return array{cost:float,days:int}
 */
function shipping_estimate(array $method, string $cep): array
{
    $origin = shipping_digits((string) (($method['settings'] ?? [])['origin_cep'] ?? ''));
    $o = strlen($origin) === 8 ? (int) $origin[0] : 0; // padrão: Grande São Paulo
    $diff = abs((int) $cep[0] - $o);

    $sedex = stripos($method['label'] . ' ' . ($method['service_code'] ?? ''), 'sedex') !== false
          || ($method['service_code'] ?? '') === '03220';
    $weightExtra = max(0, ((int) ($method['_weight'] ?? 500) - 500) / 1000) * 4.5;

    if ($sedex) {
        return ['cost' => round(29.90 + 7.5 * $diff + $weightExtra, 2), 'days' => 1 + (int) ceil($diff / 2) + 1];
    }
    return ['cost' => round(19.90 + 4.8 * $diff + $weightExtra, 2), 'days' => 4 + $diff];
}

/**
 * Calcula todas as opções de entrega para o CEP.
 *
 * @return array<int,array{id:int,label:string,cost:float,days_min:?int,days_max:?int,free:bool,estimated:bool}>
 */
function shipping_quotes(string $cep, float $subtotal, int $qty, bool $freeCampaign): array
{
    $cep = shipping_digits($cep);
    if (strlen($cep) !== 8) return [];

    $out = [];
    foreach (get_active_shipping_methods() as $m) {
        $m['_weight'] = shipping_weight_grams($qty);
        $isFree = $freeCampaign
            || ($m['free_above'] !== null && $subtotal >= (float) $m['free_above'])
            || $m['carrier'] === 'retirada';

        $cost = null; $days = null; $estimated = false;

        if ($m['flat_rate'] !== null) {
            $cost = (float) $m['flat_rate'];
        } elseif ($m['carrier'] === 'correios') {
            $real = shipping_correios_quote($m, $cep);
            if ($real) { $cost = $real['cost']; $days = $real['days']; }
        }
        if ($cost === null && !$isFree) {
            $est = shipping_estimate($m, $cep);
            $cost = $est['cost']; $days = $est['days']; $estimated = true;
        }

        $min = $m['estimated_days_min'] !== null ? (int) $m['estimated_days_min'] : $days;
        $max = $m['estimated_days_max'] !== null ? (int) $m['estimated_days_max'] : $days;

        $out[] = [
            'id'        => (int) $m['id'],
            'label'     => (string) $m['label'],
            'cost'      => $isFree ? 0.0 : (float) $cost,
            'days_min'  => $min,
            'days_max'  => $max,
            'free'      => $isFree,
            'estimated' => $estimated && !$isFree,
        ];
    }
    usort($out, fn($a, $b) => $a['cost'] <=> $b['cost']);
    return $out;
}
