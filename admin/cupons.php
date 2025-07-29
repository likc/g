<?php
// ============================================
// ARQUIVO: admin/cupons.php - Gestão de Cupons
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
        case 'create_coupon':
            $codigo = strtoupper(sanitize($_POST['codigo']));
            $tipo = sanitize($_POST['tipo']);
            $valor = floatval($_POST['valor']);
            $valorMinimo = floatval($_POST['valor_minimo'] ?? 0);
            $limiteUso = !empty($_POST['limite_uso']) ? intval($_POST['limite_uso']) : null;
            $dataInicio = !empty($_POST['data_inicio']) ? $_POST['data_inicio'] : null;
            $dataFim = !empty($_POST['data_fim']) ? $_POST['data_fim'] : null;
            $descricao = sanitize($_POST['descricao']);
            
            // Validações
            if (empty($codigo) || strlen($codigo) < 3) {
                $errors[] = 'Código deve ter pelo menos 3 caracteres.';
            }
            
            if (!in_array($tipo, ['percentage', 'fixed'])) {
                $errors[] = 'Tipo de cupom inválido.';
            }
            
            if ($valor <= 0) {
                $errors[] = 'Valor deve ser maior que zero.';
            }
            
            if ($tipo == 'percentage' && $valor > 100) {
                $errors[] = 'Porcentagem não pode ser maior que 100%.';
            }
            
            // Verificar se código já existe
            $stmt = $db->prepare("SELECT id FROM coupons WHERE codigo = ?");
            $stmt->execute([$codigo]);
            if ($stmt->fetch()) {
                $errors[] = 'Este código já existe.';
            }
            
            if (empty($errors)) {
                $stmt = $db->prepare("
                    INSERT INTO coupons (codigo, tipo, valor, valor_minimo, limite_uso, data_inicio, data_fim, descricao) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                if ($stmt->execute([$codigo, $tipo, $valor, $valorMinimo, $limiteUso, $dataInicio, $dataFim, $descricao])) {
                    flashMessage('Cupom criado com sucesso!', 'success');
                    redirect('cupons.php');
                } else {
                    $errors[] = 'Erro ao criar cupom.';
                }
            }
            break;
            
        case 'toggle_coupon':
            $couponId = intval($_POST['coupon_id']);
            $ativo = intval($_POST['ativo']);
            
            $stmt = $db->prepare("UPDATE coupons SET ativo = ? WHERE id = ?");
            if ($stmt->execute([$ativo, $couponId])) {
                flashMessage('Status do cupom atualizado!', 'success');
            } else {
                $errors[] = 'Erro ao atualizar cupom.';
            }
            redirect('cupons.php');
            break;
            
        case 'delete_coupon':
            $couponId = intval($_POST['coupon_id']);
            
            $stmt = $db->prepare("DELETE FROM coupons WHERE id = ?");
            if ($stmt->execute([$couponId])) {
                flashMessage('Cupom removido com sucesso!', 'success');
            } else {
                $errors[] = 'Erro ao remover cupom.';
            }
            redirect('cupons.php');
            break;
    }
}

