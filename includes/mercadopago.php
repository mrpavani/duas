<?php
// ==========================================================================
// DUÁS - CLIENTE MERCADO PAGO
// Cria preferência de Checkout Pro, consulta pagamentos e traduz as
// respostas (status / status_detail) em mensagens amigáveis em pt-BR.
// ==========================================================================

require_once __DIR__ . '/settings.php';

define('MP_API_BASE', 'https://api.mercadopago.com');

/**
 * Retorna o meio de pagamento Mercado Pago ativo + credenciais decodificadas.
 * @return array{row:array,access_token:string,public_key:string,sandbox:bool}|null
 */
function mp_config(): ?array
{
    foreach (get_active_payment_methods() as $pm) {
        if ($pm['provider'] === 'mercado_pago') {
            $c = $pm['credentials'] ?? [];
            $token = trim((string) ($c['access_token'] ?? ''));
            if ($token === '') return null; // cadastrado mas sem credencial
            return [
                'row'          => $pm,
                'access_token' => $token,
                'public_key'   => trim((string) ($c['public_key'] ?? '')),
                'sandbox'      => ($pm['environment'] ?? 'sandbox') !== 'production',
            ];
        }
    }
    return null;
}

function mp_is_ready(): bool
{
    return mp_config() !== null;
}

/**
 * O Mercado Pago só aceita notification_url / back_urls em HTTPS público.
 * Em ambiente local (http, localhost, IP privado) devolve null para que a
 * URL simplesmente não seja enviada — o pagamento continua funcionando e a
 * conciliação é feita pelo botão "Sincronizar" no /admin.
 */
function mp_public_url(?string $url): ?string
{
    if (!$url) return null;
    $p = parse_url($url);
    if (($p['scheme'] ?? '') !== 'https') return null;

    $host = $p['host'] ?? '';
    if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) return null;
    if (str_ends_with($host, '.local') || str_ends_with($host, '.test')) return null;
    if (filter_var($host, FILTER_VALIDATE_IP)
        && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return null;
    }
    return $url;
}

/**
 * Bundle de CAs usado para validar o certificado TLS do Mercado Pago.
 * Se o php.ini já define curl.cainfo/openssl.cafile, respeita a configuração
 * do servidor; senão usa o bundle versionado em /certs.
 */
function mp_ca_bundle(): ?string
{
    foreach ([ini_get('curl.cainfo'), ini_get('openssl.cafile')] as $configured) {
        if (is_string($configured) && $configured !== '' && is_readable($configured)) {
            return null; // o servidor já resolve a verificação
        }
    }
    $bundled = __DIR__ . '/../certs/cacert.pem';
    return is_readable($bundled) ? $bundled : null;
}

/**
 * Chamada HTTP à API do Mercado Pago.
 * @return array{ok:bool,status:int,data:array,error:?string}
 */
function mp_api(string $method, string $path, ?array $body, string $token, array $extraHeaders = []): array
{
    $ch = curl_init(MP_API_BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => array_merge([
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ], $extraHeaders),
    ]);
    if ($ca = mp_ca_bundle()) {
        curl_setopt($ch, CURLOPT_CAINFO, $ca);
    }
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlErr = curl_error($ch);
    // curl_close() é obsoleto no PHP 8+: o handle é liberado automaticamente.

    if ($raw === false) {
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'Falha de conexão com o Mercado Pago: ' . $curlErr];
    }

    $data = json_decode((string) $raw, true);
    if (!is_array($data)) $data = ['raw' => $raw];

    $ok = $status >= 200 && $status < 300;
    $error = $ok ? null : ($data['message'] ?? ('Erro HTTP ' . $status . ' do Mercado Pago.'));

    return ['ok' => $ok, 'status' => $status, 'data' => $data, 'error' => $error];
}

/**
 * Cria uma preferência de Checkout Pro para o pedido.
 * @return array{ok:bool,init_point:?string,preference_id:?string,error:?string,data:array}
 */
