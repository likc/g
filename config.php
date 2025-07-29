<?php
// ============================================
// CONFIG.PHP - GOURMERIA
// Configuração principal do sistema
// ============================================

// Iniciar sessão se não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// CONFIGURAÇÕES DO BANCO DE DADOS
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'minec761_gourmeria');
define('DB_USER', 'minec761_gourmeria');
define('DB_PASS', 'p2tzoip2v018');

// ============================================
// CONFIGURAÇÕES GERAIS
// ============================================
define('SITE_URL', 'https://gourmeria.jp');
define('UPLOAD_DIR', '/uploads/');
define('UPLOAD_PATH', __DIR__ . '/uploads/');

// Configurações do Mailgun
define('MAILGUN_API_KEY', 'sua-api-key-do-mailgun'); // Substitua pela sua API key
define('MAILGUN_DOMAIN', 'seu-dominio.mailgun.org'); // Substitua pelo seu domínio
define('MAILGUN_FROM_EMAIL', 'noreply@gourmeria.jp');
define('MAILGUN_FROM_NAME', 'Gourmeria - Doces Gourmet');

// ============================================
// CONEXÃO COM BANCO DE DADOS
// ============================================
try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// ============================================
// FUNÇÕES DE AUTENTICAÇÃO
// ============================================

/**
 * Hash da senha (sem criptografia conforme solicitado)
 */
function hashPassword($password) {
    return $password; // Retorna senha sem criptografia
}

/**
 * Verificar senha (comparação direta)
 */
function verifyPassword($password, $hash) {
    return $password === $hash; // Comparação direta
}

/**
 * Verificar se usuário está logado
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Verificar se é moderador ou admin
 */
function isModerator() {
    if (!isLoggedIn()) return false;
    return in_array($_SESSION['user_role'] ?? '', ['moderator', 'admin']);
}

/**
 * Verificar se é admin
 */
function isAdmin() {
    if (!isLoggedIn()) return false;
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

// ============================================
// FUNÇÕES DE CONFIGURAÇÃO DO SITE
// ============================================

/**
 * Buscar configuração do site
 */
function getSiteSetting($key, $default = '') {
    global $db;
    static $settings = null;
    
    if ($settings === null) {
        try {
            $stmt = $db->prepare("SELECT setting_key, setting_value FROM site_settings");
            $stmt->execute();
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $settings = [];
        }
    }
    
    return $settings[$key] ?? $default;
}

/**
 * Salvar configuração do site
 */
function setSiteSetting($key, $value) {
    global $db;
    try {
        $stmt = $db->prepare("
            INSERT INTO site_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        return false;
    }
}

// ============================================
// FUNÇÕES DE UTILIDADE
// ============================================

/**
 * Sanitizar dados de entrada
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirecionar para uma página
 */
function redirect($url) {
    if (!headers_sent()) {
        header("Location: $url");
        exit;
    }
    echo "<script>window.location.href='$url';</script>";
    exit;
}

/**
 * Validar e-mail
 */
function validateEmail($email) {
    if (empty($email)) return false;
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    
    // Lista de domínios temporários (adicione mais conforme necessário)
    $disposableDomains = [
        '10minutemail.com', 'guerrillamail.com', 'mailinator.com', 
        'yopmail.com', 'tempmail.org', 'throwaway.email'
    ];
    
    $domain = substr(strrchr($email, "@"), 1);
    if (in_array(strtolower($domain), $disposableDomains)) return false;
    
    return true;
}

/**
 * Gerar token seguro
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Formatar preço em Yen
 */
function formatPrice($price) {
    return '¥' . number_format($price, 0, ',', '.');
}

// ============================================
// SISTEMA DE MENSAGENS FLASH
// ============================================

/**
 * Definir mensagem flash
 */
function flashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Obter e limpar mensagem flash
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

// ============================================
// FUNÇÕES DE E-MAIL COM MAILGUN
// ============================================

/**
 * Enviar e-mail via Mailgun
 */
function sendEmailViaMailgun($to, $subject, $message, $isHtml = true) {
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.mailgun.net/v3/' . MAILGUN_DOMAIN . '/messages');
        curl_setopt($ch, CURLOPT_USERPWD, 'api:' . MAILGUN_API_KEY);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array(
            'from' => MAILGUN_FROM_NAME . ' <' . MAILGUN_FROM_EMAIL . '>',
            'to' => $to,
            'subject' => $subject,
            'html' => $isHtml ? $message : null,
            'text' => $isHtml ? null : $message
        ));
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            return json_decode($result, true);
        } else {
            error_log('Mailgun Error: HTTP ' . $httpCode . ' - ' . $result);
            return false;
        }
    } catch (Exception $e) {
        error_log('Mailgun Exception: ' . $e->getMessage());
        return false;
    }
}

/**
 * E-mail de boas-vindas da newsletter
 */
