<?php
require_once 'config.php';

// Se já está logado, redirecionar
if (isLoggedIn()) {
    redirect('index.php');
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email']);
    
    if (empty($email) || !validateEmail($email)) {
        $message = 'Por favor, informe um e-mail válido.';
        $messageType = 'error';
    } else {
        try {
            // Verificar se o e-mail existe
            $stmt = $db->prepare("SELECT id, name FROM users WHERE email = ? AND ativo = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Gerar token de reset
                $token = generateToken();
                $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                // Salvar token no banco
                $stmt = $db->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
                $stmt->execute([$token, $expires, $user['id']]);
                
                // Enviar e-mail
                $emailSent = sendPasswordResetEmail($email, $token, $user['name']);
                
                if ($emailSent) {
                    $message = 'E-mail de recuperação enviado! Verifique sua caixa de entrada e spam.';
                    $messageType = 'success';
                    
                    // Log da atividade
                    logActivity('password_reset_requested', "Reset de senha solicitado para: $email", $user['id']);
                } else {
                    $message = 'Erro ao enviar e-mail. Tente novamente mais tarde.';
                    $messageType = 'error';
                }
            } else {
                // Por segurança, mostrar sucesso mesmo se e-mail não existir
                $message = 'Se este e-mail estiver cadastrado, você receberá as instruções de recuperação.';
                $messageType = 'info';
            }
        } catch (Exception $e) {
            error_log('Forgot Password Error: ' . $e->getMessage());
            $message = 'Erro interno. Tente novamente mais tarde.';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - Gourmeria</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gold: #DAA520;
            --dark-gold: #B8860B;
            --background-dark: #0A0A0A;
            --surface-dark: #1A1A1A;
            --text-primary: #FFFFFF;
            --text-secondary: #CCCCCC;
            --text-muted: #999999;
            --border-color: #333333;
            --success-color: #28a745;
            --error-color: #dc3545;
            --info-color: #17a2b8;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--background-dark);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .forgot-container {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 500px;
            position: relative;
            overflow: hidden;
        }
        
        .forgot-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-gold), var(--dark-gold));
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo i {
            font-size: 48px;
            color: var(--primary-gold);
            margin-bottom: 10px;
        }
        
        .logo h1 {
            font-size: 28px;
            color: var(--primary-gold);
            margin-bottom: 5px;
        }
        
        .logo p {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .form-title {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-title h2 {
            font-size: 24px;
            color: var(--text-primary);
            margin-bottom: 10px;
        }
        
        .form-title p {
            color: var(--text-secondary);
            font-size: 16px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 18px;
        }
        
        input[type="email"] {
            width: 100%;
            padding: 15px 15px 15px 50px;
            background: var(--background-dark);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        input[type="email"]:focus {
            outline: none;
            border-color: var(--primary-gold);
            box-shadow: 0 0 0 3px rgba(218, 165, 32, 0.1);
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary-gold), var(--dark-gold));
            color: var(--background-dark);
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(218, 165, 32, 0.3);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message.success {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }
        
        .message.error {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid var(--error-color);
            color: var(--error-color);
        }
        
        .message.info {
            background: rgba(23, 162, 184, 0.1);
            border: 1px solid var(--info-color);
            color: var(--info-color);
        }
        
        .back-link {
            text-align: center;
            margin-top: 25px;
        }
        
        .back-link a {
            color: var(--text-secondary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s ease;
        }
        
        .back-link a:hover {
            color: var(--primary-gold);
        }
        
        .help-text {
            background: rgba(218, 165, 32, 0.1);
            border: 1px solid var(--primary-gold);
            border-radius: 10px;
            padding: 20px;
            margin-top: 25px;
        }
        
        .help-text h4 {
            color: var(--primary-gold);
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .help-text ul {
            color: var(--text-secondary);
            padding-left: 20px;
            font-size: 14px;
        }
        
        .help-text li {
            margin-bottom: 5px;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .forgot-container {
                padding: 30px 20px;
            }
            
            .logo h1 {
                font-size: 24px;
            }
            
            .form-title h2 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="logo">
            <i class="fas fa-gem"></i>
            <h1>Gourmeria</h1>
            <p>Doces Gourmet para Brasileiros no Japão</p>
        </div>
        
        <div class="form-title">
            <h2><i class="fas fa-key"></i> Recuperar Senha</h2>
            <p>Digite seu e-mail para receber as instruções</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php if ($messageType == 'success'): ?>
                    <i class="fas fa-check-circle"></i>
                <?php elseif ($messageType == 'error'): ?>
                    <i class="fas fa-exclamation-circle"></i>
                <?php else: ?>
                    <i class="fas fa-info-circle"></i>
                <?php endif; ?>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="forgotForm">
            <div class="form-group">
                <label for="email">E-mail cadastrado</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" placeholder="Digite seu e-mail" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
            </div>
            
            <button type="submit" class="btn" id="submitBtn">
                <i class="fas fa-paper-plane"></i>
                <span>Enviar Instruções</span>
            </button>
        </form>
        
        <div class="help-text">
            <h4><i class="fas fa-lightbulb"></i> Como funciona?</h4>
            <ul>
                <li>Digite o e-mail cadastrado na sua conta</li>
                <li>Você receberá um link de recuperação</li>
                <li>Clique no link para criar uma nova senha</li>
                <li>O link expira em 24 horas por segurança</li>
            </ul>
        </div>
        
        <div class="back-link">
            <a href="login.php">
                <i class="fas fa-arrow-left"></i>
                Voltar ao Login
            </a>
        </div>
    </div>
    
    <script>
        document.getElementById('forgotForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const email = document.getElementById('email').value;
            
            if (!email) {
                e.preventDefault();
                alert('Por favor, digite seu e-mail.');
                return;
            }
            
            // Validação básica de e-mail
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Por favor, digite um e-mail válido.');
                return;
            }
            
            // Desabilitar botão e mostrar loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
            
            // Re-habilitar após 10 segundos (em caso de erro)
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> <span>Enviar Instruções</span>';
            }, 10000);
        });
        
        // Auto-focus no campo de e-mail
        document.getElementById('email').focus();
        
        // Animação de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.forgot-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                container.style.transition = 'all 0.6s ease';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>