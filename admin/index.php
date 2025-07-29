<?php
// admin/index.php - Painel Administrativo Principal
require_once '../config.php';

// Verificar se é admin ou moderador
if (!isModerator()) {
    flashMessage('Acesso negado.', 'error');
    redirect('../index.php');
}

// Buscar estatísticas
$stats = [];

// Total de pedidos
$stmt = $db->prepare("SELECT COUNT(*) as total FROM orders");
$stmt->execute();
$stats['total_orders'] = $stmt->fetch()['total'];

// Pedidos hoje
$stmt = $db->prepare("SELECT COUNT(*) as total FROM orders WHERE DATE(created_at) = CURDATE()");
$stmt->execute();
$stats['orders_today'] = $stmt->fetch()['total'];

// Receita total
$stmt = $db->prepare("SELECT SUM(total) as total FROM orders WHERE status != 'cancelled'");
$stmt->execute();
$stats['total_revenue'] = $stmt->fetch()['total'] ?? 0;

// Receita do mês
$stmt = $db->prepare("SELECT SUM(total) as total FROM orders WHERE status != 'cancelled' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
$stmt->execute();
$stats['month_revenue'] = $stmt->fetch()['total'] ?? 0;

// Total de produtos
$stmt = $db->prepare("SELECT COUNT(*) as total FROM products WHERE ativo = 1");
$stmt->execute();
$stats['total_products'] = $stmt->fetch()['total'];

// Total de usuários
$stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'customer'");
$stmt->execute();
$stats['total_users'] = $stmt->fetch()['total'];

// Pedidos recentes
$stmt = $db->prepare("
    SELECT o.*, u.nome, u.sobrenome, u.email 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recent_orders = $stmt->fetchAll();

// Produtos com baixo estoque
$stmt = $db->prepare("
    SELECT * FROM products 
    WHERE ativo = 1 AND gerenciar_estoque = 1 AND estoque <= 5 
    ORDER BY estoque ASC 
    LIMIT 10
");
$stmt->execute();
$low_stock = $stmt->fetchAll();

// Vendas por mês (últimos 6 meses)
$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as orders,
        SUM(total) as revenue
    FROM orders 
    WHERE status != 'cancelled' 
    AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");
$stmt->execute();
$monthly_sales = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - Gourmeria</title>
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
        
        .admin-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }
        
        .sidebar {
            background: #111;
            border-right: 2px solid #DAA520;
            padding: 20px 0;
            position: fixed;
            width: 250px;
            height: 100vh;
            overflow-y: auto;
        }
        
        .logo {
            text-align: center;
            padding: 20px;
            border-bottom: 1px solid #333;
            margin-bottom: 20px;
        }
        
        .logo h1 {
            color: #DAA520;
            font-size: 24px;
        }
        
        .logo p {
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .nav-menu {
            list-style: none;
            padding: 0 20px;
        }
        
        .nav-menu li {
            margin-bottom: 5px;
        }
        
        .nav-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: #ccc;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .nav-menu a:hover,
        .nav-menu a.active {
            background: #DAA520;
            color: #000;
        }
        
        .nav-menu .nav-section {
            color: #DAA520;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 20px 0 10px 15px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 30px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #333;
        }
        
        .page-title {
            color: #DAA520;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            color: #ccc;
        }
        
        .user-info a {
            color: #DAA520;
            text-decoration: none;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: #111;
            border: 2px solid #333;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            transition: border-color 0.3s;
        }
        
        .stat-card:hover {
            border-color: #DAA520;
        }
        
        .stat-icon {
            font-size: 32px;
            color: #DAA520;
            margin-bottom: 15px;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #DAA520;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #ccc;
            font-size: 14px;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .panel {
            background: #111;
            border: 2px solid #333;
            border-radius: 10px;
            padding: 25px;
        }
        
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #333;
        }
        
        .panel-title {
            color: #DAA520;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 15px;
            background: #DAA520;
            color: #000;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn:hover {
            background: #B8860B;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid #DAA520;
            color: #DAA520;
        }
        
        .btn-outline:hover {
            background: #DAA520;
            color: #000;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #333;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-info h4 {
            color: #DAA520;
            margin-bottom: 5px;
        }
        
        .order-info p {
            color: #ccc;
            font-size: 13px;
        }
        
        .order-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
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
        
        .stock-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #333;
        }
        
        .stock-item:last-child {
            border-bottom: none;
        }
        
        .stock-info h4 {
            color: #DAA520;
            margin-bottom: 3px;
            font-size: 14px;
        }
        
        .stock-info p {
            color: #ccc;
            font-size: 12px;
        }
        
        .stock-level {
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 12px;
        }
        
        .stock-critical {
            background: #dc3545;
            color: #fff;
        }
        
        .stock-low {
            background: #ffc107;
            color: #000;
        }
        
        .chart-container {
            margin-top: 20px;
            height: 300px;
            background: #222;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .quick-action {
            background: #222;
            border: 2px solid #333;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            text-decoration: none;
            color: #ccc;
            transition: all 0.3s;
        }
        
        .quick-action:hover {
            border-color: #DAA520;
            color: #DAA520;
        }
        
        .quick-action i {
            font-size: 24px;
            margin-bottom: 10px;
            display: block;
        }
        
        .alert {
            background: #dc3545;
            color: #fff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert.warning {
            background: #ffc107;
            color: #000;
        }
        
        .alert.info {
            background: #17a2b8;
            color: #fff;
        }
        
        @media (max-width: 768px) {
            .admin-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                position: static;
                width: 100%;
                height: auto;
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <h1><i class="fas fa-gem"></i> Gourmeria</h1>
                <p>Painel Administrativo</p>
            </div>
            
            <nav>
                <ul class="nav-menu">
                    <li><a href="index.php" class="active">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a></li>
                    
                    <div class="nav-section">Vendas</div>
                    <li><a href="pedidos.php">
                        <i class="fas fa-shopping-bag"></i> Pedidos
                    </a></li>
                    <li><a href="cupons.php">
                        <i class="fas fa-tags"></i> Cupons
                    </a></li>
                    
                    <div class="nav-section">Produtos</div>
                    <li><a href="produtos.php">
                        <i class="fas fa-box"></i> Produtos
                    </a></li>
                    <li><a href="categorias.php">
                        <i class="fas fa-list"></i> Categorias
                    </a></li>
                    
                    <div class="nav-section">Usuários</div>
                    <li><a href="usuarios.php">
                        <i class="fas fa-users"></i> Clientes
                    </a></li>
                    <?php if (isAdmin()): ?>
                    <li><a href="admins.php">
                        <i class="fas fa-user-shield"></i> Administradores
                    </a></li>
                    <?php endif; ?>
                    
                    <div class="nav-section">Marketing</div>
                    <li><a href="newsletter.php">
                        <i class="fas fa-envelope"></i> Newsletter
                    </a></li>
                    
                    <div class="nav-section">Configurações</div>
                    <li><a href="configuracoes.php">
                        <i class="fas fa-cog"></i> Site
                    </a></li>
                    
                    <div class="nav-section">Sair</div>
                    <li><a href="../index.php">
                        <i class="fas fa-home"></i> Ver Site
                    </a></li>
                    <li><a href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a></li>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1 class="page-title">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </h1>
                <div class="user-info">
                    <span>Bem-vindo, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</span>
                    <a href="../minha-conta.php">Minha Conta</a>
                </div>
            </div>
            
            <?php if (!empty($low_stock)): ?>
                <div class="alert warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Atenção!</strong> Você tem <?php echo count($low_stock); ?> produto(s) com estoque baixo.
                        <a href="produtos.php?filter=low_stock" style="color: inherit; text-decoration: underline;">Ver produtos</a>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Estatísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['total_orders']); ?></div>
                    <div class="stat-label">Total de Pedidos</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['orders_today']); ?></div>
                    <div class="stat-label">Pedidos Hoje</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-yen-sign"></i>
                    </div>
                    <div class="stat-number"><?php echo formatPrice($stats['total_revenue']); ?></div>
                    <div class="stat-label">Receita Total</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-number"><?php echo formatPrice($stats['month_revenue']); ?></div>
                    <div class="stat-label">Receita do Mês</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['total_products']); ?></div>
                    <div class="stat-label">Produtos Ativos</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="stat-label">Clientes</div>
                </div>
            </div>
            
            <div class="content-grid">
                <!-- Pedidos Recentes -->
                <div class="panel">
                    <div class="panel-header">
                        <h2 class="panel-title">
                            <i class="fas fa-clock"></i> Pedidos Recentes
                        </h2>
                        <a href="pedidos.php" class="btn btn-outline">Ver Todos</a>
                    </div>
                    
                    <?php if (empty($recent_orders)): ?>
                        <p style="color: #666; text-align: center; padding: 20px;">
                            Nenhum pedido encontrado.
                        </p>
                    <?php else: ?>
                        <?php foreach ($recent_orders as $order): ?>
                            <div class="order-item">
                                <div class="order-info">
                                    <h4>#<?php echo htmlspecialchars($order['order_number']); ?></h4>
                                    <p>
                                        <?php echo htmlspecialchars($order['nome'] . ' ' . $order['sobrenome']); ?> •
                                        <?php echo formatPrice($order['total']); ?> •
                                        <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                                    </p>
                                </div>
                                <div class="order-status status-<?php echo $order['status']; ?>">
                                    <?php
                                    $statusLabels = [
                                        'pending' => 'Pendente',
                                        'processing' => 'Processando',
                                        'shipped' => 'Enviado',
                                        'delivered' => 'Entregue',
                                        'cancelled' => 'Cancelado'
                                    ];
                                    echo $statusLabels[$order['status']] ?? $order['status'];
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Sidebar Content -->
                <div>
                    <!-- Estoque Baixo -->
                    <?php if (!empty($low_stock)): ?>
                        <div class="panel" style="margin-bottom: 30px;">
                            <div class="panel-header">
                                <h2 class="panel-title">
                                    <i class="fas fa-exclamation-triangle"></i> Estoque Baixo
                                </h2>
                                <a href="produtos.php?filter=low_stock" class="btn btn-outline">Ver Todos</a>
                            </div>
                            
                            <?php foreach (array_slice($low_stock, 0, 5) as $product): ?>
                                <div class="stock-item">
                                    <div class="stock-info">
                                        <h4><?php echo htmlspecialchars($product['nome']); ?></h4>
                                        <p><?php echo formatPrice($product['preco']); ?></p>
                                    </div>
                                    <div class="stock-level <?php echo $product['estoque'] <= 2 ? 'stock-critical' : 'stock-low'; ?>">
                                        <?php echo $product['estoque']; ?> un.
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Ações Rápidas -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2 class="panel-title">
                                <i class="fas fa-bolt"></i> Ações Rápidas
                            </h2>
                        </div>
                        
                        <div class="quick-actions">
                            <a href="produtos.php?action=new" class="quick-action">
                                <i class="fas fa-plus"></i>
                                Novo Produto
                            </a>
                            
                            <a href="cupons.php?action=new" class="quick-action">
                                <i class="fas fa-tag"></i>
                                Novo Cupom
                            </a>
                            
                            <a href="categorias.php?action=new" class="quick-action">
                                <i class="fas fa-folder-plus"></i>
                                Nova Categoria
                            </a>
                            
                            <a href="newsletter.php?action=send" class="quick-action">
                                <i class="fas fa-paper-plane"></i>
                                Enviar Newsletter
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gráfico de Vendas -->
            <div class="panel" style="margin-top: 30px;">
                <div class="panel-header">
                    <h2 class="panel-title">
                        <i class="fas fa-chart-area"></i> Vendas dos Últimos 6 Meses
                    </h2>
                </div>
                
                <div class="chart-container">
                    <?php if (!empty($monthly_sales)): ?>
                        <div style="text-align: center; color: #ccc;">
                            <i class="fas fa-chart-line" style="font-size: 48px; margin-bottom: 15px;"></i>
                            <p>Gráfico de vendas seria implementado aqui com Chart.js ou similar</p>
                            <div style="margin-top: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px;">
                                <?php foreach ($monthly_sales as $sale): ?>
                                    <div style="background: #333; padding: 15px; border-radius: 8px;">
                                        <div style="color: #DAA520; font-weight: bold;">
                                            <?php echo date('M/Y', strtotime($sale['month'] . '-01')); ?>
                                        </div>
                                        <div style="font-size: 14px;">
                                            <?php echo $sale['orders']; ?> pedidos
                                        </div>
                                        <div style="font-size: 12px;">
                                            <?php echo formatPrice($sale['revenue']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; color: #666;">
                            <i class="fas fa-chart-line" style="font-size: 48px; margin-bottom: 15px;"></i>
                            <p>Sem dados de vendas para exibir</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto-refresh da página a cada 5 minutos
        setTimeout(function() {
            location.reload();
        }, 300000);
        
        // Destacar navegação ativa
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.nav-menu a');
            
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === currentPage || 
                    (currentPage === '' && link.getAttribute('href') === 'index.php')) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>