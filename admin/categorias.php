
<?php
// ============================================
// ARQUIVO: admin/categorias.php - Gestão de Categorias
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

// Processar ações
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = sanitize($_POST['action']);
    
    switch ($action) {
        case 'create_category':
            $nome = sanitize($_POST['nome']);
            $descricao = sanitize($_POST['descricao']);
            $ordem = intval($_POST['ordem']);
            $ativo = isset($_POST['ativo']);
            
            if (empty($nome)) {
                $errors[] = 'Nome é obrigatório.';
            }
            
            if (empty($errors)) {
                // Upload da imagem se fornecida
                $imagem = null;
                if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
                    $imagem = uploadImage($_FILES['imagem'], 'categories');
                    if (!$imagem) {
                        $errors[] = 'Erro ao fazer upload da imagem.';
                    }
                }
                
                if (empty($errors)) {
                    $stmt = $db->prepare("INSERT INTO categories (nome, descricao, imagem, ordem, ativo) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt->execute([$nome, $descricao, $imagem, $ordem, $ativo])) {
                        flashMessage('Categoria criada com sucesso!', 'success');
                        redirect('categorias.php');
                    } else {
                        $errors[] = 'Erro ao criar categoria.';
                    }
                }
            }
            break;
            
        case 'update_category':
            $categoryId = intval($_POST['category_id']);
            $nome = sanitize($_POST['nome']);
            $descricao = sanitize($_POST['descricao']);
            $ordem = intval($_POST['ordem']);
            $ativo = isset($_POST['ativo']);
            
            if (empty($nome)) {
                $errors[] = 'Nome é obrigatório.';
            }
            
            if (empty($errors)) {
                $stmt = $db->prepare("UPDATE categories SET nome = ?, descricao = ?, ordem = ?, ativo = ? WHERE id = ?");
                if ($stmt->execute([$nome, $descricao, $ordem, $ativo, $categoryId])) {
                    // Upload de nova imagem se fornecida
                    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
                        // Buscar imagem atual
                        $stmt = $db->prepare("SELECT imagem FROM categories WHERE id = ?");
                        $stmt->execute([$categoryId]);
                        $currentImage = $stmt->fetch()['imagem'];
                        
                        $novaImagem = uploadImage($_FILES['imagem'], 'categories');
                        if ($novaImagem) {
                            $stmt = $db->prepare("UPDATE categories SET imagem = ? WHERE id = ?");
                            $stmt->execute([$novaImagem, $categoryId]);
                            
                            // Deletar imagem anterior
                            if ($currentImage) {
                                deleteImage($currentImage);
                            }
                        }
                    }
                    
                    flashMessage('Categoria atualizada com sucesso!', 'success');
                    redirect('categorias.php');
                } else {
                    $errors[] = 'Erro ao atualizar categoria.';
                }
            }
            break;
            
        case 'delete_category':
            $categoryId = intval($_POST['category_id']);
            
            // Verificar se há produtos na categoria
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
            $stmt->execute([$categoryId]);
            $productCount = $stmt->fetch()['count'];
            
            if ($productCount > 0) {
                $errors[] = "Não é possível remover a categoria pois ela contém {$productCount} produto(s).";
            } else {
                // Buscar imagem para deletar
                $stmt = $db->prepare("SELECT imagem FROM categories WHERE id = ?");
                $stmt->execute([$categoryId]);
                $category = $stmt->fetch();
                
                $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
                if ($stmt->execute([$categoryId])) {
                    // Deletar imagem
                    if ($category['imagem']) {
                        deleteImage($category['imagem']);
                    }
                    
                    flashMessage('Categoria removida com sucesso!', 'success');
                } else {
                    $errors[] = 'Erro ao remover categoria.';
                }
            }
            redirect('categorias.php');
            break;
    }
}

