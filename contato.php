<?php
require_once 'config.php';

$message = '';
$messageType = '';

// Processar formulário de contato
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = sanitize($_POST['nome'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $telefone = sanitize($_POST['telefone'] ?? '');
    $assunto = sanitize($_POST['assunto'] ?? '');
    $mensagem = sanitize($_POST['mensagem'] ?? '');
    
    // Validações
    $errors = [];
    
    if (empty($nome)) $errors[] = 'Nome é obrigatório';
    if (empty($email) || !validateEmail($email)) $errors[] = 'E-mail válido é obrigatório';
    if (empty($assunto)) $errors[] = 'Assunto é obrigatório';
    if (empty($mensagem)) $errors[] = 'Mensagem é obrigatória';
    
    if (empty($errors)) {
        try {
            // Salvar mensagem no banco (opcional)
            $stmt = $db->prepare("
                INSERT INTO contact_messages (nome, email, telefone, assunto, mensagem, ip_address, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            // Criar tabela se não existir
            $db->exec("
                CREATE TABLE IF NOT EXISTS contact_messages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nome VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    telefone VARCHAR(20),
                    assunto VARCHAR(255) NOT NULL,
                    mensagem TEXT NOT NULL,
                    ip_address VARCHAR(45),
                    respondido BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            $stmt->execute([$nome, $email, $telefone, $assunto, $mensagem, $_SERVER['REMOTE_ADDR'] ?? '']);
            
            // Enviar e-mail de notificação para admin
            $adminEmail = getSiteSetting('email_contato', 'contato@gourmeria.jp');
            $emailSubject = "[Gourmeria] Nova mensagem de contato: $assunto";
            
            $emailBody = "
            <h3>Nova mensagem de contato recebida</h3>
            <p><strong>Nome:</strong> $nome</p>
            <p><strong>E-mail:</strong> $email</p>
            <p><strong>Telefone:</strong> $telefone</p>
            <p><strong>Assunto:</strong> $assunto</p>
            <p><strong>Mensagem:</strong></p>
            <div style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>
                " . nl2br(htmlspecialchars($mensagem)) . "
            </div>
            <p><small>Data: " . date('d/m/Y H:i:s') . "</small></p>
            ";
            
            sendEmailViaMailgun($adminEmail, $emailSubject, $emailBody);
            
            // Enviar e-mail de confirmação para o cliente
            $confirmSubject = "Mensagem recebida - Gourmeria";
            $confirmBody = "
            <h2>Olá $nome!</h2>
            <p>Recebemos sua mensagem e entraremos em contato em breve.</p>
            <p><strong>Resumo da sua mensagem:</strong></p>
            <p><strong>Assunto:</strong> $assunto</p>
            <p><strong>Data:</strong> " . date('d/m/Y H:i:s') . "</p>
            <p>Obrigado por entrar em contato conosco!</p>
            <hr>
            <p><small>Gourmeria - Doces Gourmet para Brasileiros no Japão</small></p>
            ";
            
            sendEmailViaMailgun($email, $confirmSubject, $confirmBody);
            
            $message = 'Mensagem enviada com sucesso! Entraremos em contato em breve.';
            $messageType = 'success';
            
            // Limpar campos
            $nome = $email = $telefone = $assunto = $mensagem = '';
            
        } catch (Exception $e) {
            error_log('Contact Form Error: ' . $e->getMessage());
            $message = 'Erro ao enviar mensagem. Tente novamente.';
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
    <title>Contato - Gourmeria</title>
    <meta name="description" content="Entre em contato com a Gourmeria. Estamos aqui para ajudar com seus pedidos e dúvidas sobre nossos doces gourmet.">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gold: #DAA520;
            --dark-gold: #B8860B;
            --background-dark: #0A0A0A;
            --surface-dark: #1A1A1A;
            --surface-light: #2A2A2A;
            --text-primary: #FFFFFF;
            --text-secondary: #CCCCCC;
            --text-muted: #999999;
            --border-color: #333333;
            --success-color: #28a745;
            --error-color: #dc3545;
            --gradient-primary: linear-gradient(135deg, #DAA520 0%, #B8860B 100%);
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
            line-height: 1.6;
        }
        
        /* Header Simples */
        .simple-header {
            background: var(--surface-dark);
            border-bottom: 1px solid var(--border-color);
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .simple-header .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo-simple {
            color: var(--primary-gold);
            text-decoration: none;
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header-nav {
            display: flex;
            gap: 30px;
            align-items: center;
        }
        
        .header-nav a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .header-nav a:hover,
        .header-nav a.active {
            color: var(--primary-gold);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Hero Section */
        .hero-section {
            background: var(--surface-dark);
            padding: 80px 0;
            text-align: center;
        }
        
        .hero-section h1 {
            font-size: 48px;
            font-weight: 700;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
        }
        
        .hero-section p {
            font-size: 20px;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Contact Content */
        .contact-content {
            padding: 80px 0;
        }
        
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: start;
        }
        
        .contact-form {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 40px;
        }
        
        .form-title {
            font-size: 28px;
            color: var(--primary-gold);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 15px 20px;
            background: var(--background-dark);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 16px;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary-gold);
            box-shadow: 0 0 0 3px rgba(218, 165, 32, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            background: var(--gradient-primary);
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
        
        /* Contact Info */
        .contact-info {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 40px;
            height: fit-content;
        }
        
        .info-title {
            font-size: 28px;
            color: var(--primary-gold);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: var(--background-dark);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .info-item:hover {
            border-color: var(--primary-gold);
            transform: translateY(-2px);
        }
        
        .info-icon {
            width: 50px;
            height: 50px;
            background: var(--gradient-primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--background-dark);
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .info-content h4 {
            color: var(--text-primary);
            margin-bottom: 8px;
            font-size: 18px;
        }
        
        .info-content p {
            color: var(--text-secondary);
            margin-bottom: 5px;
        }
        
        .info-content a {
            color: var(--primary-gold);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .info-content a:hover {
            color: var(--dark-gold);
        }
        
        /* Business Hours */
        .hours-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 20px;
        }
        
        .hour-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .hour-item:last-child {
            border-bottom: none;
        }
        
        .day {
            color: var(--text-secondary);
        }
        
        .time {
            color: var(--text-primary);
            font-weight: 500;
        }
        
        /* Social Links */
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .social-link {
            width: 50px;
            height: 50px;
            background: var(--background-dark);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .social-link:hover {
            background: var(--primary-gold);
            border-color: var(--primary-gold);
            color: var(--background-dark);
            transform: translateY(-3px);
        }
        
        /* Message Alert */
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
        
        /* FAQ Section */
        .faq-section {
            background: var(--surface-dark);
            padding: 60px 0;
            margin-top: 80px;
        }
        
        .faq-title {
            text-align: center;
            font-size: 32px;
            color: var(--primary-gold);
            margin-bottom: 40px;
        }
        
        .faq-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
        }
        
        .faq-item {
            background: var(--background-dark);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 25px;
        }
        
        .faq-question {
            color: var(--primary-gold);
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .faq-answer {
            color: var(--text-secondary);
            line-height: 1.6;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-section h1 {
                font-size: 36px;
            }
            
            .contact-grid {
                grid-template-columns: 1fr;
                gap: 40px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .hours-grid {
                grid-template-columns: 1fr;
            }
            
            .faq-grid {
                grid-template-columns: 1fr;
            }
            
            .header-nav {
                display: none;
            }
            
            .contact-form,
            .contact-info {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="simple-header">
        <div class="container">
            <a href="index.php" class="logo-simple">
                <i class="fas fa-gem"></i>
                Gourmeria
            </a>
            <nav class="header-nav">
                <a href="index.php">Início</a>
                <a href="produtos.php">Produtos</a>
                <a href="sobre.php">Sobre</a>
                <a href="contato.php" class="active">Contato</a>
                <a href="carrinho.php"><i class="fas fa-shopping-cart"></i></a>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1>Fale Conosco</h1>
            <p>Estamos aqui para ajudar! Entre em contato conosco e tire todas as suas dúvidas sobre nossos doces gourmet.</p>
        </div>
    </section>

    <!-- Contact Content -->
    <section class="contact-content">
        <div class="container">
            <div class="contact-grid">
                <!-- Formulário de Contato -->
                <div class="contact-form">
                    <h2 class="form-title">
                        <i class="fas fa-envelope"></i>
                        Envie sua Mensagem
                    </h2>
                    
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
                    
                    <form method="POST" id="contactForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="nome">Nome Completo *</label>
                                <input type="text" id="nome" name="nome" required value="<?php echo htmlspecialchars($nome ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="telefone">Telefone</label>
                                <input type="tel" id="telefone" name="telefone" value="<?php echo htmlspecialchars($telefone ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">E-mail *</label>
                            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="assunto">Assunto *</label>
                            <select id="assunto" name="assunto" required>
                                <option value="">Selecione o assunto</option>
                                <option value="Pedido" <?php echo ($assunto ?? '') == 'Pedido' ? 'selected' : ''; ?>>Pedido</option>
                                <option value="Dúvida sobre Produto" <?php echo ($assunto ?? '') == 'Dúvida sobre Produto' ? 'selected' : ''; ?>>Dúvida sobre Produto</option>
                                <option value="Entrega" <?php echo ($assunto ?? '') == 'Entrega' ? 'selected' : ''; ?>>Entrega</option>
                                <option value="Reclamação" <?php echo ($assunto ?? '') == 'Reclamação' ? 'selected' : ''; ?>>Reclamação</option>
                                <option value="Sugestão" <?php echo ($assunto ?? '') == 'Sugestão' ? 'selected' : ''; ?>>Sugestão</option>
                                <option value="Outro" <?php echo ($assunto ?? '') == 'Outro' ? 'selected' : ''; ?>>Outro</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="mensagem">Mensagem *</label>
                            <textarea id="mensagem" name="mensagem" placeholder="Digite sua mensagem aqui..." required><?php echo htmlspecialchars($mensagem ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn" id="submitBtn">
                            <i class="fas fa-paper-plane"></i>
                            <span>Enviar Mensagem</span>
                        </button>
                    </form>
                </div>
                
                <!-- Informações de Contato -->
                <div class="contact-info">
                    <h2 class="info-title">
                        <i class="fas fa-info-circle"></i>
                        Informações de Contato
                    </h2>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="info-content">
                            <h4>Endereço</h4>
                            <p><?php echo getSiteSetting('endereco_loja', 'Hamamatsu, Shizuoka, Japão'); ?></p>
                            <p><small>Retirada apenas com agendamento</small></p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="info-content">
                            <h4>Telefone</h4>
                            <p><a href="tel:<?php echo getSiteSetting('telefone_contato'); ?>"><?php echo getSiteSetting('telefone_contato'); ?></a></p>
                            <p><small>WhatsApp disponível</small></p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="info-content">
                            <h4>E-mail</h4>
                            <p><a href="mailto:<?php echo getSiteSetting('email_contato'); ?>"><?php echo getSiteSetting('email_contato'); ?></a></p>
                            <p><small>Respondemos em até 24h</small></p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="info-content">
                            <h4>Horário de Atendimento</h4>
                            <div class="hours-grid">
                                <div class="hour-item">
                                    <span class="day">Segunda - Sexta</span>
                                    <span class="time">9:00 - 18:00</span>
                                </div>
                                <div class="hour-item">
                                    <span class="day">Sábado</span>
                                    <span class="time">9:00 - 15:00</span>
                                </div>
                                <div class="hour-item">
                                    <span class="day">Domingo</span>
                                    <span class="time">Fechado</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="social-links">
                        <a href="#" class="social-link" title="WhatsApp">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                        <a href="#" class="social-link" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="social-link" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="social-link" title="LINE">
                            <i class="fab fa-line"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section">
        <div class="container">
            <h2 class="faq-title">Perguntas Frequentes</h2>
            <div class="faq-grid">
                <div class="faq-item">
                    <h4 class="faq-question">Como faço um pedido?</h4>
                    <p class="faq-answer">Você pode fazer seu pedido através do nosso site, selecionando os produtos desejados e finalizando no carrinho. Também aceitamos pedidos via WhatsApp.</p>
                </div>
                
                <div class="faq-item">
                    <h4 class="faq-question">Qual o prazo de entrega?</h4>
                    <p class="faq-answer">Para entregas via Yamato: 1-2 dias úteis. Para retirada em nossa loja: 24-48 horas após confirmação do pagamento, com agendamento.</p>
                </div>
                
                <div class="faq-item">
                    <h4 class="faq-question">Fazem entregas em todo o Japão?</h4>
                    <p class="faq-answer">Sim! Entregamos em todo o território japonês através da Yamato Transport. Frete grátis para pedidos acima de ¥5.000.</p>
                </div>
                
                <div class="faq-item">
                    <h4 class="faq-question">Qual o prazo de validade dos doces?</h4>
                    <p class="faq-answer">Nossos doces são feitos frescos. A validade varia de 3 a 7 dias dependendo do produto. Sempre informamos a validade na embalagem.</p>
                </div>
                
                <div class="faq-item">
                    <h4 class="faq-question">Posso personalizar meu pedido?</h4>
                    <p class="faq-answer">Sim! Fazemos doces personalizados para festas e eventos especiais. Entre em contato conosco para discutir suas necessidades.</p>
                </div>
                
                <div class="faq-item">
                    <h4 class="faq-question">Como posso pagar?</h4>
                    <p class="faq-answer">Aceitamos PIX, transferência bancária e dinheiro na entrega (para região de Hamamatsu). Pagamento via cartão em breve!</p>
                </div>
            </div>
        </div>
    </section>

    <script>
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            
            // Validações simples
            const nome = document.getElementById('nome').value.trim();
            const email = document.getElementById('email').value.trim();
            const assunto = document.getElementById('assunto').value;
            const mensagem = document.getElementById('mensagem').value.trim();
            
            if (!nome || !email || !assunto || !mensagem) {
                e.preventDefault();
                alert('Por favor, preencha todos os campos obrigatórios.');
                return;
            }
            
            // Validação de e-mail
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Por favor, digite um e-mail válido.');
                return;
            }
            
            // Desabilitar botão e mostrar loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
            
            // Re-habilitar após 10 segundos em caso de erro
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> <span>Enviar Mensagem</span>';
            }, 10000);
        });
        
        // Auto-hide success messages
        setTimeout(function() {
            const successMessage = document.querySelector('.message.success');
            if (successMessage) {
                successMessage.style.opacity = '0';
                successMessage.style.transform = 'translateY(-20px)';
                setTimeout(() => successMessage.remove(), 300);
            }
        }, 5000);
        
        // Animações
        document.addEventListener('DOMContentLoaded', function() {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);
            
            const animatedElements = document.querySelectorAll('.info-item, .faq-item');
            animatedElements.forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(30px)';
                el.style.transition = 'all 0.6s ease';
                observer.observe(el);
            });
        });
    </script>
</body>
</html>