function mp_create_preference(array $order, array $items, string $backUrl, ?string $notificationUrl = null): array
{
    $cfg = mp_config();
    if (!$cfg) return ['ok' => false, 'init_point' => null, 'preference_id' => null, 'error' => 'Mercado Pago não configurado.', 'data' => []];

    $mpItems = [];
    foreach ($items as $it) {
        $mpItems[] = [
            'title'       => mb_substr((string) $it['name'], 0, 250),
            'quantity'    => (int) $it['qty'],
            'currency_id' => 'BRL',
            'unit_price'  => round((float) $it['price'], 2),
        ];
    }
    // desconto / frete como itens de ajuste
    if (!empty($order['discount']) && (float) $order['discount'] > 0) {
        $mpItems[] = ['title' => 'Desconto', 'quantity' => 1, 'currency_id' => 'BRL', 'unit_price' => -round((float) $order['discount'], 2)];
    }
    if (!empty($order['shipping']) && (float) $order['shipping'] > 0) {
        $mpItems[] = ['title' => 'Frete', 'quantity' => 1, 'currency_id' => 'BRL', 'unit_price' => round((float) $order['shipping'], 2)];
    }

    $body = [
        'items'              => $mpItems,
        'external_reference' => $order['order_code'],
        'back_urls'          => ['success' => $backUrl, 'failure' => $backUrl, 'pending' => $backUrl],
        'statement_descriptor' => 'DUAS',
    ];
    // auto_return e notification_url exigem HTTPS público
    if (mp_public_url($backUrl)) $body['auto_return'] = 'approved';
    if ($notify = mp_public_url($notificationUrl)) $body['notification_url'] = $notify;
    if (!empty($order['customer_email'])) {
        $body['payer'] = ['email' => $order['customer_email'], 'name' => $order['customer_name'] ?? ''];
    }

    $res = mp_api('POST', '/checkout/preferences', $body, $cfg['access_token']);
    if (!$res['ok']) {
        return ['ok' => false, 'init_point' => null, 'preference_id' => null, 'error' => $res['error'], 'data' => $res['data']];
    }

    $d = $res['data'];
    $point = $cfg['sandbox'] ? ($d['sandbox_init_point'] ?? $d['init_point'] ?? null) : ($d['init_point'] ?? null);
    return ['ok' => true, 'init_point' => $point, 'preference_id' => $d['id'] ?? null, 'error' => null, 'data' => $d];
}

/**
 * Consulta um pagamento pelo id.
 * @return array{ok:bool,data:array,error:?string}
 */
function mp_get_payment(string $paymentId): array
{
    $cfg = mp_config();
    if (!$cfg) return ['ok' => false, 'data' => [], 'error' => 'Mercado Pago não configurado.'];
    $res = mp_api('GET', '/v1/payments/' . rawurlencode($paymentId), null, $cfg['access_token']);
    return ['ok' => $res['ok'], 'data' => $res['data'], 'error' => $res['error']];
}

// --------------------------------------------------------------------------
// Tradução de status
// --------------------------------------------------------------------------

/** Status geral do pagamento -> rótulo pt-BR + tom visual. */
function mp_status_label(string $status): array
{
    $map = [
        'approved'     => ['Aprovado', 'ok'],
        'authorized'   => ['Autorizado (aguardando captura)', 'info'],
        'in_process'   => ['Em análise', 'warn'],
        'in_mediation' => ['Em disputa', 'warn'],
        'pending'      => ['Pendente', 'warn'],
        'rejected'     => ['Recusado', 'error'],
        'cancelled'    => ['Cancelado', 'error'],
        'refunded'     => ['Estornado', 'error'],
        'charged_back' => ['Chargeback', 'error'],
    ];
    [$label, $tone] = $map[$status] ?? [ucfirst($status ?: 'desconhecido'), 'warn'];
    return ['label' => $label, 'tone' => $tone];
}

/**
 * Mensagem amigável a partir de status + status_detail do Mercado Pago.
 * @return array{tone:string,title:string,message:string}
 */
