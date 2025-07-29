<?php
require_once 'config.php';

// Se já está logado, redirecionar
if (isLoggedIn()) {
    redirect('index.php');
}

$token = $_GET['token'] ?? '';
$message = '';
$messageType = '';
$showForm = false;
$user = null;

// Verificar token
if ($token) {
    try {
        $stmt = $db->prepare("
            SELECT id, name, email 
            FROM users 
            WHERE reset_token = ? 
            AND reset_token_expires > NOW() 
            AND ativo = 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            $showForm = true;
        } else {
            $message = 'Link de recuperação inválido ou expirado. Solicite um novo link.';
            $messageType = 'error';
        }
    } catch (Exception $e) {
        error_log('Reset Password Token Validation Error: ' . $e->getMessage());
        $message = 'Erro ao validar token. Tente novamente.';
        $messageType = 'error';
    }
} else {
    $message = 'Token de recuperação não fornecido.';
    $messageType = 'error';
}

// Processar nova senha
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $showForm) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validações
    $errors = [];
    
    if (empty($password)) {
        $errors[] = 'Digite a nova senha.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'A senha deve ter pelo menos 6 caracteres.';
    }
    
    if (empty($confirmPassword)) {
        $errors[] = 'Confirme a nova senha.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'As senhas não coincidem.';
    }
    
    if (empty($errors)) {
        try {
            // Atualizar senha (sem criptografia conforme solicitado)
            $hashedPassword = hashPassword($password);
            
            $stmt = $db->prepare("
                UPDATE users 
                SET password = ?, reset_token = NULL, reset_token_expires = NULL 
                WHERE id = ?
            ");
            
            if ($stmt->execute([$hashedPassword, $user['id']])) {
                // Log da atividade
                logActivity('password_reset_completed', 'Senha redefinida com sucesso', $user['id']);
                
                flashMessage('Senha redefinida com sucesso! Faça login com sua nova senha.', 'success');
                redirect('login.php');
            } else {
                $message = 'Erro ao atualizar senha. Tente novamente.';
                $messageType = 'error';
            }
        } catch (Exception $e) {
            error_log('Reset Password Update Error: ' . $e->getMessage());
            $message = 'Erro interno. Tente novamente mais tarde.';
            $messageType = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha - Gourmeria</title>
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
            --warning-color: #ffc107;
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
        
        .reset-container {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 500px;
            position: relative;
            overflow: hidden;
        }
        
        .reset-container::before {
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
        
        .user-info {
            background: rgba(218, 165, 32, 0.1);
            border: 1px solid var(--primary-gold);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .user-info .user-name {
            color: var(--primary-gold);
            font-weight: 600;
            font-size: 18px;
        }
        
        .user-info .user-email {
            color: var(--text-secondary);
            font-size: 14px;
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
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            cursor: pointer;
            font-size: 18px;
            transition: color 0.3s ease;
        }
        
        .toggle-password:hover {
            color: var(--primary-gold);
        }
        
        input[type="password"], input[type="text"] {
            width: 100%;
            padding: 15px 50px 15px 50px;
            background: var(--background-dark);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        input:focus {
            outline: none;
            border-color: var(--primary-gold);
            box-shadow: 0 0 0 3px rgba(218, 165, 32, 0.1);
        }
        
        .password-strength {
            margin-top: 8px;
            font-size: 12px;
        }
        
        .strength-bar {
            height: 4px;
            background: var(--border-color);
            border-radius: 2px;
            overflow: hidden;
            margin: 5px 0;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .strength-weak .strength-fill {
            width: 33%;
            background: var(--error-color);
        }
        
        .strength-medium .strength-fill {
            width: 66%;
            background: var(--warning-color);
        }
        
        .strength-strong .strength-fill {
            width: 100%;
            background: var(--success-color);
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
            align-items: flex-start;
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
        
        .security-tips {
            background: rgba(23, 162, 184, 0.1);
            border: 1px solid #17a2b8;
            border-radius: 10px;
            padding: 20px;
            margin-top: 25px;
        }
        
        .security-tips h4 {
            color: #17a2b8;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .security-tips ul {
            color: var(--text-secondary);
            padding-left: 20px;
            font-size: 14px;
        }
        
        .security-tips li {
            margin-bottom: 5px;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .reset-container {
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
    <div class="reset-container">
        <div class="logo">
            <i class="fas fa-gem"></i>
            <h1>Gourmeria</h1>
            <p>Doces Gourmet para Brasileiros no Japão</p>
        </div>
        
        <?php if ($showForm): ?>
            <div class="form-title">
                <h2><i class="fas fa-key"></i> Nova Senha</h2>
                <p>Crie uma senha segura para sua conta</p>
            </div>
            
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
            </div>
            
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php if ($messageType == 'success'): ?>
                        <i class="fas fa-check-circle"></i>
                    <?php else: ?>
                        <i class="fas fa-exclamation-circle"></i>
                    <?php endif; ?>
                    <div><?php echo $message; ?></div>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="resetForm">
                <div class="form-group">
                    <label for="password">Nova Senha</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Digite sua nova senha" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('password')"></i>
                    </div>
                    <div class="password-strength" id="passwordStrength">
                        <div class="strength-bar">
                            <div class="strength-fill"></div>
                        </div>
                        <span class="strength-text">Digite uma senha</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirmar Senha</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirme sua nova senha" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password')"></i>
                    </div>
                    <div id="passwordMatch" style="margin-top: 8px; font-size: 12px;"></div>
                </div>
                
                <button type="submit" class="btn" id="submitBtn">
                    <i class="fas fa-save"></i>
                    <span>Redefinir Senha</span>
                </button>
            </form>
            
            <div class="security-tips">
                <h4><i class="fas fa-shield-alt"></i> Dicas de Segurança</h4>
                <ul>
                    <li>Use pelo menos 8 caracteres</li>
                    <li>Combine letras, números e símbolos</li>
                    <li>Evite informações pessoais óbvias</li>
                    <li>Não reutilize senhas de outros sites</li>
                </ul>
            </div>
        <?php else: ?>
            <div class="form-title">
                <h2><i class="fas fa-exclamation-triangle"></i> Link Inválido</h2>
                <p>Não foi possível verificar o link de recuperação</p>
            </div>
            
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo htmlspecialchars($message); ?></div>
            </div>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="forgot-password.php">
                <i class="fas fa-arrow-left"></i>
                Solicitar Novo Link
            </a>
            <span style="margin: 0 10px;">|</span>
            <a href="login.php">
                <i class="fas fa-sign-in-alt"></i>
                Fazer Login
            </a>
        </div>
    </div>
    
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash toggle-password';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye toggle-password';
            }
        }
        
        function checkPasswordStrength(password) {
            const strengthEl = document.getElementById('passwordStrength');
            let strength = 0;
            let text = 'Muito fraca';
            
            if (password.length >= 6) strength += 1;
            if (password.length >= 8) strength += 1;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 1;
            if (/\d/.test(password)) strength += 1;
            if (/[^a-zA-Z\d]/.test(password)) strength += 1;
            
            strengthEl.className = 'password-strength';
            
            if (strength <= 2) {
                strengthEl.classList.add('strength-weak');
                text = 'Fraca';
            } else if (strength <= 4) {
                strengthEl.classList.add('strength-medium');
                text = 'Média';
            } else {
                strengthEl.classList.add('strength-strong');
                text = 'Forte';
            }
            
            strengthEl.querySelector('.strength-text').textContent = text;
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchEl = document.getElementById('passwordMatch');
            
            if (confirmPassword.length === 0) {
                matchEl.textContent = '';
                return true;
            }
            
            if (password === confirmPassword) {
                matchEl.innerHTML = '<span style="color: var(--success-color);"><i class="fas fa-check"></i> Senhas coincidem</span>';
                return true;
            } else {
                matchEl.innerHTML = '<span style="color: var(--error-color);"><i class="fas fa-times"></i> Senhas não coincidem</span>';
                return false;
            }
        }
        
        document.getElementById('password').addEventListener('input', function() {
            checkPasswordStrength(this.value);
            checkPasswordMatch();
        });
        
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
        
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const submitBtn = document.getElementById('submitBtn');
            
            if (password.length < 6) {
                e.preventDefault();
                alert('A senha deve ter pelo menos 6 caracteres.');
                return;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('As senhas não coincidem.');
                return;
            }
            
            // Desabilitar botão e mostrar loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redefinindo...';
        });
        
        // Auto-focus no primeiro campo
        document.getElementById('password')?.focus();
        
        // Animação de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.reset-container');
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