// Buscar cupons
$stmt = $db->prepare("
    SELECT *, 
           (CASE 
               WHEN data_fim < CURDATE() THEN 'Expirado'
               WHEN data_inicio > CURDATE() THEN 'Futuro'
               WHEN limite_uso IS NOT NULL AND usado >= limite_uso THEN 'Esgotado'
               WHEN ativo = 0 THEN 'Inativo'
               ELSE 'Ativo'
           END) as status_calculado
    FROM coupons 
    ORDER BY created_at DESC
");
$stmt->execute();
$cupons = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Cupons - Admin Gourmeria</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS base do admin */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background-color: #000; color: #fff; line-height: 1.6; }
        .main-content { margin-left: 250px; padding: 30px; }
        
        .create-coupon {
            background: #111;
            border: 2px solid #333;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
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
        
        .coupons-grid {
            display: grid;
            gap: 20px;
        }
        
        .coupon-card {
            background: #111;
            border: 2px solid #333;
            border-radius: 10px;
            padding: 25px;
            transition: border-color 0.3s;
        }
        
        .coupon-card:hover {
            border-color: #DAA520;
        }
        
        .coupon-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .coupon-code {
            font-size: 24px;
            font-weight: bold;
            color: #DAA520;
            font-family: 'Courier New', monospace;
            background: #222;
            padding: 10px 15px;
            border-radius: 8px;
            border: 2px dashed #DAA520;
        }
        
        .coupon-status {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-ativo {
            background: #28a745;
            color: #fff;
        }
        
        .status-inativo {
            background: #6c757d;
            color: #fff;
        }
        
        .status-expirado {
            background: #dc3545;
            color: #fff;
        }
        
        .status-futuro {
            background: #17a2b8;
            color: #fff;
        }
        
        .status-esgotado {
            background: #ffc107;
            color: #000;
        }
        
        .coupon-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            text-align: center;
        }
        
        .detail-label {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .detail-value {
            color: #DAA520;
            font-weight: bold;
        }
        
        .coupon-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .progress-bar {
            background: #333;
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .progress-fill {
            background: #DAA520;
            height: 100%;
            transition: width 0.3s;
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
                <i class="fas fa-tags"></i> Gestão de Cupons
            </h1>
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
        
        <!-- Criar Cupom -->
        <div class="create-coupon">
            <h2 style="color: #DAA520; margin-bottom: 20px;">
                <i class="fas fa-plus"></i> Criar Novo Cupom
            </h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="create_coupon">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="codigo">Código do Cupom *</label>
                        <input 
                            type="text" 
                            id="codigo" 
                            name="codigo" 
                            placeholder="Ex: DESCONTO10" 
                            required
                            style="text-transform: uppercase;"
                            value="<?php echo htmlspecialchars($_POST['codigo'] ?? ''); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="tipo">Tipo *</label>
                        <select id="tipo" name="tipo" required onchange="updateValueLabel()">
                            <option value="percentage" <?php echo ($_POST['tipo'] ?? '') == 'percentage' ? 'selected' : ''; ?>>Porcentagem (%)</option>
                            <option value="fixed" <?php echo ($_POST['tipo'] ?? '') == 'fixed' ? 'selected' : ''; ?>>Valor Fixo (¥)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="valor" id="valor_label">Valor *</label>
                        <input 
                            type="number" 
                            id="valor" 
                            name="valor" 
                            step="0.01" 
                            min="0.01" 
                            required
                            value="<?php echo htmlspecialchars($_POST['valor'] ?? ''); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="valor_minimo">Valor Mínimo (¥)</label>
                        <input 
                            type="number" 
                            id="valor_minimo" 
                            name="valor_minimo" 
                            step="0.01" 
                            min="0"
                            value="<?php echo htmlspecialchars($_POST['valor_minimo'] ?? ''); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="limite_uso">Limite de Uso</label>
                        <input 
                            type="number" 
                            id="limite_uso" 
                            name="limite_uso" 
                            min="1"
                            placeholder="Deixe vazio para ilimitado"
                            value="<?php echo htmlspecialchars($_POST['limite_uso'] ?? ''); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="data_inicio">Data de Início</label>
                        <input 
                            type="date" 
                            id="data_inicio" 
                            name="data_inicio"
                            value="<?php echo htmlspecialchars($_POST['data_inicio'] ?? ''); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="data_fim">Data de Fim</label>
                        <input 
                            type="date" 
                            id="data_fim" 
                            name="data_fim"
                            value="<?php echo htmlspecialchars($_POST['data_fim'] ?? ''); ?>"
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="descricao">Descrição</label>
                    <textarea 
                        id="descricao" 
                        name="descricao" 
                        rows="3" 
                        placeholder="Descrição do cupom para referência interna"
                    ><?php echo htmlspecialchars($_POST['descricao'] ?? ''); ?></textarea>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-plus"></i> Criar Cupom
                </button>
            </form>
        </div>
        
        <!-- Lista de Cupons -->
        <div style="margin-bottom: 20px;">
            <h2 style="color: #DAA520;">
                <i class="fas fa-list"></i> Cupons Existentes (<?php echo count($cupons); ?>)
            </h2>
        </div>
        
        <?php if (empty($cupons)): ?>
            <div style="text-align: center; color: #666; padding: 60px;">
                <i class="fas fa-tag" style="font-size: 64px; margin-bottom: 20px;"></i>
                <h3>Nenhum cupom criado ainda</h3>
                <p>Crie seu primeiro cupom usando o formulário acima.</p>
            </div>
        <?php else: ?>
            <div class="coupons-grid">
                <?php foreach ($cupons as $cupom): ?>
                    <div class="coupon-card">
                        <div class="coupon-header">
                            <div class="coupon-code"><?php echo htmlspecialchars($cupom['codigo']); ?></div>
                            <div class="coupon-status status-<?php echo strtolower(str_replace(' ', '', $cupom['status_calculado'])); ?>">
                                <?php echo $cupom['status_calculado']; ?>
                            </div>
                        </div>
                        
                        <div class="coupon-details">
                            <div class="detail-item">
                                <div class="detail-label">Desconto</div>
                                <div class="detail-value">
                                    <?php 
                                    if ($cupom['tipo'] == 'percentage') {
                                        echo $cupom['valor'] . '%';
                                    } else {
                                        echo formatPrice($cupom['valor']);
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Valor Mínimo</div>
                                <div class="detail-value">
                                    <?php echo $cupom['valor_minimo'] > 0 ? formatPrice($cupom['valor_minimo']) : 'Nenhum'; ?>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Usado</div>
                                <div class="detail-value">
                                    <?php echo $cupom['usado']; ?>
                                    <?php if ($cupom['limite_uso']): ?>
                                        / <?php echo $cupom['limite_uso']; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">Válido até</div>
                                <div class="detail-value">
                                    <?php echo $cupom['data_fim'] ? date('d/m/Y', strtotime($cupom['data_fim'])) : 'Sem limite'; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($cupom['limite_uso'] && $cupom['usado'] > 0): ?>
                            <div>
                                <div class="detail-label">Progresso de Uso</div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo ($cupom['usado'] / $cupom['limite_uso']) * 100; ?>%"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($cupom['descricao']): ?>
                            <div style="margin-top: 15px; color: #ccc; font-style: italic;">
                                <?php echo htmlspecialchars($cupom['descricao']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="coupon-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_coupon">
                                <input type="hidden" name="coupon_id" value="<?php echo $cupom['id']; ?>">
                                <input type="hidden" name="ativo" value="<?php echo $cupom['ativo'] ? 0 : 1; ?>">
                                <button type="submit" class="btn" style="background: <?php echo $cupom['ativo'] ? '#6c757d' : '#28a745'; ?>;">
                                    <i class="fas fa-<?php echo $cupom['ativo'] ? 'pause' : 'play'; ?>"></i>
                                    <?php echo $cupom['ativo'] ? 'Desativar' : 'Ativar'; ?>
                                </button>
                            </form>
                            
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja remover este cupom?')">
                                <input type="hidden" name="action" value="delete_coupon">
                                <input type="hidden" name="coupon_id" value="<?php echo $cupom['id']; ?>">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        function updateValueLabel() {
            const tipo = document.getElementById('tipo').value;
            const label = document.getElementById('valor_label');
            
            if (tipo === 'percentage') {
                label.textContent = 'Porcentagem (%) *';
            } else {
                label.textContent = 'Valor Fixo (¥) *';
            }
        }
        
        // Inicializar label
        updateValueLabel();
        
        // Gerar código aleatório
        function generateRandomCode() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let result = '';
            for (let i = 0; i < 8; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('codigo').value = result;
        }
        
        // Adicionar botão para gerar código
        document.addEventListener('DOMContentLoaded', function() {
            const codigoField = document.getElementById('codigo');
            const generateBtn = document.createElement('button');
            generateBtn.type = 'button';
            generateBtn.className = 'btn';
            generateBtn.style.marginTop = '5px';
            generateBtn.innerHTML = '<i class="fas fa-random"></i> Gerar Código';
            generateBtn.onclick = generateRandomCode;
            
            codigoField.parentNode.appendChild(generateBtn);
        });
    </script>
</body>
</html>