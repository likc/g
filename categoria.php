<?php
require_once 'config.php';

$categoryId = (int)($_GET['id'] ?? 0);

if (!$categoryId) {
    flashMessage('Categoria não encontrada.', 'error');
    redirect('categorias.php');
}

// Buscar dados da categoria
$stmt = $db->prepare("SELECT * FROM categories WHERE id = ? AND ativo = 1");
$stmt->execute([$categoryId]);
$categoria = $stmt->fetch();

if (!$categoria) {
    flashMessage('Categoria não encontrada.', 'error');
    redirect('categorias.php');
}

// Parâmetros de filtro e ordenação
$sort = sanitize($_GET['sort'] ?? 'name');
$order = sanitize($_GET['order'] ?? 'asc');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;

// Validar ordenação
$valid_sorts = ['name', 'price', 'created_at', 'views', 'vendas'];
$valid_orders = ['asc', 'desc'];
if (!in_array($sort, $valid_sorts)) $sort = 'name';
if (!in_array($order, $valid_orders)) $order = 'asc';

// Contar total de produtos da categoria
$stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ? AND ativo = 1");
$stmt->execute([$categoryId]);
$total_products = $stmt->fetchColumn();

// Calcular paginação
$total_pages = ceil($total_products / $per_page);
$offset = ($page - 1) * $per_page;

// Buscar produtos da categoria
$sql = "
    SELECT * FROM products 
    WHERE category_id = ? AND ativo = 1 
    ORDER BY $sort $order 
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute([$categoryId]);
$produtos = $stmt->fetchAll();

