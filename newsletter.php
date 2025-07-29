<?php
require_once 'config.php';

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $nome = sanitize($_POST['nome'] ?? '');
    
    // Validar e-mail
    if (empty($email)) {
        flashMessage('Por favor, informe seu e-mail.', 'warning');
        redirect('index.php');
    }
    
    if (!validateEmail($email)) {
        flashMessage('E-mail inválido. Por favor, verifique e tente novamente.', 'error');
        redirect('index.php');
    }
    
    try {
        // Verificar se já está cadastrado
        $stmt = $db->prepare("SELECT id FROM newsletter WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            flashMessage('Este e-mail já está cadastrado na nossa newsletter! 📧', 'info');
        } else {
            // Inserir na newsletter
            $token = generateToken();
            $stmt = $db->prepare("INSERT INTO newsletter (email, nome, token, created_at) VALUES (?, ?, ?, NOW())");
            
            if ($stmt->execute([$email, $nome, $token])) {
                flashMessage('✅ E-mail cadastrado com sucesso! Você receberá nossas novidades em breve.', 'success');
                
                // Log da ação
                logActivity('newsletter_signup', 'E-mail cadastrado na newsletter: ' . $email);
                
                // TODO: Integração com Mailgun aqui
                // sendWelcomeEmail($email, $nome);
                
            } else {
                flashMessage('Erro ao cadastrar e-mail. Tente novamente.', 'error');
            }
        }
    } catch (Exception $e) {
        // Log do erro
        error_log('Erro newsletter: ' . $e->getMessage());
        flashMessage('Erro interno. Tente novamente mais tarde.', 'error');
    }
    
    redirect('index.php');
} else {
    // Se não for POST, redirecionar para home
    redirect('index.php');
}
?>