<?php
$page_title = "Minha Conta & Pedidos";
require_once __DIR__ . '/includes/header.php';
$orders = $_SESSION['orders'] ?? [];
?>

<div class="section" style="padding-top: 40px;">
    <div class="container">
        
        <!-- Header User Welcome -->
        <div style="display: flex; justify-content: space-between; align-items: flex-end; padding-bottom: 24px; border-bottom: 1px solid var(--color-border); margin-bottom: 40px; flex-wrap: wrap; gap: 16px;">
            <div>
                <span class="subtitle">Bem-vinda de volta</span>
                <h1 style="font-size: 2.2rem;">Olá, Mariana Silva</h1>
                <p style="color: var(--color-text-muted); font-size: 0.9rem;">mariana.silva@exemplo.com • Cliente Duás desde 2025</p>
            </div>
            <a href="index.php" class="btn btn-secondary btn-sm">Sair da Conta</a>
        </div>

        <div class="account-layout">
            <!-- Side Navigation Menu -->
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <a href="#pedidos" class="btn btn-primary btn-full" style="justify-content: flex-start;">Meus Pedidos</a>
                <a href="#enderecos" class="btn btn-secondary btn-full" style="justify-content: flex-start; background: transparent;">Meus Endereços</a>
                <a href="#dados" class="btn btn-secondary btn-full" style="justify-content: flex-start; background: transparent;">Dados Pessoais</a>
                <a href="#favoritos" class="btn btn-secondary btn-full" style="justify-content: flex-start; background: transparent;">Lista de Desejos</a>
            </div>

            <!-- Dashboard Main Content -->
            <div>
                <!-- Orders History Section -->
                <h2 style="font-size: 1.5rem; margin-bottom: 20px;" id="pedidos">Histórico de Pedidos</h2>

                <?php if (empty($orders)): ?>
                    <p style="color: var(--color-text-muted);">Você ainda não realizou nenhum pedido na Duás.</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 24px;">
                        <?php foreach ($orders as $order): ?>
                            <div style="border: 1px solid var(--color-border); background: var(--color-bg-main);">
                                <div style="background: var(--color-bg-neutral); padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--color-border); flex-wrap: wrap; gap: 12px;">
                                    <div>
                                        <strong>Pedido #<?php echo htmlspecialchars($order['id']); ?></strong>
                                        <div style="font-size: 0.8rem; color: var(--color-text-muted); margin-top: 2px;">Realizado em <?php echo htmlspecialchars($order['date']); ?></div>
                                    </div>
                                    <div style="text-align: right;">
                                        <span style="background: #E8F5E9; color: #2E7D32; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; padding: 4px 10px; border-radius: 2px;">
                                            <?php echo htmlspecialchars($order['status']); ?>
                                        </span>
                                        <div style="font-size: 0.95rem; font-weight: 600; margin-top: 4px;">Total: R$ <?php echo number_format($order['total'], 2, ',', '.'); ?></div>
                                    </div>
                                </div>
                                <div style="padding: 20px;">
                                    <?php foreach ($order['items'] as $item): ?>
                                        <div style="display: flex; justify-content: space-between; font-size: 0.9rem; padding: 8px 0; border-bottom: 1px dashed var(--color-border);">
                                            <span><strong><?php echo $item['qty']; ?>x</strong> <?php echo htmlspecialchars($item['name']); ?> (Tam: <?php echo htmlspecialchars($item['size']); ?>)</span>
                                            <span>R$ <?php echo number_format($item['price'], 2, ',', '.'); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Addresses Section -->
                <div style="margin-top: 50px; border-top: 1px solid var(--color-border); padding-top: 40px;" id="enderecos">
                    <h2 style="font-size: 1.5rem; margin-bottom: 20px;">Endereço de Entrega Principal</h2>
                    <div style="border: 1px solid var(--color-border); padding: 24px; max-width: 440px;">
                        <strong>Mariana Silva (Principal)</strong>
                        <p style="font-size: 0.9rem; color: var(--color-text-muted); margin: 8px 0;">
                            Rua Oscar Freire, 980 - Apto 42<br>
                            Pinheiros, São Paulo / SP<br>
                            CEP: 01426-000
                        </p>
                        <button class="btn btn-outline btn-sm" style="margin-top: 12px;">Editar Endereço</button>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
