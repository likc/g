<?php
require_once 'config.php';

// Buscar produtos em destaque
$stmt = $db->prepare("
    SELECT p.*, c.nome as categoria_nome 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.ativo = 1 AND p.featured = 1 
    ORDER BY p.created_at DESC 
    LIMIT 8
");
$stmt->execute();
$produtosDestaque = $stmt->fetchAll();

// Buscar categorias ativas
$stmt = $db->prepare("SELECT * FROM categories WHERE ativo = 1 ORDER BY ordem ASC");
$stmt->execute();
$categorias = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getSiteSetting('site_name', 'Gourmeria'); ?> - Doces Gourmet para Brasileiros no Japão</title>
    <meta name="description" content="<?php echo getSiteSetting('site_description', 'Doces Gourmet para Brasileiros no Japão'); ?>">
    
    <!-- CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gold: #DAA520;
            --dark-gold: #B8860B;
            --light-gold: #F4E4A1;
            --background-dark: #0A0A0A;
            --surface-dark: #1A1A1A;
            --surface-light: #2A2A2A;
            --text-primary: #FFFFFF;
            --text-secondary: #CCCCCC;
            --text-muted: #999999;
            --border-color: #333333;
            --shadow-primary: 0 4px 20px rgba(218, 165, 32, 0.1);
            --shadow-hover: 0 8px 30px rgba(218, 165, 32, 0.2);
            --gradient-primary: linear-gradient(135deg, #DAA520 0%, #B8860B 100%);
            --gradient-dark: linear-gradient(135deg, #0A0A0A 0%, #1A1A1A 100%);
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
            overflow-x: hidden;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Scrollbar personalizada */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--surface-dark);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-gold);
            border-radius: 4px;
        }
        
        /* Header Moderno */
        header {
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .header-scroll {
            background: rgba(10, 10, 10, 0.98);
            box-shadow: var(--shadow-primary);
        }
        
        .header-top {
            background: var(--surface-dark);
            padding: 8px 0;
            font-size: 13px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .header-top .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-top .contact-info {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .header-top .delivery-info {
            font-weight: 500;
            color: var(--primary-gold);
        }
        
        .header-main {
            padding: 15px 0;
        }
        
        .header-main .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-gold);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }
        
        .logo:hover {
            transform: scale(1.05);
        }
        
        .logo i {
            font-size: 32px;
        }
        
        .nav-menu {
            display: flex;
            list-style: none;
            gap: 35px;
        }
        
        .nav-menu a {
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
            position: relative;
            transition: all 0.3s ease;
            padding: 5px 0;
        }
        
        .nav-menu a::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--gradient-primary);
            transition: width 0.3s ease;
        }
        
        .nav-menu a:hover::before {
            width: 100%;
        }
        
        .nav-menu a:hover {
            color: var(--primary-gold);
        }
        
        .header-actions {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .header-actions a {
            color: var(--text-primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            padding: 8px 12px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .header-actions a:hover {
            color: var(--primary-gold);
            background: rgba(218, 165, 32, 0.1);
        }
        
        .cart-count {
            background: var(--gradient-primary);
            color: var(--background-dark);
            border-radius: 50%;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 700;
            min-width: 20px;
            text-align: center;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        /* Menu Mobile */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 24px;
            cursor: pointer;
            padding: 8px;
        }
        
        /* Main Content */
        main {
            margin-top: 100px;
            min-height: calc(100vh - 100px);
        }
        
        /* Hero Section Moderna */
        .hero {
            background: var(--gradient-dark);
            position: relative;
            padding: 120px 0;
            text-align: center;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 600"><defs><radialGradient id="gold" cx="50%" cy="50%"><stop offset="0%" stop-color="%23DAA520" stop-opacity="0.1"/><stop offset="100%" stop-color="%23DAA520" stop-opacity="0"/></radialGradient></defs><circle cx="200" cy="150" r="100" fill="url(%23gold)"/><circle cx="800" cy="450" r="150" fill="url(%23gold)"/><circle cx="500" cy="300" r="200" fill="url(%23gold)"/></svg>');
            background-size: cover;
            opacity: 0.3;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero h1 {
            font-size: 56px;
            font-weight: 700;
            margin-bottom: 24px;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
        }
        
        .hero p {
            font-size: 20px;
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            color: var(--text-secondary);
            font-weight: 400;
        }
        
        .hero-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 16px 32px;
            background: var(--gradient-primary);
            color: var(--background-dark);
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            box-shadow: var(--shadow-primary);
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
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
        
        /* Sections */
        .section {
            padding: 100px 0;
        }
        
        .section-title {
            text-align: center;
            font-size: 42px;
            font-weight: 700;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
        }
        
        .section-subtitle {
            text-align: center;
            font-size: 18px;
            color: var(--text-secondary);
            margin-bottom: 60px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Categories Grid Moderna */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        
        .category-card {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            transition: all 0.4s ease;
            text-decoration: none;
            color: var(--text-primary);
            position: relative;
            overflow: hidden;
        }
        
        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--gradient-primary);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .category-card:hover::before {
            opacity: 0.1;
        }
        
        .category-card:hover {
            border-color: var(--primary-gold);
            transform: translateY(-10px);
            box-shadow: var(--shadow-hover);
            color: var(--text-primary);
        }
        
        .category-card i {
            font-size: 48px;
            color: var(--primary-gold);
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
        }
        
        .category-card h3 {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 15px;
            position: relative;
            z-index: 2;
        }
        
        .category-card p {
            color: var(--text-secondary);
            position: relative;
            z-index: 2;
        }
        
        /* Products Grid Moderna */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        
        .product-card {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.4s ease;
            position: relative;
        }
        
        .product-card:hover {
            border-color: var(--primary-gold);
            transform: translateY(-8px);
            box-shadow: var(--shadow-hover);
        }
        
        .product-image {
            height: 240px;
            background: var(--surface-light);
            position: relative;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .product-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--gradient-primary);
            color: var(--background-dark);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .product-info {
            padding: 25px;
        }
        
        .product-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--primary-gold);
            line-height: 1.3;
        }
        
        .product-description {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-price {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-gold);
            margin-bottom: 20px;
        }
        
        .product-price .old-price {
            text-decoration: line-through;
            color: var(--text-muted);
            font-size: 18px;
            margin-right: 10px;
        }
        
        .product-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-small {
            padding: 12px 20px;
            font-size: 14px;
            flex: 1;
            text-align: center;
            border-radius: 10px;
            font-weight: 600;
        }
        
        /* Newsletter Section */
        .newsletter-section {
            background: var(--surface-dark);
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            margin: 40px 0;
        }
        
        .newsletter-form {
            max-width: 500px;
            margin: 0 auto;
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .newsletter-form input {
            flex: 1;
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            background: var(--background-dark);
            color: var(--text-primary);
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .newsletter-form input:focus {
            outline: none;
            border-color: var(--primary-gold);
            box-shadow: 0 0 0 3px rgba(218, 165, 32, 0.1);
        }
        
        .newsletter-form input::placeholder {
            color: var(--text-muted);
        }
        
        /* Footer Moderno */
        footer {
            background: var(--surface-dark);
            border-top: 1px solid var(--border-color);
            padding: 60px 0 30px;
            margin-top: 100px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 50px;
            margin-bottom: 40px;
        }
        
        .footer-section h3 {
            color: var(--primary-gold);
            margin-bottom: 25px;
            font-size: 20px;
            font-weight: 600;
        }
        
        .footer-section p, .footer-section li {
            color: var(--text-secondary);
            margin-bottom: 12px;
            line-height: 1.6;
        }
        
        .footer-section ul {
            list-style: none;
        }
        
        .footer-section a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .footer-section a:hover {
            color: var(--primary-gold);
        }
        
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            background: var(--background-dark);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .social-links a:hover {
            background: var(--primary-gold);
            border-color: var(--primary-gold);
            color: var(--background-dark);
            transform: translateY(-3px);
        }
        
        .footer-bottom {
            border-top: 1px solid var(--border-color);
            padding-top: 30px;
            text-align: center;
            color: var(--text-muted);
        }
        
        /* Flash Messages */
        .flash-message {
            padding: 16px 24px;
            margin: 20px 0;
            border-radius: 12px;
            font-weight: 500;
            border-left: 4px solid;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { transform: translateX(-100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
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
        
        .flash-info {
            background: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
            border-color: #17a2b8;
        }
        
        .flash-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border-color: #ffc107;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }
            
            .header-top {
                display: none;
            }
            
            .header-main .container {
                flex-direction: row;
                justify-content: space-between;
            }
            
            .nav-menu {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--surface-dark);
                flex-direction: column;
                padding: 20px;
                gap: 15px;
                border-top: 1px solid var(--border-color);
            }
            
            .nav-menu.active {
                display: flex;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
            
            .header-actions {
                gap: 10px;
            }
            
            .header-actions a span {
                display: none;
            }
            
            .hero {
                padding: 80px 0;
            }
            
            .hero h1 {
                font-size: 36px;
            }
            
            .hero p {
                font-size: 16px;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
            
            .section {
                padding: 60px 0;
            }
            
            .section-title {
                font-size: 32px;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .categories-grid {
                grid-template-columns: 1fr;
            }
            
            .newsletter-form {
                flex-direction: column;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .social-links {
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .logo {
                font-size: 24px;
            }
            
            .hero h1 {
                font-size: 28px;
            }
            
            .section-title {
                font-size: 26px;
            }
            
            .newsletter-section {
                padding: 40px 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header id="header">
        <div class="header-top">
            <div class="container">
                <div class="contact-info">
                    <span><i class="fas fa-phone"></i> <?php echo getSiteSetting('telefone_contato', '+81-XX-XXXX-XXXX'); ?></span>
                    <span><i class="fas fa-envelope"></i> <?php echo getSiteSetting('email_contato', 'contato@gourmeria.jp'); ?></span>
                </div>
                <div class="delivery-info">
                    <i class="fas fa-shipping-fast"></i> Entrega para todo o Japão | Retirada em Hamamatsu
                </div>
            </div>
        </div>
        
        <div class="header-main">
            <div class="container">
                <a href="index.php" class="logo">
                    <i class="fas fa-gem"></i>
                    <span>Gourmeria</span>
                </a>
                
                <nav>
                    <ul class="nav-menu" id="navMenu">
                        <li><a href="index.php"><i class="fas fa-home"></i> Início</a></li>
                        <li><a href="produtos.php"><i class="fas fa-birthday-cake"></i> Produtos</a></li>
                        <li><a href="categorias.php"><i class="fas fa-th-large"></i> Categorias</a></li>
                        <li><a href="sobre.php"><i class="fas fa-info-circle"></i> Sobre</a></li>
                        <li><a href="contato.php"><i class="fas fa-envelope"></i> Contato</a></li>
                    </ul>
                </nav>
                
                <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="header-actions">
                    <?php if (isLoggedIn()): ?>
                        <a href="minha-conta.php">
                            <i class="fas fa-user"></i>
                            <span><?php echo $_SESSION['user_name']; ?></span>
                        </a>
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Sair</span>
                        </a>
                    <?php else: ?>
                        <a href="login.php">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Entrar</span>
                        </a>
                        <a href="register.php">
                            <i class="fas fa-user-plus"></i>
                            <span>Cadastrar</span>
                        </a>
                    <?php endif; ?>
                    
                    <a href="carrinho.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Carrinho</span>
                        <?php if (isLoggedIn()): ?>
                            <?php
                            $stmt = $db->prepare("SELECT SUM(quantidade) as total FROM cart WHERE user_id = ?");
                            $stmt->execute([$_SESSION['user_id']]);
                            $cartCount = $stmt->fetch()['total'] ?? 0;
                            if ($cartCount > 0):
                            ?>
                                <span class="cart-count"><?php echo $cartCount; ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </a>
                    
                    <?php if (isModerator()): ?>
                        <a href="admin/">
                            <i class="fas fa-cog"></i>
                            <span>Admin</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <?php 
        $flash = getFlashMessage();
        if ($flash):
        ?>
            <div class="container">
                <div class="flash-message flash-<?php echo $flash['type']; ?>">
                    <i class="fas fa-info-circle"></i>
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Hero Section -->
        <section class="hero">
            <div class="container">
                <div class="hero-content">
                    <h1>Doces Gourmet Brasileiros no Japão</h1>
                    <p>Sabores únicos e artesanais que trazem o melhor do Brasil para sua mesa no Japão. Qualidade premium com a tradição que você conhece e ama.</p>
                    <div class="hero-buttons">
                        <a href="produtos.php" class="btn">
                            <i class="fas fa-eye"></i> Ver Produtos
                        </a>
                        <a href="sobre.php" class="btn btn-outline">
                            <i class="fas fa-heart"></i> Sobre Nós
                        </a>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Categories Section -->
        <section class="section">
            <div class="container">
                <h2 class="section-title">Nossas Categorias</h2>
                <p class="section-subtitle">Explore nossa seleção especial de doces artesanais, feitos com ingredientes premium e muito carinho.</p>
                <div class="categories-grid">
                    <?php foreach ($categorias as $categoria): ?>
                        <a href="categoria.php?id=<?php echo $categoria['id']; ?>" class="category-card">
                            <i class="fas fa-cookie-bite"></i>
                            <h3><?php echo htmlspecialchars($categoria['nome']); ?></h3>
                            <p><?php echo htmlspecialchars($categoria['descricao']); ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        
        <!-- Featured Products -->
        <?php if (!empty($produtosDestaque)): ?>
        <section class="section">
            <div class="container">
                <h2 class="section-title">Produtos em Destaque</h2>
                <p class="section-subtitle">Nossos doces mais populares, escolhidos especialmente para você.</p>
                <div class="products-grid">
                    <?php foreach ($produtosDestaque as $produto): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <?php if ($produto['imagem_principal']): ?>
                                    <img src="<?php echo UPLOAD_DIR . $produto['imagem_principal']; ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>">
                                <?php else: ?>
                                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; background: var(--surface-light); color: var(--text-muted);">
                                        <i class="fas fa-image" style="font-size: 48px;"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="product-badge">Destaque</div>
                            </div>
                            <div class="product-info">
                                <h3 class="product-title"><?php echo htmlspecialchars($produto['nome']); ?></h3>
                                <p class="product-description"><?php echo htmlspecialchars($produto['descricao_curta']); ?></p>
                                <div class="product-price">
                                    <?php if ($produto['preco_promocional']): ?>
                                        <span class="old-price"><?php echo formatPrice($produto['preco']); ?></span>
                                        <?php echo formatPrice($produto['preco_promocional']); ?>
                                    <?php else: ?>
                                        <?php echo formatPrice($produto['preco']); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="product-actions">
                                    <a href="produto.php?id=<?php echo $produto['id']; ?>" class="btn btn-small btn-outline">
                                        <i class="fas fa-eye"></i> Ver Detalhes
                                    </a>
                                    <?php if (isLoggedIn()): ?>
                                        <a href="add-carrinho.php?id=<?php echo $produto['id']; ?>" class="btn btn-small">
                                            <i class="fas fa-plus"></i> Adicionar
                                        </a>
                                    <?php else: ?>
                                        <a href="login.php" class="btn btn-small">
                                            <i class="fas fa-sign-in-alt"></i> Login
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
        
        <!-- Newsletter Section -->
        <section class="section">
            <div class="container">
                <div class="newsletter-section">
                    <h2 class="section-title">Receba Nossas Novidades</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 0;">Seja o primeiro a saber sobre novos produtos e promoções exclusivas!</p>
                    <form action="newsletter.php" method="POST" class="newsletter-form">
                        <input type="email" name="email" placeholder="Digite seu melhor e-mail" required>
                        <button type="submit" class="btn">
                            <i class="fas fa-paper-plane"></i> Inscrever-se
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><i class="fas fa-gem"></i> Gourmeria</h3>
                    <p>Doces gourmet brasileiros com a qualidade e tradição que você merece, agora no Japão.</p>
                    <p><i class="fas fa-map-marker-alt"></i> Hamamatsu, Shizuoka, Japão</p>
                    <p><i class="fas fa-phone"></i> <?php echo getSiteSetting('telefone_contato'); ?></p>
                    <p><i class="fas fa-envelope"></i> <?php echo getSiteSetting('email_contato'); ?></p>
                </div>
                
                <div class="footer-section">
                    <h3>Links Rápidos</h3>
                    <ul>
                        <li><a href="produtos.php">Produtos</a></li>
                        <li><a href="categorias.php">Categorias</a></li>
                        <li><a href="sobre.php">Sobre Nós</a></li>
                        <li><a href="contato.php">Contato</a></li>
                        <li><a href="politica-privacidade.php">Política de Privacidade</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Entrega & Retirada</h3>
                    <ul>
                        <li><i class="fas fa-truck"></i> Todo o Japão via Yamato</li>
                        <li><i class="fas fa-store"></i> Retirada em Hamamatsu</li>
                        <li><i class="fas fa-gift"></i> Frete grátis acima de ¥<?php echo number_format(getSiteSetting('frete_gratuito_valor', 5000)); ?></li>
                        <li><i class="fas fa-clock"></i> <?php echo getSiteSetting('horario_funcionamento', '9:00 às 18:00'); ?></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Siga-nos</h3>
                    <p>Fique por dentro das novidades nas redes sociais!</p>
                    <div class="social-links">
                        <a href="#" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                        <a href="#" title="LINE"><i class="fab fa-line"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Gourmeria. Todos os direitos reservados. | Desenvolvido com <i class="fas fa-heart" style="color: var(--primary-gold);"></i> para brasileiros no Japão</p>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script>
        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.getElementById('header');
            if (window.scrollY > 100) {
                header.classList.add('header-scroll');
            } else {
                header.classList.remove('header-scroll');
            }
        });
        
        // Mobile menu toggle
        function toggleMobileMenu() {
            const navMenu = document.getElementById('navMenu');
            const toggleBtn = document.querySelector('.mobile-menu-toggle i');
            
            navMenu.classList.toggle('active');
            
            if (navMenu.classList.contains('active')) {
                toggleBtn.className = 'fas fa-times';
            } else {
                toggleBtn.className = 'fas fa-bars';
            }
        }
        
        // Auto-hide flash messages
        setTimeout(function() {
            const flashMessage = document.querySelector('.flash-message');
            if (flashMessage) {
                flashMessage.style.opacity = '0';
                flashMessage.style.transform = 'translateX(-100%)';
                setTimeout(() => flashMessage.remove(), 300);
            }
        }, 5000);
        
        // Newsletter form enhancement
        document.querySelector('.newsletter-form').addEventListener('submit', function(e) {
            const button = this.querySelector('button');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
            button.disabled = true;
            
            // Re-enable after 3 seconds (in case of redirect issues)
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 3000);
        });
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>