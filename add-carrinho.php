<?php
require_once 'config.php';

// Verificar se está logado
if (!isLoggedIn()) {
    flashMessage('Faça login para adicionar produtos ao carrinho.', 'warning');
    redirect('login.php');
}

$productId = (int)($_GET['id'] ?? 0);
$quantidade = max(1, (int)($_GET['qty'] ?? 1));

if (!$productId) {
    flashMessage('Produto não encontrado.', 'error');
    redirect('produtos.php');
}

try {
    // Verificar se o produto existe e está ativo
    $stmt = $db->prepare("SELECT id, nome, preco, preco_promocional, ativo FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $produto = $stmt->fetch();
    
    if (!$produto || !$produto['ativo']) {
        flashMessage('Produto não disponível.', 'error');
        redirect('produtos.php');
    }
    
    // Verificar se já existe no carrinho
    $stmt = $db->prepare("SELECT id, quantidade FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$_SESSION['user_id'], $productId]);
    $cartItem = $stmt->fetch();
    
    if ($cartItem) {
        // Atualizar quantidade
        $novaQuantidade = $cartItem['quantidade'] + $quantidade;
        $stmt = $db->prepare("UPDATE cart SET quantidade = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$novaQuantidade, $cartItem['id']]);
        $message = 'Quantidade atualizada no carrinho!';
    } else {
        // Adicionar novo item
        $stmt = $db->prepare("INSERT INTO cart (user_id, product_id, quantidade) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $productId, $quantidade]);
        $message = 'Produto adicionado ao carrinho!';
    }
    
    // Log da atividade
    logActivity('cart_add', "Produto {$produto['nome']} adicionado ao carrinho");
    
    flashMessage($message, 'success');
    
    // Redirecionar para o carrinho ou página anterior
    $redirect = $_GET['redirect'] ?? 'carrinho.php';
    redirect($redirect);
    
} catch (Exception $e) {
    error_log('Add to Cart Error: ' . $e->getMessage());
    flashMessage('Erro ao adicionar produto ao carrinho.', 'error');
    redirect('produtos.php');
}
?>