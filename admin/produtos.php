<?php
// ============================================
// ARQUIVO: admin/produtos.php - Gestão de Produtos
// ============================================
?>
<?php
require_once '../config.php';

if (!isModerator()) {
    flashMessage('Acesso negado.', 'error');
    redirect('../index.php');
}

$errors = [];
$success = false;
$editProduct = null;

// Processar ações
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = sanitize($_POST['action']);
    
    switch ($action) {
        case 'create_product':
        case 'update_product':
            $nome = sanitize($_POST['nome']);
            $descricao = sanitize($_POST['descricao']);
            $descricaoCarry = sanitize($_POST['descricao_curta']);
            $preco = floatval($_POST['preco']);
            $precoPromocional = !empty($_POST['preco_promocional']) ? floatval($_POST['preco_promocional']) : null;
            $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
            $peso = !empty($_POST['peso']) ? floatval($_POST['peso']) : null;
            $dimensoes = sanitize($_POST['dimensoes']);
            $estoque = intval($_POST['estoque']);
            $gerenciarEstoque = isset($_POST['gerenciar_estoque']);
            $ativo = isset($_POST['ativo']);
            $featured = isset($_POST['featured']);
            $metaTitle = sanitize($_POST['meta_title']);
            $metaDescription = sanitize($_POST['meta_description']);
            $slug = sanitize($_POST['slug']);
            
            // Validações
            if (empty($nome)) {
                $errors[] = 'Nome é obrigatório.';
            }
            
            if ($preco <= 0) {
                $errors[] = 'Preço deve ser maior que zero.';
            }
            
            if ($precoPromocional && $precoPromocional >= $preco) {
                $errors[] = 'Preço promocional deve ser menor que o preço normal.';
            }
            
            if (empty($slug)) {
                $slug = strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9\s]/', '', $nome)));
            }
            
            // Verificar se slug já existe (exceto para o próprio produto em caso de edição)
            $productId = $action == 'update_product' ? intval($_POST['product_id']) : 0;
            $stmt = $db->prepare("SELECT id FROM products WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $productId]);
            if ($stmt->fetch()) {
                $slug .= '-' . time();
            }
            
            if (empty($errors)) {
                try {
                    if ($action == 'create_product') {
                        // Upload da imagem principal
                        $imagemPrincipal = null;
                        if (isset($_FILES['imagem_principal']) && $_FILES['imagem_principal']['error'] == 0) {
                            $imagemPrincipal = uploadImage($_FILES['imagem_principal'], 'products');
                            if (!$imagemPrincipal) {
                                $errors[] = 'Erro ao fazer upload da imagem.';
                            }
                        }
                        
                        if (empty($errors)) {
                            $stmt = $db->prepare("
                                INSERT INTO products (nome, descricao, descricao_curta, preco, preco_promocional, category_id, imagem_principal, peso, dimensoes, estoque, gerenciar_estoque, ativo, featured, meta_title, meta_description, slug) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            
                            if ($stmt->execute([$nome, $descricao, $descricaoCarry, $preco, $precoPromocional, $categoryId, $imagemPrincipal, $peso, $dimensoes, $estoque, $gerenciarEstoque, $ativo, $featured, $metaTitle, $metaDescription, $slug])) {
                                $productId = $db->lastInsertId();
                                
                                // Upload de imagens adicionais
                                if (isset($_FILES['imagens_adicionais'])) {
                                    foreach ($_FILES['imagens_adicionais']['tmp_name'] as $key => $tmpName) {
                                        if ($_FILES['imagens_adicionais']['error'][$key] == 0) {
                                            $imagemPath = uploadImage([
                                                'name' => $_FILES['imagens_adicionais']['name'][$key],
                                                'tmp_name' => $tmpName
                                            ], 'products');
                                            
                                            if ($imagemPath) {
                                                $stmt = $db->prepare("INSERT INTO product_images (product_id, imagem, ordem) VALUES (?, ?, ?)");
                                                $stmt->execute([$productId, $imagemPath, $key]);
                                            }
                                        }
                                    }
                                }
                                
                                flashMessage('Produto criado com sucesso!', 'success');
                                redirect('produtos.php');
                            } else {
                                $errors[] = 'Erro ao criar produto.';
                            }
                        }
                    } else {
                        // Atualizar produto
                        $productId = intval($_POST['product_id']);
                        
                        $stmt = $db->prepare("
                            UPDATE products SET 
                            nome = ?, descricao = ?, descricao_curta = ?, preco = ?, preco_promocional = ?, 
                            category_id = ?, peso = ?, dimensoes = ?, estoque = ?, gerenciar_estoque = ?, 
                            ativo = ?, featured = ?, meta_title = ?, meta_description = ?, slug = ?
                            WHERE id = ?
                        ");
                        
                        if ($stmt->execute([$nome, $descricao, $descricaoCarry, $preco, $precoPromocional, $categoryId, $peso, $dimensoes, $estoque, $gerenciarEstoque, $ativo, $featured, $metaTitle, $metaDescription, $slug, $productId])) {
                            // Upload de nova imagem principal se fornecida
                            if (isset($_FILES['imagem_principal']) && $_FILES['imagem_principal']['error'] == 0) {
                                // Buscar imagem atual para deletar
                                $stmt = $db->prepare("SELECT imagem_principal FROM products WHERE id = ?");
                                $stmt->execute([$productId]);
                                $currentImage = $stmt->fetch()['imagem_principal'];
                                
                                $novaImagem = uploadImage($_FILES['imagem_principal'], 'products');
                                if ($novaImagem) {
                                    $stmt = $db->prepare("UPDATE products SET imagem_principal = ? WHERE id = ?");
                                    $stmt->execute([$novaImagem, $productId]);
                                    
                                    // Deletar imagem anterior
                                    if ($currentImage) {
                                        deleteImage($currentImage);
                                    }
                                }
                            }
                            
                            flashMessage('Produto atualizado com sucesso!', 'success');
                            redirect('produtos.php');
                        } else {
                            $errors[] = 'Erro ao atualizar produto.';
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = 'Erro interno: ' . $e->getMessage();
                }
            }
            break;
            
        case 'delete_product':
            $productId = intval($_POST['product_id']);
            
            try {
                $db->getConnection()->beginTransaction();
                
                // Buscar imagens para deletar
                $stmt = $db->prepare("SELECT imagem_principal FROM products WHERE id = ?");
                $stmt->execute([$productId]);
                $product = $stmt->fetch();
                
                $stmt = $db->prepare("SELECT imagem FROM product_images WHERE product_id = ?");
                $stmt->execute([$productId]);
                $images = $stmt->fetchAll();
                
                // Deletar produto (cascade irá deletar imagens e itens relacionados)
                $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$productId]);
                
                // Deletar arquivos de imagem
                if ($product['imagem_principal']) {
                    deleteImage($product['imagem_principal']);
                }
                
                foreach ($images as $image) {
                    deleteImage($image['imagem']);
                }
                
                $db->getConnection()->commit();
                flashMessage('Produto removido com sucesso!', 'success');
            } catch (Exception $e) {
                $db->getConnection()->rollback();
                $errors[] = 'Erro ao remover produto.';
            }
            
            redirect('produtos.php');
            break;
    }
}

// Buscar produto para edição
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$editId]);
    $editProduct = $stmt->fetch();
    
    if (!$editProduct) {
        flashMessage('Produto não encontrado.', 'error');
        redirect('produtos.php');
    }
}

