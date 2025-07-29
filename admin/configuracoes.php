<?php
// ============================================
// ARQUIVO: admin/configuracoes.php - Configurações do Site
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

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = sanitize($_POST['action']);
    
    if ($action == 'update_settings') {
        $settings = [
            'site_name' => sanitize($_POST['site_name']),
            'site_description' => sanitize($_POST['site_description']),
            'email_contato' => sanitize($_POST['email_contato']),
            'telefone_contato' => sanitize($_POST['telefone_contato']),
            'endereco_retirada' => sanitize($_POST['endereco_retirada']),
            'horario_funcionamento' => sanitize($_POST['horario_funcionamento']),
            'frete_gratuito_valor' => floatval($_POST['frete_gratuito_valor']),
            'taxa_yamato' => floatval($_POST['taxa_yamato']),
            'currency' => sanitize($_POST['currency']),
            'currency_symbol' => sanitize($_POST['currency_symbol'])
        ];
        
        // Validações básicas
        if (empty($settings['site_name'])) {
            $errors[] = 'Nome do site é obrigatório.';
        }
        
        if (!validateEmail($settings['email_contato'])) {
            $errors[] = 'E-mail de contato inválido.';
        }
        
        if ($settings['frete_gratuito_valor'] < 0) {
            $errors[] = 'Valor para frete grátis deve ser positivo.';
        }
        
        if ($settings['taxa_yamato'] < 0) {
            $errors[] = 'Taxa do Yamato deve ser positiva.';
        }
        
        if (empty($errors)) {
            try {
                $db->getConnection()->beginTransaction();
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("
                        INSERT INTO site_settings (setting_key, setting_value) 
                        VALUES (?, ?) 
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ");
                    $stmt->execute([$key, $value]);
                }
                
                $db->getConnection()->commit();
                flashMessage('Configurações atualizadas com sucesso!', 'success');
                redirect('configuracoes.php');
            } catch (Exception $e) {
                $db->getConnection()->rollback();
                $errors[] = 'Erro ao salvar configurações.';
            }
        }
    }
}

// Buscar configurações atuais
$stmt = $db->prepare("SELECT setting_key, setting_value FROM site_settings");
$stmt->execute();
$currentSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Definir valores padrão se não existirem
$defaults = [
    'site_name' => 'Gourmeria',
    'site_description' => 'Doces Gourmet para Brasileiros no Japão',
    'email_contato' => 'contato@gourmeria.jp',
    'telefone_contato' => '+81-XX-XXXX-XXXX',
    'endereco_retirada' => 'Hamamatsu, Shizuoka',
    'horario_funcionamento' => '9:00 às 18:00',
    'frete_gratuito_valor' => '5000',
    'taxa_yamato' => '500',
    'currency' => 'JPY',
    'currency_symbol' => '¥'
];

