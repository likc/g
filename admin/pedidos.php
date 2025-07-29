<?php
// ============================================
// ARQUIVO: admin/pedidos.php - Gestão de Pedidos
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
    $orderId = intval($_POST['order_id']);
    
    switch ($action) {
        case 'update_status':
            $newStatus = sanitize($_POST['status']);
            $trackingCode = sanitize($_POST['tracking_code'] ?? '');
            
            $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
            if (!in_array($newStatus, $validStatuses)) {
                $errors[] = 'Status inválido.';
                break;
            }
            
            $updateFields = ['status = ?'];
            $params = [$newStatus];
            
            if ($newStatus == 'shipped' && !empty($trackingCode)) {
                $updateFields[] = 'codigo_rastreamento = ?';
                $updateFields[] = 'data_envio = NOW()';
                $params[] = $trackingCode;
            }
            
            $params[] = $orderId;
            
            $sql = "UPDATE orders SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            
            if ($stmt->execute($params)) {
                flashMessage('Status do pedido atualizado com sucesso!', 'success');
            } else {
                $errors[] = 'Erro ao atualizar status do pedido.';
            }
            break;
    }
    
    redirect('pedidos.php');
}

// Filtros
$status = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Construir query
$where = "WHERE 1=1";
$params = [];

if (!empty($status)) {
    $where .= " AND o.status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $where .= " AND (o.order_number LIKE ? OR u.nome LIKE ? OR u.sobrenome LIKE ? OR u.email LIKE ?)";
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

// Buscar pedidos
$sql = "
    SELECT o.*, u.nome, u.sobrenome, u.email,
           COUNT(oi.id) as total_items
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    {$where}
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT {$limit} OFFSET {$offset}
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Contar total
$countSql = "SELECT COUNT(DISTINCT o.id) FROM orders o JOIN users u ON o.user_id = u.id {$where}";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalOrders = $countStmt->fetchColumn();
$totalPages = ceil($totalOrders / $limit);

// Estatísticas dos status
$stmt = $db->prepare("
    SELECT status, COUNT(*) as count 
    FROM orders 
    GROUP BY status 
    ORDER BY count DESC
");
$stmt->execute();
$statusStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Pedidos - Admin Gourmeria</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS base do admin seria incluído aqui */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background-color: #000; color: #fff; line-height: 1.6; }
        
        .main-content { margin-left: 250px; padding: 30px; }
        
        .filters {
            background: #111;
            border: 2px solid #333;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .filters-row {
            display: grid;
            grid-template-columns: 1fr 200px 200px auto;
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
        
        .status-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .status-stat {
            background: #111;
            border: 2px solid #333;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        
        .status-stat.active {
            border-color: #DAA520;
        }
        
        .orders-table {
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
        
        .order-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-pending { background: #ffc107; color: #000; }
        .status-processing { background: #17a2b8; color: #fff; }
        .status-shipped { background: #6f42c1; color: #fff; }
        .status-delivered { background: #28a745; color: #fff; }
        .status-cancelled { background: #dc3545; color: #fff; }
        
        .actions {
            display: flex;
            gap: 5px;
        }
        
        .btn-small {
            padding: 5px 10px;
            font-size: 12px;
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
                <i class="fas fa-shopping-bag"></i> Gestão de Pedidos
            </h1>
        </div>
        
        <!-- Estatísticas por Status -->
        <div class="status-stats">
            <a href="?status=" class="status-stat <?php echo empty($status) ? 'active' : ''; ?>">
                <div style="font-size: 20px; font-weight: bold; color: #DAA520;">
                    <?php echo array_sum($statusStats); ?>
                </div>
                <div style="font-size: 12px; color: #ccc;">Todos</div>
            </a>
            
            <?php
            $statusLabels = [
                'pending' => 'Pendentes',
                'processing' => 'Processando',
                'shipped' => 'Enviados',
                'delivered' => 'Entregues',
                'cancelled' => 'Cancelados'
            ];
            
            foreach ($statusLabels as $statusKey => $label):
                $count = $statusStats[$statusKey] ?? 0;
            ?>
                <a href="?status=<?php echo $statusKey; ?>" class="status-stat <?php echo $status == $statusKey ? 'active' : ''; ?>">
                    <div style="font-size: 20px; font-weight: bold; color: #DAA520;">
                        <?php echo $count; ?>
                    </div>
                    <div style="font-size: 12px; color: #ccc;"><?php echo $label; ?></div>
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Filtros -->
        <div class="filters">
            <form method="GET">
                <div class="filters-row">
                    <div class="form-group">
                        <label for="search">Buscar por número do pedido, nome ou e-mail:</label>
                        <input 
                            type="text" 
                            id="search" 
                            name="search" 
                            placeholder="Digite sua busca..."
                            value="<?php echo htmlspecialchars($search); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select id="status" name="status">
                            <option value="">Todos os status</option>
                            <?php foreach ($statusLabels as $statusKey => $label): ?>
                                <option value="<?php echo $statusKey; ?>" <?php echo $status == $statusKey ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div></div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Tabela de Pedidos -->
        <div class="orders-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>Pedido</th>
                        <th>Cliente</th>
                        <th>Data</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Itens</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #666; padding: 40px;">
                                Nenhum pedido encontrado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>
                                    <strong style="color: #DAA520;">#<?php echo htmlspecialchars($order['order_number']); ?></strong>
                                    <?php if ($order['metodo_entrega'] == 'yamato' && $order['codigo_rastreamento']): ?>
                                        <br><small style="color: #ccc;">Rastreio: <?php echo htmlspecialchars($order['codigo_rastreamento']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($order['nome'] . ' ' . $order['sobrenome']); ?></div>
                                    <small style="color: #ccc;"><?php echo htmlspecialchars($order['email']); ?></small>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($order['created_at'])); ?><br>
                                    <small style="color: #ccc;"><?php echo date('H:i', strtotime($order['created_at'])); ?></small>
                                </td>
                                <td>
                                    <span class="order-status status-<?php echo $order['status']; ?>">
                                        <?php echo $statusLabels[$order['status']] ?? $order['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo formatPrice($order['total']); ?></strong>
                                    <?php if ($order['desconto'] > 0): ?>
                                        <br><small style="color: #28a745;">-<?php echo formatPrice($order['desconto']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $order['total_items']; ?> item(s)</td>
                                <td>
                                    <div class="actions">
                                        <a href="pedido.php?id=<?php echo $order['id']; ?>" class="btn btn-small">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-small" onclick="openStatusModal(<?php echo $order['id']; ?>, '<?php echo $order['status']; ?>', '<?php echo htmlspecialchars($order['codigo_rastreamento']); ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
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
            <h2 style="color: #DAA520; margin-bottom: 20px;">Atualizar Status do Pedido</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" id="modal_order_id">
                
                <div class="form-group">
                    <label for="modal_status">Novo Status:</label>
                    <select id="modal_status" name="status" required>
                        <option value="pending">Pendente</option>
                        <option value="processing">Processando</option>
                        <option value="shipped">Enviado</option>
                        <option value="delivered">Entregue</option>
                        <option value="cancelled">Cancelado</option>
                    </select>
                </div>
                
                <div class="form-group" id="tracking_group" style="display: none;">
                    <label for="modal_tracking">Código de Rastreamento:</label>
                    <input type="text" id="modal_tracking" name="tracking_code" placeholder="Código do Yamato">
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn" onclick="closeStatusModal()" style="background: #666;">
                        Cancelar
                    </button>
                    <button type="submit" class="btn">
                        Atualizar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openStatusModal(orderId, currentStatus, trackingCode) {
            document.getElementById('modal_order_id').value = orderId;
            document.getElementById('modal_status').value = currentStatus;
            document.getElementById('modal_tracking').value = trackingCode;
            
            toggleTrackingField();
            document.getElementById('statusModal').style.display = 'block';
        }
        
        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
        }
        
        function toggleTrackingField() {
            const status = document.getElementById('modal_status').value;
            const trackingGroup = document.getElementById('tracking_group');
            
            if (status === 'shipped') {
                trackingGroup.style.display = 'block';
            } else {
                trackingGroup.style.display = 'none';
            }
        }
        
        document.getElementById('modal_status').addEventListener('change', toggleTrackingField);
        
        // Fechar modal clicando fora
        window.onclick = function(event) {
            const modal = document.getElementById('statusModal');
            if (event.target === modal) {
                closeStatusModal();
            }
        }
    </script>
</body>
</html>