// Buscar produtos em destaque da categoria
$stmt = $db->prepare("
    SELECT * FROM products 
    WHERE category_id = ? AND ativo = 1 AND featured = 1 
    ORDER BY created_at DESC 
    LIMIT 4
");
$stmt->execute([$categoryId]);
$produtosDestaque = $stmt->fetchAll();

// Buscar outras categorias para sugestões
$stmt = $db->prepare("
    SELECT * FROM categories 
    WHERE id != ? AND ativo = 1 
    ORDER BY ordem ASC 
    LIMIT 3
");
$stmt->execute([$categoryId]);
$outrasCategories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($categoria['nome']); ?> - Gourmeria</title>
    <meta name="description" content="<?php echo htmlspecialchars($categoria['descricao']); ?> - Doces gourmet brasileiros no Japão.">
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
        
        /* Category Hero */
        .category-hero {
            background: var(--surface-dark);
            padding: 60px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .category-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 400"><defs><radialGradient id="gold" cx="50%" cy="50%"><stop offset="0%" stop-color="%23DAA520" stop-opacity="0.1"/><stop offset="100%" stop-color="%23DAA520" stop-opacity="0"/></radialGradient></defs><circle cx="200" cy="100" r="80" fill="url(%23gold)"/><circle cx="800" cy="300" r="100" fill="url(%23gold)"/></svg>');
            background-size: cover;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .category-icon {
            width: 100px;
            height: 100px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 48px;
            color: var(--background-dark);
        }
        
        .category-hero h1 {
            font-size: 42px;
            font-weight: 700;
            color: var(--primary-gold);
            margin-bottom: 20px;
        }
        
        .category-hero p {
            font-size: 18px;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto 30px;
        }
        
        .category-stats {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-top: 30px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-gold);
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 5px;
        }
        
        /* Filters */
        .filters-section {
            background: var(--surface-dark);
            border-bottom: 1px solid var(--border-color);
            padding: 20px 0;
            position: sticky;
            top: 80px;
            z-index: 90;
        }
        
        .filters-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }
        
        .results-info {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .sort-controls {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .sort-select {
            padding: 8px 12px;
            background: var(--background-dark);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
            cursor: pointer;
        }
        
        /* Products Grid */
        .products-section {
            padding: 40px 0;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .product-card {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.4s ease;
        }
        
        .product-card:hover {
            border-color: var(--primary-gold);
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(218, 165, 32, 0.2);
        }
        
        .product-image {
            height: 200px;
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
            transform: scale(1.1);
        }
        
        .product-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--gradient-primary);
            color: var(--background-dark);
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-gold);
            margin-bottom: 10px;
            line-height: 1.3;
        }
        
        .product-description {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-price {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-gold);
            margin-bottom: 15px;
        }
        
        .product-price .old-price {
            text-decoration: line-through;
            color: var(--text-muted);
            font-size: 16px;
            margin-right: 8px;
        }
        
        .product-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            flex: 1;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: var(--gradient-primary);
            color: var(--background-dark);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(218, 165, 32, 0.3);
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
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 40px 0;
        }
        
        .pagination a,
        .pagination span {
            padding: 10px 15px;
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .pagination a:hover {
            border-color: var(--primary-gold);
            color: var(--primary-gold);
        }
        
        .pagination .current {
            background: var(--primary-gold);
            color: var(--background-dark);
            border-color: var(--primary-gold);
        }
        
        /* Related Categories */
        .related-section {
            background: var(--surface-dark);
            padding: 60px 0;
            margin-top: 80px;
        }
        
        .section-title {
            text-align: center;
            font-size: 28px;
            color: var(--primary-gold);
            margin-bottom: 40px;
        }
        
        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }
        
        .related-card {
            background: var(--background-dark);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            padding: 30px 20px;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--text-primary);
        }
        
        .related-card:hover {
            border-color: var(--primary-gold);
            transform: translateY(-5px);
            color: var(--text-primary);
        }
        
        .related-card i {
            font-size: 36px;
            color: var(--primary-gold);
            margin-bottom: 15px;
        }
        
        .related-card h4 {
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .related-card p {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-muted);
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            color: var(--text-secondary);
            margin-bottom: 15px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .category-hero h1 {
                font-size: 32px;
            }
            
            .category-stats {
                gap: 20px;
            }
            
            .filters-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .sort-controls {
                width: 100%;
                justify-content: space-between;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .related-grid {
                grid-template-columns: 1fr;
            }
            
            .header-nav {
                display: none;
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
                <span class="breadcrumb-current"><?php echo htmlspecialchars($categoria['nome']); ?></span>
            </div>
        </div>
    </section>

    <!-- Category Hero -->
    <section class="category-hero">
        <div class="container">
            <div class="hero-content">
                <div class="category-icon">
                    <i class="fas fa-cookie-bite"></i>
                </div>
                <h1><?php echo htmlspecialchars($categoria['nome']); ?></h1>
                <p><?php echo htmlspecialchars($categoria['descricao']); ?></p>
                
                <div class="category-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $total_products; ?></div>
                        <div class="stat-label">Produtos</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count($produtosDestaque); ?></div>
                        <div class="stat-label">Em Destaque</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">100%</div>
                        <div class="stat-label">Artesanal</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($total_products > 0): ?>
    <!-- Filters -->
    <section class="filters-section">
        <div class="container">
            <div class="filters-container">
                <div class="results-info">
                    Mostrando <?php echo min($per_page, $total_products - ($page - 1) * $per_page); ?> de <?php echo $total_products; ?> produtos
                </div>
                <div class="sort-controls">
                    <span style="color: var(--text-muted);">Ordenar por:</span>
                    <select class="sort-select" onchange="updateSort(this.value)">
                        <option value="name-asc" <?php echo ($sort == 'name' && $order == 'asc') ? 'selected' : ''; ?>>Nome (A-Z)</option>
                        <option value="name-desc" <?php echo ($sort == 'name' && $order == 'desc') ? 'selected' : ''; ?>>Nome (Z-A)</option>
                        <option value="price-asc" <?php echo ($sort == 'price' && $order == 'asc') ? 'selected' : ''; ?>>Menor Preço</option>
                        <option value="price-desc" <?php echo ($sort == 'price' && $order == 'desc') ? 'selected' : ''; ?>>Maior Preço</option>
                        <option value="created_at-desc" <?php echo ($sort == 'created_at' && $order == 'desc') ? 'selected' : ''; ?>>Mais Recentes</option>
                        <option value="views-desc" <?php echo ($sort == 'views' && $order == 'desc') ? 'selected' : ''; ?>>Mais Vistos</option>
                    </select>
                </div>
            </div>
        </div>
    </section>

    <!-- Products -->
    <section class="products-section">
        <div class="container">
            <div class="products-grid">
                <?php foreach ($produtos as $produto): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php if ($produto['imagem_principal']): ?>
                                <img src="<?php echo UPLOAD_DIR . $produto['imagem_principal']; ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>">
                            <?php else: ?>
                                <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: var(--text-muted);">
                                    <i class="fas fa-image" style="font-size: 48px;"></i>
                                </div>
                            <?php endif; ?>
                            <?php if ($produto['featured']): ?>
                                <div class="product-badge">Destaque</div>
                            <?php endif; ?>
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
                                <a href="produto.php?id=<?php echo $produto['id']; ?>" class="btn btn-outline">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                                <?php if (isLoggedIn()): ?>
                                    <a href="add-carrinho.php?id=<?php echo $produto['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Adicionar
                                    </a>
                                <?php else: ?>
                                    <a href="login.php" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt"></i> Login
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?id=<?php echo $categoryId; ?>&page=<?php echo $page - 1; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?id=<?php echo $categoryId; ?>&page=<?php echo $i; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?id=<?php echo $categoryId; ?>&page=<?php echo $page + 1; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php else: ?>
    <!-- Empty State -->
    <section class="products-section">
        <div class="container">
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>Nenhum produto encontrado</h3>
                <p>Esta categoria ainda não possui produtos disponíveis.</p>
                <a href="categorias.php" class="btn btn-primary" style="margin-top: 20px; display: inline-flex;">
                    <i class="fas fa-arrow-left"></i> Ver Outras Categorias
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Related Categories -->
    <?php if (!empty($outrasCategories)): ?>
    <section class="related-section">
        <div class="container">
            <h2 class="section-title">Outras Categorias</h2>
            <div class="related-grid">
                <?php foreach ($outrasCategories as $outraCategoria): ?>
                    <a href="categoria.php?id=<?php echo $outraCategoria['id']; ?>" class="related-card">
                        <i class="fas fa-cookie-bite"></i>
                        <h4><?php echo htmlspecialchars($outraCategoria['nome']); ?></h4>
                        <p><?php echo htmlspecialchars($outraCategoria['descricao']); ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <script>
        function updateSort(value) {
            const [sort, order] = value.split('-');
            const url = new URL(window.location);
            url.searchParams.set('sort', sort);
            url.searchParams.set('order', order);
            url.searchParams.set('page', '1'); // Reset to first page
            window.location = url.toString();
        }

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
            
            const animatedElements = document.querySelectorAll('.product-card, .related-card');
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