function mp_friendly(string $status, ?string $statusDetail): array
{
    $detail = (string) $statusDetail;

    $details = [
        // aprovados / pendentes
        'accredited'                          => ['ok',   'Pagamento aprovado e creditado.'],
        'partially_refunded'                  => ['warn', 'Pagamento aprovado com estorno parcial.'],
        'pending_contingency'                 => ['warn', 'Pagamento em processamento no Mercado Pago. Reprocesse em alguns minutos.'],
        'pending_review_manual'               => ['warn', 'Pagamento em análise manual pelo Mercado Pago. Aguarde a liberação.'],
        'pending_waiting_payment'             => ['warn', 'Aguardando o pagamento (Pix ou boleto ainda não compensado).'],
        'pending_waiting_transfer'            => ['warn', 'Aguardando a transferência do Pix.'],
        'pending_capture'                     => ['info', 'Pagamento autorizado, aguardando captura do valor.'],
        // recusas de cartão
        'cc_rejected_bad_filled_card_number'  => ['error', 'Número do cartão inválido. Confira e digite novamente.'],
        'cc_rejected_bad_filled_date'         => ['error', 'Data de validade do cartão inválida.'],
        'cc_rejected_bad_filled_security_code' => ['error', 'Código de segurança (CVV) inválido.'],
        'cc_rejected_bad_filled_other'        => ['error', 'Há dados do cartão incorretos. Revise as informações e tente de novo.'],
        'cc_rejected_insufficient_amount'     => ['error', 'Cartão sem limite/saldo suficiente para esta compra.'],
        'cc_rejected_high_risk'               => ['error', 'Pagamento recusado por análise de risco. Tente outro meio de pagamento.'],
        'cc_rejected_call_for_authorize'      => ['error', 'O emissor do cartão pede autorização. Ligue para o banco e autorize o valor.'],
        'cc_rejected_card_disabled'           => ['error', 'Cartão desabilitado. Contate o banco emissor para ativá-lo.'],
        'cc_rejected_card_error'              => ['error', 'Não foi possível processar o cartão. Tente novamente em instantes.'],
        'cc_rejected_duplicated_payment'      => ['error', 'Pagamento duplicado: já existe uma cobrança com os mesmos dados.'],
        'cc_rejected_max_attempts'            => ['error', 'Número máximo de tentativas atingido. Use outro cartão.'],
        'cc_rejected_invalid_installments'    => ['error', 'O número de parcelas escolhido não é permitido para este cartão.'],
        'cc_rejected_other_reason'            => ['error', 'O emissor do cartão recusou o pagamento. Tente outro cartão ou meio de pagamento.'],
        'cc_rejected_blacklist'              => ['error', 'Cartão bloqueado para compras. Contate o emissor.'],
        'cc_rejected_3ds_challenge'          => ['error', 'A autenticação do cartão (3D Secure) não foi concluída.'],
        'rejected_by_bank'                   => ['error', 'Pagamento rejeitado pelo banco emissor.'],
        'rejected_insufficient_data'         => ['error', 'Faltam dados para concluir o pagamento.'],
        'bank_error'                         => ['error', 'Erro no banco emissor ao processar o pagamento. Tente novamente.'],
        'expired'                            => ['error', 'O prazo para pagamento expirou. Refaça o pedido.'],
        'by_collector'                       => ['error', 'Pagamento cancelado pela loja.'],
        'by_payer'                           => ['error', 'Pagamento cancelado pelo cliente.'],
    ];

    if (isset($details[$detail])) {
        [$tone, $msg] = $details[$detail];
    } else {
        $base = mp_status_label($status);
        $tone = $base['tone'];
        $msg = $detail !== ''
            ? 'Retorno do Mercado Pago: ' . $detail . '.'
            : 'Pagamento com status "' . $base['label'] . '".';
    }

    $titles = ['ok' => 'Pagamento aprovado', 'info' => 'Pagamento autorizado', 'warn' => 'Pagamento pendente', 'error' => 'Pagamento não aprovado'];
    return ['tone' => $tone, 'title' => $titles[$tone] ?? 'Pagamento', 'message' => $msg];
}

// --------------------------------------------------------------------------
// Checkout transparente: métodos disponíveis + criação de pagamento
// --------------------------------------------------------------------------

/**
 * Métodos de pagamento ATIVOS da conta (GET /v1/payment_methods).
 * @return array<int,array<string,mixed>>
 */
function mp_payment_methods(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $cfg = mp_config();
    if (!$cfg) return $cache = [];

    $res = mp_api('GET', '/v1/payment_methods', null, $cfg['access_token']);
    if (!$res['ok'] || !is_array($res['data'])) return $cache = [];

    $out = [];
    foreach ($res['data'] as $m) {
        if (!is_array($m) || ($m['status'] ?? '') !== 'active') continue;
        $out[] = [
            'id'                     => $m['id'] ?? '',
            'name'                   => $m['name'] ?? ($m['id'] ?? ''),
            'payment_type_id'        => $m['payment_type_id'] ?? '',
            'thumbnail'              => $m['secure_thumbnail'] ?? $m['thumbnail'] ?? null,
            'additional_info_needed' => $m['additional_info_needed'] ?? [],
        ];
    }
    return $cache = $out;
}

/** Tipos que exigem tokenização de cartão no navegador. */
const MP_CARD_TYPES = ['credit_card', 'debit_card', 'prepaid_card'];

/**
 * Tipos que o checkout transparente desta loja consegue processar.
 * Fora da lista (ex.: account_money, digital_wallet) só funcionam no
 * Checkout Pro, com o comprador logado no Mercado Pago.
 */
const MP_SUPPORTED_TYPES = ['credit_card', 'debit_card', 'prepaid_card', 'bank_transfer', 'ticket', 'atm'];

function mp_is_card_type(string $type): bool
{
    return in_array($type, MP_CARD_TYPES, true);
}

