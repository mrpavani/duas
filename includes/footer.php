<?php
// ==========================================================================
// DUÁS - FOOTER TEMPLATE & MODALS
// ==========================================================================
?>

<footer class="footer">
    <div class="container">
        <div class="footer-grid">

            <!-- Brand Column -->
            <div class="footer-brand">
                <a href="index.php" class="footer-brand-name">Modevo.</a>
                <p>Onde a alfaiataria autoral encontra o cotidiano. Nosso compromisso é criar peças com qualidade, conforto e propósito &mdash; uma de cada vez.</p>
                <div class="footer-social">
                    <a href="https://www.instagram.com/duasporll/" target="_blank" rel="noopener" aria-label="Instagram Duás">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                        </svg>
                    </a>
                    <a href="https://www.tiktok.com/@duasporll?_r=1&_t=ZS-9AKfuC37ahZ" target="_blank" rel="noopener" aria-label="TikTok Duás">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12.75 2h2.9c.16 1.02.53 1.9 1.1 2.63a5.02 5.02 0 0 0 3.25 1.9v2.94a8.02 8.02 0 0 1-4.35-1.4v6.3a6.13 6.13 0 1 1-6.13-6.13c.35 0 .7.03 1.03.09v3.02a3.16 3.16 0 1 0 2.2 3.02V2z"></path>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Destaques da Loja -->
            <div>
                <h4 class="footer-title">Destaques da Loja</h4>
                <div class="footer-links">
                    <a href="pecas.php?categoria=Vestidos">Vestidos</a>
                    <a href="pecas.php?categoria=Blazers">Blazers</a>
                    <a href="pecas.php?categoria=Conjuntos">Conjuntos</a>
                    <a href="pecas.php?categoria=Blusas">Blusas</a>
                    <a href="pecas.php?categoria=Calças">Calças</a>
                    <a href="pecas.php">Novidades</a>
                </div>
            </div>

            <!-- Links Rápidos -->
            <div>
                <h4 class="footer-title">Links Rápidos</h4>
                <div class="footer-links">
                    <a href="index.php">Início</a>
                    <a href="pecas.php">Loja</a>
                    <a href="quem-somos.php">Quem Somos</a>
                    <a href="blog.php">Blog</a>
                    <a href="contato.php">Contato</a>
                </div>
            </div>

            <!-- Serviços ao Cliente -->
            <div>
                <h4 class="footer-title">Serviços ao Cliente</h4>
                <div class="footer-links">
                    <a href="contato.php">Acompanhar Pedido</a>
                    <a href="contato.php">Trocas e Devoluções</a>
                    <a href="contato.php">Perguntas Frequentes</a>
                    <a href="politica-privacidade.php">Política de Privacidade</a>
                    <a href="politica-privacidade.php#cookies">Cookies e LGPD</a>
                </div>
            </div>

            <!-- Informações de Contato -->
            <div>
                <h4 class="footer-title">Informações de Contato</h4>
                <div class="footer-contact">
                    <p>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"></path>
                        </svg>
                        <span>(11) 96610-1333</span>
                    </p>
                    <p>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        <span>contato@duasporll.com.br</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Bottom Copyright -->
        <div class="footer-bottom">
            <div>Copyright <?php echo date('Y'); ?> &mdash; DUÁS - Todos os direitos reservados.</div>
        </div>
    </div>
</footer>

<!-- Include Cart Drawer Slide-Over -->
<?php require_once __DIR__ . '/cart-drawer.php'; ?>

<!-- Guia de Medidas Modal Popup -->
<div class="modal-backdrop" id="measuresModalBackdrop">
    <div class="modal-box">
        <button class="modal-close" id="measuresModalClose">&times;</button>
        <h3 style="font-family: var(--font-heading); margin-bottom: 8px;">Guia de Medidas Duás</h3>
        <p style="font-size: 0.9rem; color: var(--color-text-muted); margin-bottom: 20px;">Nossas modelagens são desenvolvidas seguindo padrões internacionais de alfaiataria com folga de vestibilidade confortável.</p>

        <?php
        $activeGuideSizes = function_exists('get_active_sizes') ? get_active_sizes() : [];
        if (!$activeGuideSizes) {
            $activeGuideSizes = [
                ['code' => 'PP', 'name' => 'Extra Pequeno (34)', 'busto_hint' => '80-84', 'cintura_hint' => '62-66', 'quadril_hint' => '90-94', 'comprimento_hint' => '110'],
                ['code' => 'P', 'name' => 'Pequeno (36/38)', 'busto_hint' => '84-88', 'cintura_hint' => '66-70', 'quadril_hint' => '94-98', 'comprimento_hint' => '112'],
                ['code' => 'M', 'name' => 'Médio (40)', 'busto_hint' => '90-94', 'cintura_hint' => '72-76', 'quadril_hint' => '100-104', 'comprimento_hint' => '113'],
                ['code' => 'G', 'name' => 'Grande (42)', 'busto_hint' => '96-100', 'cintura_hint' => '78-82', 'quadril_hint' => '106-110', 'comprimento_hint' => '114'],
                ['code' => 'GG', 'name' => 'Extra Grande (44)', 'busto_hint' => '102-106', 'cintura_hint' => '84-88', 'quadril_hint' => '112-116', 'comprimento_hint' => '115'],
                ['code' => 'Extra G', 'name' => 'Extra G Especial (46)', 'busto_hint' => '108-112', 'cintura_hint' => '90-94', 'quadril_hint' => '118-122', 'comprimento_hint' => '116'],
            ];
        }
        ?>
        <table class="table-measures">
            <thead>
                <tr>
                    <th>Tamanho</th>
                    <th>Busto (cm)</th>
                    <th>Cintura (cm)</th>
                    <th>Quadril (cm)</th>
                    <th>Comp. (cm)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activeGuideSizes as $gs): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($gs['code']); ?></strong> <span style="font-size: 0.8rem; color: var(--color-text-muted);">(<?php echo htmlspecialchars($gs['name']); ?>)</span></td>
                        <td><?php echo htmlspecialchars($gs['busto_hint'] ?: '&mdash;'); ?></td>
                        <td><?php echo htmlspecialchars($gs['cintura_hint'] ?: '&mdash;'); ?></td>
                        <td><?php echo htmlspecialchars($gs['quadril_hint'] ?: '&mdash;'); ?></td>
                        <td><?php echo htmlspecialchars($gs['comprimento_hint'] ?: '&mdash;'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 24px; font-size: 0.85rem; color: var(--color-text-muted); background: var(--color-bg-neutral); padding: 16px;">
            <strong>Dica de Vestibilidade:</strong> Se você prefere um caimento mais solto (oversized) como os de nossos desfiles, recomendamos escolher o tamanho acima do habitual.
        </div>
    </div>
</div>

<!-- Aviso de cookies (LGPD) -->
<div class="cookie-bar" id="cookieBar" hidden>
    <p>
        Usamos apenas cookies necessários para manter seu carrinho enquanto você navega.
        Não fazemos rastreamento publicitário. Saiba mais na
        <a href="politica-privacidade.php">Política de Privacidade</a>.
    </p>
    <button type="button" class="btn btn-primary btn-sm" id="cookieAccept">Entendi</button>
</div>

<script src="js/app.js"></script>
</body>
</html>
