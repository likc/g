<?php
require_once 'config.php';

// Verificar se usuário está logado
requireLogin();

$activeTab = sanitize($_GET['tab'] ?? 'pedidos');
$errors = [];
$success = false;

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = sanitize($_POST['action']);
    
    switch ($action) {
        case 'update_profile':
            $nome = sanitize($_POST['nome']);
            $sobrenome = sanitize($_POST['sobrenome']);
            $telefone = sanitize($_POST['telefone']);
            
            if (empty($nome) || empty($sobrenome)) {
                $errors[] = 'Nome e sobrenome são obrigatórios.';
            }
            
            if (empty($errors)) {
                $stmt = $db->prepare("UPDATE users SET nome = ?, sobrenome = ?, telefone = ? WHERE id = ?");
                if ($stmt->execute([$nome, $sobrenome, $telefone, $_SESSION['user_id']])) {
                    $_SESSION['user_name'] = $nome;
                    flashMessage('Perfil atualizado com sucesso!', 'success');
                } else {
                    $errors[] = 'Erro ao atualizar perfil.';
                }
            }
            break;
            
        case 'change_password':
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            // Buscar senha atual
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!verifyPassword($currentPassword, $user['password'])) {
                $errors[] = 'Senha atual incorreta.';
            } elseif (strlen($newPassword) < 6) {
                $errors[] = 'Nova senha deve ter pelo menos 6 caracteres.';
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = 'Confirmação de senha não confere.';
            }
            
            if (empty($errors)) {
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt->execute([hashPassword($newPassword), $_SESSION['user_id']])) {
                    flashMessage('Senha alterada com sucesso!', 'success');
                } else {
                    $errors[] = 'Erro ao alterar senha.';
                }
            }
            break;
            
        case 'add_address':
            $nome = sanitize($_POST['nome']);
            $cep = sanitize($_POST['cep']);
            $estado = sanitize($_POST['estado']);
            $cidade = sanitize($_POST['cidade']);
            $endereco = sanitize($_POST['endereco']);
            $complemento = sanitize($_POST['complemento']);
            $telefone = sanitize($_POST['telefone']);
            $isDefault = isset($_POST['is_default']);
            
            if (empty($nome) || empty($cidade) || empty($endereco)) {
                $errors[] = 'Campos obrigatórios: Nome, Cidade, Endereço.';
            }
            
            if (empty($errors)) {
                // Se é padrão, remover padrão dos outros
                if ($isDefault) {
                    $stmt = $db->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                }
                
                $stmt = $db->prepare("
                    INSERT INTO addresses (user_id, nome, cep, estado, cidade, endereco, complemento, telefone, is_default, tipo) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'entrega')
                ");
                
                if ($stmt->execute([$_SESSION['user_id'], $nome, $cep, $estado, $cidade, $endereco, $complemento, $telefone, $isDefault])) {
                    flashMessage('Endereço adicionado com sucesso!', 'success');
                } else {
                    $errors[] = 'Erro ao adicionar endereço.';
                }
            }
            break;
            
        case 'delete_address':
            $addressId = intval($_POST['address_id']);
            
            $stmt = $db->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$addressId, $_SESSION['user_id']])) {
                flashMessage('Endereço removido com sucesso!', 'success');
            } else {
                $errors[] = 'Erro ao remover endereço.';
            }
            break;
    }
    
    redirect('minha-conta.php?tab=' . $activeTab);
}

// Buscar dados do usuário
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$usuario = $stmt->fetch();

