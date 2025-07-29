<?php
require_once 'config.php';

// Verificar se está logado
if (!isLoggedIn()) {
    flashMessage('Faça login para acessar seu carrinho.', 'warning');
    redirect('login.php');
}

// Processar ações do carrinho
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_quantity':
                $cartId = (int)($_POST['cart_id'] ?? 0);
                $quantidade = max(1, (int)($_POST['quantidade'] ?? 1));
                
                $stmt = $db->prepare("UPDATE cart SET quantidade = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
                $stmt->execute([$quantidade, $cartId, $_SESSION['user_id']]);
                
                flashMessage('Quantidade atualizada!', 'success');
                break;
                
            case 'remove_item':
                $cartId = (int)($_POST['cart_id'] ?? 0);
                
                $stmt = $db->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                $stmt->execute([$cartId, $_SESSION['user_id']]);
                
                flashMessage('Item removido do carrinho!', 'success');
                break;
                
            case 'clear_cart':
                $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                
                flashMessage('Carrinho limpo com sucesso!', 'success');
                break;
                
            case 'apply_coupon':
                $couponCode = sanitize($_POST['coupon_code'] ?? '');
                // TODO: Implementar sistema de cupons
                flashMessage('Sistema de cupons será implementado em breve.', 'info');
                break;
        }
        
        // Se for requisição AJAX, retornar JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        
    } catch (Exception $e) {
        error_log('Cart Error: ' . $e->getMessage());
        flashMessage('Erro ao processar solicitação.', 'error');
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Erro interno']);
            exit;
        }
    }
    
    redirect('carrinho.php');
}

