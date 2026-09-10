<?php
$page_title = "Política de Privacidade";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/settings.php';

$contatoEmail = 'contato@duasmoda.com.br';
$atualizadoEm = '10 de Setembro de 2026';
?>

<div class="section" style="padding-top: 40px;">
    <div class="container container-narrow">

        <div style="text-align: center; margin-bottom: 44px;">
            <span class="subtitle">Privacidade e proteção de dados</span>
            <h1 style="margin-bottom: 14px;">Política de Privacidade</h1>
            <p class="lead">Como a Duás trata seus dados pessoais, em conformidade com a Lei nº 13.709/2018 (LGPD).</p>
            <p style="font-size: 0.8rem; color: var(--color-text-light); margin-top: 10px;">Última atualização: <?php echo $atualizadoEm; ?></p>
        </div>

        <div class="legal-doc">

            <div class="legal-highlight">
                <strong>O essencial em três linhas</strong>
                <ul>
                    <li>Coletamos apenas o necessário para entregar seu pedido: nome, e-mail, telefone, CPF e endereço.</li>
                    <li><strong>Dados de cartão nunca passam pela Duás</strong> — são digitados em campo criptografado e enviados direto ao Mercado Pago.</li>
                    <li>Não há cadastro nem senha: os dados são pedidos a cada compra e o carrinho é apagado ao fechar o navegador.</li>
                </ul>
            </div>

            <h2>1. Quem é o controlador dos dados</h2>
            <p>
                A <strong>Duás</strong> é a controladora dos dados pessoais coletados neste site, nos termos do
                art. 5º, VI da LGPD. Para qualquer assunto relacionado a privacidade, fale com o nosso encarregado
                pelo e-mail <a href="mailto:<?php echo $contatoEmail; ?>"><?php echo $contatoEmail; ?></a>.
            </p>

            <h2>2. Quais dados coletamos e para quê</h2>
            <table class="legal-table">
                <thead>
                    <tr><th>Dado</th><th>Para que usamos</th><th>Base legal (art. 7º)</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Nome completo e CPF</td>
                        <td>Identificar o comprador, emitir a nota fiscal e processar o pagamento</td>
                        <td>Execução de contrato e obrigação legal</td>
                    </tr>
                    <tr>
                        <td>E-mail</td>
                        <td>Enviar a confirmação do pedido e todas as atualizações (pagamento, separação, envio, rastreio)</td>
                        <td>Execução de contrato</td>
                    </tr>
                    <tr>
                        <td>Telefone</td>
                        <td>Contato sobre o pedido e uso pela transportadora na entrega</td>
                        <td>Execução de contrato</td>
                    </tr>
                    <tr>
                        <td>Endereço completo</td>
                        <td>Calcular o frete e entregar o pedido</td>
                        <td>Execução de contrato</td>
                    </tr>
                    <tr>
                        <td>Registro do aceite desta política (data, hora e IP)</td>
                        <td>Comprovar o consentimento</td>
                        <td>Cumprimento de obrigação legal</td>
                    </tr>
                    <tr>
                        <td>E-mail da newsletter <em>(opcional)</em></td>
                        <td>Enviar novidades e lançamentos</td>
                        <td>Consentimento — revogável a qualquer momento</td>
                    </tr>
                </tbody>
            </table>

            <h2>3. Dados de pagamento &mdash; responsabilidade do Mercado Pago</h2>
            <div class="legal-highlight legal-highlight-strong">
                <p>
                    Os pagamentos são processados pelo <strong>Mercado Pago</strong>, instituição de pagamento
                    certificada no padrão <strong>PCI DSS</strong>. Ao pagar com cartão, os dados são digitados em
                    um campo criptografado do próprio Mercado Pago e convertidos em um <em>token</em> antes de sair
                    do seu navegador.
                </p>
                <p>
                    <strong>A Duás não recebe, não processa e não armazena, em nenhum momento, o número do cartão,
                    a data de validade ou o código de segurança (CVV).</strong> Esses dados trafegam exclusivamente
                    entre você e o Mercado Pago, que é o responsável pelo seu tratamento e guarda.
                </p>
                <p>
                    Nos nossos servidores fica apenas o resultado da transação: identificador do pagamento, status
                    (aprovado, pendente ou recusado), valor, forma de pagamento e bandeira. Nada que permita
                    reutilizar o seu cartão.
                </p>
                <p style="margin-bottom:0;">
                    O tratamento feito pelo Mercado Pago é regido pela política de privacidade dele, disponível em
                    <a href="https://www.mercadopago.com.br/privacidade" target="_blank" rel="noopener">mercadopago.com.br/privacidade</a>.
                </p>
            </div>

            <h2>4. Com quem compartilhamos</h2>
            <p>Seus dados são compartilhados apenas com quem é indispensável para concluir a compra:</p>
            <ul>
                <li><strong>Mercado Pago</strong> &mdash; processamento do pagamento e prevenção a fraude.</li>
                <li><strong>Transportadora</strong> (Correios ou similar) &mdash; nome, telefone e endereço, para a entrega.</li>
                <li><strong>Autoridades públicas</strong> &mdash; somente quando houver obrigação legal ou ordem judicial.</li>
            </ul>
            <p><strong>Não vendemos, alugamos nem cedemos seus dados pessoais para fins publicitários de terceiros.</strong></p>

            <h2>5. Por quanto tempo guardamos</h2>
            <ul>
                <li><strong>Dados do pedido</strong>: mantidos pelo prazo exigido pela legislação fiscal e pelo
                    Código de Defesa do Consumidor, para eventual troca, garantia ou fiscalização.</li>
                <li><strong>Carrinho de compras</strong>: fica apenas na sessão do navegador e é apagado quando você
                    fecha o navegador ou após 2 horas de inatividade. Não criamos cadastro nem histórico de navegação vinculado a você.</li>
                <li><strong>Newsletter</strong>: até você pedir o descadastramento.</li>
            </ul>

            <h2>6. Seus direitos</h2>
            <p>O art. 18 da LGPD garante a você, a qualquer momento e sem custo, o direito de:</p>
            <ul>
                <li>confirmar se tratamos seus dados e acessar uma cópia deles;</li>
                <li>corrigir dados incompletos, inexatos ou desatualizados;</li>
                <li>solicitar anonimização, bloqueio ou eliminação de dados desnecessários ou tratados em desconformidade;</li>
                <li>pedir a portabilidade dos dados a outro fornecedor;</li>
                <li>revogar o consentimento e solicitar a eliminação dos dados tratados com essa base;</li>
                <li>ser informado sobre com quem compartilhamos seus dados;</li>
                <li>opor-se a um tratamento feito sem o seu consentimento.</li>
            </ul>
            <p>
                Para exercer qualquer um deles, escreva para
                <a href="mailto:<?php echo $contatoEmail; ?>"><?php echo $contatoEmail; ?></a>.
                Respondemos em até 15 dias. Podemos pedir uma confirmação de identidade antes de atender ao pedido,
                justamente para proteger os seus dados.
            </p>
            <p>
                Alguns dados não podem ser apagados de imediato quando ainda existe obrigação legal de guarda
                (por exemplo, notas fiscais). Nesse caso explicamos o motivo e o prazo.
            </p>

            <h2>7. Cookies</h2>
            <p>Usamos o mínimo possível:</p>
            <ul>
                <li><strong>Cookie de sessão (necessário)</strong>: mantém seu carrinho enquanto você navega.
                    É apagado ao fechar o navegador e não identifica você pessoalmente.</li>
                <li><strong>Registro do aviso de cookies</strong>: guarda no seu navegador que você já viu o aviso,
                    só para não repeti-lo.</li>
            </ul>
            <p>Não utilizamos cookies de publicidade nem rastreamento de comportamento entre sites.</p>

            <h2>8. Segurança</h2>
            <p>
                O site trafega em HTTPS, o acesso ao painel administrativo é protegido por senha com hash e
                proteção contra CSRF, e os dados de pagamento são tokenizados pelo Mercado Pago antes de sair do
                seu navegador. Ainda assim, nenhum sistema é infalível: se ocorrer um incidente de segurança com
                risco relevante a você, comunicaremos você e a ANPD, conforme o art. 48 da LGPD.
            </p>

            <h2>9. Menores de idade</h2>
            <p>
                A loja é destinada a maiores de 18 anos. Não coletamos intencionalmente dados de crianças e
                adolescentes. Se identificarmos um cadastro nessa situação, os dados serão eliminados.
            </p>

            <h2>10. Alterações desta política</h2>
            <p>
                Podemos atualizar este documento para refletir mudanças legais ou do serviço. A data de
                atualização no topo sempre indica a versão vigente. Mudanças relevantes serão comunicadas no site.
            </p>

            <div class="legal-footer">
                <p>
                    Dúvidas sobre privacidade? Fale com o encarregado pelo tratamento de dados:
                    <a href="mailto:<?php echo $contatoEmail; ?>"><strong><?php echo $contatoEmail; ?></strong></a>
                </p>
                <a href="contato.php" class="btn btn-outline">Falar com o atendimento</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