// Buscar pedidos
$stmt = $db->prepare("
    SELECT o.*, COUNT(oi.id) as total_items 
    FROM orders o 
    LEFT JOIN order_items oi ON o.id = oi.order_id 
    WHERE o.user_id = ? 
    GROUP BY o.id 
    ORDER BY o.created_at DESC 
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$pedidos = $stmt->fetchAll();

// Buscar endereços
$stmt = $db->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$enderecos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha Conta - Gourmeria</title>
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
            max-width: 1200px;
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
        
        .account-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 40px;
        }
        
        .sidebar {
            background: #111;
            border: 2px solid #333;
            border-radius: 10px;
            padding: 30px;
            height: fit-content;
            position: sticky;
            top: 120px;
        }
        
        .user-info {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            background: #DAA520;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 32px;
            color: #000;
        }
        
        .nav-menu {
            list-style: none;
        }
        
        .nav-menu li {
            margin-bottom: 10px;
        }
        
        .nav-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            color: #ccc;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .nav-menu a:hover,
        .nav-menu a.active {
            background: #DAA520;
            color: #000;
        }
        
        .content {
            background: #111;
            border: 2px solid #333;
            border-radius: 10px;
            padding: 40px;
        }
        
        .content-header {
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .content-title {
            color: #DAA520;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #DAA520;
            font-weight: bold;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #333;
            border-radius: 5px;
            background: #000;
            color: #fff;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #DAA520;
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
        
        .btn-danger {
            background: #dc3545;
            color: #fff;
        }
        
        .btn-danger:hover {
            background: #c82333;
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
        
        .orders-grid {
            display: grid;
            gap: 20px;
        }
        
        .order-card {
            background: #222;
            border: 2px solid #333;
            border-radius: 8px;
            padding: 20px;
            transition: border-color 0.3s;
        }
        
        .order-card:hover {
            border-color: #DAA520;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .order-number {
            font-size: 18px;
            font-weight: bold;
            color: #DAA520;
        }
        
        .order-status {
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 12px;
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
        
        .order-info {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            color: #ccc;
            font-size: 14px;
        }
        
        .addresses-grid {
            display: grid;
            gap: 20px;
        }
        
        .address-card {
            background: #222;
            border: 2px solid #333;
            border-radius: 8px;
            padding: 20px;
            position: relative;
        }
        
        .address-card.default {
            border-color: #DAA520;
        }
        
        .address-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .address-name {
            font-weight: bold;
            color: #DAA520;
        }
        
        .default-badge {
            background: #DAA520;
            color: #000;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .address-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2000;
        }
        
        .modal-content {
            background: #111;
            border: 2px solid #DAA520;
            border-radius: 10px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-title {
            color: #DAA520;
            font-size: 20px;
        }
        
        .close-modal {
            background: none;
            border: none;
            color: #ccc;
            font-size: 24px;
            cursor: pointer;
        }
        
        .close-modal:hover {
            color: #DAA520;
        }
        
        .errors {
            background: #dc3545;
            color: #fff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .errors ul {
            list-style: none;
        }
        
        .errors li {
            margin-bottom: 5px;
        }
        
        .flash-message {
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 5px;
            font-weight: bold;
        }
        
        .flash-success {
            background: #28a745;
            color: #fff;
        }
        
        .flash-error {
            background: #dc3545;
            color: #fff;
        }
        
        .flash-info {
            background: #17a2b8;
            color: #fff;
        }
        
        .flash-warning {
            background: #ffc107;
            color: #000;
        }
        
        @media (max-width: 768px) {
            .account-container {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .sidebar {
                position: static;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .order-info {
                grid-template-columns: 1fr;
                gap: 10px;
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
                <a href="produtos.php">Produtos</a>
                <a href="carrinho.php">Carrinho</a>
                <a href="logout.php">Sair</a>
            </nav>
        </div>
    </header>

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
            
            <?php if (!empty($errors)): ?>
                <div class="errors">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="account-container">
                <!-- Sidebar -->
                <aside class="sidebar">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($usuario['nome'], 0, 1)); ?>
                        </div>
                        <h3 style="color: #DAA520; margin-bottom: 5px;">
                            <?php echo htmlspecialchars($usuario['nome'] . ' ' . $usuario['sobrenome']); ?>
                        </h3>
                        <p style="color: #ccc; font-size: 14px;">
                            <?php echo htmlspecialchars($usuario['email']); ?>
                        </p>
                    </div>
                    
                    <nav>
                        <ul class="nav-menu">
                            <li><a href="?tab=pedidos" class="<?php echo $activeTab == 'pedidos' ? 'active' : ''; ?>">
                                <i class="fas fa-shopping-bag"></i> Meus Pedidos
                            </a></li>
                            <li><a href="?tab=perfil" class="<?php echo $activeTab == 'perfil' ? 'active' : ''; ?>">
                                <i class="fas fa-user"></i> Perfil
                            </a></li>
                            <li><a href="?tab=enderecos" class="<?php echo $activeTab == 'enderecos' ? 'active' : ''; ?>">
                                <i class="fas fa-map-marker-alt"></i> Endereços
                            </a></li>
                            <li><a href="?tab=senha" class="<?php echo $activeTab == 'senha' ? 'active' : ''; ?>">
                                <i class="fas fa-lock"></i> Alterar Senha
                            </a></li>
                        </ul>
                    </nav>
                </aside>
                
                <!-- Content -->
                <main class="content">
                    <!-- Pedidos -->
                    <div class="tab-content <?php echo $activeTab == 'pedidos' ? 'active' : ''; ?>" id="pedidos">
                        <div class="content-header">
                            <h1 class="content-title">
                                <i class="fas fa-shopping-bag"></i> Meus Pedidos
                            </h1>
                        </div>
                        
                        <?php if (empty($pedidos)): ?>
                            <div style="text-align: center; color: #666; padding: 40px;">
                                <i class="fas fa-shopping-cart" style="font-size: 64px; margin-bottom: 20px;"></i>
                                <h3>Você ainda não fez nenhum pedido</h3>
                                <p>Explore nossos produtos e faça seu primeiro pedido!</p>
                                <a href="produtos.php" class="btn" style="margin-top: 20px;">
                                    <i class="fas fa-search"></i> Ver Produtos
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="orders-grid">
                                <?php foreach ($pedidos as $pedido): ?>
                                    <div class="order-card">
                                        <div class="order-header">
                                            <div class="order-number">
                                                #<?php echo htmlspecialchars($pedido['order_number']); ?>
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
                                        
                                        <div class="order-info">
                                            <div>
                                                <strong>Data:</strong><br>
                                                <?php echo date('d/m/Y H:i', strtotime($pedido['created_at'])); ?>
                                            </div>
                                            <div>
                                                <strong>Total:</strong><br>
                                                <?php echo formatPrice($pedido['total']); ?>
                                            </div>
                                            <div>
                                                <strong>Itens:</strong><br>
                                                <?php echo $pedido['total_items']; ?> produto(s)
                                            </div>
                                        </div>
                                        
                                        <div style="margin-top: 15px; text-align: right;">
                                            <a href="pedido.php?id=<?php echo $pedido['id']; ?>" class="btn btn-outline">
                                                <i class="fas fa-eye"></i> Ver Detalhes
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Perfil -->
                    <div class="tab-content <?php echo $activeTab == 'perfil' ? 'active' : ''; ?>" id="perfil">
                        <div class="content-header">
                            <h1 class="content-title">
                                <i class="fas fa-user"></i> Meu Perfil
                            </h1>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="nome">Nome *</label>
                                    <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($usuario['nome']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="sobrenome">Sobrenome *</label>
                                    <input type="text" id="sobrenome" name="sobrenome" value="<?php echo htmlspecialchars($usuario['sobrenome']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">E-mail</label>
                                <input type="email" id="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" disabled style="opacity: 0.7;">
                                <small style="color: #666;">O e-mail não pode ser alterado. Entre em contato conosco se necessário.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="telefone">Telefone</label>
                                <input type="tel" id="telefone" name="telefone" value="<?php echo htmlspecialchars($usuario['telefone']); ?>" placeholder="+81-XX-XXXX-XXXX">
                            </div>
                            
                            <button type="submit" class="btn">
                                <i class="fas fa-save"></i> Salvar Alterações
                            </button>
                        </form>
                    </div>
                    
                    <!-- Endereços -->
                    <div class="tab-content <?php echo $activeTab == 'enderecos' ? 'active' : ''; ?>" id="enderecos">
                        <div class="content-header">
                            <h1 class="content-title">
                                <i class="fas fa-map-marker-alt"></i> Meus Endereços
                            </h1>
                            <button type="button" class="btn" onclick="openAddressModal()">
                                <i class="fas fa-plus"></i> Adicionar Endereço
                            </button>
                        </div>
                        
                        <?php if (empty($enderecos)): ?>
                            <div style="text-align: center; color: #666; padding: 40px;">
                                <i class="fas fa-map-marker-alt" style="font-size: 64px; margin-bottom: 20px;"></i>
                                <h3>Nenhum endereço cadastrado</h3>
                                <p>Adicione endereços para facilitar suas compras!</p>
                            </div>
                        <?php else: ?>
                            <div class="addresses-grid">
                                <?php foreach ($enderecos as $endereco): ?>
                                    <div class="address-card <?php echo $endereco['is_default'] ? 'default' : ''; ?>">
                                        <div class="address-header">
                                            <div class="address-name"><?php echo htmlspecialchars($endereco['nome']); ?></div>
                                            <?php if ($endereco['is_default']): ?>
                                                <div class="default-badge">Padrão</div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div style="color: #ccc; line-height: 1.4;">
                                            <?php echo htmlspecialchars($endereco['endereco']); ?><br>
                                            <?php if ($endereco['complemento']): ?>
                                                <?php echo htmlspecialchars($endereco['complemento']); ?><br>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($endereco['cidade']); ?>, <?php echo htmlspecialchars($endereco['estado']); ?><br>
                                            <?php if ($endereco['cep']): ?>
                                                CEP: <?php echo htmlspecialchars($endereco['cep']); ?><br>
                                            <?php endif; ?>
                                            <?php if ($endereco['telefone']): ?>
                                                Tel: <?php echo htmlspecialchars($endereco['telefone']); ?>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="address-actions">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_address">
                                                <input type="hidden" name="address_id" value="<?php echo $endereco['id']; ?>">
                                                <button type="submit" class="btn btn-danger" onclick="return confirm('Remover este endereço?')">
                                                    <i class="fas fa-trash"></i> Remover
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Alterar Senha -->
                    <div class="tab-content <?php echo $activeTab == 'senha' ? 'active' : ''; ?>" id="senha">
                        <div class="content-header">
                            <h1 class="content-title">
                                <i class="fas fa-lock"></i> Alterar Senha
                            </h1>
                        </div>
                        
                        <form method="POST" style="max-width: 400px;">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="form-group">
                                <label for="current_password">Senha Atual *</label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">Nova Senha *</label>
                                <input type="password" id="new_password" name="new_password" required minlength="6">
                                <small style="color: #666;">Mínimo 6 caracteres</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirmar Nova Senha *</label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" class="btn">
                                <i class="fas fa-key"></i> Alterar Senha
                            </button>
                        </form>
                    </div>
                </main>
            </div>
        </div>
    </main>

    <!-- Modal Adicionar Endereço -->
    <div class="modal" id="addressModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Adicionar Endereço</h2>
                <button type="button" class="close-modal" onclick="closeAddressModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="add_address">
                
                <div class="form-group">
                    <label for="modal_nome">Nome do Endereço *</label>
                    <input type="text" id="modal_nome" name="nome" placeholder="Ex: Casa, Trabalho, etc." required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="modal_cep">CEP</label>
                        <input type="text" id="modal_cep" name="cep" placeholder="123-4567">
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_estado">Estado</label>
                        <input type="text" id="modal_estado" name="estado" placeholder="Shizuoka">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="modal_cidade">Cidade *</label>
                    <input type="text" id="modal_cidade" name="cidade" placeholder="Hamamatsu" required>
                </div>
                
                <div class="form-group">
                    <label for="modal_endereco">Endereço *</label>
                    <input type="text" id="modal_endereco" name="endereco" placeholder="Rua, número, bairro" required>
                </div>
                
                <div class="form-group">
                    <label for="modal_complemento">Complemento</label>
                    <input type="text" id="modal_complemento" name="complemento" placeholder="Apartamento, casa, etc.">
                </div>
                
                <div class="form-group">
                    <label for="modal_telefone">Telefone</label>
                    <input type="tel" id="modal_telefone" name="telefone" placeholder="+81-XX-XXXX-XXXX">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_default" style="width: auto; margin-right: 10px;">
                        Definir como endereço padrão
                    </label>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-outline" onclick="closeAddressModal()">
                        Cancelar
                    </button>
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddressModal() {
            document.getElementById('addressModal').style.display = 'block';
        }
        
        function closeAddressModal() {
            document.getElementById('addressModal').style.display = 'none';
        }
        
        // Fechar modal clicando fora
        window.onclick = function(event) {
            const modal = document.getElementById('addressModal');
            if (event.target === modal) {
                closeAddressModal();
            }
        }
        
        // Validação de senha
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && newPassword !== confirmPassword) {
                this.setCustomValidity('Senhas não coincidem');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>