// Filtros e busca
$search = sanitize($_GET['search'] ?? '');
$category = intval($_GET['category'] ?? 0);
$status = sanitize($_GET['status'] ?? '');
$filter = sanitize($_GET['filter'] ?? '');

$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (p.nome LIKE ? OR p.descricao LIKE ?)";
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam]);
}

if ($category > 0) {
    $where .= " AND p.category_id = ?";
    $params[] = $category;
}

if ($status === 'active') {
    $where .= " AND p.ativo = 1";
} elseif ($status === 'inactive') {
    $where .= " AND p.ativo = 0";
}

if ($filter === 'low_stock') {
    $where .= " AND p.gerenciar_estoque = 1 AND p.estoque <= 5";
} elseif ($filter === 'featured') {
    $where .= " AND p.featured = 1";
}

// Buscar produtos
$stmt = $db->prepare("
    SELECT p.*, c.nome as categoria_nome 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    {$where} 
    ORDER BY p.created_at DESC
");
$stmt->execute($params);
$produtos = $stmt->fetchAll();

// Buscar categorias para filtros e formulário
$stmt = $db->prepare("SELECT * FROM categories WHERE ativo = 1 ORDER BY nome ASC");
$stmt->execute();
$categorias = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Produtos - Admin Gourmeria</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS base do admin */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background-color: #000; color: #fff; line-height: 1.6; }
        .main-content { margin-left: 250px; padding: 30px; }
        
        .product-form {
            background: #111;
            border: 2px solid #333;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #DAA520;
            font-weight: bold;
        }
        
        .form-group input,
        .form-group select,
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
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #DAA520;
        }
        
        .checkbox-group {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #ccc;
            cursor: pointer;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        .btn {
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
        
        .btn:hover { background: #B8860B; }
        
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
        
        .filters {
            background: #111;
            border: 2px solid #333;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .filters-row {
            display: grid;
            grid-template-columns: 1fr 200px 150px 150px auto;
            gap: 15px;
            align-items: end;
        }
        
        .products-grid {
            display: grid;
            gap: 20px;
        }
        
        .product-card {
            background: #111;
            border: 2px solid #333;
            border-radius: 10px;
            padding: 20px;
            display: grid;
            grid-template-columns: 120px 1fr auto;
            gap: 20px;
            align-items: center;
        }
        
        .product-card:hover {
            border-color: #DAA520;
        }
        
        .product-image {
            width: 120px;
            height: 120px;
            background: #222;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-info h3 {
            color: #DAA520;
            margin-bottom: 10px;
        }
        
        .product-info p {
            color: #ccc;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .product-status {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .status-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .status-active {
            background: #28a745;
            color: #fff;
        }
        
        .status-inactive {
            background: #6c757d;
            color: #fff;
        }
        
        .status-featured {
            background: #DAA520;
            color: #000;
        }
        
        .status-low-stock {
            background: #ffc107;
            color: #000;
        }
        
        .product-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .file-input {
            width: 100%;
            padding: 12px;
            border: 2px dashed #333;
            border-radius: 5px;
            background: #222;
            color: #ccc;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-input:hover {
            border-color: #DAA520;
            background: #111;
        }
        
        .file-input input[type="file"] {
            position: absolute;
            left: -9999px;
        }
        
        .form-help {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
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
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .product-card {
                grid-template-columns: 1fr;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar seria incluído aqui -->
    
    <main class="main-content">
        <div class="header">
            <h1 class="page-title">
                <i class="fas fa-box"></i> Gestão de Produtos
            </h1>
            <div>
                <?php if (!$editProduct): ?>
                    <a href="?action=new" class="btn">
                        <i class="fas fa-plus"></i> Novo Produto
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="errors">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- Formulário de Produto -->
        <?php if (isset($_GET['action']) && $_GET['action'] == 'new' || $editProduct): ?>
            <div class="product-form">
                <h2 style="color: #DAA520; margin-bottom: 20px;">
                    <i class="fas fa-<?php echo $editProduct ? 'edit' : 'plus'; ?>"></i> 
                    <?php echo $editProduct ? 'Editar' : 'Novo'; ?> Produto
                </h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?php echo $editProduct ? 'update_product' : 'create_product'; ?>">
                    <?php if ($editProduct): ?>
                        <input type="hidden" name="product_id" value="<?php echo $editProduct['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nome">Nome do Produto *</label>
                            <input 
                                type="text" 
                                id="nome" 
                                name="nome" 
                                required
                                value="<?php echo htmlspecialchars($editProduct['nome'] ?? $_POST['nome'] ?? ''); ?>"
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="category_id">Categoria</label>
                            <select id="category_id" name="category_id">
                                <option value="">Selecione uma categoria</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['id']; ?>" 
                                        <?php echo ($editProduct['category_id'] ?? $_POST['category_id'] ?? '') == $categoria['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($categoria['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="preco">Preço (¥) *</label>
                            <input 
                                type="number" 
                                id="preco" 
                                name="preco" 
                                step="0.01" 
                                min="0" 
                                required
                                value="<?php echo htmlspecialchars($editProduct['preco'] ?? $_POST['preco'] ?? ''); ?>"
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="preco_promocional">Preço Promocional (¥)</label>
                            <input 
                                type="number" 
                                id="preco_promocional" 
                                name="preco_promocional" 
                                step="0.01" 
                                min="0"
                                value="<?php echo htmlspecialchars($editProduct['preco_promocional'] ?? $_POST['preco_promocional'] ?? ''); ?>"
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="estoque">Estoque</label>
                            <input 
                                type="number" 
                                id="estoque" 
                                name="estoque" 
                                min="0"
                                value="<?php echo htmlspecialchars($editProduct['estoque'] ?? $_POST['estoque'] ?? '0'); ?>"
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="peso">Peso (g)</label>
                            <input 
                                type="number" 
                                id="peso" 
                                name="peso" 
                                step="0.1" 
                                min="0"
                                value="<?php echo htmlspecialchars($editProduct['peso'] ?? $_POST['peso'] ?? ''); ?>"
                            >
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="descricao_curta">Descrição Curta</label>
                        <input 
                            type="text" 
                            id="descricao_curta" 
                            name="descricao_curta" 
                            maxlength="500"
                            value="<?php echo htmlspecialchars($editProduct['descricao_curta'] ?? $_POST['descricao_curta'] ?? ''); ?>"
                        >
                        <div class="form-help">Resumo do produto para listagens (máx. 500 caracteres)</div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="descricao">Descrição Completa</label>
                        <textarea 
                            id="descricao" 
                            name="descricao" 
                            rows="6"
                        ><?php echo htmlspecialchars($editProduct['descricao'] ?? $_POST['descricao'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="dimensoes">Dimensões</label>
                            <input 
                                type="text" 
                                id="dimensoes" 
                                name="dimensoes" 
                                placeholder="Ex: 10x10x5 cm"
                                value="<?php echo htmlspecialchars($editProduct['dimensoes'] ?? $_POST['dimensoes'] ?? ''); ?>"
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="slug">URL Amigável (Slug)</label>
                            <input 
                                type="text" 
                                id="slug" 
                                name="slug" 
                                placeholder="Será gerado automaticamente se vazio"
                                value="<?php echo htmlspecialchars($editProduct['slug'] ?? $_POST['slug'] ?? ''); ?>"
                            >
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Imagem Principal</label>
                        <div class="file-input-wrapper">
                            <div class="file-input" onclick="document.getElementById('imagem_principal').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                Clique para selecionar imagem
                                <input type="file" id="imagem_principal" name="imagem_principal" accept="image/*">
                            </div>
                        </div>
                        <?php if ($editProduct && $editProduct['imagem_principal']): ?>
                            <div style="margin-top: 10px;">
                                <small>Imagem atual:</small><br>
                                <img src="../<?php echo UPLOAD_DIR . $editProduct['imagem_principal']; ?>" 
                                     style="max-width: 100px; max-height: 100px; border-radius: 5px;">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!$editProduct): ?>
                        <div class="form-group">
                            <label>Imagens Adicionais</label>
                            <div class="file-input-wrapper">
                                <div class="file-input" onclick="document.getElementById('imagens_adicionais').click()">
                                    <i class="fas fa-images"></i>
                                    Selecionar múltiplas imagens
                                    <input type="file" id="imagens_adicionais" name="imagens_adicionais[]" accept="image/*" multiple>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="ativo" 
                                <?php echo ($editProduct['ativo'] ?? true) ? 'checked' : ''; ?>>
                            Produto Ativo
                        </label>
                        
                        <label>
                            <input type="checkbox" name="featured" 
                                <?php echo ($editProduct['featured'] ?? false) ? 'checked' : ''; ?>>
                            Produto em Destaque
                        </label>
                        
                        <label>
                            <input type="checkbox" name="gerenciar_estoque" 
                                <?php echo ($editProduct['gerenciar_estoque'] ?? true) ? 'checked' : ''; ?>>
                            Gerenciar Estoque
                        </label>
                    </div>
                    
                    <!-- SEO -->
                    <h3 style="color: #DAA520; margin: 30px 0 15px;">SEO</h3>
                    
                    <div class="form-group">
                        <label for="meta_title">Título da Página</label>
                        <input 
                            type="text" 
                            id="meta_title" 
                            name="meta_title"
                            value="<?php echo htmlspecialchars($editProduct['meta_title'] ?? $_POST['meta_title'] ?? ''); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="meta_description">Meta Descrição</label>
                        <textarea 
                            id="meta_description" 
                            name="meta_description" 
                            rows="3"
                        ><?php echo htmlspecialchars($editProduct['meta_description'] ?? $_POST['meta_description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 15px; justify-content: flex-end;">
                        <a href="produtos.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                        <button type="submit" class="btn">
                            <i class="fas fa-save"></i> 
                            <?php echo $editProduct ? 'Atualizar' : 'Criar'; ?> Produto
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            
            <!-- Filtros -->
            <div class="filters">
                <form method="GET">
                    <div class="filters-row">
                        <div class="form-group">
                            <label for="search">Buscar:</label>
                            <input 
                                type="text" 
                                id="search" 
                                name="search" 
                                placeholder="Nome ou descrição..."
                                value="<?php echo htmlspecialchars($search); ?>"
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="category">Categoria:</label>
                            <select id="category" name="category">
                                <option value="">Todas</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['id']; ?>" <?php echo $category == $categoria['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($categoria['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status:</label>
                            <select id="status" name="status">
                                <option value="">Todos</option>
                                <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Ativos</option>
                                <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inativos</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="filter">Filtro:</label>
                            <select id="filter" name="filter">
                                <option value="">Nenhum</option>
                                <option value="featured" <?php echo $filter == 'featured' ? 'selected' : ''; ?>>Destaque</option>
                                <option value="low_stock" <?php echo $filter == 'low_stock' ? 'selected' : ''; ?>>Estoque Baixo</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Lista de Produtos -->
            <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="color: #DAA520;">
                    Produtos (<?php echo count($produtos); ?>)
                </h2>
            </div>
            
            <?php if (empty($produtos)): ?>
                <div style="text-align: center; color: #666; padding: 60px;">
                    <i class="fas fa-box-open" style="font-size: 64px; margin-bottom: 20px;"></i>
                    <h3>Nenhum produto encontrado</h3>
                    <p>Crie seu primeiro produto ou ajuste os filtros de busca.</p>
                    <a href="?action=new" class="btn" style="margin-top: 20px;">
                        <i class="fas fa-plus"></i> Criar Primeiro Produto
                    </a>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($produtos as $produto): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <?php if ($produto['imagem_principal']): ?>
                                    <img src="../<?php echo UPLOAD_DIR . $produto['imagem_principal']; ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>">
                                <?php else: ?>
                                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #666;">
                                        <i class="fas fa-image" style="font-size: 32px;"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-info">
                                <h3><?php echo htmlspecialchars($produto['nome']); ?></h3>
                                <p><strong>Preço:</strong> <?php echo formatPrice($produto['preco']); ?>
                                    <?php if ($produto['preco_promocional']): ?>
                                        → <?php echo formatPrice($produto['preco_promocional']); ?>
                                    <?php endif; ?>
                                </p>
                                <p><strong>Categoria:</strong> <?php echo htmlspecialchars($produto['categoria_nome'] ?: 'Sem categoria'); ?></p>
                                <?php if ($produto['gerenciar_estoque']): ?>
                                    <p><strong>Estoque:</strong> <?php echo $produto['estoque']; ?> unidades</p>
                                <?php endif; ?>
                                <p><strong>Criado:</strong> <?php echo date('d/m/Y', strtotime($produto['created_at'])); ?></p>
                                
                                <div class="product-status">
                                    <?php if ($produto['ativo']): ?>
                                        <span class="status-badge status-active">Ativo</span>
                                    <?php else: ?>
                                        <span class="status-badge status-inactive">Inativo</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($produto['featured']): ?>
                                        <span class="status-badge status-featured">Destaque</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($produto['gerenciar_estoque'] && $produto['estoque'] <= 5): ?>
                                        <span class="status-badge status-low-stock">Estoque Baixo</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="product-actions">
                                <a href="../produto.php?id=<?php echo $produto['id']; ?>" class="btn btn-outline" target="_blank">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                                
                                <a href="?edit=<?php echo $produto['id']; ?>" class="btn">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja remover este produto?')">
                                    <input type="hidden" name="action" value="delete_product">
                                    <input type="hidden" name="product_id" value="<?php echo $produto['id']; ?>">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-trash"></i> Remover
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <script>
        // Auto-submit filtros
        document.addEventListener('DOMContentLoaded', function() {
            const selects = document.querySelectorAll('#category, #status, #filter');
            selects.forEach(select => {
                select.addEventListener('change', function() {
                    this.form.submit();
                });
            });
            
            // Mostrar nome do arquivo selecionado
            document.getElementById('imagem_principal').addEventListener('change', function() {
                const fileName = this.files[0] ? this.files[0].name : 'Nenhum arquivo selecionado';
                this.parentElement.querySelector('.file-input').innerHTML = `
                    <i class="fas fa-check"></i> ${fileName}
                `;
            });
            
            if (document.getElementById('imagens_adicionais')) {
                document.getElementById('imagens_adicionais').addEventListener('change', function() {
                    const count = this.files.length;
                    const text = count > 0 ? `${count} arquivo(s) selecionado(s)` : 'Nenhum arquivo selecionado';
                    this.parentElement.querySelector('.file-input').innerHTML = `
                        <i class="fas fa-check"></i> ${text}
                    `;
                });
            }
        });
    </script>
</body>
</html>