// Buscar categorias
$stmt = $db->prepare("
    SELECT c.*, COUNT(p.id) as total_produtos 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id AND p.ativo = 1
    GROUP BY c.id 
    ORDER BY c.ordem ASC, c.nome ASC
");
$stmt->execute();
$categorias = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Categorias - Admin Gourmeria</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS base do admin */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background-color: #000; color: #fff; line-height: 1.6; }
        .main-content { margin-left: 250px; padding: 30px; }
        
        .category-form {
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
        
        .categories-grid {
            display: grid;
            gap: 20px;
        }
        
        .category-card {
            background: #111;
            border: 2px solid #333;
            border-radius: 10px;
            padding: 25px;
            transition: border-color 0.3s;
        }
        
        .category-card:hover {
            border-color: #DAA520;
        }
        
        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .category-info {
            display: grid;
            grid-template-columns: 80px 1fr auto;
            gap: 20px;
            align-items: center;
        }
        
        .category-image {
            width: 80px;
            height: 80px;
            background: #222;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .category-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .category-details h3 {
            color: #DAA520;
            margin-bottom: 10px;
        }
        
        .category-details p {
            color: #ccc;
            margin-bottom: 5px;
        }
        
        .category-status {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #28a745;
            color: #fff;
        }
        
        .status-inactive {
            background: #6c757d;
            color: #fff;
        }
        
        .category-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            justify-content: flex-end;
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
            max-width: 600px;
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
            border-bottom: 1px solid #333;
            padding-bottom: 15px;
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
    </style>
</head>
<body>
    <!-- Sidebar seria incluído aqui -->
    
    <main class="main-content">
        <div class="header">
            <h1 class="page-title">
                <i class="fas fa-list"></i> Gestão de Categorias
            </h1>
            <div>
                <button type="button" class="btn" onclick="openCategoryModal()">
                    <i class="fas fa-plus"></i> Nova Categoria
                </button>
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
        
        <!-- Lista de Categorias -->
        <div style="margin-bottom: 20px;">
            <h2 style="color: #DAA520;">
                Categorias (<?php echo count($categorias); ?>)
            </h2>
        </div>
        
        <?php if (empty($categorias)): ?>
            <div style="text-align: center; color: #666; padding: 60px;">
                <i class="fas fa-list" style="font-size: 64px; margin-bottom: 20px;"></i>
                <h3>Nenhuma categoria criada ainda</h3>
                <p>Crie sua primeira categoria para organizar os produtos.</p>
                <button type="button" class="btn" onclick="openCategoryModal()" style="margin-top: 20px;">
                    <i class="fas fa-plus"></i> Criar Primeira Categoria
                </button>
            </div>
        <?php else: ?>
            <div class="categories-grid">
                <?php foreach ($categorias as $categoria): ?>
                    <div class="category-card">
                        <div class="category-info">
                            <div class="category-image">
                                <?php if ($categoria['imagem']): ?>
                                    <img src="../<?php echo UPLOAD_DIR . $categoria['imagem']; ?>" alt="<?php echo htmlspecialchars($categoria['nome']); ?>">
                                <?php else: ?>
                                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #666;">
                                        <i class="fas fa-folder" style="font-size: 24px;"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="category-details">
                                <h3><?php echo htmlspecialchars($categoria['nome']); ?></h3>
                                <?php if ($categoria['descricao']): ?>
                                    <p><?php echo htmlspecialchars($categoria['descricao']); ?></p>
                                <?php endif; ?>
                                <p><strong>Produtos:</strong> <?php echo $categoria['total_produtos']; ?></p>
                                <p><strong>Ordem:</strong> <?php echo $categoria['ordem']; ?></p>
                                <p><strong>Criada:</strong> <?php echo date('d/m/Y', strtotime($categoria['created_at'])); ?></p>
                            </div>
                            
                            <div>
                                <div class="category-status status-<?php echo $categoria['ativo'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $categoria['ativo'] ? 'Ativa' : 'Inativa'; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="category-actions">
                            <a href="../produtos.php?category=<?php echo $categoria['id']; ?>" class="btn" target="_blank">
                                <i class="fas fa-eye"></i> Ver Produtos
                            </a>
                            
                            <button type="button" class="btn" onclick="editCategory(<?php echo htmlspecialchars(json_encode($categoria)); ?>)">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja remover esta categoria?')">
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="category_id" value="<?php echo $categoria['id']; ?>">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-trash"></i> Remover
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Modal Categoria -->
    <div class="modal" id="categoryModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Nova Categoria</h2>
                <button type="button" class="close-modal" onclick="closeCategoryModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" id="categoryForm">
                <input type="hidden" name="action" id="formAction" value="create_category">
                <input type="hidden" name="category_id" id="categoryId">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nome">Nome da Categoria *</label>
                        <input type="text" id="nome" name="nome" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="ordem">Ordem de Exibição</label>
                        <input type="number" id="ordem" name="ordem" min="0" value="0">
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label for="descricao">Descrição</label>
                    <textarea id="descricao" name="descricao" rows="4" placeholder="Descrição da categoria (opcional)"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Imagem da Categoria</label>
                    <div class="file-input-wrapper">
                        <div class="file-input" onclick="document.getElementById('imagem').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            Clique para selecionar imagem
                            <input type="file" id="imagem" name="imagem" accept="image/*">
                        </div>
                    </div>
                    <div id="currentImage" style="margin-top: 10px; display: none;">
                        <small>Imagem atual:</small><br>
                        <img id="currentImagePreview" style="max-width: 100px; max-height: 100px; border-radius: 5px;">
                    </div>
                </div>
                
                <div style="margin: 20px 0;">
                    <label style="display: flex; align-items: center; gap: 8px; color: #ccc; cursor: pointer;">
                        <input type="checkbox" id="ativo" name="ativo" checked>
                        Categoria Ativa
                    </label>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn" onclick="closeCategoryModal()" style="background: #666;">
                        Cancelar
                    </button>
                    <button type="submit" class="btn" id="submitBtn">
                        <i class="fas fa-save"></i> Criar Categoria
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCategoryModal() {
            // Reset form
            document.getElementById('categoryForm').reset();
            document.getElementById('formAction').value = 'create_category';
            document.getElementById('categoryId').value = '';
            document.getElementById('modalTitle').textContent = 'Nova Categoria';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Criar Categoria';
            document.getElementById('currentImage').style.display = 'none';
            document.getElementById('ativo').checked = true;
            
            document.getElementById('categoryModal').style.display = 'block';
        }
        
        function closeCategoryModal() {
            document.getElementById('categoryModal').style.display = 'none';
        }
        
        function editCategory(category) {
            document.getElementById('formAction').value = 'update_category';
            document.getElementById('categoryId').value = category.id;
            document.getElementById('modalTitle').textContent = 'Editar Categoria';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Atualizar Categoria';
            
            document.getElementById('nome').value = category.nome;
            document.getElementById('descricao').value = category.descricao || '';
            document.getElementById('ordem').value = category.ordem;
            document.getElementById('ativo').checked = category.ativo == 1;
            
            // Mostrar imagem atual se existir
            if (category.imagem) {
                document.getElementById('currentImage').style.display = 'block';
                document.getElementById('currentImagePreview').src = '../' + '<?php echo UPLOAD_DIR; ?>' + category.imagem;
            } else {
                document.getElementById('currentImage').style.display = 'none';
            }
            
            document.getElementById('categoryModal').style.display = 'block';
        }
        
        // Fechar modal clicando fora
        window.onclick = function(event) {
            const modal = document.getElementById('categoryModal');
            if (event.target === modal) {
                closeCategoryModal();
            }
        }
        
        // Mostrar nome do arquivo selecionado
        document.getElementById('imagem').addEventListener('change', function() {
            const fileName = this.files[0] ? this.files[0].name : 'Nenhum arquivo selecionado';
            this.parentElement.querySelector('.file-input').innerHTML = `
                <i class="fas fa-check"></i> ${fileName}
            `;
        });
    </script>
</body>
</html>