$settings = array_merge($defaults, $currentSettings);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Admin Gourmeria</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS base do admin */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background-color: #000; color: #fff; line-height: 1.6; }
        .main-content { margin-left: 250px; padding: 30px; }
        
        .settings-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .settings-section {
            background: #111;
            border: 2px solid #333;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .section-title {
            color: #DAA520;
            font-size: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #333;
            padding-bottom: 15px;
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
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #333;
            border-radius: 5px;
            background: #000;
            color: #fff;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #DAA520;
        }
        
        .form-help {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .btn {
            padding: 15px 30px;
            background: #DAA520;
            color: #000;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn:hover {
            background: #B8860B;
            transform: translateY(-2px);
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
        
        .info-box {
            background: #222;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .info-box h4 {
            color: #DAA520;
            margin-bottom: 10px;
        }
        
        .info-box p {
            color: #ccc;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .currency-preview {
            background: #333;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
            font-family: monospace;
            color: #DAA520;
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
        
        .backup-section {
            background: #333;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .backup-actions {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <!-- Sidebar seria incluído aqui -->
    
    <main class="main-content">
        <div class="header">
            <h1 class="page-title">
                <i class="fas fa-cog"></i> Configurações do Site
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
        
        <div class="settings-container">
            <form method="POST">
                <input type="hidden" name="action" value="update_settings">
                
                <!-- Informações Gerais -->
                <div class="settings-section">
                    <h2 class="section-title">
                        <i class="fas fa-info-circle"></i> Informações Gerais
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="site_name">Nome do Site *</label>
                            <input 
                                type="text" 
                                id="site_name" 
                                name="site_name" 
                                value="<?php echo htmlspecialchars($settings['site_name']); ?>"
                                required
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="email_contato">E-mail de Contato *</label>
                            <input 
                                type="email" 
                                id="email_contato" 
                                name="email_contato" 
                                value="<?php echo htmlspecialchars($settings['email_contato']); ?>"
                                required
                            >
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="site_description">Descrição do Site</label>
                        <textarea 
                            id="site_description" 
                            name="site_description" 
                            rows="3"
                        ><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                        <div class="form-help">Descrição que aparece nos resultados de busca</div>
                    </div>
                </div>
                
                <!-- Contato e Localização -->
                <div class="settings-section">
                    <h2 class="section-title">
                        <i class="fas fa-map-marker-alt"></i> Contato e Localização
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="telefone_contato">Telefone de Contato</label>
                            <input 
                                type="text" 
                                id="telefone_contato" 
                                name="telefone_contato" 
                                value="<?php echo htmlspecialchars($settings['telefone_contato']); ?>"
                                placeholder="+81-XX-XXXX-XXXX"
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="horario_funcionamento">Horário de Funcionamento</label>
                            <input 
                                type="text" 
                                id="horario_funcionamento" 
                                name="horario_funcionamento" 
                                value="<?php echo htmlspecialchars($settings['horario_funcionamento']); ?>"
                                placeholder="9:00 às 18:00"
                            >
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="endereco_retirada">Endereço para Retirada</label>
                        <input 
                            type="text" 
                            id="endereco_retirada" 
                            name="endereco_retirada" 
                            value="<?php echo htmlspecialchars($settings['endereco_retirada']); ?>"
                            placeholder="Hamamatsu, Shizuoka"
                        >
                        <div class="form-help">Endereço onde os clientes podem retirar os produtos</div>
                    </div>
                </div>
                
                <!-- Configurações de Entrega -->
                <div class="settings-section">
                    <h2 class="section-title">
                        <i class="fas fa-shipping-fast"></i> Configurações de Entrega
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="taxa_yamato">Taxa de Entrega Yamato (¥)</label>
                            <input 
                                type="number" 
                                id="taxa_yamato" 
                                name="taxa_yamato" 
                                step="0.01" 
                                min="0"
                                value="<?php echo htmlspecialchars($settings['taxa_yamato']); ?>"
                            >
                            <div class="form-help">Valor cobrado para entrega via Yamato</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="frete_gratuito_valor">Valor para Frete Grátis (¥)</label>
                            <input 
                                type="number" 
                                id="frete_gratuito_valor" 
                                name="frete_gratuito_valor" 
                                step="0.01" 
                                min="0"
                                value="<?php echo htmlspecialchars($settings['frete_gratuito_valor']); ?>"
                            >
                            <div class="form-help">Valor mínimo para frete grátis (0 = sempre cobrar frete)</div>
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <h4>Como funciona o frete:</h4>
                        <p>• Se o valor do pedido for maior ou igual ao "Valor para Frete Grátis", o frete será gratuito</p>
                        <p>• Caso contrário, será cobrada a "Taxa de Entrega Yamato"</p>
                        <p>• Retiradas no local são sempre gratuitas</p>
                    </div>
                </div>
                
                <!-- Configurações de Moeda -->
                <div class="settings-section">
                    <h2 class="section-title">
                        <i class="fas fa-yen-sign"></i> Configurações de Moeda
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="currency">Código da Moeda</label>
                            <select id="currency" name="currency">
                                <option value="JPY" <?php echo $settings['currency'] == 'JPY' ? 'selected' : ''; ?>>JPY - Iene Japonês</option>
                                <option value="BRL" <?php echo $settings['currency'] == 'BRL' ? 'selected' : ''; ?>>BRL - Real Brasileiro</option>
                                <option value="USD" <?php echo $settings['currency'] == 'USD' ? 'selected' : ''; ?>>USD - Dólar Americano</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="currency_symbol">Símbolo da Moeda</label>
                            <input 
                                type="text" 
                                id="currency_symbol" 
                                name="currency_symbol" 
                                value="<?php echo htmlspecialchars($settings['currency_symbol']); ?>"
                                maxlength="5"
                            >
                        </div>
                    </div>
                    
                    <div class="currency-preview">
                        <strong>Preview:</strong> <span id="preview_price"><?php echo $settings['currency_symbol']; ?>1,250</span>
                    </div>
                </div>
                
                <!-- Botões de Ação -->
                <div style="text-align: center; margin-top: 40px;">
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Salvar Configurações
                    </button>
                </div>
            </form>
            
            <!-- Seção de Backup/Informações do Sistema -->
            <div class="settings-section">
                <h2 class="section-title">
                    <i class="fas fa-database"></i> Informações do Sistema
                </h2>
                
                <div class="info-box">
                    <h4>Informações do Banco de Dados:</h4>
                    <p><strong>Host:</strong> <?php echo DB_HOST; ?></p>
                    <p><strong>Database:</strong> <?php echo DB_NAME; ?></p>
                    <p><strong>Usuário:</strong> <?php echo DB_USER; ?></p>
                </div>
                
                <div class="backup-section">
                    <h4 style="color: #DAA520; margin-bottom: 15px;">Backup e Manutenção</h4>
                    <p style="color: #ccc; margin-bottom: 15px;">
                        Para backup do banco de dados, utilize as ferramentas da sua hospedagem ou acesse o phpMyAdmin.
                    </p>
                    
                    <div class="backup-actions">
                        <a href="../" class="btn btn-outline" target="_blank">
                            <i class="fas fa-external-link-alt"></i> Ver Site
                        </a>
                        
                        <a href="https://cpanel.hostgator.com.br" class="btn btn-outline" target="_blank">
                            <i class="fas fa-server"></i> Acessar cPanel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Atualizar preview da moeda
        function updateCurrencyPreview() {
            const symbol = document.getElementById('currency_symbol').value || '¥';
            document.getElementById('preview_price').textContent = symbol + '1,250';
        }
        
        document.getElementById('currency_symbol').addEventListener('input', updateCurrencyPreview);
        
        // Auto-atualizar símbolo baseado na moeda selecionada
        document.getElementById('currency').addEventListener('change', function() {
            const symbols = {
                'JPY': '¥',
                'BRL': 'R$',
                'USD': '$'
            };
            
            const selectedCurrency = this.value;
            if (symbols[selectedCurrency]) {
                document.getElementById('currency_symbol').value = symbols[selectedCurrency];
                updateCurrencyPreview();
            }
        });
        
        // Validações em tempo real
        document.getElementById('frete_gratuito_valor').addEventListener('input', function() {
            const value = parseFloat(this.value);
            if (value < 0) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#333';
            }
        });
        
        document.getElementById('taxa_yamato').addEventListener('input', function() {
            const value = parseFloat(this.value);
            if (value < 0) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#333';
            }
        });
    </script>
</body>
</html>