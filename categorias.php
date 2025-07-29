<?php
require_once 'config.php';

// Buscar todas as categorias ativas
$stmt = $db->prepare("
    SELECT c.*, COUNT(p.id) as total_produtos 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id AND p.ativo = 1 
    WHERE c.ativo = 1 
    GROUP BY c.id 
    ORDER BY c.ordem ASC
");
$stmt->execute();
$categorias = $stmt->fetchAll();

// Buscar produtos em destaque por categoria
$produtosPorCategoria = [];
foreach ($categorias as $categoria) {
    $stmt = $db->prepare("
        SELECT * FROM products 
        WHERE category_id = ? AND ativo = 1 AND featured = 1 
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    $stmt->execute([$categoria['id']]);
    $produtosPorCategoria[$categoria['id']] = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorias - Gourmeria</title>
    <meta name="description" content="Explore nossas categorias de doces gourmet brasileiros: bolos, docinhos, tortas e muito mais!">
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
        
        /* Hero Section */
        .hero-section {
            background: var(--surface-dark);
            padding: 80px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 600"><defs><radialGradient id="gold" cx="50%" cy="50%"><stop offset="0%" stop-color="%23DAA520" stop-opacity="0.1"/><stop offset="100%" stop-color="%23DAA520" stop-opacity="0"/></radialGradient></defs><circle cx="300" cy="200" r="150" fill="url(%23gold)"/><circle cx="700" cy="400" r="200" fill="url(%23gold)"/></svg>');
            background-size: cover;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero-section h1 {
            font-size: 48px;
            font-weight: 700;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
        }
        
        .hero-section p {
            font-size: 20px;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Categories Grid */
        .categories-section {
            padding: 80px 0;
        }
        
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 40px;
        }
        
        .category-card {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.4s ease;
            position: relative;
        }
        
        .category-card:hover {
            border-color: var(--primary-gold);
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(218, 165, 32, 0.2);
        }
        
        .category-header {
            padding: 30px;
            text-align: center;
            background: linear-gradient(135deg, var(--surface-light) 0%, var(--surface-dark) 100%);
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }
        
        .category-icon {
            width: 80px;
            height: 80px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 36px;
            color: var(--background-dark);
        }
        
        .category-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-gold);
            margin-bottom: 10px;
        }
        
        .category-description {
            color: var(--text-secondary);
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .category-stats {
            display: flex;
            justify-content: center;
            gap: 20px;
            font-size: 14px;
            color: var(--text-muted);
        }
        
        .category-products {
            padding: 20px;
        }
        
        .products-preview {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .product-mini {
            aspect-ratio: 1;
            background: var(--surface-light);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .product-mini:hover {
            border-color: var(--primary-gold);
            transform: scale(1.05);
        }
        
        .product-mini img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-mini .placeholder {
            color: var(--text-muted);
            font-size: 24px;
        }
        
        .category-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            flex: 1;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--gradient-primary);
            color: var(--background-dark);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(218, 165, 32, 0.3);
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
        
        /* Stats Section */
        .stats-section {
            background: var(--surface-dark);
            padding: 60px 0;
            margin: 80px 0;
            border-radius: 20px;
            text-align: center;
        }
        
        .stats-title {
            font-size: 32px;
            color: var(--primary-gold);
            margin-bottom: 40px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
        }
        
        .stat-item {
            padding: 20px;
        }
        
        .stat-number {
            font-size: 42px;
            font-weight: 700;
            color: var(--primary-gold);
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 16px;
            font-weight: 500;
        }
        
        /* Featured Categories */
        .featured-section {
            background: var(--surface-dark);
            padding: 60px 0;
            margin-bottom: 80px;
            border-radius: 20px;
        }
        
        .featured-title {
            text-align: center;
            font-size: 32px;
            color: var(--primary-gold);
            margin-bottom: 40px;
        }
        
        .featured-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }
        
        .featured-card {
            background: var(--background-dark);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .featured-card:hover {
            border-color: var(--primary-gold);
            transform: translateY(-5px);
        }
        
        .featured-icon {
            font-size: 48px;
            color: var(--primary-gold);
            margin-bottom: 20px;
        }
        
        .featured-card h4 {
            color: var(--text-primary);
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .featured-card p {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.6;
        }
        
        /* Empty State */
        .empty-products {
            text-align: center;
            padding: 20px;
            color: var(--text-muted);
            font-style: italic;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-section h1 {
                font-size: 36px;
            }
            
            .categories-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .featured-grid {
                grid-template-columns: 1fr;
            }
            
            .header-nav {
                display: none;
            }
            
            .products-preview {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .category-actions {
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
                <a href="categorias.php" class="active">Categorias</a>
                <a href="sobre.php">Sobre</a>
                <a href="contato.php">Contato</a>
                <a href="carrinho.php"><i class="fas fa-shopping-cart"></i></a>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <h1>Nossas Categorias</h1>
                <p>Descubra nossa variedade de doces gourmet brasileiros, organizados por categoria para facilitar sua escolha.</p>
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="categories-section">
        <div class="container">
            <div class="categories-grid">
                <?php foreach ($categorias as $categoria): ?>
                    <div class="category-card">
                        <div class="category-header">
                            <div class="category-icon">
                                <i class="fas fa-cookie-bite"></i>
                            </div>
                            <h3 class="category-title"><?php echo htmlspecialchars($categoria['nome']); ?></h3>
                            <p class="category-description"><?php echo htmlspecialchars($categoria['descricao']); ?></p>
                            <div class="category-stats">
                                <span><i class="fas fa-box"></i> <?php echo $categoria['total_produtos']; ?> produtos</span>
                            </div>
                        </div>
                        
                        <div class="category-products">
                            <?php if (!empty($produtosPorCategoria[$categoria['id']])): ?>
                                <div class="products-preview">
                                    <?php foreach (array_slice($produtosPorCategoria[$categoria['id']], 0, 3) as $produto): ?>
                                        <div class="product-mini">
                                            <?php if ($produto['imagem_principal']): ?>
                                                <img src="<?php echo UPLOAD_DIR . $produto['imagem_principal']; ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>">
                                            <?php else: ?>
                                                <i class="fas fa-image placeholder"></i>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <?php for ($i = count($produtosPorCategoria[$categoria['id']]); $i < 3; $i++): ?>
                                        <div class="product-mini">
                                            <i class="fas fa-plus placeholder"></i>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-products">
                                    <i class="fas fa-box-open" style="font-size: 24px; margin-bottom: 10px;"></i>
                                    <p>Produtos em breve...</p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="category-actions">
                                <a href="produtos.php?category=<?php echo $categoria['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-eye"></i>
                                    Ver Produtos
                                </a>
                                <?php if ($categoria['total_produtos'] > 0): ?>
                                    <a href="categoria.php?id=<?php echo $categoria['id']; ?>" class="btn btn-outline">
                                        <i class="fas fa-info"></i>
                                        Detalhes
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <h2 class="stats-title">Nossa Variedade</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($categorias); ?></div>
                    <div class="stat-label">Categorias</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo array_sum(array_column($categorias, 'total_produtos')); ?></div>
                    <div class="stat-label">Produtos Disponíveis</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">100%</div>
                    <div class="stat-label">Artesanal</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">Fresh</div>
                    <div class="stat-label">Feito Diariamente</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Categories -->
    <section class="featured-section">
        <div class="container">
            <h2 class="featured-title">Por que Escolher Nossos Doces?</h2>
            <div class="featured-grid">
                <div class="featured-card">
                    <div class="featured-icon">
                        <i class="fas fa-award"></i>
                    </div>
                    <h4>Qualidade Premium</h4>
                    <p>Ingredientes selecionados e técnicas artesanais garantem o sabor autêntico dos doces brasileiros.</p>
                </div>
                
                <div class="featured-card">
                    <div class="featured-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h4>Feito com Amor</h4>
                    <p>Cada doce é preparado com carinho especial para trazer o gostinho de casa para você.</p>
                </div>
                
                <div class="featured-card">
                    <div class="featured-icon">
                        <i class="fas fa-shipping-fast"></i>
                    </div>
                    <h4>Entrega Rápida</h4>
                    <p>Entregamos em todo o Japão via Yamato, mantendo a qualidade e frescor dos produtos.</p>
                </div>
                
                <div class="featured-card">
                    <div class="featured-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h4>Tradição Brasileira</h4>
                    <p>Receitas tradicionais passadas de geração em geração, adaptadas para o Japão.</p>
                </div>
            </div>
        </div>
    </section>

    <script>
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
            
            // Aplicar animação aos cards
            const animatedElements = document.querySelectorAll('.category-card, .featured-card');
            animatedElements.forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(30px)';
                el.style.transition = `all 0.6s ease ${index * 0.1}s`;
                observer.observe(el);
            });
        });
        
        // Contador animado para estatísticas
        function animateCounters() {
            const counters = document.querySelectorAll('.stat-number');
            counters.forEach(counter => {
                const text = counter.textContent;
                if (text === '100%' || text === 'Fresh') return;
                
                const target = parseInt(text);
                let current = 0;
                const increment = target / 30;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        counter.textContent = target;
                        clearInterval(timer);
                    } else {
                        counter.textContent = Math.floor(current);
                    }
                }, 50);
            });
        }
        
        // Iniciar contador quando a seção entrar na tela
        const statsObserver = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounters();
                    statsObserver.unobserve(entry.target);
                }
            });
        });
        
        const statsSection = document.querySelector('.stats-section');
        if (statsSection) {
            statsObserver.observe(statsSection);
        }
        
        // Hover effects nos mini produtos
        document.querySelectorAll('.product-mini').forEach(mini => {
            mini.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.1) rotate(2deg)';
            });
            
            mini.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1) rotate(0deg)';
            });
        });
    </script>
</body>
</html>