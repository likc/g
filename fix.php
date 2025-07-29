<?php
// ============================================
// SCRIPT PARA CORRIGIR SISTEMA DE SENHAS
// Execute uma única vez para remover criptografia
// ============================================

require_once 'config.php';

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Correção de Senhas - Gourmeria</title>
    <style>
        body { font-family: Arial, sans-serif; background: #0A0A0A; color: #fff; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .alert { padding: 15px; margin: 10px 0; border-radius: 5px; }
        .alert-success { background: #28a745; }
        .alert-error { background: #dc3545; }
        .alert-warning { background: #ffc107; color: #000; }
        .alert-info { background: #17a2b8; }
        h1 { color: #DAA520; }
        .code { background: #1a1a1a; padding: 10px; border-radius: 5px; font-family: monospace; }
    </style>
</head>
<body>
<div class='container'>
<h1>🔧 Correção do Sistema de Senhas - Gourmeria</h1>";

// Verificar se é admin
session_start();
if (!isset($_SESSION['user_id']) || !isModerator()) {
    echo "<div class='alert alert-error'>❌ Acesso negado. Faça login como administrador.</div>";
    echo "<a href='login.php' style='color: #DAA520;'>Fazer Login</a>";
    echo "</div></body></html>";
    exit;
}

try {
    echo "<div class='alert alert-info'>ℹ️ Iniciando correção do sistema de senhas...</div>";
    
    // 1. Backup da tabela users atual
    echo "<h3>1. Criando backup da tabela users...</h3>";
    $db->exec("CREATE TABLE IF NOT EXISTS users_backup_" . date('Ymd_His') . " AS SELECT * FROM users");
    echo "<div class='alert alert-success'>✅ Backup criado com sucesso!</div>";
    
    // 2. Verificar usuários com senhas criptografadas
    echo "<h3>2. Verificando usuários com senhas criptografadas...</h3>";
    $stmt = $db->query("SELECT id, email, password FROM users WHERE LENGTH(password) > 20");
    $usersWithHashedPasswords = $stmt->fetchAll();
    
    echo "<div class='alert alert-info'>📊 Encontrados " . count($usersWithHashedPasswords) . " usuários com senhas criptografadas.</div>";
    
    if (count($usersWithHashedPasswords) > 0) {
        echo "<div class='alert alert-warning'>
            ⚠️ <strong>ATENÇÃO:</strong> Para usuários com senhas criptografadas, será necessário resetar manualmente.
            <br>As senhas serão definidas como 'temp123' temporariamente.
            <br>Oriente os usuários a alterarem suas senhas no primeiro login.
        </div>";
        
        // Resetar senhas criptografadas para temporária
        foreach ($usersWithHashedPasswords as $user) {
            $stmt = $db->prepare("UPDATE users SET password = 'temp123' WHERE id = ?");
            $stmt->execute([$user['id']]);
            echo "<div style='color: #ffc107;'>🔄 Usuário {$user['email']}: senha resetada para 'temp123'</div>";
        }
    }
    
    // 3. Atualizar funções no config.php
    echo "<h3>3. Instruções para atualizar config.php:</h3>";
    echo "<div class='alert alert-warning'>
        <strong>📝 Atualize as seguintes funções no seu config.php:</strong>
        <div class='code'>
// REMOVER a função hashPassword() e substituir por:
function hashPassword(\$password) {
    return \$password; // Retorna senha sem criptografia
}

// REMOVER a função verifyPassword() e substituir por:
function verifyPassword(\$password, \$hash) {
    return \$password === \$hash; // Comparação direta
}
        </div>
    </div>";
    
    // 4. Configuração do Mailgun
    echo "<h3>4. Configuração do Mailgun:</h3>";
    echo "<div class='alert alert-info'>
        <strong>📧 Para configurar o Mailgun, adicione no config.php:</strong>
        <div class='code'>
// Configurações do Mailgun
define('MAILGUN_API_KEY', 'sua-api-key-do-mailgun');
define('MAILGUN_DOMAIN', 'seu-dominio.mailgun.org');
define('MAILGUN_FROM_EMAIL', 'noreply@gourmeria.jp');
define('MAILGUN_FROM_NAME', 'Gourmeria');

// Função para enviar e-mail via Mailgun
function sendEmailViaMailgun(\$to, \$subject, \$message, \$isHtml = true) {
    \$ch = curl_init();
    curl_setopt(\$ch, CURLOPT_URL, 'https://api.mailgun.net/v3/' . MAILGUN_DOMAIN . '/messages');
    curl_setopt(\$ch, CURLOPT_USERPWD, 'api:' . MAILGUN_API_KEY);
    curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt(\$ch, CURLOPT_POST, true);
    curl_setopt(\$ch, CURLOPT_POSTFIELDS, array(
        'from' => MAILGUN_FROM_NAME . ' <' . MAILGUN_FROM_EMAIL . '>',
        'to' => \$to,
        'subject' => \$subject,
        'html' => \$isHtml ? \$message : null,
        'text' => \$isHtml ? null : \$message
    ));
    
    \$result = curl_exec(\$ch);
    curl_close(\$ch);
    
    return json_decode(\$result, true);
}

// Função para e-mail de boas-vindas da newsletter
function sendWelcomeEmail(\$email, \$nome = '') {
    \$subject = '🎉 Bem-vindo à Newsletter da Gourmeria!';
    \$message = '
    <h2>Olá' . (\$nome ? \" \$nome\" : '') . '!</h2>
    <p>Seja bem-vindo à nossa newsletter! 🍰</p>
    <p>Você receberá as melhores ofertas e novidades dos nossos doces gourmet brasileiros no Japão.</p>
    <p>Obrigado por fazer parte da família Gourmeria!</p>
    <hr>
    <p><small>Gourmeria - Doces Gourmet para Brasileiros no Japão</small></p>
    ';
    
    return sendEmailViaMailgun(\$email, \$subject, \$message);
}
        </div>
    </div>";
    
    // 5. Verificações finais
    echo "<h3>5. Verificações finais:</h3>";
    
    // Verificar estrutura da tabela newsletter
    $stmt = $db->query("SHOW COLUMNS FROM newsletter");
    $newsletterColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('created_at', $newsletterColumns)) {
        echo "<div class='alert alert-warning'>⚠️ Adicionando coluna 'created_at' na tabela newsletter...</div>";
        $db->exec("ALTER TABLE newsletter ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "<div class='alert alert-success'>✅ Coluna adicionada!</div>";
    }
    
    // Verificar tabela de logs
    $stmt = $db->query("SHOW TABLES LIKE 'activity_logs'");
    if (!$stmt->fetch()) {
        echo "<div class='alert alert-warning'>⚠️ Criando tabela de logs de atividade...</div>";
        $db->exec("
            CREATE TABLE activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                action VARCHAR(100),
                description TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_action (action),
                INDEX idx_created_at (created_at)
            )
        ");
        echo "<div class='alert alert-success'>✅ Tabela de logs criada!</div>";
    }
    
    echo "<div class='alert alert-success'>
        <h4>🎉 Correção concluída com sucesso!</h4>
        <strong>Próximos passos:</strong>
        <ol>
            <li>Atualize o config.php com as funções mostradas acima</li>
            <li>Configure suas credenciais do Mailgun</li>
            <li>Teste o sistema de login</li>
            <li>Teste o cadastro na newsletter</li>
            <li>Oriente usuários com senhas 'temp123' a alterarem suas senhas</li>
        </ol>
    </div>";
    
    echo "<div style='margin-top: 30px;'>
        <a href='index.php' style='background: #DAA520; color: #000; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
            🏠 Voltar ao Site
        </a>
        <a href='admin/' style='background: #17a2b8; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; margin-left: 10px;'>
            🔧 Painel Admin
        </a>
    </div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-error'>❌ Erro: " . $e->getMessage() . "</div>";
}

echo "</div>
<script>
// Auto-refresh do status
setTimeout(() => {
    document.querySelector('h1').innerHTML += ' ✅ Concluído';
}, 1000);
</script>
</body>
</html>";
?>