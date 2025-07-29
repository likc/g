<?php
// ============================================
// ARQUIVO: admin/usuarios.php - Gestão de Usuários
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
        case 'update_user_status':
            $userId = intval($_POST['user_id']);
            $status = sanitize($_POST['status']);
            
            if (!in_array($status, ['active', 'inactive'])) {
                $errors[] = 'Status inválido.';
                break;
            }
            
            $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'customer'");
            if ($stmt->execute([$status, $userId])) {
                flashMessage('Status do usuário atualizado!', 'success');
            } else {
                $errors[] = 'Erro ao atualizar status do usuário.';
            }
            redirect('usuarios.php');
            break;
            
        case 'promote_user':
            if (!isAdmin()) {
                $errors[] = 'Apenas administradores podem promover usuários.';
                break;
            }
            
            $userId = intval($_POST['user_id']);
            $newRole = sanitize($_POST['new_role']);
            
            if (!in_array($newRole, ['customer', 'moderator', 'admin'])) {
                $errors[] = 'Cargo inválido.';
                break;
            }
            
            $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
            if ($stmt->execute([$newRole, $userId])) {
                flashMessage('Cargo do usuário atualizado!', 'success');
            } else {
                $errors[] = 'Erro ao atualizar cargo do usuário.';
            }
            redirect('usuarios.php');
            break;
    }
}

