<?php
require_once 'config.php';

// Verificar se está logado
if (!isLoggedIn()) {
    flashMessage('Faça login para finalizar sua compra.', 'warning');
    redirect('login.php');
}

// Buscar itens do carrinho
$stmt = $db->prepare("
    SELECT c.*, p.nome, p.preco, p.preco_promocional, p.imagem_principal 
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ? AND p.ativo = 1
");
$stmt->execute([$_SESSION['user_id']]);
$cartItems = $stmt->fetchAll();

if (empty($cartItems)) {
    flashMessage('Seu carrinho está vazio.', 'info');
    redirect('produtos.php');
}

// Calcular totais
$subtotal = 0;
foreach ($cartItems as $item) {
    $preco = $item['preco_promocional'] ?: $item['preco'];
    $subtotal += $preco * $item['quantidade'];
}

$freteGratis = getSiteSetting('frete_gratuito_valor', 5000);
$frete = ($subtotal >= $freteGratis) ? 0 : getSiteSetting('valor_frete', 800);
$total = $subtotal + $frete;

// Buscar dados do usuário
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userData = $stmt->fetch();

// Processar pedido
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome_completo = sanitize($_POST['nome_completo']);
    $telefone = sanitize($_POST['telefone']);
    $cep = sanitize($_POST['cep']);
    $endereco = sanitize($_POST['endereco']);
    $numero = sanitize($_POST['numero']);
    $complemento = sanitize($_POST['complemento']);
    $bairro = sanitize($_POST['bairro']);
    $cidade = sanitize($_POST['cidade']);
    $estado = sanitize($_POST['estado']);
    $observacoes = sanitize($_POST['observacoes']);
    $forma_pagamento = sanitize($_POST['forma_pagamento']);
    $tipo_entrega = sanitize($_POST['tipo_entrega']);
    
    // Validações
    $errors = [];
    
    if (empty($nome_completo)) $errors[] = 'Nome completo é obrigatório';
    if (empty($telefone)) $errors[] = 'Telefone é obrigatório';
    if ($tipo_entrega == 'entrega') {
        if (empty($cep)) $errors[] = 'CEP é obrigatório';
        if (empty($endereco)) $errors[] = 'Endereço é obrigatório';
        if (empty($cidade)) $errors[] = 'Cidade é obrigatória';
    }
    if (empty($forma_pagamento)) $errors[] = 'Forma de pagamento é obrigatória';
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Criar pedido
            $stmt = $db->prepare("
                INSERT INTO orders (
                    user_id, nome_completo, telefone, cep, endereco, numero, 
                    complemento, bairro, cidade, estado, observacoes, 
                    forma_pagamento, tipo_entrega, subtotal, frete, total, 
                    status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', NOW())
            ");
            
            $stmt->execute([
                $_SESSION['user_id'], $nome_completo, $telefone, $cep, $endereco, 
                $numero, $complemento, $bairro, $cidade, $estado, $observacoes,
                $forma_pagamento, $tipo_entrega, $subtotal, $frete, $total
            ]);
            
            $orderId = $db->lastInsertId();
            
            // Adicionar itens do pedido
            foreach ($cartItems as $item) {
                $preco = $item['preco_promocional'] ?: $item['preco'];
                
                $stmt = $db->prepare("
                    INSERT INTO order_items (order_id, product_id, quantidade, preco_unitario, subtotal) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $itemSubtotal = $preco * $item['quantidade'];
                $stmt->execute([$orderId, $item['product_id'], $item['quantidade'], $preco, $itemSubtotal]);
            }
            
            // Limpar carrinho
            $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            // Log da atividade
            logActivity('order_created', "Pedido #$orderId criado - Total: " . formatPrice($total));
            
            // Enviar e-mail de confirmação
            $orderData = [
                'id' => $orderId,
                'customer_name' => $nome_completo,
                'total' => $total,
                'status' => 'Pendente',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            sendOrderConfirmationEmail($userData['email'], $orderData);
            
            $db->commit();
            
            flashMessage('Pedido realizado com sucesso! Número: #' . $orderId, 'success');
            redirect('pedido-confirmado.php?id=' . $orderId);
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Checkout Error: ' . $e->getMessage());
            flashMessage('Erro ao processar pedido. Tente novamente.', 'error');
        }
    } else {
        foreach ($errors as $error) {
            flashMessage($error, 'error');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Compra - Gourmeria</title>
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
            --warning-color: #ffc107;
            --error-color: #dc3545;
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .checkout-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 40px 0;
        }
        
        .checkout-header h1 {
            font-size: 42px;
            color: var(--primary-gold);
            margin-bottom: 10px;
        }
        
        .checkout-header p {
            color: var(--text-secondary);
            font-size: 18px;
        }
        
        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 40px;
        }
        
        .checkout-form {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 30px;
        }
        
        .order-summary {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 30px;
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        
        .section-title {
            font-size: 20px;
            color: var(--primary-gold);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .form-row.triple {
            grid-template-columns: 2fr 1fr 1fr;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 15px;
            background: var(--background-dark);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary-gold);
            box-shadow: 0 0 0 3px rgba(218, 165, 32, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .radio-option {
            flex: 1;
            min-width: 200px;
        }
        
        .radio-option input[type="radio"] {
            display: none;
        }
        
        .radio-option label {
            display: block;
            padding: 15px 20px;
            background: var(--background-dark);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .radio-option input[type="radio"]:checked + label {
            border-color: var(--primary-gold);
            background: rgba(218, 165, 32, 0.1);
            color: var(--primary-gold);
        }
        
        .cart-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .cart-item:last-child {
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
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
        }
        
        .summary-row.total {
            border-top: 2px solid var(--border-color);
            margin-top: 15px;
            padding-top: 15px;
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-gold);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px 32px;
            background: linear-gradient(135deg, var(--primary-gold), var(--dark-gold));
            color: var(--background-dark);
            text-decoration: none;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn:hover {
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
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            margin-bottom: 30px;
            transition: color 0.3s ease;
        }
        
        .back-link:hover {
            color: var(--primary-gold);
        }
        
        .free-shipping {
            background: var(--success-color);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            margin-left: 10px;
        }
        
        .flash-message {
            padding: 16px 24px;
            margin: 20px 0;
            border-radius: 12px;
            font-weight: 500;
            border-left: 4px solid;
        }
        
        .flash-success {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border-color: #28a745;
        }
        
        .flash-error {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border-color: #dc3545;
        }
        
        .flash-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border-color: #ffc107;
        }
        
        @media (max-width: 768px) {
            .checkout-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .order-summary {
                order: -1;
                position: static;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .radio-group {
                flex-direction: column;
            }
            
            .checkout-header h1 {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="carrinho.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Voltar ao Carrinho
        </a>
        
        <?php 
        $flash = getFlashMessage();
        if ($flash):
        ?>
            <div class="flash-message flash-<?php echo $flash['type']; ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="checkout-header">
            <h1><i class="fas fa-shopping-bag"></i> Finalizar Compra</h1>
            <p>Preencha os dados para concluir seu pedido</p>
        </div>
        
        <form method="POST" class="checkout-grid">
            <div class="checkout-form">
                <div class="section-title">
                    <i class="fas fa-user"></i> Dados Pessoais
                </div>
                
                <div class="form-group">
                    <label for="nome_completo">Nome Completo *</label>
                    <input type="text" id="nome_completo" name="nome_completo" required value="<?php echo htmlspecialchars($userData['name'] ?? ''); ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="telefone">Telefone *</label>
                        <input type="tel" id="telefone" name="telefone" required value="<?php echo htmlspecialchars($userData['telefone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="email">E-mail (confirmação)</label>
                        <input type="email" value="<?php echo htmlspecialchars($userData['email']); ?>" disabled>
                    </div>
                </div>
                
                <div class="section-title">
                    <i class="fas fa-truck"></i> Tipo de Entrega
                </div>
                
                <div class="form-group">
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="entrega" name="tipo_entrega" value="entrega" checked>
                            <label for="entrega">
                                <i class="fas fa-truck"></i><br>
                                Entrega (Yamato)<br>
                                <small><?php echo formatPrice($frete); ?></small>
                            </label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="retirada" name="tipo_entrega" value="retirada">
                            <label for="retirada">
                                <i class="fas fa-store"></i><br>
                                Retirada<br>
                                <small>Grátis (Hamamatsu)</small>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div id="endereco-section">
                    <div class="section-title">
                        <i class="fas fa-map-marker-alt"></i> Endereço de Entrega
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cep">CEP *</label>
                            <input type="text" id="cep" name="cep" placeholder="000-0000">
                        </div>
                        <div class="form-group">
                            <label for="estado">Estado *</label>
                            <input type="text" id="estado" name="estado" placeholder="Ex: Aichi">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="cidade">Cidade *</label>
                        <input type="text" id="cidade" name="cidade" placeholder="Ex: Nagoya">
                    </div>
                    
                    <div class="form-group">
                        <label for="bairro">Bairro</label>
                        <input type="text" id="bairro" name="bairro">
                    </div>
                    
                    <div class="form-row triple">
                        <div class="form-group">
                            <label for="endereco">Endereço *</label>
                            <input type="text" id="endereco" name="endereco" placeholder="Rua, Avenida...">
                        </div>
                        <div class="form-group">
                            <label for="numero">Número</label>
                            <input type="text" id="numero" name="numero">
                        </div>
                        <div class="form-group">
                            <label for="complemento">Complemento</label>
                            <input type="text" id="complemento" name="complemento" placeholder="Apto, Sala...">
                        </div>
                    </div>
                </div>
                
                <div class="section-title">
                    <i class="fas fa-credit-card"></i> Forma de Pagamento
                </div>
                
                <div class="form-group">
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="pix" name="forma_pagamento" value="pix">
                            <label for="pix">
                                <i class="fas fa-qrcode"></i><br>
                                PIX<br>
                                <small>Instantâneo</small>
                            </label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="transferencia" name="forma_pagamento" value="transferencia">
                            <label for="transferencia">
                                <i class="fas fa-university"></i><br>
                                Transferência<br>
                                <small>Banco Japonês</small>
                            </label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="dinheiro" name="forma_pagamento" value="dinheiro">
                            <label for="dinheiro">
                                <i class="fas fa-money-bill"></i><br>
                                Dinheiro<br>
                                <small>Na entrega</small>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="observacoes">Observações</label>
                    <textarea id="observacoes" name="observacoes" placeholder="Instruções especiais, horário preferido, etc."></textarea>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-check"></i> Finalizar Pedido
                </button>
            </div>
            
            <div class="order-summary">
                <div class="section-title">
                    <i class="fas fa-shopping-cart"></i> Resumo do Pedido
                </div>
                
                <div class="cart-items">
                    <?php foreach ($cartItems as $item): ?>
                        <?php $preco = $item['preco_promocional'] ?: $item['preco']; ?>
                        <div class="cart-item">
                            <div class="item-image">
                                <?php if ($item['imagem_principal']): ?>
                                    <img src="<?php echo UPLOAD_DIR . $item['imagem_principal']; ?>" alt="<?php echo htmlspecialchars($item['nome']); ?>">
                                <?php endif; ?>
                            </div>
                            <div class="item-info">
                                <div class="item-name"><?php echo htmlspecialchars($item['nome']); ?></div>
                                <div class="item-details">Qtd: <?php echo $item['quantidade']; ?></div>
                            </div>
                            <div class="item-price">
                                <?php echo formatPrice($preco * $item['quantidade']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="margin-top: 20px;">
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span><?php echo formatPrice($subtotal); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Frete:</span>
                        <span>
                            <?php if ($frete == 0): ?>
                                Grátis <span class="free-shipping">Frete Grátis!</span>
                            <?php else: ?>
                                <?php echo formatPrice($frete); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span><?php echo formatPrice($total); ?></span>
                    </div>
                </div>
                
                <?php if ($subtotal < $freteGratis): ?>
                    <div style="background: rgba(255, 193, 7, 0.1); padding: 15px; border-radius: 8px; margin-top: 20px; text-align: center; color: #ffc107;">
                        <i class="fas fa-truck"></i>
                        Faltam <?php echo formatPrice($freteGratis - $subtotal); ?> para frete grátis!
                    </div>
                <?php endif; ?>
                
                <a href="carrinho.php" class="btn btn-secondary" style="margin-top: 15px;">
                    <i class="fas fa-edit"></i> Editar Carrinho
                </a>
            </div>
        </form>
    </div>
    
    <script>
        // Controlar exibição do endereço baseado no tipo de entrega
        document.querySelectorAll('input[name="tipo_entrega"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const enderecoSection = document.getElementById('endereco-section');
                if (this.value === 'retirada') {
                    enderecoSection.style.display = 'none';
                    // Remover required dos campos de endereço
                    enderecoSection.querySelectorAll('input[required]').forEach(input => {
                        input.removeAttribute('required');
                    });
                } else {
                    enderecoSection.style.display = 'block';
                    // Adicionar required nos campos obrigatórios
                    document.getElementById('cep').setAttribute('required', '');
                    document.getElementById('endereco').setAttribute('required', '');
                    document.getElementById('cidade').setAttribute('required', '');
                }
            });
        });
        
        // Máscara para CEP
        document.getElementById('cep').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 3) {
                value = value.slice(0, 3) + '-' + value.slice(3, 7);
            }
            e.target.value = value;
        });
        
        // Validação do formulário
        document.querySelector('form').addEventListener('submit', function(e) {
            const formaPagamento = document.querySelector('input[name="forma_pagamento"]:checked');
            const tipoEntrega = document.querySelector('input[name="tipo_entrega"]:checked');
            
            if (!formaPagamento) {
                e.preventDefault();
                alert('Por favor, selecione uma forma de pagamento.');
                return;
            }
            
            if (!tipoEntrega) {
                e.preventDefault();
                alert('Por favor, selecione o tipo de entrega.');
                return;
            }
            
            // Confirmar pedido
            if (!confirm('Confirma a finalização do pedido?')) {
                e.preventDefault();
            }
        });
        
        // Auto-hide flash messages
        setTimeout(function() {
            const flashMessage = document.querySelector('.flash-message');
            if (flashMessage) {
                flashMessage.style.opacity = '0';
                setTimeout(() => flashMessage.remove(), 300);
            }
        }, 5000);
    </script>
</body>
</html>