function sendWelcomeEmail($email, $nome = '') {
    $subject = '🎉 Bem-vindo à Newsletter da Gourmeria!';
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #DAA520, #B8860B); color: white; padding: 30px; text-align: center; }
            .content { padding: 30px; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            .btn { display: inline-block; background: #DAA520; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🍰 Gourmeria</h1>
                <p>Doces Gourmet Brasileiros no Japão</p>
            </div>
            <div class="content">
                <h2>Olá' . ($nome ? " $nome" : '') . '! 👋</h2>
                <p>Seja bem-vindo à nossa newsletter exclusiva!</p>
                <p>Agora você receberá:</p>
                <ul>
                    <li>🎂 Novidades sobre nossos doces gourmet</li>
                    <li>🎁 Ofertas e promoções exclusivas</li>
                    <li>📰 Receitas e dicas especiais</li>
                    <li>🚚 Informações sobre entregas</li>
                </ul>
                <p>Obrigado por fazer parte da família Gourmeria!</p>
                <a href="' . SITE_URL . '" class="btn">Visite nossa loja 🛒</a>
            </div>
            <div class="footer">
                <p>Gourmeria - Doces Gourmet para Brasileiros no Japão</p>
                <p>Hamamatsu, Shizuoka | contato@gourmeria.jp</p>
            </div>
        </div>
    </body>
    </html>';
    
    return sendEmailViaMailgun($email, $subject, $message, true);
}

/**
 * E-mail de confirmação de pedido
 */
function sendOrderConfirmationEmail($email, $orderData) {
    $subject = '✅ Confirmação do Pedido #' . $orderData['id'] . ' - Gourmeria';
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #DAA520, #B8860B); color: white; padding: 30px; text-align: center; }
            .content { padding: 30px; }
            .order-summary { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            .total { font-size: 18px; font-weight: bold; color: #DAA520; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🎉 Pedido Confirmado!</h1>
                <p>Pedido #' . $orderData['id'] . '</p>
            </div>
            <div class="content">
                <p>Olá <strong>' . htmlspecialchars($orderData['customer_name']) . '</strong>!</p>
                <p>Seu pedido foi confirmado com sucesso! 🎂</p>
                
                <div class="order-summary">
                    <h3>📋 Resumo do Pedido</h3>
                    <p><strong>Data:</strong> ' . date('d/m/Y H:i', strtotime($orderData['created_at'])) . '</p>
                    <p><strong>Status:</strong> ' . $orderData['status'] . '</p>
                    <p class="total"><strong>Total:</strong> ' . formatPrice($orderData['total']) . '</p>
                </div>
                
                <p>📱 Acompanhe seu pedido em nossa plataforma ou entre em contato conosco.</p>
                <p>🚚 Enviaremos uma notificação quando seu pedido estiver a caminho!</p>
                
                <p>Obrigado por escolher a Gourmeria! ❤️</p>
            </div>
            <div class="footer">
                <p>Gourmeria - Doces Gourmet para Brasileiros no Japão</p>
                <p>Hamamatsu, Shizuoka | contato@gourmeria.jp</p>
            </div>
        </div>
    </body>
    </html>';
    
    return sendEmailViaMailgun($email, $subject, $message, true);
}

/**
 * E-mail de recuperação de senha
 */
function sendPasswordResetEmail($email, $token, $userName = '') {
    $subject = '🔐 Redefinição de Senha - Gourmeria';
    $resetLink = SITE_URL . '/reset-password.php?token=' . $token;
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #DAA520, #B8860B); color: white; padding: 30px; text-align: center; }
            .content { padding: 30px; }
            .btn { display: inline-block; background: #DAA520; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔐 Redefinição de Senha</h1>
            </div>
            <div class="content">
                <p>Olá' . ($userName ? " <strong>$userName</strong>" : '') . '!</p>
                <p>Recebemos uma solicitação para redefinir sua senha na Gourmeria.</p>
                
                <div class="warning">
                    <p><strong>⚠️ Importante:</strong> Se você não solicitou esta redefinição, ignore este e-mail. Sua senha permanecerá inalterada.</p>
                </div>
                
                <p>Para criar uma nova senha, clique no botão abaixo:</p>
                <a href="' . $resetLink . '" class="btn">🔄 Redefinir Senha</a>
                
                <p>⏰ Este link expira em 24 horas por segurança.</p>
                
                <p>Se o botão não funcionar, copie e cole este link no seu navegador:</p>
                <p style="word-break: break-all; color: #666; font-size: 12px;">' . $resetLink . '</p>
            </div>
            <div class="footer">
                <p>Gourmeria - Doces Gourmet para Brasileiros no Japão</p>
                <p>Hamamatsu, Shizuoka | contato@gourmeria.jp</p>
            </div>
        </div>
    </body>
    </html>';
    
    return sendEmailViaMailgun($email, $subject, $message, true);
}

// ============================================
// FUNÇÃO DE LOG DE ATIVIDADES
// ============================================

/**
 * Registrar atividade do usuário
 */
function logActivity($action, $description = '', $userId = null) {
    global $db;
    
    try {
        if ($userId === null && isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
        }
        
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log('Log Activity Error: ' . $e->getMessage());
        return false;
    }
}

// ============================================
// FUNÇÕES AUXILIARES
// ============================================

/**
 * Detectar se é dispositivo móvel
 */
function isMobile() {
    return preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
}

/**
 * Verificar se diretório de upload existe
 */
function checkUploadDir() {
    if (!file_exists(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0755, true);
    }
}

// ============================================
// CONFIGURAÇÕES DE ERRO
// ============================================
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// ============================================
// VERIFICAR DIRETÓRIO DE UPLOAD
// ============================================
checkUploadDir();

// ============================================
// TIMEZONE
// ============================================
date_default_timezone_set('Asia/Tokyo');
?>