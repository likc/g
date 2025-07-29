<?php
require_once 'config.php';

// Verificar se está logado
if (!isLoggedIn()) {
    redirect('login.php');
}

$orderId = $_GET['id'] ?? 0;

if (!$orderId) {
    flashMessage('Pedido não encontrado.', 'error');
    redirect('index.php');
}

// Buscar dados do pedido
$stmt = $db->prepare("
    SELECT o.*, u.email 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$orderId, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    flashMessage('Pedido não encontrado ou sem permissão.', 'error');
    redirect('index.php');
}

// Buscar itens do pedido
$stmt = $db->prepare("
    SELECT oi.*, p.nome, p.imagem_principal 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->execute([$orderId]);
$orderItems = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido Confirmado - Gourmeria</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gold: #DAA520;
            --dark-gold: #B8860B;
            --background-dark: #0A0A0A;
            --surface-dark: #1A1A1A;
            --surface-light: #2A2A2A;
            --text-primary: #FFFFFF;
            --text-secondary: #CCCCCC;
            --text-muted: #999999;
            --border-color: #333333;
            --success-color: #28a745;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--background-dark);
            color: var(--text-primary);
            line-height: 1.6;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .success-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 60px 0;
            background: var(--surface-dark);
            border-radius: 20px;
            border: 1px solid var(--border-color);
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: var(--success-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: bounce 1s ease;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-20px); }
            60% { transform: translateY(-10px); }
        }
        
        .success-icon i {
            font-size: 48px;
            color: white;
        }
        
        .success-header h1 {
            font-size: 36px;
            color: var(--success-color);
            margin-bottom: 15px;
        }
        
        .success-header p {
            font-size: 18px;
            color: var(--text-secondary);
        }
        
        .order-number {
            background: linear-gradient(135deg, var(--primary-gold), var(--dark-gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            font-size: 24px;
        }
        
        .order-details {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 20px;
            color: var(--primary-gold);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-item {
            background: var(--background-dark);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }
        
        .info-label {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-size: 16px;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .order-items {
            margin-top: 30px;
        }
        
        .order-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 60px;
            height: 60px;
            background: var(--surface-light);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-name {
            font-weight: 600;
            color: var(--primary-gold);
            margin-bottom: 5px;
        }
        
        .item-details {
            font-size: 14px;
            color: var(--text-muted);
        }
        
        .item-price {
            text-align: right;
        }
        
        .item-unit-price {
            font-size: 14px;
            color: var(--text-muted);
        }
        
        .item-total-price {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .order-summary {
            background: var(--background-dark);
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }
        
        .summary-row.total {
            border-top: 2px solid var(--border-color);
            margin-top: 10px;
            padding-top: 15px;
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-gold);
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pendente {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            border: 1px solid #ffc107;
        }
        
        .payment-info {
            background: rgba(218, 165, 32, 0.1);
            border: 1px solid var(--primary-gold);
            border-radius: 12px;
            padding: 20px;
            margin: 30px 0;
        }
        
        .payment-info h3 {
            color: var(--primary-gold);
            margin-bottom: 15px;
        }
        
        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 40px;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-gold), var(--dark-gold));
            color: var(--background-dark);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(218, 165, 32, 0.3);
        }
        
        .btn-secondary {
            background: transparent;
            border: 2px solid var(--primary-gold);
            color: var(--primary-gold);
        }
        
        .btn-secondary:hover {
            background: var(--primary-gold);
            color: var(--background-dark);
        }
        
        .timeline {
            margin-top: 30px;
        }
        
        .timeline-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .timeline-icon {
            width: 40px;
            height: 40px;
            background: var(--success-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }
        
        .timeline-icon.pending {
            background: var(--text-muted);
        }
        
        .timeline-content h4 {
            color: var(--text-primary);
            margin-bottom: 5px;
        }
        
        .timeline-content p {
            color: var(--text-muted);
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            
            .success-header {
                padding: 40px 20px;
            }
            
            .success-header h1 {
                font-size: 28px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .btn {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-header">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h1>Pedido Confirmado!</h1>
            <p>Seu pedido foi recebido com sucesso</p>
            <div class="order-number">Pedido #<?php echo $order['id']; ?></div>
        </div>
        
        <div class="order-details">
            <div class="section-title">
                <i class="fas fa-info-circle"></i> Informações do Pedido
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Data do Pedido</div>
                    <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="info-value">
                        <span class="status-badge status-<?php echo $order['status']; ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Forma de Pagamento</div>
                    <div class="info-value"><?php echo ucfirst($order['forma_pagamento']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Tipo de Entrega</div>
                    <div class="info-value"><?php echo ucfirst($order['tipo_entrega']); ?></div>
                </div>
            </div>
            
            <?php if ($order['forma_pagamento'] == 'pix'): ?>
            <div class="payment-info">
                <h3><i class="fas fa-qrcode"></i> Instruções de Pagamento PIX</h3>
                <p><strong>Chave PIX:</strong> contato@gourmeria.jp</p>
                <p><strong>Valor:</strong> <?php echo formatPrice($order['total']); ?></p>
                <p><strong>Beneficiário:</strong> Gourmeria Doces Gourmet</p>
                <p><small>Após o pagamento, envie o comprovante via WhatsApp ou e-mail para confirmação.</small></p>
            </div>
            <?php elseif ($order['forma_pagamento'] == 'transferencia'): ?>
            <div class="payment-info">
                <h3><i class="fas fa-university"></i> Dados para Transferência</h3>
                <p><strong>Banco:</strong> Seven Bank</p>
                <p><strong>Agência:</strong> 001</p>
                <p><strong>Conta:</strong> 1234567-8</p>
                <p><strong>Titular:</strong> Gourmeria Ltda</p>
                <p><strong>Valor:</strong> <?php echo formatPrice($order['total']); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="order-items">
                <div class="section-title">
                    <i class="fas fa-list"></i> Itens do Pedido
                </div>
                
                <?php foreach ($orderItems as $item): ?>
                <div class="order-item">
                    <div class="item-image">
                        <?php if ($item['imagem_principal']): ?>
                            <img src="<?php echo UPLOAD_DIR . $item['imagem_principal']; ?>" alt="<?php echo htmlspecialchars($item['nome']); ?>">
                        <?php endif; ?>
                    </div>
                    <div class="item-info">
                        <div class="item-name"><?php echo htmlspecialchars($item['nome']); ?></div>
                        <div class="item-details">Quantidade: <?php echo $item['quantidade']; ?></div>
                    </div>
                    <div class="item-price">
                        <div class="item-unit-price"><?php echo formatPrice($item['preco_unitario']); ?> cada</div>
                        <div class="item-total-price"><?php echo formatPrice($item['subtotal']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="order-summary">
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span><?php echo formatPrice($order['subtotal']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Frete:</span>
                        <span><?php echo $order['frete'] > 0 ? formatPrice($order['frete']) : 'Grátis'; ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span><?php echo formatPrice($order['total']); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="order-details">
            <div class="section-title">
                <i class="fas fa-clock"></i> Acompanhe seu Pedido
            </div>
            
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="timeline-content">
                        <h4>Pedido Confirmado</h4>
                        <p><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-icon pending">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="timeline-content">
                        <h4>Aguardando Pagamento</h4>
                        <p>Confirme o pagamento para prosseguir</p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-icon pending">
                        <i class="fas fa-cookie-bite"></i>
                    </div>
                    <div class="timeline-content">
                        <h4>Preparando Pedido</h4>
                        <p>Seus doces serão preparados com carinho</p>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-icon pending">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="timeline-content">
                        <h4><?php echo $order['tipo_entrega'] == 'entrega' ? 'Enviado' : 'Pronto para Retirada'; ?></h4>
                        <p><?php echo $order['tipo_entrega'] == 'entrega' ? 'Você receberá o código de rastreamento' : 'Aguardando retirada em nossa loja'; ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="actions">
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-home"></i> Continuar Comprando
            </a>
            <a href="minha-conta.php" class="btn btn-secondary">
                <i class="fas fa-user"></i> Minha Conta
            </a>
            <a href="contato.php" class="btn btn-secondary">
                <i class="fas fa-headset"></i> Suporte
            </a>
        </div>
    </div>
    
    <script>
        // Animação de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.order-details, .actions');
            elements.forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(30px)';
                setTimeout(() => {
                    el.style.transition = 'all 0.6s ease';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });
        
        // Copiar dados PIX/Transferência
        document.querySelectorAll('.payment-info p').forEach(p => {
            if (p.innerHTML.includes('Chave PIX') || p.innerHTML.includes('Conta')) {
                p.style.cursor = 'pointer';
                p.title = 'Clique para copiar';
                p.addEventListener('click', function() {
                    const text = this.textContent.split(': ')[1];
                    if (text) {
                        navigator.clipboard.writeText(text).then(() => {
                            const original = this.innerHTML;
                            this.innerHTML = original + ' <span style="color: #28a745;">✓ Copiado!</span>';
                            setTimeout(() => {
                                this.innerHTML = original;
                            }, 2000);
                        });
                    }
                });
            }
        });
    </script>
</body>
</html>