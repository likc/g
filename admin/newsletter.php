
<?php
// ============================================
// ARQUIVO: admin/newsletter.php - Gestão da Newsletter
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
        case 'send_newsletter':
            $subject = sanitize($_POST['subject']);
            $message = $_POST['message']; // Permitir HTML
            $sendToAll = isset($_POST['send_to_all']);
            
            if (empty($subject) || empty($message)) {
                $errors[] = 'Assunto e mensagem são obrigatórios.';
                break;
            }
            
            // Buscar lista de emails
            if ($sendToAll) {
                $stmt = $db->prepare("SELECT email, nome FROM newsletter WHERE status = 'active' UNION SELECT email, nome FROM users WHERE role = 'customer' AND status = 'active'");
            } else {
                $stmt = $db->prepare("SELECT email, nome FROM newsletter WHERE status = 'active'");
            }
            $stmt->execute();
            $recipients = $stmt->fetchAll();
            
            if (empty($recipients)) {
                $errors[] = 'Nenhum destinatário encontrado.';
                break;
            }
            
            // Aqui você implementaria o envio real de email
            // Por simplicidade, vamos apenas simular
            $sentCount = 0;
            foreach ($recipients as $recipient) {
                // Substituir variáveis na mensagem
                $personalizedMessage = str_replace(
                    ['{{NOME}}', '{{EMAIL}}'], 
                    [$recipient['nome'] ?: 'Cliente', $recipient['email']], 
                    $message
                );
                
                // Simular envio (aqui você usaria uma biblioteca como PHPMailer)
                // mail($recipient['email'], $subject, $personalizedMessage, $headers);
                $sentCount++;
            }
            
            flashMessage("Newsletter enviada para {$sentCount} destinatário(s)!", 'success');
            redirect('newsletter.php');
            break;
            
        case 'export_emails':
            $stmt = $db->prepare("SELECT email, nome, created_at FROM newsletter WHERE status = 'active' ORDER BY created_at DESC");
            $stmt->execute();
            $emails = $stmt->fetchAll();
            
            // Gerar CSV
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="newsletter_emails_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Email', 'Nome', 'Data de Cadastro']);
            
            foreach ($emails as $email) {
                fputcsv($output, [
                    $email['email'],
                    $email['nome'] ?: '',
                    date('d/m/Y H:i', strtotime($email['created_at']))
                ]);
            }
            
            fclose($output);
            exit;
            
        case 'remove_email':
            $emailId = intval($_POST['email_id']);
            
            $stmt = $db->prepare("DELETE FROM newsletter WHERE id = ?");
            if ($stmt->execute([$emailId])) {
                flashMessage('E-mail removido da newsletter!', 'success');
            } else {
                $errors[] = 'Erro ao remover e-mail.';
            }
            redirect('newsletter.php');
            break;
    }
}

// Buscar estatísticas
$stmt = $db->prepare("SELECT COUNT(*) as total FROM newsletter WHERE status = 'active'");
$stmt->execute();
$totalSubscribers = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM newsletter WHERE status = 'unsubscribed'");
$stmt->execute();
$totalUnsubscribed = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM newsletter WHERE DATE(created_at) = CURDATE()");
$stmt->execute();
$todaySubscribers = $stmt->fetch()['total'];

