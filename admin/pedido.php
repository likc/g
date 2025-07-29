<?php
// ============================================
// ARQUIVO: pedido.php - Visualização de Pedido Individual
// ============================================
?>
<?php
require_once 'config.php';

$orderId = intval($_GET['id'] ?? 0);

if ($orderId <= 0) {
    flashMessage('Pedido não encontrado.', 'error');
    redirect('index.php');
}

// Buscar pedido
$stmt = $db->prepare("
    SELECT o.*, u.nome, u.sobrenome, u.email, u.telefone,
           c.codigo as cupom_codigo, c.tipo as cupom_tipo, c.valor as cupom_valor
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN coupons c ON o.coupon_id = c.id
    WHERE o.id = ?
");
$stmt->execute([$orderId]);
$pedido = $stmt->fetch();

if (!$pedido) {
    flashMessage('Pedido não encontrado.', 'error');
    redirect('index.php');
}

// Verificar se o usuário pode ver este pedido
if (!isModerator() && $pedido['user_id'] != $_SESSION['user_id']) {
    flashMessage('Acesso negado.', 'error');
    redirect('index.php');
}

// Buscar itens do pedido
$stmt = $db->prepare("
    SELECT oi.*, p.nome, p.imagem_principal 
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");
$stmt->execute([$orderId]);
$itens = $stmt->fetchAll();

// Decodificar endereço de entrega
$enderecoEntrega = null;
if ($pedido['endereco_entrega']) {
    $enderecoEntrega = json_decode($pedido['endereco_entrega'], true);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido #<?php echo htmlspecialchars($pedido['order_number']); ?> - Gourmeria</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background-color: #000;
            color: #fff;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .header-simple {
            background: #000;
            border-bottom: 2px solid #DAA520;
            padding: 20px 0;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
        }
        
        .header-simple .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #DAA520;
            text-decoration: none;
        }
        
        .header-nav a {
            color: #fff;
            text-decoration: none;
            margin: 0 15px;
            transition: color 0.3s;
        }
        
        .header-nav a:hover {
            color: #DAA520;
        }
        
        main {
            margin-top: 80px;
            padding: 40px 0;
        }
        
        .order-header {
            background: #111;
            border: 2px solid #DAA520;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .order-title {
            color: #DAA520;
            font-size: 32px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .order-subtitle {
            color: #ccc;
            margin-bottom: 20px;
        }
        
        .order-status-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .order-status {
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #ffc107;
            color: #000;
        }
        
        .status-processing {
            background: #17a2b8;
            color: #fff;
        }
        
        .status-shipped {
            background: #6f42c1;
            color: #fff;
        }
        
        .status-delivered {
            background: #28a745;
            color: #fff;
        }
        
        .status-cancelled {
            background: #dc3545;
            color: #fff;
        }
        
        .order-details {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .panel {
            background: #111;
            border: 2px solid #333;
            border-radius: 10px;
            padding: 25px;
        }
        
        .panel-title {
            color: #DAA520;
            font-size: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #333;
            padding-bottom: 15px;
        }
        
        .item-row {
            display: grid;
            grid-template-columns: 80px 1fr auto auto;
            gap: 20px;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #333;
        }
        
        .item-row:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 80px;
            height: 80px;
            background: #222;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .item-info h4 {
            color: #DAA520;
            margin-bottom: 5px;
        }
        
        .item-info p {
            color: #ccc;
            font-size: 14px;
        }
        
        .item-quantity {
            text-align: center;
            color: #DAA520;
            font-weight: bold;
        }
        
        .item-total {
            text-align: right;
            font-weight: bold;
            color: #DAA520;
        }
        
        .order-summary {
            border-top: 2px solid #333;
            padding-top: 20px;
            margin-top: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 5px 0;
        }
        
        .summary-row.total {
            border-top: 2px solid #DAA520;
            padding-top: 15px;
            margin-top: 15px;
            font-size: 18px;
            font-weight: bold;
            color: #DAA520;
        }
        
        .info-grid {
            display: grid;
            gap: 15px;
        }
        
        .info-item {
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }
        
        .info-label {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #DAA520;
            font-weight: bold;
        }
        
        .address-info {
            background: #222;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: #DAA520;
            color: #000;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn:hover {
            background: #B8860B;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid #DAA520;
            color: #DAA520;
        }
        
        .btn-outline:hover {
            background: #DAA520;
            color: #000;
        }
        
        .tracking-info {
            background: #333;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            text-align: center;
        }
        
        .tracking-code {
            font-family: 'Courier New', monospace;
            font-size: 18px;
            color: #DAA520;
            font-weight: bold;
            margin: 10px 0;
        }
        
        @media (max-width: 768px) {
            .order-details {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .item-row {
                grid-template-columns: 60px 1fr;
                gap: 15px;
            }
            
            .item-quantity,
            .item-total {
                grid-column: 1 / -1;
                text-align: left;
                margin-top: 10px;
            }
            
            .order-status-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <header class="header-simple">
        <div class="container">
            <a href="index.php" class="logo">
                <i class="fas fa-gem"></i> Gourmeria
            </a>
            
            <nav class="header-nav">
                <a href="index.php">Início</a>
                <?php if (isModerator()): ?>
                    <a href="admin/">Admin</a>
                <?php else: ?>
                    <a href="minha-conta.php">Minha Conta</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <!-- Cabeçalho do Pedido -->
            <div class="order-header">
                <div class="order-status-header">
                    <div>
                        <h1 class="order-title">
                            <i class="fas fa-receipt"></i> Pedido #<?php echo htmlspecialchars($pedido['order_number']); ?>
                        </h1>
                        <p class="order-subtitle">
                            Realizado em <?php echo date('d/m/Y \à\s H:i', strtotime($pedido['created_at'])); ?>
                        </p>
                    </div>
                    
                    <div class="order-status status-<?php echo $pedido['status']; ?>">
                        <?php
                        $statusLabels = [
                            'pending' => 'Pendente',
                            'processing' => 'Processando',
                            'shipped' => 'Enviado',
                            'delivered' => 'Entregue',
                            'cancelled' => 'Cancelado'
                        ];
                        echo $statusLabels[$pedido['status']] ?? $pedido['status'];
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="order-details">
                <!-- Itens do Pedido -->
                <div class="panel">
                    <h2 class="panel-title">
                        <i class="fas fa-shopping-bag"></i> Itens do Pedido
                    </h2>
                    
                    <?php foreach ($itens as $item): ?>
                        <div class="item-row">
                            <div class="item-image">
                                <?php if ($item['imagem_principal']): ?>
                                    <img src="<?php echo UPLOAD_DIR . $item['imagem_principal']; ?>" alt="<?php echo htmlspecialchars($item['nome']); ?>">
                                <?php else: ?>
                                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #666;">
                                        <i class="fas fa-image" style="font-size: 24px;"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="item-info">
                                <h4><?php echo htmlspecialchars($item['nome']); ?></h4>
                                <p>Preço unitário: <?php echo formatPrice($item['preco_unitario']); ?></p>
                            </div>
                            
                            <div class="item-quantity">
                                Qtd: <?php echo $item['quantidade']; ?>
                            </div>
                            
                            <div class="item-total">
                                <?php echo formatPrice($item['subtotal']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Resumo do Pedido -->
                    <div class="order-summary">
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span><?php echo formatPrice($pedido['subtotal']); ?></span>
                        </div>
                        
                        <?php if ($pedido['desconto'] > 0): ?>
                            <div class="summary-row" style="color: #28a745;">
                                <span>
                                    Desconto
                                    <?php if ($pedido['cupom_codigo']): ?>
                                        (<?php echo htmlspecialchars($pedido['cupom_codigo']); ?>)
                                    <?php endif; ?>:
                                </span>
                                <span>-<?php echo formatPrice($pedido['desconto']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="summary-row">
                            <span>Frete:</span>
                            <span>
                                <?php if ($pedido['frete'] > 0): ?>
                                    <?php echo formatPrice($pedido['frete']); ?>
                                <?php else: ?>
                                    <span style="color: #28a745;">Grátis</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span><?php echo formatPrice($pedido['total']); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Informações do Pedido -->
                <div>
                    <!-- Dados do Cliente -->
                    <div class="panel" style="margin-bottom: 20px;">
                        <h3 class="panel-title">
                            <i class="fas fa-user"></i> Cliente
                        </h3>
                        
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Nome</div>
                                <div class="info-value"><?php echo htmlspecialchars($pedido['nome'] . ' ' . $pedido['sobrenome']); ?></div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">E-mail</div>
                                <div class="info-value"><?php echo htmlspecialchars($pedido['email']); ?></div>
                            </div>
                            
                            <?php if ($pedido['telefone']): ?>
                                <div class="info-item">
                                    <div class="info-label">Telefone</div>
                                    <div class="info-value"><?php echo htmlspecialchars($pedido['telefone']); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Entrega -->
                    <div class="panel" style="margin-bottom: 20px;">
                        <h3 class="panel-title">
                            <i class="fas fa-<?php echo $pedido['metodo_entrega'] == 'yamato' ? 'truck' : 'store'; ?>"></i> 
                            <?php echo $pedido['metodo_entrega'] == 'yamato' ? 'Entrega' : 'Retirada'; ?>
                        </h3>
                        
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Método</div>
                                <div class="info-value">
                                    <?php echo $pedido['metodo_entrega'] == 'yamato' ? 'Entrega Yamato' : 'Retirada no Local'; ?>
                                </div>
                            </div>
                            
                            <?php if ($pedido['metodo_entrega'] == 'yamato' && $enderecoEntrega): ?>
                                <div class="info-item">
                                    <div class="info-label">Endereço de Entrega</div>
                                    <div class="address-info">
                                        <strong><?php echo htmlspecialchars($enderecoEntrega['nome']); ?></strong><br>
                                        <?php echo htmlspecialchars($enderecoEntrega['endereco']); ?><br>
                                        <?php if ($enderecoEntrega['complemento']): ?>
                                            <?php echo htmlspecialchars($enderecoEntrega['complemento']); ?><br>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($enderecoEntrega['cidade']); ?>, <?php echo htmlspecialchars($enderecoEntrega['estado']); ?><br>
                                        <?php if ($enderecoEntrega['cep']): ?>
                                            CEP: <?php echo htmlspecialchars($enderecoEntrega['cep']); ?><br>
                                        <?php endif; ?>
                                        <?php if ($enderecoEntrega['telefone']): ?>
                                            Tel: <?php echo htmlspecialchars($enderecoEntrega['telefone']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($pedido['data_envio']): ?>
                                <div class="info-item">
                                    <div class="info-label">Data de Envio</div>
                                    <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($pedido['data_envio'])); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($pedido['codigo_rastreamento']): ?>
                                <div class="info-item">
                                    <div class="info-label">Rastreamento</div>
                                    <div class="tracking-info">
                                        <div>Código de Rastreamento Yamato:</div>
                                        <div class="tracking-code"><?php echo htmlspecialchars($pedido['codigo_rastreamento']); ?></div>
                                        <a href="https://toi.kuronekoyamato.co.jp/cgi-bin/tneko" target="_blank" class="btn btn-outline">
                                            <i class="fas fa-external-link-alt"></i> Rastrear Pacote
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Observações -->
                    <?php if ($pedido['observacoes']): ?>
                        <div class="panel">
                            <h3 class="panel-title">
                                <i class="fas fa-comment"></i> Observações
                            </h3>
                            <div style="color: #ccc; line-height: 1.5;">
                                <?php echo nl2br(htmlspecialchars($pedido['observacoes'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Ações -->
            <div style="text-align: center; margin-top: 40px;">
                <?php if (!isModerator()): ?>
                    <a href="minha-conta.php?tab=pedidos" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Voltar aos Meus Pedidos
                    </a>
                <?php else: ?>
                    <a href="admin/pedidos.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Voltar aos Pedidos
                    </a>
                <?php endif; ?>
                
                <a href="produtos.php" class="btn" style="margin-left: 15px;">
                    <i class="fas fa-shopping-cart"></i> Continuar Comprando
                </a>
            </div>
        </div>
    </main>
</body>
</html>