<?php
require_once 'config.php';

$productId = (int)($_GET['id'] ?? 0);

if (!$productId) {
    flashMessage('Produto não encontrado.', 'error');
    redirect('produtos.php');
}

// Buscar dados do produto
$stmt = $db->prepare("
    SELECT p.*, c.nome as categoria_nome, c.id as categoria_id 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.id = ? AND p.ativo = 1
");
$stmt->execute([$productId]);
$produto = $stmt->fetch();

if (!$produto) {
    flashMessage('Produto não encontrado ou indisponível.', 'error');
    redirect('produtos.php');
}

// Atualizar views do produto
$stmt = $db->prepare("UPDATE products SET views = views + 1 WHERE id = ?");
$stmt->execute([$productId]);

// Buscar produtos relacionados da mesma categoria
$stmt = $db->prepare("
    SELECT * FROM products 
    WHERE category_id = ? AND id != ? AND ativo = 1 
    ORDER BY RAND() 
    LIMIT 4
");
$stmt->execute([$produto['categoria_id'], $productId]);
$produtosRelacionados = $stmt->fetchAll();

// Buscar avaliações do produto
$stmt = $db->prepare("
    SELECT r.*, u.name as user_name 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.product_id = ? AND r.aprovado = 1 
    ORDER BY r.created_at DESC 
    LIMIT 10
");
$stmt->execute([$productId]);
$avaliacoes = $stmt->fetchAll();

// Calcular média das avaliações
$stmt = $db->prepare("
    SELECT AVG(rating) as media, COUNT(*) as total 
    FROM reviews 
    WHERE product_id = ? AND aprovado = 1
");
$stmt->execute([$productId]);
$avaliacaoStats = $stmt->fetch();

$mediaAvaliacoes = $avaliacaoStats['media'] ? round($avaliacaoStats['media'], 1) : 0;
$totalAvaliacoes = $avaliacaoStats['total'];

// Verificar se usuário já avaliou (se logado)
$jaAvaliou = false;
if (isLoggedIn()) {
    $stmt = $db->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ?");
    $stmt->execute([$productId, $_SESSION['user_id']]);
    $jaAvaliou = $stmt->fetch() ? true : false;
}

// Processar adição ao carrinho via AJAX ou form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    if (!isLoggedIn()) {
        flashMessage('Faça login para adicionar produtos ao carrinho.', 'warning');
        redirect('login.php');
    }
    
    $quantidade = max(1, (int)($_POST['quantidade'] ?? 1));
    $observacoes = sanitize($_POST['observacoes'] ?? '');
    
    try {
        // Verificar se já existe no carrinho
        $stmt = $db->prepare("SELECT id, quantidade FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$_SESSION['user_id'], $productId]);
        $cartItem = $stmt->fetch();
        
        if ($cartItem) {
            // Atualizar quantidade
            $novaQuantidade = $cartItem['quantidade'] + $quantidade;
            $stmt = $db->prepare("UPDATE cart SET quantidade = ?, observacoes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$novaQuantidade, $observacoes, $cartItem['id']]);
            $message = 'Quantidade atualizada no carrinho!';
        } else {
            // Adicionar novo item
            $stmt = $db->prepare("INSERT INTO cart (user_id, product_id, quantidade, observacoes) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $productId, $quantidade, $observacoes]);
            $message = 'Produto adicionado ao carrinho!';
        }
        
        logActivity('cart_add', "Produto {$produto['nome']} adicionado ao carrinho");
        flashMessage($message, 'success');
        
        // Se for requisição AJAX, retornar JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
        }
        
    } catch (Exception $e) {
        error_log('Add to Cart Error: ' . $e->getMessage());
        $errorMessage = 'Erro ao adicionar produto ao carrinho.';
        flashMessage($errorMessage, 'error');
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errorMessage]);
            exit;
        }
    }
}

// Preço final do produto
$precoFinal = $produto['preco_promocional'] ?: $produto['preco'];
$temPromocao = !empty($produto['preco_promocional']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($produto['nome']); ?> - Gourmeria</title>
    <meta name="description" content="<?php echo htmlspecialchars($produto['descricao_curta'] ?: $produto['descricao']); ?>">
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
        
        .header-nav a:hover {
            color: var(--primary-gold);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Breadcrumb */
        .breadcrumb {
            padding: 20px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .breadcrumb-list {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        
        .breadcrumb-list a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .breadcrumb-list a:hover {
            color: var(--primary-gold);
        }
        
        .breadcrumb-separator {
            color: var(--text-muted);
        }
        
        .breadcrumb-current {
            color: var(--primary-gold);
            font-weight: 500;
        }
        
        /* Product Section */
        .product-section {
            padding: 40px 0;
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            margin-bottom: 60px;
        }
        
        /* Product Images */
        .product-images {
            position: relative;
        }
        
        .main-image {
            width: 100%;
            height: 500px;
            background: var(--surface-dark);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            position: relative;
            margin-bottom: 20px;
        }
        
        .main-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .main-image:hover img {
            transform: scale(1.05);
        }
        
        .image-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-muted);
        }
        
        .image-placeholder i {
            font-size: 80px;
            opacity: 0.3;
        }
        
        .product-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--gradient-primary);
            color: var(--background-dark);
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Product Info */
        .product-info {
            position: relative;
        }
        
        .product-category {
            color: var(--primary-gold);
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
        }
        
        .product-title {
            font-size: 36px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 20px;
            line-height: 1.2;
        }
        
        .product-rating {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stars {
            display: flex;
            gap: 3px;
        }
        
        .star {
            color: var(--text-muted);
            font-size: 18px;
        }
        
        .star.filled {
            color: var(--warning-color);
        }
        
        .rating-text {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .product-price {
            margin-bottom: 30px;
        }
        
        .current-price {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-gold);
        }
        
        .old-price {
            font-size: 24px;
            color: var(--text-muted);
            text-decoration: line-through;
            margin-right: 15px;
        }
        
        .discount-badge {
            background: var(--success-color);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
            margin-left: 10px;
        }
        
        .product-description {
            color: var(--text-secondary);
            font-size: 16px;
            line-height: 1.7;
            margin-bottom: 30px;
        }
        
        .product-details {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .details-title {
            color: var(--primary-gold);
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .details-list {
            list-style: none;
        }
        
        .details-list li {
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
        }
        
        .details-list li:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .detail-value {
            color: var(--text-primary);
        }
        
        /* Add to Cart Form */
        .add-to-cart-form {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            padding: 30px;
        }
        
        .form-title {
            color: var(--primary-gold);
            font-weight: 600;
            margin-bottom: 20px;
            font-size: 18px;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .quantity-label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            background: var(--background-dark);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .qty-btn {
            background: none;
            border: none;
            color: var(--text-primary);
            padding: 10px 15px;
            cursor: pointer;
            transition: all 0.3s ease;
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
            width: 60px;
            padding: 10px 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .observations {
            margin-bottom: 25px;
        }
        
        .observations label {
            display: block;
            color: var(--text-secondary);
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .observations textarea {
            width: 100%;
            background: var(--background-dark);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-primary);
            padding: 12px 15px;
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
        }
        
        .observations textarea:focus {
            outline: none;
            border-color: var(--primary-gold);
        }
        
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px 30px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            width: 100%;
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
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Product Tabs */
        .product-tabs {
            margin-top: 60px;
        }
        
        .tab-buttons {
            display: flex;
            gap: 0;
            margin-bottom: 30px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .tab-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            padding: 15px 25px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }
        
        .tab-btn.active {
            color: var(--primary-gold);
            border-bottom-color: var(--primary-gold);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Reviews */
        .reviews-section {
            margin-top: 30px;
        }
        
        .reviews-summary {
            background: var(--surface-dark);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .average-rating {
            font-size: 48px;
            font-weight: 700;
            color: var(--primary-gold);
            margin-bottom: 10px;
        }
        
        .reviews-list {
            display: grid;
            gap: 20px;
        }
        
        .review-item {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            padding: 25px;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .reviewer-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .review-date {
            color: var(--text-muted);
            font-size: 14px;
        }
        
        .review-rating {
            margin-bottom: 15px;
        }
        
        .review-comment {
            color: var(--text-secondary);
            line-height: 1.6;
        }
        
        /* Related Products */
        .related-section {
            background: var(--surface-dark);
            padding: 60px 0;
            margin-top: 80px;
            border-radius: 20px;
        }
        
        .section-title {
            text-align: center;
            font-size: 32px;
            color: var(--primary-gold);
            margin-bottom: 40px;
        }
        
        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }
        
        .related-product {
            background: var(--background-dark);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--text-primary);
        }
        
        .related-product:hover {
            border-color: var(--primary-gold);
            transform: translateY(-5px);
            color: var(--text-primary);
        }
        
        .related-image {
            height: 150px;
            background: var(--surface-light);
            overflow: hidden;
        }
        
        .related-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .related-info {
            padding: 20px;
        }
        
        .related-title {
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--primary-gold);
        }
        
        .related-price {
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
            border: 1px solid #dc3545;
            color: #dc3545;
        }
        
        .flash-warning {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid var(--warning-color);
            color: var(--warning-color);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .product-grid {
                grid-template-columns: 1fr;
                gap: 40px;
            }
            
            .main-image {
                height: 300px;
            }
            
            .product-title {
                font-size: 28px;
            }
            
            .current-price {
                font-size: 28px;
            }
            
            .tab-buttons {
                flex-wrap: wrap;
            }
            
            .related-grid {
                grid-template-columns: 1fr;
            }
            
            .header-nav {
                display: none;
            }
            
            .add-to-cart-form {
                position: sticky;
                bottom: 20px;
                z-index: 50;
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
                <a href="carrinho.php"><i class="fas fa-shopping-cart"></i></a>
            </nav>
        </div>
    </header>

    <!-- Breadcrumb -->
    <section class="breadcrumb">
        <div class="container">
            <div class="breadcrumb-list">
                <a href="index.php"><i class="fas fa-home"></i> Início</a>
                <span class="breadcrumb-separator">/</span>
                <a href="categorias.php">Categorias</a>
                <span class="breadcrumb-separator">/</span>
                <a href="categoria.php?id=<?php echo $produto['categoria_id']; ?>"><?php echo htmlspecialchars($produto['categoria_nome']); ?></a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-current"><?php echo htmlspecialchars($produto['nome']); ?></span>
            </div>
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
                <?php else: ?>
                    <i class="fas fa-exclamation-circle"></i>
                <?php endif; ?>
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Product Section -->
    <section class="product-section">
        <div class="container">
            <div class="product-grid">
                <!-- Product Images -->
                <div class="product-images">
                    <div class="main-image">
                        <?php if ($produto['imagem_principal']): ?>
                            <img src="<?php echo UPLOAD_DIR . $produto['imagem_principal']; ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>">
                        <?php else: ?>
                            <div class="image-placeholder">
                                <i class="fas fa-image"></i>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($produto['featured']): ?>
                            <div class="product-badge">Destaque</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Product Info -->
                <div class="product-info">
                    <div class="product-category"><?php echo htmlspecialchars($produto['categoria_nome']); ?></div>
                    
                    <h1 class="product-title"><?php echo htmlspecialchars($produto['nome']); ?></h1>
                    
                    <?php if ($totalAvaliacoes > 0): ?>
                        <div class="product-rating">
                            <div class="stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star star <?php echo $i <= $mediaAvaliacoes ? 'filled' : ''; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="rating-text"><?php echo $mediaAvaliacoes; ?> / 5 (<?php echo $totalAvaliacoes; ?> avaliações)</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="product-price">
                        <?php if ($temPromocao): ?>
                            <span class="old-price"><?php echo formatPrice($produto['preco']); ?></span>
                            <span class="current-price"><?php echo formatPrice($produto['preco_promocional']); ?></span>
                            <?php 
                            $desconto = round((($produto['preco'] - $produto['preco_promocional']) / $produto['preco']) * 100);
                            ?>
                            <span class="discount-badge">-<?php echo $desconto; ?>%</span>
                        <?php else: ?>
                            <span class="current-price"><?php echo formatPrice($produto['preco']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($produto['descricao_curta']): ?>
                        <div class="product-description">
                            <?php echo nl2br(htmlspecialchars($produto['descricao_curta'])); ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Product Details -->
                    <div class="product-details">
                        <h3 class="details-title"><i class="fas fa-info-circle"></i> Detalhes do Produto</h3>
                        <ul class="details-list">
                            <?php if ($produto['peso']): ?>
                                <li>
                                    <span class="detail-label">Peso:</span>
                                    <span class="detail-value"><?php echo $produto['peso']; ?>g</span>
                                </li>
                            <?php endif; ?>
                            <?php if ($produto['dimensoes']): ?>
                                <li>
                                    <span class="detail-label">Dimensões:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($produto['dimensoes']); ?></span>
                                </li>
                            <?php endif; ?>
                            <?php if ($produto['validade']): ?>
                                <li>
                                    <span class="detail-label">Validade:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($produto['validade']); ?></span>
                                </li>
                            <?php endif; ?>
                            <li>
                                <span class="detail-label">Categoria:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($produto['categoria_nome']); ?></span>
                            </li>
                            <li>
                                <span class="detail-label">Código:</span>
                                <span class="detail-value">#<?php echo str_pad($produto['id'], 4, '0', STR_PAD_LEFT); ?></span>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Add to Cart Form -->
                    <div class="add-to-cart-form">
                        <h3 class="form-title"><i class="fas fa-shopping-cart"></i> Adicionar ao Carrinho</h3>
                        
                        <form method="POST" id="addToCartForm">
                            <input type="hidden" name="add_to_cart" value="1">
                            
                            <div class="quantity-selector">
                                <span class="quantity-label">Quantidade:</span>
                                <div class="quantity-controls">
                                    <button type="button" class="qty-btn" onclick="changeQuantity(-1)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" name="quantidade" id="quantidade" value="1" min="1" max="99" class="qty-input">
                                    <button type="button" class="qty-btn" onclick="changeQuantity(1)">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="observations">
                                <label for="observacoes">Observações (opcional):</label>
                                <textarea name="observacoes" id="observacoes" placeholder="Alguma observação especial sobre o produto..."></textarea>
                            </div>
                            
                            <?php if (isLoggedIn()): ?>
                                <button type="submit" class="btn btn-primary" id="addToCartBtn">
                                    <i class="fas fa-cart-plus"></i>
                                    <span>Adicionar ao Carrinho</span>
                                </button>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-outline">
                                    <i class="fas fa-sign-in-alt"></i>
                                    Faça Login para Comprar
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Product Tabs -->
            <div class="product-tabs">
                <div class="tab-buttons">
                    <button class="tab-btn active" onclick="showTab('description')">Descrição</button>
                    <?php if ($produto['ingredientes']): ?>
                        <button class="tab-btn" onclick="showTab('ingredients')">Ingredientes</button>
                    <?php endif; ?>
                    <button class="tab-btn" onclick="showTab('reviews')">Avaliações (<?php echo $totalAvaliacoes; ?>)</button>
                </div>
                
                <div id="description" class="tab-content active">
                    <div style="background: var(--surface-dark); padding: 30px; border-radius: 15px;">
                        <?php if ($produto['descricao']): ?>
                            <?php echo nl2br(htmlspecialchars($produto['descricao'])); ?>
                        <?php else: ?>
                            <p style="color: var(--text-secondary); font-style: italic;">Descrição detalhada não disponível.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($produto['ingredientes']): ?>
                    <div id="ingredients" class="tab-content">
                        <div style="background: var(--surface-dark); padding: 30px; border-radius: 15px;">
                            <h3 style="color: var(--primary-gold); margin-bottom: 15px;">Ingredientes:</h3>
                            <?php echo nl2br(htmlspecialchars($produto['ingredientes'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div id="reviews" class="tab-content">
                    <div class="reviews-section">
                        <?php if ($totalAvaliacoes > 0): ?>
                            <div class="reviews-summary">
                                <div class="average-rating"><?php echo $mediaAvaliacoes; ?></div>
                                <div class="stars" style="justify-content: center; margin-bottom: 10px;">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star star <?php echo $i <= $mediaAvaliacoes ? 'filled' : ''; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <p style="color: var(--text-secondary);">Baseado em <?php echo $totalAvaliacoes; ?> avaliações</p>
                            </div>
                            
                            <div class="reviews-list">
                                <?php foreach ($avaliacoes as $avaliacao): ?>
                                    <div class="review-item">
                                        <div class="review-header">
                                            <span class="reviewer-name"><?php echo htmlspecialchars($avaliacao['user_name']); ?></span>
                                            <span class="review-date"><?php echo date('d/m/Y', strtotime($avaliacao['created_at'])); ?></span>
                                        </div>
                                        <div class="review-rating">
                                            <div class="stars">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star star <?php echo $i <= $avaliacao['rating'] ? 'filled' : ''; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <?php if ($avaliacao['titulo']): ?>
                                            <h4 style="color: var(--primary-gold); margin-bottom: 10px;"><?php echo htmlspecialchars($avaliacao['titulo']); ?></h4>
                                        <?php endif; ?>
                                        <?php if ($avaliacao['comentario']): ?>
                                            <div class="review-comment"><?php echo nl2br(htmlspecialchars($avaliacao['comentario'])); ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                                <i class="fas fa-star" style="font-size: 48px; margin-bottom: 20px; opacity: 0.3;"></i>
                                <h3 style="margin-bottom: 10px; color: var(--text-secondary);">Nenhuma avaliação ainda</h3>
                                <p>Seja o primeiro a avaliar este produto!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Related Products -->
    <?php if (!empty($produtosRelacionados)): ?>
        <section class="related-section">
            <div class="container">
                <h2 class="section-title">Produtos Relacionados</h2>
                <div class="related-grid">
                    <?php foreach ($produtosRelacionados as $relacionado): ?>
                        <a href="produto.php?id=<?php echo $relacionado['id']; ?>" class="related-product">
                            <div class="related-image">
                                <?php if ($relacionado['imagem_principal']): ?>
                                    <img src="<?php echo UPLOAD_DIR . $relacionado['imagem_principal']; ?>" alt="<?php echo htmlspecialchars($relacionado['nome']); ?>">
                                <?php else: ?>
                                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: var(--text-muted);">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="related-info">
                                <h4 class="related-title"><?php echo htmlspecialchars($relacionado['nome']); ?></h4>
                                <div class="related-price">
                                    <?php echo formatPrice($relacionado['preco_promocional'] ?: $relacionado['preco']); ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <script>
        // Funções de quantidade
        function changeQuantity(delta) {
            const input = document.getElementById('quantidade');
            const currentValue = parseInt(input.value) || 1;
            const newValue = Math.max(1, Math.min(99, currentValue + delta));
            input.value = newValue;
        }
        
        // Tabs
        function showTab(tabName) {
            // Esconder todos os conteúdos
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remover active de todos os botões
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Mostrar conteúdo selecionado
            document.getElementById(tabName).classList.add('active');
            
            // Ativar botão selecionado
            event.target.classList.add('active');
        }
        
        // Form de adicionar ao carrinho
        document.getElementById('addToCartForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('addToCartBtn');
            const originalText = btn.innerHTML;
            
            // Mostrar loading
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adicionando...';
            
            // Enviar via AJAX
            const formData = new FormData(this);
            
            fetch('produto.php?id=<?php echo $productId; ?>', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mostrar sucesso
                    btn.innerHTML = '<i class="fas fa-check"></i> Adicionado!';
                    btn.style.background = 'var(--success-color)';
                    
                    // Resetar após 3 segundos
                    setTimeout(() => {
                        btn.innerHTML = originalText;
                        btn.style.background = '';
                        btn.disabled = false;
                    }, 3000);
                    
                    // Mostrar mensagem de sucesso
                    showToast(data.message, 'success');
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                btn.innerHTML = originalText;
                btn.disabled = false;
                showToast('Erro ao adicionar produto ao carrinho.', 'error');
            });
        });
        
        // Função para mostrar toast
        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `flash-message flash-${type}`;
            toast.style.position = 'fixed';
            toast.style.top = '20px';
            toast.style.right = '20px';
            toast.style.zIndex = '1000';
            toast.style.minWidth = '300px';
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
            `;
            
            document.body.appendChild(toast);
            
            // Remover após 5 segundos
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }
        
        // Auto-hide flash messages
        setTimeout(function() {
            const flashMessage = document.querySelector('.flash-message');
            if (flashMessage) {
                flashMessage.style.opacity = '0';
                setTimeout(() => flashMessage.remove(), 300);
            }
        }, 5000);
        
        // Animações
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
            
            const animatedElements = document.querySelectorAll('.related-product, .review-item');
            animatedElements.forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(30px)';
                el.style.transition = `all 0.6s ease ${index * 0.1}s`;
                observer.observe(el);
            });
        });
    </script>
</body>
</html>