// Filtros
$search = sanitize($_GET['search'] ?? '');
$role = sanitize($_GET['role'] ?? '');
$status = sanitize($_GET['status'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Construir query
$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (nome LIKE ? OR sobrenome LIKE ? OR email LIKE ?)";
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if (!empty($role)) {
    $where .= " AND role = ?";
    $params[] = $role;
}

if (!empty($status)) {
    $where .= " AND status = ?";
    $params[] = $status;
}

// Buscar usuários
$sql = "
    SELECT u.*, 
           COUNT(DISTINCT o.id) as total_pedidos,
           COALESCE(SUM(CASE WHEN o.status != 'cancelled' THEN o.total ELSE 0 END), 0) as total_gasto,
           MAX(o.created_at) as ultimo_pedido
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id
    {$where}
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT {$limit} OFFSET {$offset}
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

// Contar total
$countSql = "SELECT COUNT(*) FROM users {$where}";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalUsuarios = $countStmt->fetchColumn();
$totalPages = ceil($totalUsuarios / $limit);

// Estatísticas
$stmt = $db->prepare("
    SELECT 
        role,
        status,
        COUNT(*) as count
    FROM users 
    GROUP BY role, status
    ORDER BY role, status
");
$stmt->execute();
$stats = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Usuários - Admin Gourmeria</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS base do admin */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background-color: #000; color: #fff; line-height: 1.6; }
        .main-content { margin-left: 250px; padding: 30px; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #111;
            border: 2px solid #333;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #DAA520;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #ccc;
            font-size: 12px;
            text-transform: uppercase;
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
            grid-template-columns: 1fr 150px 150px auto;
            gap: 15px;
            align-items: end;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #DAA520;
            font-weight: bold;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #333;
            border-radius: 5px;
            background: #000;
            color: #fff;
            font-size: 14px;
        }
        
        .btn {
            padding: 10px 20px;
            background: #DAA520;
            color: #000;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn:hover { background: #B8860B; }
        
        .users-table {
            background: #111;
            border: 2px solid #333;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #333;
        }
        
        .table th {
            background: #222;
            color: #DAA520;
            font-weight: bold;
        }
        
        .table tr:hover {
            background: #222;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: #DAA520;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #000;
        }
        
        .user-details h4 {
            color: #DAA520;
            margin-bottom: 3px;
        }
        
        .user-details p {
            color: #ccc;
            font-size: 13px;
        }
        
        .user-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #28a745;
            color: #fff;
        }
        
        .status-inactive {
            background: #dc3545;
            color: #fff;
        }
        
        .role-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .role-customer {
            background: #6c757d;
            color: #fff;
        }
        
        .role-moderator {
            background: #17a2b8;
            color: #fff;
        }
        
        .role-admin {
            background: #DAA520;
            color: #000;
        }
        
        .actions {
            display: flex;
            gap: 5px;
        }
        
        .btn-small {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .btn-danger {
            background: #dc3545;
            color: #fff;
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
            max-width: 400px;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            background: #111;
            color: #fff;
            text-decoration: none;
            border: 1px solid #333;
            border-radius: 4px;
        }
        
        .pagination a:hover { border-color: #DAA520; }
        .pagination .current { background: #DAA520; color: #000; }
    </style>
</head>
<body>
    <!-- Sidebar seria incluído aqui -->
    
    <main class="main-content">
        <div class="header">
            <h1 class="page-title">
                <i class="fas fa-users"></i> Gestão de Usuários
            </h1>
        </div>
        
        <!-- Estatísticas -->
        <div class="stats-grid">
            <?php
            $roleLabels = [
                'customer' => 'Clientes',
                'moderator' => 'Moderadores', 
                'admin' => 'Administradores'
            ];
            
            $statusCounts = [];
            foreach ($stats as $stat) {
                $roleLabel = $roleLabels[$stat['role']] ?? $stat['role'];
                $statusCounts[$roleLabel][$stat['status']] = $stat['count'];
            }
            
            foreach ($statusCounts as $role => $statuses):
                $total = array_sum($statuses);
            ?>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total; ?></div>
                    <div class="stat-label"><?php echo $role; ?></div>
                    <?php if (isset($statuses['active']) && isset($statuses['inactive'])): ?>
                        <div style="font-size: 11px; color: #666; margin-top: 5px;">
                            <?php echo $statuses['active']; ?> ativos, <?php echo $statuses['inactive']; ?> inativos
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Filtros -->
        <div class="filters">
            <form method="GET">
                <div class="filters-row">
                    <div class="form-group">
                        <label for="search">Buscar por nome ou e-mail:</label>
                        <input 
                            type="text" 
                            id="search" 
                            name="search" 
                            placeholder="Digite sua busca..."
                            value="<?php echo htmlspecialchars($search); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Cargo:</label>
                        <select id="role" name="role">
                            <option value="">Todos</option>
                            <option value="customer" <?php echo $role == 'customer' ? 'selected' : ''; ?>>Cliente</option>
                            <option value="moderator" <?php echo $role == 'moderator' ? 'selected' : ''; ?>>Moderador</option>
                            <option value="admin" <?php echo $role == 'admin' ? 'selected' : ''; ?>>Administrador</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select id="status" name="status">
                            <option value="">Todos</option>
                            <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Ativo</option>
                            <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inativo</option>
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
        
        <!-- Tabela de Usuários -->
        <div class="users-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Cargo</th>
                        <th>Status</th>
                        <th>Pedidos</th>
                        <th>Total Gasto</th>
                        <th>Último Pedido</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #666; padding: 40px;">
                                Nenhum usuário encontrado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($usuario['nome'], 0, 1)); ?>
                                        </div>
                                        <div class="user-details">
                                            <h4><?php echo htmlspecialchars($usuario['nome'] . ' ' . $usuario['sobrenome']); ?></h4>
                                            <p><?php echo htmlspecialchars($usuario['email']); ?></p>
                                            <p>Desde: <?php echo date('d/m/Y', strtotime($usuario['created_at'])); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge role-<?php echo $usuario['role']; ?>">
                                        <?php echo $roleLabels[$usuario['role']] ?? $usuario['role']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="user-status status-<?php echo $usuario['status']; ?>">
                                        <?php echo $usuario['status'] == 'active' ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                                <td><?php echo $usuario['total_pedidos']; ?></td>
                                <td><?php echo formatPrice($usuario['total_gasto']); ?></td>
                                <td>
                                    <?php if ($usuario['ultimo_pedido']): ?>
                                        <?php echo date('d/m/Y', strtotime($usuario['ultimo_pedido'])); ?>
                                    <?php else: ?>
                                        <span style="color: #666;">Nunca</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <?php if ($usuario['role'] == 'customer'): ?>
                                            <button type="button" class="btn btn-small" 
                                                onclick="updateStatus(<?php echo $usuario['id']; ?>, '<?php echo $usuario['status'] == 'active' ? 'inactive' : 'active'; ?>')">
                                                <i class="fas fa-<?php echo $usuario['status'] == 'active' ? 'pause' : 'play'; ?>"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (isAdmin() && $usuario['id'] != $_SESSION['user_id']): ?>
                                            <button type="button" class="btn btn-small" onclick="promoteUser(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['nome']); ?>', '<?php echo $usuario['role']; ?>')">
                                                <i class="fas fa-user-cog"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginação -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Anterior</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Próxima</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Modal Atualizar Status -->
    <div class="modal" id="statusModal">
        <div class="modal-content">
            <h2 style="color: #DAA520; margin-bottom: 20px;">Alterar Status</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_user_status">
                <input type="hidden" name="user_id" id="status_user_id">
                <input type="hidden" name="status" id="new_status">
                
                <p id="status_message" style="margin-bottom: 20px;"></p>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn" onclick="closeStatusModal()" style="background: #666;">
                        Cancelar
                    </button>
                    <button type="submit" class="btn">
                        Confirmar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Promover Usuário -->
    <div class="modal" id="promoteModal">
        <div class="modal-content">
            <h2 style="color: #DAA520; margin-bottom: 20px;">Alterar Cargo</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="promote_user">
                <input type="hidden" name="user_id" id="promote_user_id">
                
                <p id="promote_message" style="margin-bottom: 20px;"></p>
                
                <div class="form-group">
                    <label for="new_role">Novo Cargo:</label>
                    <select id="new_role" name="new_role" required>
                        <option value="customer">Cliente</option>
                        <option value="moderator">Moderador</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn" onclick="closePromoteModal()" style="background: #666;">
                        Cancelar
                    </button>
                    <button type="submit" class="btn">
                        Alterar Cargo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updateStatus(userId, newStatus) {
            document.getElementById('status_user_id').value = userId;
            document.getElementById('new_status').value = newStatus;
            
            const action = newStatus === 'active' ? 'ativar' : 'desativar';
            document.getElementById('status_message').textContent = `Tem certeza que deseja ${action} este usuário?`;
            
            document.getElementById('statusModal').style.display = 'block';
        }
        
        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
        }
        
        function promoteUser(userId, userName, currentRole) {
            document.getElementById('promote_user_id').value = userId;
            document.getElementById('promote_message').textContent = `Alterar cargo de ${userName}:`;
            document.getElementById('new_role').value = currentRole;
            
            document.getElementById('promoteModal').style.display = 'block';
        }
        
        function closePromoteModal() {
            document.getElementById('promoteModal').style.display = 'none';
        }
        
        // Fechar modais clicando fora
        window.onclick = function(event) {
            const statusModal = document.getElementById('statusModal');
            const promoteModal = document.getElementById('promoteModal');
            
            if (event.target === statusModal) {
                closeStatusModal();
            }
            if (event.target === promoteModal) {
                closePromoteModal();
            }
        }
    </script>
</body>
</html>