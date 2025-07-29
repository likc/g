<?php
require_once 'config.php';

// Se já estiver logado, redirecionar
if (isLoggedIn()) {
    redirect('index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    // Validações
    if (empty($email)) {
        $errors[] = 'E-mail é obrigatório';
    } elseif (!validateEmail($email)) {
        $errors[] = 'E-mail inválido';
    }
    
    if (empty($password)) {
        $errors[] = 'Senha é obrigatória';
    }
    
    // Se não há erros, tentar fazer login
    if (empty($errors)) {
        if ($userClass->login($email, $password)) {
            flashMessage('Login realizado com sucesso!', 'success');
            
            // Redirecionar para a página anterior ou para o painel
            $redirect = $_GET['redirect'] ?? (isModerator() ? 'admin/' : 'minha-conta.php');
            redirect($redirect);
        } else {
            $errors[] = 'E-mail ou senha incorretos';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gourmeria</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        
        .login-container {
            background: #111;
            border: 2px solid #DAA520;
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
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
        
        .form-group {
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
        
        .form-links {
            text-align: center;
            margin-top: 20px;
        }
        
        .form-links a {
            color: #DAA520;
            text-decoration: none;
            margin: 0 10px;
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
        
        @media (max-width: 480px) {
            .login-container {
                margin: 20px;
                padding: 30px 20px;
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
    
    <div class="login-container">
        <div class="logo">
            <h1><i class="fas fa-gem"></i> Gourmeria</h1>
            <p>Faça login em sua conta</p>
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
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">
                    <i class="fas fa-envelope"></i> E-mail
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
                <label for="password">
                    <i class="fas fa-lock"></i> Senha
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="Digite sua senha"
                    required
                >
            </div>
            
            <button type="submit" class="btn">
                <i class="fas fa-sign-in-alt"></i> Entrar
            </button>
        </form>
        
        <div class="divider">
            <span>ou</span>
        </div>
        
        <div class="form-links">
            <a href="register.php">
                <i class="fas fa-user-plus"></i> Criar Conta
            </a>
            |
            <a href="esqueci-senha.php">
                <i class="fas fa-key"></i> Esqueci a Senha
            </a>
        </div>
        
        <div style="margin-top: 30px; text-align: center; color: #666; font-size: 14px;">
            <p>Não tem uma conta? <a href="register.php" style="color: #DAA520;">Cadastre-se gratuitamente</a></p>
        </div>
    </div>
    
    <script>
        // Focar no primeiro campo vazio
        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.getElementById('email');
            const passwordField = document.getElementById('password');
            
            if (!emailField.value) {
                emailField.focus();
            } else {
                passwordField.focus();
            }
        });
        
        // Validação em tempo real
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value;
            if (email && !email.includes('@')) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#333';
            }
        });
    </script>
</body>
</html>