// Buscar inscritos recentes
$stmt = $db->prepare("SELECT * FROM newsletter ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$recentSubscribers = $stmt->fetchAll();

// Buscar todos os emails para a lista completa
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$stmt = $db->prepare("SELECT * FROM newsletter ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}");
$stmt->execute();
$allSubscribers = $stmt->fetchAll();

$stmt = $db->prepare("SELECT COUNT(*) FROM newsletter");
$stmt->execute();
$totalPages = ceil($stmt->fetchColumn() / $limit);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão da Newsletter - Admin Gourmeria</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS base do admin */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background-color: #000; color: #fff; line-height: 1.6; }
        .main-content { margin-left: 250px; padding: 30px; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #111;
            border: 2px solid #333;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
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
            margin-bottom: 30px;
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
        
        .form-group {
            margin-bottom: 20px;
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
        
        .btn-outline {
            background: transparent;
            border: 2px solid #DAA520;
            color: #DAA520;
        }
        
        .btn-outline:hover {
            background: #DAA520;
            color: #000;
        }
        
        .btn-danger {
            background: #dc3545;
            color: #fff;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 15px 0;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        .subscriber-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #333;
        }
        
        .subscriber-item:last-child {
            border-bottom: none;
        }
        
        .subscriber-info h4 {
            color: #DAA520;
            margin-bottom: 3px;
        }
        
        .subscriber-info p {
            color: #ccc;
            font-size: 12px;
        }
        
        .subscriber-status {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #28a745;
            color: #fff;
        }
        
        .status-unsubscribed {
            background: #6c757d;
            color: #fff;
        }
        
        .newsletter-table {
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
            padding: 12px;
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
        
        .email-editor {
            min-height: 200px;
            font-family: monospace;
        }
        
        .preview-panel {
            background: #222;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 20px;
            margin-top: 15px;
        }
        
        .preview-panel h4 {
            color: #DAA520;
            margin-bottom: 15px;
        }
        
        .variable-help {
            background: #333;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
            font-size: 12px;
            color: #ccc;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
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
                <i class="fas fa-envelope"></i> Gestão da Newsletter
            </h1>
            <div>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="export_emails">
                    <button type="submit" class="btn btn-outline">
                        <i class="fas fa-download"></i> Exportar E-mails
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo number_format($totalSubscribers); ?></div>
                <div class="stat-label">Inscritos Ativos</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-number"><?php echo number_format($totalUnsubscribed); ?></div>
                <div class="stat-label">Descadastrados</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-number"><?php echo number_format($todaySubscribers); ?></div>
                <div class="stat-label">Inscrições Hoje</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-number">
                    <?php 
                    $total = $totalSubscribers + $totalUnsubscribed;
                    $rate = $total > 0 ? ($totalSubscribers / $total) * 100 : 0;
                    echo number_format($rate, 1);
                    ?>%
                </div>
                <div class="stat-label">Taxa de Retenção</div>
            </div>
        </div>
        
        <div class="content-grid">
            <!-- Enviar Newsletter -->
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">
                        <i class="fas fa-paper-plane"></i> Enviar Newsletter
                    </h2>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="send_newsletter">
                    
                    <div class="form-group">
                        <label for="subject">Assunto *</label>
                        <input type="text" id="subject" name="subject" required placeholder="Assunto da newsletter">
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Mensagem *</label>
                        <textarea id="message" name="message" rows="12" class="email-editor" required placeholder="Digite sua mensagem aqui..."></textarea>
                        
                        <div class="variable-help">
                            <strong>Variáveis disponíveis:</strong><br>
                            {{NOME}} - Nome do destinatário<br>
                            {{EMAIL}} - E-mail do destinatário
                        </div>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="send_to_all" name="send_to_all">
                        <label for="send_to_all">Enviar também para todos os clientes cadastrados</label>
                    </div>
                    
                    <button type="submit" class="btn" onclick="return confirm('Tem certeza que deseja enviar a newsletter?')">
                        <i class="fas fa-paper-plane"></i> Enviar Newsletter
                    </button>
                </form>
            </div>
            
            <!-- Inscritos Recentes -->
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">
                        <i class="fas fa-clock"></i> Inscrições Recentes
                    </h2>
                </div>
                
                <?php if (empty($recentSubscribers)): ?>
                    <p style="color: #666; text-align: center; padding: 20px;">
                        Nenhuma inscrição ainda.
                    </p>
                <?php else: ?>
                    <?php foreach ($recentSubscribers as $subscriber): ?>
                        <div class="subscriber-item">
                            <div class="subscriber-info">
                                <h4><?php echo htmlspecialchars($subscriber['email']); ?></h4>
                                <p>
                                    <?php if ($subscriber['nome']): ?>
                                        <?php echo htmlspecialchars($subscriber['nome']); ?> • 
                                    <?php endif; ?>
                                    <?php echo date('d/m/Y H:i', strtotime($subscriber['created_at'])); ?>
                                </p>
                            </div>
                            <div class="subscriber-status status-<?php echo $subscriber['status']; ?>">
                                <?php echo $subscriber['status'] == 'active' ? 'Ativo' : 'Descadastrado'; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Lista Completa de E-mails -->
        <div class="panel">
            <div class="panel-header">
                <h2 class="panel-title">
                    <i class="fas fa-list"></i> Todos os E-mails (<?php echo $totalSubscribers + $totalUnsubscribed; ?>)
                </h2>
            </div>
            
            <div class="newsletter-table">
                <table class="table">
                    <thead>
                        <tr>
                            <th>E-mail</th>
                            <th>Nome</th>
                            <th>Status</th>
                            <th>Data de Inscrição</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allSubscribers)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #666; padding: 40px;">
                                    Nenhum e-mail cadastrado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($allSubscribers as $subscriber): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($subscriber['email']); ?></td>
                                    <td><?php echo htmlspecialchars($subscriber['nome'] ?: '-'); ?></td>
                                    <td>
                                        <span class="subscriber-status status-<?php echo $subscriber['status']; ?>">
                                            <?php echo $subscriber['status'] == 'active' ? 'Ativo' : 'Descadastrado'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($subscriber['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Remover este e-mail da newsletter?')">
                                            <input type="hidden" name="action" value="remove_email">
                                            <input type="hidden" name="email_id" value="<?php echo $subscriber['id']; ?>">
                                            <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
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
                        <a href="?page=<?php echo $page - 1; ?>">Anterior</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>">Próxima</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Preview da newsletter
        document.getElementById('message').addEventListener('input', function() {
            // Aqui você poderia implementar um preview em tempo real
        });
        
        // Contar caracteres
        document.getElementById('subject').addEventListener('input', function() {
            const length = this.value.length;
            // Adicionar contador de caracteres se necessário
        });
    </script>
</body>
</html>