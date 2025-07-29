<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sobre Nós - Gourmeria</title>
    <meta name="description" content="Conheça a história da Gourmeria, nossa missão de levar doces gourmet brasileiros para a comunidade brasileira no Japão.">
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
            line-height: 1.7;
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
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 600"><defs><radialGradient id="gold" cx="50%" cy="50%"><stop offset="0%" stop-color="%23DAA520" stop-opacity="0.1"/><stop offset="100%" stop-color="%23DAA520" stop-opacity="0"/></radialGradient></defs><circle cx="200" cy="150" r="100" fill="url(%23gold)"/><circle cx="800" cy="450" r="150" fill="url(%23gold)"/></svg>');
            background-size: cover;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
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
        
        /* Content Sections */
        .content-section {
            padding: 80px 0;
        }
        
        .section-title {
            font-size: 36px;
            font-weight: 700;
            color: var(--primary-gold);
            text-align: center;
            margin-bottom: 50px;
        }
        
        .story-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            margin-bottom: 80px;
        }
        
        .story-text h3 {
            font-size: 28px;
            color: var(--primary-gold);
            margin-bottom: 20px;
        }
        
        .story-text p {
            font-size: 16px;
            color: var(--text-secondary);
            margin-bottom: 20px;
        }
        
        .story-image {
            background: var(--surface-dark);
            border-radius: 20px;
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border-color);
        }
        
        .story-image i {
            font-size: 80px;
            color: var(--primary-gold);
            opacity: 0.3;
        }
        
        /* Values Section */
        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            margin-top: 60px;
        }
        
        .value-card {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .value-card:hover {
            border-color: var(--primary-gold);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(218, 165, 32, 0.2);
        }
        
        .value-card i {
            font-size: 48px;
            color: var(--primary-gold);
            margin-bottom: 20px;
        }
        
        .value-card h4 {
            font-size: 22px;
            color: var(--text-primary);
            margin-bottom: 15px;
        }
        
        .value-card p {
            color: var(--text-secondary);
            line-height: 1.6;
        }
        
        /* Team Section */
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-top: 60px;
        }
        
        .team-member {
            background: var(--surface-dark);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .team-member:hover {
            border-color: var(--primary-gold);
            transform: translateY(-5px);
        }
        
        .member-photo {
            width: 120px;
            height: 120px;
            background: var(--surface-light);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid var(--primary-gold);
        }
        
        .member-photo i {
            font-size: 48px;
            color: var(--primary-gold);
        }
        
        .member-name {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 10px;
        }
        
        .member-role {
            color: var(--primary-gold);
            font-weight: 500;
            margin-bottom: 15px;
        }
        
        .member-bio {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.6;
        }
        
        /* Stats Section */
        .stats-section {
            background: var(--surface-dark);
            padding: 60px 0;
            margin: 80px 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 42px;
            font-weight: 700;
            color: var(--primary-gold);
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 16px;
            font-weight: 500;
        }
        
        /* CTA Section */
        .cta-section {
            background: var(--surface-dark);
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            margin: 80px 0;
        }
        
        .cta-section h3 {
            font-size: 32px;
            color: var(--primary-gold);
            margin-bottom: 20px;
        }
        
        .cta-section p {
            font-size: 18px;
            color: var(--text-secondary);
            margin-bottom: 30px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 32px;
            background: var(--gradient-primary);
            color: var(--background-dark);
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(218, 165, 32, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-gold);
            color: var(--primary-gold);
        }
        
        .btn-outline:hover {
            background: var(--primary-gold);
            color: var(--background-dark);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-section h1 {
                font-size: 36px;
            }
            
            .story-grid {
                grid-template-columns: 1fr;
                gap: 40px;
            }
            
            .section-title {
                font-size: 28px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .values-grid {
                grid-template-columns: 1fr;
            }
            
            .team-grid {
                grid-template-columns: 1fr;
            }
            
            .cta-section {
                padding: 40px 20px;
            }
            
            .header-nav {
                display: none;
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
                <a href="sobre.php" class="active">Sobre</a>
                <a href="contato.php">Contato</a>
                <a href="carrinho.php"><i class="fas fa-shopping-cart"></i></a>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <h1>Nossa História</h1>
                <p>Levando o sabor do Brasil para a comunidade brasileira no Japão, com doces artesanais feitos com amor e tradição.</p>
            </div>
        </div>
    </section>

    <!-- Nossa História -->
    <section class="content-section">
        <div class="container">
            <div class="story-grid">
                <div class="story-text">
                    <h3>🇧🇷 Raízes Brasileiras</h3>
                    <p>A Gourmeria nasceu do desejo de matar a saudade de casa. Como brasileiros vivendo no Japão, sabemos o quanto é especial encontrar os sabores que nos lembram do Brasil.</p>
                    <p>Nossos doces são feitos com receitas tradicionais, ingredientes selecionados e muito carinho, trazendo um pedacinho do Brasil para sua mesa no Japão.</p>
                </div>
                <div class="story-image">
                    <i class="fas fa-heart"></i>
                </div>
            </div>
            
            <div class="story-grid">
                <div class="story-image">
                    <i class="fas fa-cookie-bite"></i>
                </div>
                <div class="story-text">
                    <h3>🍰 Qualidade Artesanal</h3>
                    <p>Cada doce é preparado artesanalmente, seguindo técnicas tradicionais brasileiras adaptadas com ingredientes locais de alta qualidade disponíveis no Japão.</p>
                    <p>Nosso compromisso é com a excelência: desde a seleção dos ingredientes até a entrega final, tudo é pensado para proporcionar a melhor experiência possível.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Nossos Valores -->
    <section class="content-section" style="background: var(--surface-dark);">
        <div class="container">
            <h2 class="section-title">Nossos Valores</h2>
            <div class="values-grid">
                <div class="value-card">
                    <i class="fas fa-heart"></i>
                    <h4>Amor pela Tradição</h4>
                    <p>Preservamos as receitas tradicionais brasileiras, passadas de geração em geração, mantendo viva nossa cultura culinária.</p>
                </div>
                
                <div class="value-card">
                    <i class="fas fa-award"></i>
                    <h4>Qualidade Premium</h4>
                    <p>Utilizamos apenas ingredientes de primeira qualidade, garantindo sabor autêntico e apresentação impecável em todos os nossos produtos.</p>
                </div>
                
                <div class="value-card">
                    <i class="fas fa-users"></i>
                    <h4>Comunidade Brasileira</h4>
                    <p>Somos parte da comunidade brasileira no Japão e nosso objetivo é fortalecer nossos laços através dos sabores que nos unem.</p>
                </div>
                
                <div class="value-card">
                    <i class="fas fa-leaf"></i>
                    <h4>Sustentabilidade</h4>
                    <p>Priorizamos fornecedores locais responsáveis e práticas sustentáveis em todos os processos de produção.</p>
                </div>
                
                <div class="value-card">
                    <i class="fas fa-clock"></i>
                    <h4>Pontualidade</h4>
                    <p>Respeitamos os horários de entrega e sempre cumprimos nossos compromissos com nossos clientes.</p>
                </div>
                
                <div class="value-card">
                    <i class="fas fa-smile"></i>
                    <h4>Satisfação do Cliente</h4>
                    <p>A felicidade dos nossos clientes é nossa maior recompensa. Cada sorriso alimenta nossa paixão pelo que fazemos.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Estatísticas -->
    <section class="stats-section">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number">2000+</div>
                    <div class="stat-label">Doces Entregues</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">500+</div>
                    <div class="stat-label">Clientes Satisfeitos</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">50+</div>
                    <div class="stat-label">Variedades de Doces</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">3</div>
                    <div class="stat-label">Anos de Experiência</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Nossa Equipe -->
    <section class="content-section">
        <div class="container">
            <h2 class="section-title">Nossa Equipe</h2>
            <div class="team-grid">
                <div class="team-member">
                    <div class="member-photo">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="member-name">Ana Silva</div>
                    <div class="member-role">Chef Confeiteira</div>
                    <div class="member-bio">Especialista em doces brasileiros com 15 anos de experiência. Responsável pela criação e padronização das receitas.</div>
                </div>
                
                <div class="team-member">
                    <div class="member-photo">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="member-name">Carlos Santos</div>
                    <div class="member-role">Gerente de Operações</div>
                    <div class="member-bio">Cuida da logística e qualidade dos produtos, garantindo que cada doce chegue perfeito ao cliente.</div>
                </div>
                
                <div class="team-member">
                    <div class="member-photo">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="member-name">Maria Oliveira</div>
                    <div class="member-role">Atendimento ao Cliente</div>
                    <div class="member-bio">Responsável pelo relacionamento com os clientes e pelo suporte pós-venda. Sempre pronta para ajudar!</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="content-section">
        <div class="container">
            <div class="cta-section">
                <h3>Pronto para Experimentar?</h3>
                <p>Descubra por que somos a escolha favorita dos brasileiros no Japão quando o assunto é doce!</p>
                <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
                    <a href="produtos.php" class="btn">
                        <i class="fas fa-shopping-bag"></i>
                        Ver Produtos
                    </a>
                    <a href="contato.php" class="btn btn-outline">
                        <i class="fas fa-envelope"></i>
                        Fale Conosco
                    </a>
                </div>
            </div>
        </div>
    </section>

    <script>
        // Animações de entrada
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
            
            // Aplicar animação aos elementos
            const animatedElements = document.querySelectorAll('.value-card, .team-member, .stat-item');
            animatedElements.forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(30px)';
                el.style.transition = 'all 0.6s ease';
                observer.observe(el);
            });
        });
        
        // Contador animado para estatísticas
        function animateCounters() {
            const counters = document.querySelectorAll('.stat-number');
            counters.forEach(counter => {
                const target = parseInt(counter.textContent.replace(/\D/g, ''));
                let current = 0;
                const increment = target / 50;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        counter.textContent = target + (counter.textContent.includes('+') ? '+' : '');
                        clearInterval(timer);
                    } else {
                        counter.textContent = Math.floor(current) + (counter.textContent.includes('+') ? '+' : '');
                    }
                }, 30);
            });
        }
        
        // Iniciar contador quando a seção entrar na tela
        const statsObserver = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounters();
                    statsObserver.unobserve(entry.target);
                }
            });
        });
        
        const statsSection = document.querySelector('.stats-section');
        if (statsSection) {
            statsObserver.observe(statsSection);
        }
    </script>
</body>
</html>