<?php
// ============================================
// ARQUIVO: logout.php
// ============================================
?>
<?php
require_once 'config.php';

// Fazer logout
$userClass->logout();
flashMessage('Você foi desconectado com sucesso!', 'info');
redirect('index.php');
?>