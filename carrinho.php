<?php
// ============================================
// ARQUIVO: carrinho.php
// ============================================
?>
<?php
require_once 'config.php';

// Verificar se usuário está logado
requireLogin();

// Processar ações do carrinho
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update':
            $productId = intval($_POST['product_id']);
            $quantity = intval($_POST['quantity']);
            
            if ($cartClass->updateQuantity($_SESSION['user_id'], $productId, $quantity)) {
                flashMessage('Carrinho atualizado!', 'success');
            } else {
                flashMessage('Erro ao atualizar carrinho.', 'error');
            }
            break;
            
        case 'remove':
            $productId = intval($_POST['product_id']);
            
            if ($cartClass->removeItem($_SESSION['user_id'], $productId)) {
                flashMessage('Produto removido do carrinho!', 'info');
            } else {
                flashMessage('Erro ao remover produto.', 'error');
            }
            break;
            
        case 'clear':
            if ($cartClass->clearCart($_SESSION['user_id'])) {
                flashMessage('Carrinho limpo!', 'info');
            } else {
                flashMessage('Erro ao limpar carrinho.', 'error');
            }
            break;
    }
    
    redirect('carrinho.php');
}

// Buscar itens do carrinho
$cartItems = $cartClass->getItems($_SESSION['user_id']);

// Calcular totais
$subtotal = 0;
foreach ($cartItems as $item) {
    $preco = $item['preco_promocional'] ?: $item['preco'];
    $subtotal += $preco * $item['quantidade'];
}

$frete = 0;
$freteGratuito = floatval(getSiteSetting('frete_gratuito_valor', 5000));
if ($subtotal < $freteGratuito) {
    $frete = floatval(getSiteSetting('taxa_yamato', 500));
}