// Buscar itens do carrinho
$stmt = $db->prepare("
    SELECT c.*, p.nome, p.preco, p.preco_promocional, p.imagem_principal, p.peso
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ? AND p.ativo = 1 
    ORDER BY c.created_at ASC
");
$stmt->execute([$_SESSION['user_id']]);
$cartItems = $stmt->fetchAll();

// Calcular totais
$subtotal = 0;
$totalWeight = 0;
foreach ($cartItems as $item) {
    $preco = $item['preco_promocional'] ?: $item['preco'];
    $subtotal += $preco * $item['quantidade'];
    $totalWeight += ($item['peso'] ?? 0) * $item['quantidade'];
}

// Configurações de frete
$freteGratis = getSiteSetting('frete_gratuito_valor', 5000);
$valorFrete = getSiteSetting('valor_frete', 800);
$frete = ($subtotal >= $freteGratis) ? 0 : $valorFrete;
$total = $subtotal + $frete;

// Buscar produtos sugeridos (mais vendidos)
$stmt = $db->prepare("
    SELECT * FROM products 
    WHERE ativo = 1 
    ORDER BY vendas DESC, views DESC 
    LIMIT 4
");
$stmt->execute();
$produtosSugeridos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Carrinho - Gourmeria</title>
    <meta name="description" content="Revise seus produtos selecionados e finalize sua compra na Gourmeria.">
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
            --error-color: #dc3545;
            --warning-color: #ffc107;
            --gradient-primary: linear-gradient(135deg, #DAA520 0%, #B8860B 100%);
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
        
        /* Header Simples */
        .simple-header {
            background: var(--surface-dark);
            border-bottom: 1px solid var(--border-color);
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .simple-header .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo-simple {
            color: var(--primary-gold);
            text-decoration: none;
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header-nav {
            display: flex;
            gap: 30px;
            align-items: center;
        }
        
        .header-nav a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .header-nav a:hover,
        .header-nav a.active {
            color: var(--primary-gold);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Page Header */
        .page-header {
            background: var(--surface-dark);
            padding: 40px 0;
            text-align: center;
        }
        
        .page-header h1 {
            font-size: 36px;
            font-weight: 700;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
        }
        
        .page-header p {
            color: var(--text-secondary);
            font-size: 18px;
        }
        
        /* Cart Content */
        .cart-content {
            padding: 40px 0;
        }
        
        .cart-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 40px;
        }
        
        /* Cart Items */
        .cart-items {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 30px;
        }
        
        .items-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .items-title {
            font-size: 24px;
            color: var(--primary-gold);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .clear-cart-btn {
            background: transparent;
            border: 1px solid var(--error-color);
            color: var(--error-color);
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .clear-cart-btn:hover {
            background: var(--error-color);
            color: white;
        }
        
        .cart-item {
            display: grid;
            grid-template-columns: 80px 1fr auto auto;
            gap: 20px;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 80px;
            height: 80px;
            background: var(--surface-light);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        
        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .item-info {
            min-width: 0;
        }
        
        .item-name {
            font-weight: 600;
            color: var(--primary-gold);
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .item-price {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .item-observations {
            color: var(--text-muted);
            font-size: 12px;
            font-style: italic;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            background: var(--background-dark);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .qty-btn {
            background: none;
            border: none;
            color: var(--text-primary);
            padding: 8px 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .qty-btn:hover {
            background: var(--primary-gold);
            color: var(--background-dark);
        }
        
        .qty-input {
            background: none;
            border: none;
            color: var(--text-primary);
            text-align: center;
            width: 50px;
            padding: 8px 0;
            font-size: 14px;
            font-weight: 600;
        }
        
        .item-total {
            text-align: right;
            min-width: 80px;
        }
        
        .item-total-price {
            font-weight: 700;
            color: var(--primary-gold);
            font-size: 16px;
        }
        
        .remove-item {
            position: absolute;
            top: 10px;
            right: 0;
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 5px;
            transition: color 0.3s ease;
        }
        
        .remove-item:hover {
            color: var(--error-color);
        }
        
        /* Cart Summary */
        .cart-summary {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 30px;
            height: fit-content;
            position: sticky;
            top: 120px;
        }
        
        .summary-title {
            font-size: 24px;
            color: var(--primary-gold);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding: 8px 0;
        }
        
        .summary-row.total {
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            padding-top: 20px;
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-gold);
        }
        
        .free-shipping-notice {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid var(--success-color);
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
            color: var(--success-color);
            font-size: 14px;
        }
        
        .shipping-progress {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid var(--warning-color);
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            color: var(--warning-color);
            font-size: 14px;
        }
        
        .progress-bar {
            background: var(--border-color);
            height: 6px;
            border-radius: 3px;
            margin: 10px 0 5px;
            overflow: hidden;
        }
        
        .progress-fill {
            background: var(--warning-color);
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }
        
        /* Coupon Section */
        .coupon-section {
            margin: 25px 0;
            padding: 20px;
            background: var(--background-dark);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }
        
        .coupon-title {
            color: var(--text-secondary);
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .coupon-form {
            display: flex;
            gap: 10px;
        }
        
        .coupon-input {
            flex: 1;
            padding: 12px 15px;
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .coupon-input:focus {
            outline: none;
            border-color: var(--primary-gold);
        }
        
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: var(--gradient-primary);
            color: var(--background-dark);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(218, 165, 32, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-gold);
            color: var(--primary-gold);
        }
        
        .btn-outline:hover {
            background: var(--primary-gold);
            color: var(--background-dark);
        }
        
        .btn-secondary {
            background: var(--surface-light);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: var(--border-color);
        }
        
        .btn-full {
            width: 100%;
            margin-bottom: 15px;
        }
        
        /* Empty Cart */
        .empty-cart {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-muted);
        }
        
        .empty-cart i {
            font-size: 80px;
            margin-bottom: 30px;
            opacity: 0.3;
        }
        
        .empty-cart h3 {
            color: var(--text-secondary);
            margin-bottom: 15px;
            font-size: 24px;
        }
        
        .empty-cart p {
            margin-bottom: 30px;
            font-size: 16px;
        }
        
        /* Suggested Products */
        .suggested-section {
            background: var(--surface-dark);
            padding: 60px 0;
            margin-top: 60px;
            border-radius: 20px;
        }
        
        .section-title {
            text-align: center;
            font-size: 28px;
            color: var(--primary-gold);
            margin-bottom: 40px;
        }
        
        .suggested-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }
        
        .suggested-product {
            background: var(--background-dark);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--text-primary);
        }
        
        .suggested-product:hover {
            border-color: var(--primary-gold);
            transform: translateY(-5px);
            color: var(--text-primary);
        }
        
        .suggested-image {
            height: 150px;
            background: var(--surface-light);
            overflow: hidden;
        }
        
        .suggested-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .suggested-info {
            padding: 20px;
        }
        
        .suggested-name {
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--primary-gold);
        }
        
        .suggested-price {
            font-weight: 700;
            color: var(--text-primary);
        }
        
        /* Flash Messages */
        .flash-message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .flash-success {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }
        
        .flash-error {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid var(--error-color);
            color: var(--error-color);
        }
        
        .flash-warning {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid var(--warning-color);
            color: var(--warning-color);
        }
        
        .flash-info {
            background: rgba(23, 162, 184, 0.1);
            border: 1px solid #17a2b8;
            color: #17a2b8;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .cart-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .cart-summary {
                position: static;
                order: -1;
            }
            
            .cart-item {
                grid-template-columns: 60px 1fr;
                gap: 15px;
                grid-template-areas: 
                    "image info"
                    "controls total";
            }
            
            .item-image {
                grid-area: image;
                width: 60px;
                height: 60px;
            }
            
            .item-info {
                grid-area: info;
            }
            
            .quantity-controls {
                grid-area: controls;
                justify-self: start;
            }
            
            .item-total {
                grid-area: total;
                justify-self: end;
                align-self: center;
            }
            
            .suggested-grid {
                grid-template-columns: 1fr;
            }
            
            .header-nav {
                display: none;
            }
            
            .page-header h1 {
                font-size: 28px;
            }
            
            .coupon-form {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="simple-header">
        <div class="container">
            <a href="index.php" class="logo-simple">
                <i class="fas fa-gem"></i>
                Gourmeria
            </a>
            <nav class="header-nav">
                <a href="index.php">Início</a>
                <a href="produtos.php">Produtos</a>
                <a href="categorias.php">Categorias</a>
                <a href="sobre.php">Sobre</a>
                <a href="contato.php">Contato</a>
                <a href="carrinho.php" class="active"><i class="fas fa-shopping-cart"></i> Carrinho</a>
            </nav>
        </div>
    </header>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1><i class="fas fa-shopping-cart"></i> Meu Carrinho</h1>
            <p>Revise seus produtos e finalize sua compra</p>
        </div>
    </section>

    <!-- Flash Messages -->
    <?php 
    $flash = getFlashMessage();
    if ($flash):
    ?>
        <div class="container">
            <div class="flash-message flash-<?php echo $flash['type']; ?>">
                <?php if ($flash['type'] == 'success'): ?>
                    <i class="fas fa-check-circle"></i>
                <?php elseif ($flash['type'] == 'warning'): ?>
                    <i class="fas fa-exclamation-triangle"></i>
                <?php elseif ($flash['type'] == 'info'): ?>
                    <i class="fas fa-info-circle"></i>
                <?php else: ?>
                    <i class="fas fa-exclamation-circle"></i>
                <?php endif; ?>
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Cart Content -->
    <section class="cart-content">
        <div class="container">
            <?php if (empty($cartItems)): ?>
                <!-- Empty Cart -->
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Seu carrinho está vazio</h3>
                    <p>Que tal explorar nossos deliciosos doces gourmet?</p>
                    <a href="produtos.php" class="btn btn-primary">
                        <i class="fas fa-cookie-bite"></i>
                        Ver Produtos
                    </a>
                </div>
            <?php else: ?>
                <!-- Cart with Items -->
                <div class="cart-grid">
                    <!-- Cart Items -->
                    <div class="cart-items">
                        <div class="items-header">
                            <h2 class="items-title">
                                <i class="fas fa-list"></i>
                                Seus Produtos (<?php echo count($cartItems); ?>)
                            </h2>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="clear_cart">
                                <button type="submit" class="clear-cart-btn" onclick="return confirm('Tem certeza que deseja limpar o carrinho?')">
                                    <i class="fas fa-trash"></i> Limpar Carrinho
                                </button>
                            </form>
                        </div>
                        
                        <?php foreach ($cartItems as $item): ?>
                            <?php $preco = $item['preco_promocional'] ?: $item['preco']; ?>
                            <div class="cart-item">
                                <div class="item-image">
                                    <?php if ($item['imagem_principal']): ?>
                                        <img src="<?php echo UPLOAD_DIR . $item['imagem_principal']; ?>" alt="<?php echo htmlspecialchars($item['nome']); ?>">
                                    <?php else: ?>
                                        <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: var(--text-muted);">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="item-info">
                                    <div class="item-name">
                                        <a href="produto.php?id=<?php echo $item['product_id']; ?>" style="color: inherit; text-decoration: none;">
                                            <?php echo htmlspecialchars($item['nome']); ?>
                                        </a>
                                    </div>
                                    <div class="item-price">
                                        <?php echo formatPrice($preco); ?> cada
                                        <?php if ($item['preco_promocional']): ?>
                                            <span style="text-decoration: line-through; color: var(--text-muted); margin-left: 8px;">
                                                <?php echo formatPrice($item['preco']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($item['observacoes']): ?>
                                        <div class="item-observations">
                                            Obs: <?php echo htmlspecialchars($item['observacoes']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="quantity-controls">
                                    <button type="button" class="qty-btn" onclick="updateQuantity(<?php echo $item['id']; ?>, <?php echo $item['quantidade'] - 1; ?>)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" value="<?php echo $item['quantidade']; ?>" min="1" max="99" class="qty-input" 
                                           onchange="updateQuantity(<?php echo $item['id']; ?>, this.value)">
                                    <button type="button" class="qty-btn" onclick="updateQuantity(<?php echo $item['id']; ?>, <?php echo $item['quantidade'] + 1; ?>)">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                
                                <div class="item-total">
                                    <div class="item-total-price"><?php echo formatPrice($preco * $item['quantidade']); ?></div>
                                </div>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="remove_item">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="remove-item" onclick="return confirm('Remover este item?')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Cart Summary -->
                    <div class="cart-summary">
                        <h3 class="summary-title">
                            <i class="fas fa-calculator"></i>
                            Resumo do Pedido
                        </h3>
                        
                        <div class="summary-row">
                            <span>Subtotal (<?php echo count($cartItems); ?> itens):</span>
                            <span><?php echo formatPrice($subtotal); ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Frete:</span>
                            <span>
                                <?php if ($frete == 0): ?>
                                    <span style="color: var(--success-color);">Grátis</span>
                                <?php else: ?>
                                    <?php echo formatPrice($frete); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <?php if ($totalWeight > 0): ?>
                            <div class="summary-row">
                                <span>Peso total:</span>
                                <span><?php echo number_format($totalWeight, 0); ?>g</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span><?php echo formatPrice($total); ?></span>
                        </div>
                        
                        <!-- Shipping Notice -->
                        <?php if ($subtotal >= $freteGratis): ?>
                            <div class="free-shipping-notice">
                                <i class="fas fa-truck"></i>
                                Parabéns! Você ganhou frete grátis!
                            </div>
                        <?php else: ?>
                            <?php 
                            $remaining = $freteGratis - $subtotal;
                            $progress = ($subtotal / $freteGratis) * 100;
                            ?>
                            <div class="shipping-progress">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <span><i class="fas fa-gift"></i> Frete grátis em:</span>
                                    <strong><?php echo formatPrice($remaining); ?></strong>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo min(100, $progress); ?>%"></div>
                                </div>
                                <div style="font-size: 12px; margin-top: 5px;">
                                    <?php echo number_format($progress, 1); ?>% para frete grátis
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Coupon Section -->
                        <div class="coupon-section">
                            <div class="coupon-title">
                                <i class="fas fa-tag"></i> Cupom de Desconto
                            </div>
                            <form method="POST" class="coupon-form">
                                <input type="hidden" name="action" value="apply_coupon">
                                <input type="text" name="coupon_code" placeholder="Digite seu cupom" class="coupon-input">
                                <button type="submit" class="btn btn-secondary">Aplicar</button>
                            </form>
                        </div>
                        
                        <!-- Action Buttons -->
                        <a href="checkout.php" class="btn btn-primary btn-full">
                            <i class="fas fa-credit-card"></i>
                            Finalizar Compra
                        </a>
                        
                        <a href="produtos.php" class="btn btn-outline btn-full">
                            <i class="fas fa-plus"></i>
                            Continuar Comprando
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Suggested Products -->
    <?php if (!empty($produtosSugeridos)): ?>
        <section class="suggested-section">
            <div class="container">
                <h2 class="section-title">Você Pode Gostar Também</h2>
                <div class="suggested-grid">
                    <?php foreach ($produtosSugeridos as $sugerido): ?>
                        <a href="produto.php?id=<?php echo $sugerido['id']; ?>" class="suggested-product">
                            <div class="suggested-image">
                                <?php if ($sugerido['imagem_principal']): ?>
                                    <img src="<?php echo UPLOAD_DIR . $sugerido['imagem_principal']; ?>" alt="<?php echo htmlspecialchars($sugerido['nome']); ?>">
                                <?php else: ?>
                                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: var(--text-muted);">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="suggested-info">
                                <div class="suggested-name"><?php echo htmlspecialchars($sugerido['nome']); ?></div>
                                <div class="suggested-price">
                                    <?php echo formatPrice($sugerido['preco_promocional'] ?: $sugerido['preco']); ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <script>
        // Função para atualizar quantidade
        function updateQuantity(cartId, newQuantity) {
            if (newQuantity < 1) {
                if (confirm('Remover este item do carrinho?')) {
                    removeItem(cartId);
                }
                return;
            }
            
            if (newQuantity > 99) {
                alert('Quantidade máxima é 99 unidades.');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'update_quantity');
            formData.append('cart_id', cartId);
            formData.append('quantidade', newQuantity);
            
            fetch('carrinho.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erro ao atualizar quantidade.');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao atualizar quantidade.');
            });
        }
        
        // Função para remover item
        function removeItem(cartId) {
            const formData = new FormData();
            formData.append('action', 'remove_item');
            formData.append('cart_id', cartId);
            
            fetch('carrinho.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erro ao remover item.');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao remover item.');
            });
        }
        
        // Auto-hide flash messages
        setTimeout(function() {
            const flashMessage = document.querySelector('.flash-message');
            if (flashMessage) {
                flashMessage.style.opacity = '0';
                flashMessage.style.transform = 'translateY(-20px)';
                setTimeout(() => flashMessage.remove(), 300);
            }
        }, 5000);
        
        // Animações de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);
            
            const animatedElements = document.querySelectorAll('.cart-item, .suggested-product');
            animatedElements.forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = `all 0.6s ease ${index * 0.1}s`;
                observer.observe(el);
            });
        });
    </script>
</body>
</html>