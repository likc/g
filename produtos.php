<?php
require_once 'config.php';

// Parâmetros de busca e filtros
$search = sanitize($_GET['search'] ?? '');
$category = intval($_GET['category'] ?? 0);
$sort = sanitize($_GET['sort'] ?? 'nome');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 12; // Produtos por página
$offset = ($page - 1) * $limit;

// Construir query de busca
$where = "WHERE p.ativo = 1";
$params = [];

if (!empty($search)) {
    $where .= " AND (p.nome LIKE ? OR p.descricao LIKE ? OR p.descricao_curta LIKE ?)";
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if ($category > 0) {
    $where .= " AND p.category_id = ?";
    $params[] = $category;
}

// Ordenação
$orderBy = "ORDER BY ";
switch ($sort) {
    case 'preco_asc':
        $orderBy .= "COALESCE(p.preco_promocional, p.preco) ASC";
        break;
    case 'preco_desc':
        $orderBy .= "COALESCE(p.preco_promocional, p.preco) DESC";
        break;
    case 'nome':
        $orderBy .= "p.nome ASC";
        break;
    case 'mais_novo':
        $orderBy .= "p.created_at DESC";
        break;
    default:
        $orderBy .= "p.nome ASC";
}

// Buscar produtos
$sql = "
    SELECT p.*, c.nome as categoria_nome 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    {$where} 
    {$orderBy} 
    LIMIT {$limit} OFFSET {$offset}
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$produtos = $stmt->fetchAll();

// Contar total de produtos para paginação
$countSql = "SELECT COUNT(*) FROM products p {$where}";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalProdutos = $countStmt->fetchColumn();
$totalPaginas = ceil($totalProdutos / $limit);

// Buscar categorias para filtro
$stmt = $db->prepare("SELECT * FROM categories WHERE ativo = 1 ORDER BY ordem ASC");
$stmt->execute();
$categorias = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos - Gourmeria</title>
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
        
        /* Header simplificado para esta página */
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
            min-height: calc(100vh - 80px);
            padding: 40px 0;
        }
        
        .page-header {
            background: #111;
            border: 2px solid #DAA520;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .page-title {
            color: #DAA520;
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .filters-section {
            background: #111;
            border: 2px solid #333;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .filters-row {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            gap: 20px;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            color: #DAA520;
            font-weight: bold;
            font-size: 14px;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 10px;
            border: 2px solid #333;
            border-radius: 5px;
            background: #000;
            color: #fff;
            font-size: 14px;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #DAA520;
        }
        
        .btn {
            padding: 10px 20px;
            background: #DAA520;
            color: #000;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-block;
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
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin: 30px 0;
        }
        
        .product-card {
            background: #111;
            border: 2px solid #333;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s;
            position: relative;
        }
        
        .product-card:hover {
            border-color: #DAA520;
            transform: translateY(-5px);
        }
        
        .product-image {
            height: 220px;
            background: #222;
            position: relative;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .product-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #DAA520;
            color: #000;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .product-badge.promotion {
            background: #dc3545;
            color: #fff;
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-category {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .product-title {
            font-size: 16px;
            margin-bottom: 10px;
            color: #DAA520;
            line-height: 1.3;
        }
        
        .product-description {
            color: #ccc;
            font-size: 13px;
            margin-bottom: 15px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-price {
            font-size: 18px;
            font-weight: bold;
            color: #DAA520;
            margin-bottom: 15px;
        }
        
        .old-price {
            text-decoration: line-through;
            color: #666;
            font-size: 14px;
            margin-right: 10px;
        }
        
        .product-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-small {
            padding: 8px 15px;
            font-size: 13px;
            flex: 1;
            text-align: center;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 40px 0;
        }
        
        .pagination a,
        .pagination span {
            padding: 10px 15px;
            background: #111;
            color: #fff;
            text-decoration: none;
            border: 2px solid #333;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .pagination a:hover {
            border-color: #DAA520;
            color: #DAA520;
        }
        
        .pagination .current {
            background: #DAA520;
            color: #000;
            border-color: #DAA520;
        }
        
        .results-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            color: #ccc;
        }
        
        .no-products {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .no-products i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #333;
        }
        
        @media (max-width: 768px) {
            .filters-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
            }
            
            .pagination {
                flex-wrap: wrap;
            }
        }
        
        /* Loading animation */
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
            color: #DAA520;
        }
        
        .loading i {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                <a href="carrinho.php">
                    <i class="fas fa-shopping-cart"></i> Carrinho
                    <?php if (isLoggedIn()): ?>
                        <?php
                        $stmt = $db->prepare("SELECT SUM(quantidade) as total FROM cart WHERE user_id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $cartCount = $stmt->fetch()['total'] ?? 0;
                        if ($cartCount > 0):
                        ?>
                            <span style="background: #DAA520; color: #000; border-radius: 50%; padding: 2px 6px; font-size: 12px; margin-left: 5px;">
                                <?php echo $cartCount; ?>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </a>
                <?php if (isLoggedIn()): ?>
                    <a href="minha-conta.php"><?php echo $_SESSION['user_name']; ?></a>
                <?php else: ?>
                    <a href="login.php">Login</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-store"></i> Nossos Produtos
                </h1>
                <p>Descubra nossa seleção exclusiva de doces gourmet brasileiros</p>
            </div>
            
            <!-- Filtros -->
            <div class="filters-section">
                <form method="GET" id="filtersForm">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label for="search">Buscar produtos:</label>
                            <input 
                                type="text" 
                                id="search" 
                                name="search" 
                                placeholder="Digite o nome do produto..."
                                value="<?php echo htmlspecialchars($search); ?>"
                            >
                        </div>
                        
                        <div class="filter-group">
                            <label for="category">Categoria:</label>
                            <select id="category" name="category">
                                <option value="">Todas as categorias</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="sort">Ordenar por:</label>
                            <select id="sort" name="sort">
                                <option value="nome" <?php echo $sort == 'nome' ? 'selected' : ''; ?>>Nome (A-Z)</option>
                                <option value="mais_novo" <?php echo $sort == 'mais_novo' ? 'selected' : ''; ?>>Mais Novos</option>
                                <option value="preco_asc" <?php echo $sort == 'preco_asc' ? 'selected' : ''; ?>>Menor Preço</option>
                                <option value="preco_desc" <?php echo $sort == 'preco_desc' ? 'selected' : ''; ?>>Maior Preço</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <button type="submit" class="btn">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Informações dos resultados -->
            <div class="results-info">
                <span>
                    Mostrando <?php echo count($produtos); ?> de <?php echo $totalProdutos; ?> produtos
                    <?php if (!empty($search)): ?>
                        para "<?php echo htmlspecialchars($search); ?>"
                    <?php endif; ?>
                </span>
                
                <?php if (!empty($search) || $category > 0): ?>
                    <a href="produtos.php" class="btn-outline" style="padding: 5px 15px; font-size: 14px;">
                        <i class="fas fa-times"></i> Limpar Filtros
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Grid de produtos -->
            <?php if (empty($produtos)): ?>
                <div class="no-products">
                    <i class="fas fa-search"></i>
                    <h2>Nenhum produto encontrado</h2>
                    <p>Tente ajustar os filtros ou buscar por outros termos.</p>
                    <a href="produtos.php" class="btn" style="margin-top: 20px;">
                        Ver Todos os Produtos
                    </a>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($produtos as $produto): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <?php if ($produto['imagem_principal']): ?>
                                    <img src="<?php echo UPLOAD_DIR . $produto['imagem_principal']; ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>">
                                <?php else: ?>
                                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #666;">
                                        <i class="fas fa-image" style="font-size: 48px;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($produto['featured']): ?>
                                    <div class="product-badge">Destaque</div>
                                <?php elseif ($produto['preco_promocional']): ?>
                                    <div class="product-badge promotion">Promoção</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-info">
                                <?php if ($produto['categoria_nome']): ?>
                                    <div class="product-category"><?php echo htmlspecialchars($produto['categoria_nome']); ?></div>
                                <?php endif; ?>
                                
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
                                        <form method="POST" action="add-carrinho.php" style="flex: 1;">
                                            <input type="hidden" name="product_id" value="<?php echo $produto['id']; ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <input type="hidden" name="redirect" value="produtos.php">
                                            <button type="submit" class="btn btn-small">
                                                <i class="fas fa-cart-plus"></i> Adicionar
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <a href="login.php?redirect=<?php echo urlencode('produto.php?id=' . $produto['id']); ?>" class="btn btn-small">
                                            <i class="fas fa-sign-in-alt"></i> Login
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Paginação -->
                <?php if ($totalPaginas > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                <i class="fas fa-chevron-left"></i> Anterior
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPaginas, $page + 2);
                        
                        if ($start > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                            <?php if ($start > 2): ?>
                                <span>...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($end < $totalPaginas): ?>
                            <?php if ($end < $totalPaginas - 1): ?>
                                <span>...</span>
                            <?php endif; ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPaginas])); ?>"><?php echo $totalPaginas; ?></a>
                        <?php endif; ?>
                        
                        <?php if ($page < $totalPaginas): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                Próxima <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-submit do formulário quando mudar categoria ou ordenação
            const categorySelect = document.getElementById('category');
            const sortSelect = document.getElementById('sort');
            
            [categorySelect, sortSelect].forEach(select => {
                select.addEventListener('change', function() {
                    document.getElementById('filtersForm').submit();
                });
            });
            
            // Busca em tempo real com debounce
            const searchInput = document.getElementById('search');
            let searchTimeout;
            
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (this.value.length >= 3 || this.value.length === 0) {
                        document.getElementById('filtersForm').submit();
                    }
                }, 500);
            });
            
            // Loading state para formulários
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Carregando...';
                        submitBtn.disabled = true;
                    }
                });
            });
        });
    </script>
</body>
</html>