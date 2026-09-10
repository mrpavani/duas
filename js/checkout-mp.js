/* ==========================================================================
   DUÁS - Checkout transparente Mercado Pago
   Mostra os campos exigidos por tipo de pagamento e tokeniza o cartão
   no navegador (o PAN nunca chega ao servidor).
   ========================================================================== */
(function () {
    var cfg = window.DUAS_MP || {};
    var form = document.getElementById('checkoutForm');
    if (!form) return;

    var sections = form.querySelectorAll('.mp-fields');
    var instWrap = form.querySelector('.mp-installments-wrap');

    var CARD_TYPES = cfg.cardTypes || ['credit_card', 'debit_card', 'prepaid_card'];

    function currentType() {
        var c = form.querySelector('input[name="mp_type"]:checked');
        return c ? c.value : null;
    }
    function isCard(t) {
        return CARD_TYPES.indexOf(t) !== -1;
    }

    // Mostra/oculta os blocos conforme o tipo escolhido e liga/desliga os campos
    function syncSections() {
        var t = currentType();
        sections.forEach(function (s) {
            var d = s.dataset.type;
            var on = d === t || (d === 'card' && isCard(t));
            s.hidden = !on;
            s.querySelectorAll('input, select').forEach(function (el) {
                el.disabled = !on;
            });
        });
        // parcelas só existem em crédito
        if (instWrap) instWrap.hidden = t !== 'credit_card';
    }

    form.querySelectorAll('input[name="mp_type"]').forEach(function (i) {
        i.addEventListener('change', syncSections);
    });
    syncSections();

    // Máscara simples de validade MM/AA
    var exp = form.querySelector('[name="mp_card_exp"]');
    if (exp) {
        exp.addEventListener('input', function () {
            var v = this.value.replace(/\D/g, '').slice(0, 4);
            this.value = v.length > 2 ? v.slice(0, 2) + '/' + v.slice(2) : v;
        });
    }

    if (!cfg.publicKey || typeof MercadoPago === 'undefined') return;

    var mp = new MercadoPago(cfg.publicKey, { locale: 'pt-BR' });
    var num = form.querySelector('[name="mp_card_number"]');
    var pmId = form.querySelector('[name="mp_payment_method_id"]');
    var issuer = form.querySelector('[name="mp_issuer_id"]');
    var instSel = form.querySelector('[name="mp_installments"]');
    var lastBin = '';

    async function resolveCard() {
        if (!num) return;
        var bin = num.value.replace(/\D/g, '').slice(0, 8);
        if (bin.length < 6 || bin === lastBin) return;
        lastBin = bin;
        try {
            var pm = await mp.getPaymentMethods({ bin: bin });
            var first = pm && pm.results && pm.results[0];
            if (!first) return;
            pmId.value = first.id;

            try {
                var iss = await mp.getIssuers({ paymentMethodId: first.id, bin: bin });
                if (iss && iss[0]) issuer.value = iss[0].id;
            } catch (e) { /* alguns cartões não têm issuer */ }

            if (currentType() === 'credit_card' && instSel) {
                try {
                    var inst = await mp.getInstallments({ amount: String(cfg.amount), bin: bin });
                    var costs = inst && inst[0] && inst[0].payer_costs;
                    if (costs && costs.length) {
                        instSel.innerHTML = '';
                        costs.forEach(function (p) {
                            var o = document.createElement('option');
                            o.value = p.installments;
                            o.textContent = p.recommended_message ||
                                (p.installments + 'x de R$ ' + Number(p.installment_amount).toFixed(2));
                            instSel.appendChild(o);
                        });
                    }
                } catch (e) { /* mantém 1x */ }
            }
        } catch (e) { /* BIN incompleto/desconhecido */ }
    }

    if (num) num.addEventListener('blur', resolveCard);

    var submitting = false;
    form.addEventListener('submit', function (e) {
        var t = currentType();
        if (submitting || !isCard(t)) return; // Pix/boleto enviam direto
        e.preventDefault();

        var btn = document.getElementById('mpSubmitBtn');
        if (btn) { btn.disabled = true; btn.textContent = 'Processando pagamento...'; }

        var parts = (form.querySelector('[name="mp_card_exp"]').value || '').replace(/\s/g, '').split('/');
        var year = parts[1] || '';
        if (year.length === 2) year = '20' + year;

        mp.createCardToken({
            cardNumber: num.value.replace(/\D/g, ''),
            cardholderName: form.querySelector('[name="mp_card_name"]').value,
            cardExpirationMonth: parts[0] || '',
            cardExpirationYear: year,
            securityCode: form.querySelector('[name="mp_card_cvv"]').value,
            identificationType: 'CPF',
            identificationNumber: (form.querySelector('[name="mp_card_doc"]').value || '').replace(/\D/g, '')
        }).then(function (token) {
            form.querySelector('[name="mp_token"]').value = token.id;
            if (!pmId.value && token.payment_method_id) pmId.value = token.payment_method_id;
            submitting = true;
            form.submit();
        }).catch(function (err) {
            if (btn) { btn.disabled = false; btn.textContent = cfg.submitLabel || 'Concluir e Pagar'; }
            var list = Array.isArray(err) ? err : (err && err.cause) ? err.cause : [];
            var msgs = list.map(function (x) { return friendlyCardError(x.code) || x.message || x.code; });
            alert('Não foi possível validar o cartão:\n\n' + (msgs.length ? msgs.join('\n') : 'Confira número, validade, CVV e CPF.'));
        });
    });

    function friendlyCardError(code) {
        var m = {
            '205': 'Digite o número do cartão.',
            '208': 'Selecione o mês de validade.',
            '209': 'Selecione o ano de validade.',
            '212': 'Informe o documento (CPF).',
            '214': 'Informe o documento (CPF).',
            '221': 'Informe o nome impresso no cartão.',
            '224': 'Informe o código de segurança (CVV).',
            'E301': 'Número de cartão inválido.',
            'E302': 'Código de segurança (CVV) inválido.',
            '316': 'Nome do titular inválido.',
            '325': 'Mês de validade inválido.',
            '326': 'Ano de validade inválido.'
        };
        return m[code];
    }
})();