$total = $subtotal + $frete;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrinho - Gourmeria</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS base do site seria incluído aqui - usar o mesmo do index.php */
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        main {
            margin-top: 130px;
            min-height: calc(100vh - 130px);
            padding: 40px 0;
        }
        
        .cart-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
            margin-top: 20px;
        }
        
        .cart-items {
            background: #111;
            border: 2px solid #333;
            border-radius: 10px;
            padding: 30px;
        }
        
        .cart-item {
            display: grid;
            grid-template-columns: 80px 1fr auto auto auto;
            gap: 20px;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid #333;
        }
        
        .cart-item:last-child {
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
        
        .item-info h3 {
            color: #DAA520;
            margin-bottom: 5px;
        }
        
        .item-price {
            font-weight: bold;
            color: #DAA520;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quantity-btn {
            background: #333;
            color: #fff;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .quantity-btn:hover {
            background: #DAA520;
            color: #000;
        }
        
        .quantity-input {
            width: 60px;
            text-align: center;
            background: #000;
            color: #fff;
            border: 1px solid #333;
            border-radius: 4px;
            padding: 5px;
        }
        
        .remove-btn {
            background: #dc3545;
            color: #fff;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .remove-btn:hover {
            background: #c82333;
        }
        
        .cart-summary {
            background: #111;
            border: 2px solid #333;
            border-radius: 10px;
            padding: 30px;
            height: fit-content;
            position: sticky;
            top: 150px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 10px;
        }
        
        .summary-row.total {
            border-top: 2px solid #DAA520;
            padding-top: 15px;
            font-size: 18px;
            font-weight: bold;
            color: #DAA520;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #DAA520;
            color: #000;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            width: 100%;
            text-align: center;
            margin-top: 20px;
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
        
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-cart i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #333;
        }
        
        .page-title {
            color: #DAA520;
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .page-subtitle {
            color: #ccc;
            margin-bottom: 30px;
        }
        
        @media (max-width: 768px) {
            .cart-container {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .cart-item {
                grid-template-columns: 60px 1fr;
                gap: 15px;
            }
            
            .quantity-controls,
            .remove-btn {
                grid-column: 1 / -1;
                justify-self: start;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Header seria incluído aqui -->
    <main>
        <div class="container">
            <?php 
            $flash = getFlashMessage();
            if ($flash):
            ?>
                <div class="flash-message flash-<?php echo $flash['type']; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>
            
            <h1 class="page-title">
                <i class="fas fa-shopping-cart"></i> Meu Carrinho
            </h1>
            <p class="page-subtitle">Revise seus itens antes de finalizar o pedido</p>
            
            <?php if (empty($cartItems)): ?>
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h2>Seu carrinho está vazio</h2>
                    <p>Explore nossos produtos e adicione seus doces favoritos!</p>
                    <a href="produtos.php" class="btn" style="margin-top: 20px; display: inline-block; width: auto;">
                        <i class="fas fa-search"></i> Ver Produtos
                    </a>
                </div>
            <?php else: ?>
                <div class="cart-container">
                    <div class="cart-items">
                        <h2 style="color: #DAA520; margin-bottom: 20px;">
                            <i class="fas fa-list"></i> Itens do Carrinho
                        </h2>
                        
                        <?php foreach ($cartItems as $item): ?>
                            <div class="cart-item">
                                <div class="item-image">
                                    <?php if ($item['imagem_principal']): ?>
                                        <img src="<?php echo UPLOAD_DIR . $item['imagem_principal']; ?>" alt="<?php echo htmlspecialchars($item['nome']); ?>">
                                    <?php endif; ?>
                                </div>
                                
                                <div class="item-info">
                                    <h3><?php echo htmlspecialchars($item['nome']); ?></h3>
                                    <div class="item-price">
                                        <?php 
                                        $preco = $item['preco_promocional'] ?: $item['preco'];
                                        echo formatPrice($preco);
                                        if ($item['preco_promocional']):
                                        ?>
                                            <span style="text-decoration: line-through; color: #666; font-size: 14px; margin-left: 10px;">
                                                <?php echo formatPrice($item['preco']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="quantity-controls">
                                    <form method="POST" style="display: flex; align-items: center; gap: 10px;">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                        
                                        <button type="button" class="quantity-btn" onclick="updateQuantity(<?php echo $item['product_id']; ?>, -1)">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        
                                        <input 
                                            type="number" 
                                            name="quantity" 
                                            value="<?php echo $item['quantidade']; ?>" 
                                            min="1" 
                                            class="quantity-input"
                                            onchange="updateCart(<?php echo $item['product_id']; ?>, this.value)"
                                        >
                                        
                                        <button type="button" class="quantity-btn" onclick="updateQuantity(<?php echo $item['product_id']; ?>, 1)">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </form>
                                </div>
                                
                                <div style="text-align: right;">
                                    <div style="font-weight: bold; color: #DAA520; margin-bottom: 10px;">
                                        <?php echo formatPrice($preco * $item['quantidade']); ?>
                                    </div>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                        <button type="submit" class="remove-btn" onclick="return confirm('Remover este item?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div style="margin-top: 20px; text-align: center;">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="clear">
                                <button type="submit" class="btn btn-outline" onclick="return confirm('Limpar todo o carrinho?')" style="width: auto;">
                                    <i class="fas fa-trash"></i> Limpar Carrinho
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="cart-summary">
                        <h2 style="color: #DAA520; margin-bottom: 20px;">
                            <i class="fas fa-calculator"></i> Resumo do Pedido
                        </h2>
                        
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span><?php echo formatPrice($subtotal); ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Frete:</span>
                            <span>
                                <?php if ($frete > 0): ?>
                                    <?php echo formatPrice($frete); ?>
                                <?php else: ?>
                                    <span style="color: #28a745;">Grátis</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <?php if ($subtotal < $freteGratuito && $frete > 0): ?>
                            <div style="background: #333; padding: 15px; border-radius: 8px; margin: 15px 0; text-align: center; font-size: 14px;">
                                <i class="fas fa-info-circle" style="color: #DAA520;"></i>
                                Faltam <?php echo formatPrice($freteGratuito - $subtotal); ?> para frete grátis!
                            </div>
                        <?php endif; ?>
                        
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span><?php echo formatPrice($total); ?></span>
                        </div>
                        
                        <a href="checkout.php" class="btn">
                            <i class="fas fa-credit-card"></i> Finalizar Pedido
                        </a>
                        
                        <a href="produtos.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Continuar Comprando
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function updateQuantity(productId, change) {
            const input = document.querySelector(`input[name="quantity"][onchange*="${productId}"]`);
            const currentValue = parseInt(input.value);
            const newValue = Math.max(1, currentValue + change);
            input.value = newValue;
            updateCart(productId, newValue);
        }
        
        function updateCart(productId, quantity) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="product_id" value="${productId}">
                <input type="hidden" name="quantity" value="${quantity}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>