/**
 * Métodos ativos agrupados por tipo de pagamento, restritos aos que o
 * checkout transparente suporta.
 * @return array<string,array<int,array<string,mixed>>>
 */
function mp_available_types(): array
{
    $groups = [];
    foreach (mp_payment_methods() as $m) {
        $type = $m['payment_type_id'];
        if ($type === '' || !in_array($type, MP_SUPPORTED_TYPES, true)) continue;
        $groups[$type][] = $m;
    }
    // ordem de exibição preferida
    $order = ['credit_card', 'debit_card', 'prepaid_card', 'bank_transfer', 'ticket', 'atm'];
    uksort($groups, function ($a, $b) use ($order) {
        $ia = array_search($a, $order, true);
        $ib = array_search($b, $order, true);
        return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
    });
    return $groups;
}

function mp_type_label(string $type): string
{
    return [
        'credit_card'   => 'Cartão de crédito',
        'debit_card'    => 'Cartão de débito',
        'prepaid_card'  => 'Cartão pré-pago',
        'bank_transfer' => 'Pix',
        'ticket'        => 'Boleto bancário',
        'atm'           => 'Pagamento em caixa / lotérica',
        'account_money' => 'Saldo em conta Mercado Pago',
    ][$type] ?? $type;
}

/**
 * Cria um pagamento (POST /v1/payments) no Checkout transparente.
 * @return array{ok:bool,data:array,error:?string}
 */
function mp_create_payment(array $order, array $payload): array
{
    $cfg = mp_config();
    if (!$cfg) return ['ok' => false, 'data' => [], 'error' => 'Mercado Pago não configurado.'];

    // A mesma chave de idempotência é reusada na retentativa: se o primeiro
    // envio chegou a criar o pagamento, o MP devolve o mesmo em vez de duplicar.
    $idem = ($order['order_code'] ?? 'DUAS') . '-' . bin2hex(random_bytes(4));

    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $res = mp_api('POST', '/v1/payments', $payload, $cfg['access_token'], ['X-Idempotency-Key: ' . $idem]);
        // 5xx é instabilidade do lado do MP: vale uma retentativa
        if ($res['ok'] || $res['status'] < 500) break;
        if ($attempt === 1) usleep(700000);
    }

    return ['ok' => $res['ok'], 'data' => $res['data'], 'error' => $res['error']];
}

/**
 * Extrai dados de exibição de Pix / boleto de uma resposta de pagamento.
 * @return array<string,string>
 */
function mp_payment_extra(array $payment): array
{
    $out = [];
    $poi = $payment['point_of_interaction']['transaction_data'] ?? null;
    if (is_array($poi)) {
        if (!empty($poi['qr_code_base64'])) $out['pix_qr_base64'] = $poi['qr_code_base64'];
        if (!empty($poi['qr_code']))        $out['pix_code'] = $poi['qr_code'];
        if (!empty($poi['ticket_url']))     $out['pix_ticket_url'] = $poi['ticket_url'];
    }
    $td = $payment['transaction_details'] ?? [];
    if (!empty($td['external_resource_url'])) $out['boleto_url'] = $td['external_resource_url'];
    if (!empty($payment['barcode']['content'])) $out['boleto_barcode'] = $payment['barcode']['content'];
    return $out;
}

/**
 * Lista de mensagens de erro amigáveis a partir de uma resposta de pagamento.
 * @return string[]
 */
function mp_error_messages(array $payment): array
{
    $out = [];
    $status = (string) ($payment['status'] ?? '');
    $detail = (string) ($payment['status_detail'] ?? '');

    // Erro da API: "status" vem como código HTTP (400/500) e a mensagem útil
    // está em message/cause.
    if (ctype_digit($status)) {
        if ((int) $status >= 500) {
            return ['O Mercado Pago está instável neste momento e não conseguiu processar o pagamento. Aguarde alguns instantes e tente novamente.'];
        }
        foreach ((array) ($payment['cause'] ?? []) as $cause) {
            $c = is_array($cause) ? ($cause['description'] ?? $cause['code'] ?? '') : (string) $cause;
            if ($c !== '') $out[] = 'Mercado Pago: ' . $c;
        }
        if (!$out && !empty($payment['message'])) {
            $out[] = 'Mercado Pago: ' . $payment['message'];
        }
        return array_values(array_unique(array_filter($out)));
    }

    // Status de pagamento: só é "erro" quando de fato falhou. Pendente / em
    // análise já é comunicado pela mensagem principal do resultado.
    if (in_array($status, ['rejected', 'cancelled', 'refunded', 'charged_back'], true)) {
        $out[] = mp_friendly($status, $detail)['message'];
    }
    return array_values(array_unique(array_filter($out)));
}
