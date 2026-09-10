<?php
$page_title = "Atendimento & Contato";
require_once __DIR__ . '/includes/header.php';

$sentSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sentSuccess = true;
}
?>

<div class="section" style="padding-top: 40px;">
    <div class="container">
        
        <div class="section-header">
            <span class="subtitle">Atendimento Exclusivo</span>
            <h1>Como podemos te ajudar?</h1>
            <p class="lead">Nossa equipe de consultoria de estilo e atendimento está à disposição para sanar todas as suas dúvidas.</p>
        </div>

        <div class="contact-layout">
            <!-- Contact Info Column -->
            <div style="display: flex; flex-direction: column; gap: 32px;">
                <div style="background: var(--color-bg-neutral); padding: 32px;">
                    <h3 style="font-size: 1.2rem; margin-bottom: 12px;">Canais Diretos</h3>
                    <div style="display: flex; flex-direction: column; gap: 16px; font-size: 0.95rem; color: var(--color-text-muted);">
                        <div>
                            <strong>WhatsApp Consultoria:</strong><br>
                            <a href="https://wa.me/5511999999999" target="_blank" style="color: var(--color-primary); font-weight: 500;">+55 (11) 99999-9999</a>
                        </div>
                        <div>
                            <strong>E-mail Atendimento:</strong><br>
                            <a href="mailto:contato@duasmoda.com.br" style="color: var(--color-primary); font-weight: 500;">contato@duasmoda.com.br</a>
                        </div>
                        <div>
                            <strong>Horário de Atendimento:</strong><br>
                            Segunda a Sexta, das 09h às 18h.
                        </div>
                    </div>
                </div>

                <div style="background: var(--color-bg-neutral); padding: 32px;">
                    <h3 style="font-size: 1.2rem; margin-bottom: 12px;">Showroom & Atelier</h3>
                    <p style="font-size: 0.95rem; color: var(--color-text-muted); line-height: 1.7;">
                        Atendimento presencial exclusivo sob agendamento prévio.<br>
                        Alameda Lorena, 1400 - Jardins, São Paulo / SP.
                    </p>
                </div>
            </div>

            <!-- Contact Form Column -->
            <div>
                <?php if ($sentSuccess): ?>
                    <div style="background: #E8F5E9; border: 1px solid #A5D6A7; color: #1B5E20; padding: 24px; font-size: 0.95rem; line-height: 1.6;">
                        <h4 style="font-family: var(--font-heading); font-size: 1.3rem; margin-bottom: 8px; color: #1B5E20;">Mensagem Enviada com Sucesso!</h4>
                        <p>Obrigada por entrar em contato com a Duás. Nossa equipe retornará seu e-mail em até 24 horas úteis.</p>
                        <a href="contato.php" class="btn btn-outline btn-sm" style="margin-top: 16px; border-color: #1B5E20; color: #1B5E20;">Enviar Nova Mensagem</a>
                    </div>
                <?php else: ?>
                    <form action="contato.php" method="POST" style="display: flex; flex-direction: column; gap: 20px;">
                        <div>
                            <label style="display: block; font-size: 0.85rem; font-weight: 500; text-transform: uppercase; margin-bottom: 8px;">Nome Completo</label>
                            <input type="text" name="nome" class="form-input" placeholder="Seu nome" required>
                        </div>

                        <div>
                            <label style="display: block; font-size: 0.85rem; font-weight: 500; text-transform: uppercase; margin-bottom: 8px;">E-mail para Resposta</label>
                            <input type="email" name="email" class="form-input" placeholder="seuemail@exemplo.com" required>
                        </div>

                        <div>
                            <label style="display: block; font-size: 0.85rem; font-weight: 500; text-transform: uppercase; margin-bottom: 8px;">Assunto</label>
                            <select name="assunto" class="form-input" required>
                                <option value="Dúvida sobre Produto / Tamanho">Dúvida sobre Produto / Tamanho</option>
                                <option value="Status de Pedido / Rastreio">Status de Pedido / Rastreio</option>
                                <option value="Trocas e Devoluções">Trocas e Devoluções</option>
                                <option value="Parcerias e Imprensa">Parcerias e Imprensa</option>
                            </select>
                        </div>

                        <div>
                            <label style="display: block; font-size: 0.85rem; font-weight: 500; text-transform: uppercase; margin-bottom: 8px;">Mensagem</label>
                            <textarea name="mensagem" class="form-input" rows="5" placeholder="Como podemos te ajudar hoje?" required></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-full">Enviar Mensagem</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- FAQ Section Accordion -->
        <div style="margin-top: 90px; border-top: 1px solid var(--color-border); padding-top: 60px;">
            <div class="section-header">
                <span class="subtitle">Dúvidas Frequentes</span>
                <h2>Perguntas Frequentes (FAQ)</h2>
            </div>

            <div class="container-narrow" style="margin: 0 auto;">
                <div class="accordion-item active">
                    <button class="accordion-header">
                        <span>Qual é o prazo para trocas ou devoluções?</span>
                        <span>+</span>
                    </button>
                    <div class="accordion-content">
                        <p>Você tem até 30 dias corridos após o recebimento para solicitar a troca e até 7 dias para devolução com reembolso integral. A primeira troca é por nossa conta!</p>
                    </div>
                </div>

                <div class="accordion-item">
                    <button class="accordion-header">
                        <span>Como funciona o envio das peças?</span>
                        <span>+</span>
                    </button>
                    <div class="accordion-content">
                        <p>Enviamos para todo o Brasil via Sedex ou Transportadora expressa. Todas as compras acima de R$ 800 possuem frete grátis automático.</p>
                    </div>
                </div>

                <div class="accordion-item">
                    <button class="accordion-header">
                        <span>As peças encolhem ao lavar?</span>
                        <span>+</span>
                    </button>
                    <div class="accordion-content">
                        <p>Nossos tecidos passam por processo de pré-encolhimento. Recomendamos seguir rigorosamente as instruções de lavagem contidas na etiqueta interna e na PDP da peça.</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
