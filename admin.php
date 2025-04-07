<?php
session_start();

// 1. Проверка авторизации
// Если пользователь не авторизован (нет или не правильный токен) -> на страницу login
// Если тип пользователя = admin -> остаемся на этой странице
// Если тип пользователя = user -> на страницу user

// Подключение к БД
$db = new PDO('mysql:host=localhost; dbname=module; charset=utf8', 
'root', 
null, 
[PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

// Проверяем наличие токена в сессии
if (!isset($_SESSION['token']) || empty($_SESSION['token'])) {
    // Пользователь не авторизован, перенаправляем на страницу login
    header('Location: login.php');
    exit();
}

// Получаем информацию о пользователе по токену
$token = $_SESSION['token'];
$user = $db->query("SELECT id, login, type, name, surname FROM users WHERE token = '$token'")->fetch();

// Если пользователь не найден или токен неверный
if (!$user) {
    // Сбрасываем токен и перенаправляем на страницу авторизации
    $_SESSION['token'] = '';
    header('Location: login.php');
    exit();
}

// Проверяем тип пользователя
if ($user['type'] === 'user') {
    // Если user - перенаправляем на страницу user
    header('Location: user.php');
    exit();
}
// Если тип admin - продолжаем работу на этой странице

// 4. Обработка выхода из учетной записи
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    // Сбрасываем $_SESSION['token']
    $_SESSION['token'] = '';
    // Сбрасываем токен в БД
    $userId = $user['id'];
    $db->query("UPDATE users SET token = '' WHERE id = $userId");
    // Переходим на страницу login.php
    header('Location: login.php');
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <title>Админ-панель</title>
</head>
<body>
    <div class="login">
        <h1>Панель администратора</h1>
        <!-- 3. Отображение имени и фамилии пользователя и типа пользователя -->
        <p>ФИО: <?php echo htmlspecialchars($user['name'] ?? ''); ?> <?php echo htmlspecialchars($user['surname'] ?? ''); ?></p>
        <p>Тип пользователя: <?php echo htmlspecialchars($user['type']); ?></p>
        
        <!-- 4. Кнопка выхода из учетной записи -->
        <p><a href="admin.php?logout=1" class="logout-btn">Выйти из учетной записи</a></p>
    </div>
</body>
</html> 