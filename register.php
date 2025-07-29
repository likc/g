<?php
require_once 'config.php';

// Se já estiver logado, redirecionar
if (isLoggedIn()) {
    redirect('index.php');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = sanitize($_POST['nome']);
    $sobrenome = sanitize($_POST['sobrenome']);
    $email = sanitize($_POST['email']);
    $telefone = sanitize($_POST['telefone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validações
    if (empty($nome)) {
        $errors[] = 'Nome é obrigatório';
    } elseif (strlen($nome) < 2) {
        $errors[] = 'Nome deve ter pelo menos 2 caracteres';
    }
    
    if (empty($sobrenome)) {
        $errors[] = 'Sobrenome é obrigatório';
    } elseif (strlen($sobrenome) < 2) {
        $errors[] = 'Sobrenome deve ter pelo menos 2 caracteres';
    }
    
    if (empty($email)) {
        $errors[] = 'E-mail é obrigatório';
    } elseif (!validateEmail($email)) {
        $errors[] = 'E-mail inválido';
    } elseif ($userClass->emailExists($email)) {
        $errors[] = 'Este e-mail já está cadastrado';
    }
    
    if (empty($password)) {
        $errors[] = 'Senha é obrigatória';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Senha deve ter pelo menos 6 caracteres';
    }
    
    if (empty($confirm_password)) {
        $errors[] = 'Confirmação de senha é obrigatória';
    } elseif ($password !== $confirm_password) {
        $errors[] = 'Senhas não coincidem';
    }
    
    if (!empty($telefone) && !preg_match('/^[\d\s\-\+\(\)]+$/', $telefone)) {
        $errors[] = 'Telefone inválido';
    }
    
    // Se não há erros, criar conta
    if (empty($errors)) {
        $userData = [
            'nome' => $nome,
            'sobrenome' => $sobrenome,
            'email' => $email,
            'telefone' => $telefone,
            'password' => $password
        ];
        
        if ($userClass->register($userData)) {
            $success = true;
            flashMessage('Conta criada com sucesso! Você já pode fazer login.', 'success');
        } else {
            $errors[] = 'Erro ao criar conta. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - Gourmeria</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #000 0%, #111 100%);
            min-height: 100vh;
            padding: 20px 0;
            color: #fff;
        }
        
        .register-container {
            background: #111;
            border: 2px solid #DAA520;
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            box-shadow: 0 10px 30px rgba(218, 165, 32, 0.3);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #DAA520;
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .logo p {
            color: #ccc;
            font-size: 14px;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            flex: 1;
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #DAA520;
            font-weight: bold;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #333;
            border-radius: 8px;
            background: #000;
            color: #fff;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #DAA520;
        }
        
        .form-group input::placeholder {
            color: #666;
        }
        
        .form-group input.error {
            border-color: #dc3545;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            background: #DAA520;
            color: #000;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #B8860B;
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            background: #666;
            cursor: not-allowed;
            transform: none;
        }
        
        .form-links {
            text-align: center;
            margin-top: 20px;
        }
        
        .form-links a {
            color: #DAA520;
            text-decoration: none;
        }
        
        .form-links a:hover {
            text-decoration: underline;
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
        
        .success {
            background: #28a745;
            color: #fff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .back-home {
            position: absolute;
            top: 20px;
            left: 20px;
        }
        
        .back-home a {
            color: #DAA520;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: color 0.3s;
        }
        
        .back-home a:hover {
            color: #B8860B;
        }
        
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
        }
        
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }
        
        .divider {
            text-align: center;
            margin: 20px 0;
            color: #666;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #333;
        }
        
        .divider span {
            background: #111;
            padding: 0 15px;
        }
        
        @media (max-width: 600px) {
            .register-container {
                margin: 20px;
                padding: 30px 20px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div class="back-home">
        <a href="index.php">
            <i class="fas fa-arrow-left"></i> Voltar ao Site
        </a>
    </div>
    
    <div class="register-container">
        <div class="logo">
            <h1><i class="fas fa-gem"></i> Gourmeria</h1>
            <p>Crie sua conta e comece a comprar</p>
        </div>
        
        <?php if ($success): ?>
            <div class="success">
                <i class="fas fa-check-circle"></i> Conta criada com sucesso!
                <br><br>
                <a href="login.php" class="btn" style="display: inline-block; margin-top: 10px; text-decoration: none;">
                    Fazer Login Agora
                </a>
            </div>
        <?php else: ?>
            
            <?php if (!empty($errors)): ?>
                <div class="errors">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nome">
                            <i class="fas fa-user"></i> Nome *
                        </label>
                        <input 
                            type="text" 
                            id="nome" 
                            name="nome" 
                            placeholder="Seu nome"
                            value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>"
                            required
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="sobrenome">
                            <i class="fas fa-user"></i> Sobrenome *
                        </label>
                        <input 
                            type="text" 
                            id="sobrenome" 
                            name="sobrenome" 
                            placeholder="Seu sobrenome"
                            value="<?php echo htmlspecialchars($_POST['sobrenome'] ?? ''); ?>"
                            required
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> E-mail *
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="seu@email.com"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label for="telefone">
                        <i class="fas fa-phone"></i> Telefone
                    </label>
                    <input 
                        type="tel" 
                        id="telefone" 
                        name="telefone" 
                        placeholder="+81-XX-XXXX-XXXX (opcional)"
                        value="<?php echo htmlspecialchars($_POST['telefone'] ?? ''); ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Senha *
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Mínimo 6 caracteres"
                        required
                    >
                    <div id="passwordStrength" class="password-strength"></div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">
                        <i class="fas fa-lock"></i> Confirmar Senha *
                    </label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        placeholder="Digite a senha novamente"
                        required
                    >
                    <div id="passwordMatch" style="font-size: 12px; margin-top: 5px;"></div>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-user-plus"></i> Criar Conta
                </button>
            </form>
            
            <div class="divider">
                <span>ou</span>
            </div>
            
            <div class="form-links">
                <p>Já tem uma conta? <a href="login.php">Fazer Login</a></p>
            </div>
        
        <?php endif; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            const passwordField = document.getElementById('password');
            const confirmPasswordField = document.getElementById('confirm_password');
            const emailField = document.getElementById('email');
            
            // Verificar força da senha
            passwordField.addEventListener('input', function() {
                const password = this.value;
                const strengthDiv = document.getElementById('passwordStrength');
                
                if (password.length === 0) {
                    strengthDiv.textContent = '';
                    return;
                }
                
                let strength = 0;
                if (password.length >= 6) strength++;
                if (password.match(/[a-z]/)) strength++;
                if (password.match(/[A-Z]/)) strength++;
                if (password.match(/[0-9]/)) strength++;
                if (password.match(/[^a-zA-Z0-9]/)) strength++;
                
                if (strength < 2) {
                    strengthDiv.textContent = 'Senha fraca';
                    strengthDiv.className = 'password-strength strength-weak';
                } else if (strength < 4) {
                    strengthDiv.textContent = 'Senha média';
                    strengthDiv.className = 'password-strength strength-medium';
                } else {
                    strengthDiv.textContent = 'Senha forte';
                    strengthDiv.className = 'password-strength strength-strong';
                }
            });
            
            // Verificar se senhas coincidem
            function checkPasswordMatch() {
                const password = passwordField.value;
                const confirmPassword = confirmPasswordField.value;
                const matchDiv = document.getElementById('passwordMatch');
                
                if (confirmPassword.length === 0) {
                    matchDiv.textContent = '';
                    return;
                }
                
                if (password === confirmPassword) {
                    matchDiv.textContent = '✓ Senhas coincidem';
                    matchDiv.style.color = '#28a745';
                } else {
                    matchDiv.textContent = '✗ Senhas não coincidem';
                    matchDiv.style.color = '#dc3545';
                }
            }
            
            passwordField.addEventListener('input', checkPasswordMatch);
            confirmPasswordField.addEventListener('input', checkPasswordMatch);
            
            // Verificar e-mail em tempo real
            emailField.addEventListener('blur', function() {
                const email = this.value;
                if (email && !email.includes('@')) {
                    this.style.borderColor = '#dc3545';
                } else if (email) {
                    // Verificar se email já existe (via AJAX seria melhor, mas por simplicidade...)
                    this.style.borderColor = '#28a745';
                } else {
                    this.style.borderColor = '#333';
                }
            });
            
            // Focar no primeiro campo
            document.getElementById('nome').focus();
        });
    </script>
</body>
</html>