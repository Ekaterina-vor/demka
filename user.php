<?php
session_start();

// 1. Проверка авторизации
// Если пользователь не авторизован (нет или не правильный токен) -> на страницу login
// Если тип пользователя = admin -> на страницу admin
// Если тип пользователя = user -> остаемся на этой странице

// Подключение к БД
$db = new PDO('mysql:host=localhost; dbname=module; charset=utf8', 
'root', 
null, 
[PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

// Проверяем наличие токена в сессии
if (!isset($_SESSION['token']) || empty($_SESSION['token'])) {
    // Пользователь не авторизован, перенаправляем на страницу login
    header("Location: login.php");
    exit();
}

// Получаем информацию о пользователе по токену
$token = $_SESSION['token'];
$user = $db->query("SELECT id, login, type, name, surname FROM users WHERE token = '$token'")->fetch();

// Если пользователь не найден или токен неверный
if (!$user) {
    // Сбрасываем токен и перенаправляем на страницу авторизации
    $_SESSION['token'] = '';
    header("Location: login.php");
    exit();
}

// Проверяем тип пользователя
if ($user['type'] === 'admin') {
    // Если admin - перенаправляем на страницу admin
    header('Location: admin.php');
    exit();
}
// Если тип user - продолжаем работу на этой странице

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

$error = '';
$success = '';

// Обработка формы смены пароля
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Проверяем, что пароли введены
    if (empty($newPassword)) {
        $error = 'Введите новый пароль';
    } elseif (empty($confirmPassword)) {
        $error = 'Подтвердите пароль';
    } 
    // Проверяем совпадение паролей
    elseif ($newPassword !== $confirmPassword) {
        $error = 'Пароли не совпадают';
    } else {
        // Обновляем пароль в БД
        $userId = $user['id'];
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $result = $stmt->execute([$newPassword, $userId]);
        
        if ($result) {
            $success = 'Пароль успешно изменен';
        } else {
            $error = 'Ошибка при изменении пароля';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <title>Пользователь
    </title>
</head>
<body>
    <div class="login">
        <form method="POST" action="user.php">
            <h1>Пользователь</h1>
            
            <!-- 3. Отображение имени и фамилии пользователя и типа пользователя -->
            <p>ФИО: <?php echo htmlspecialchars($user['name'] ?? ''); ?> <?php echo htmlspecialchars($user['surname'] ?? ''); ?></p>
            <p>Тип пользователя: <?php echo htmlspecialchars($user['type']); ?></p>
            
            <label for="password">
                Новый пароль
                <?php if($error === 'Введите новый пароль'): ?><span class="error">Необходимо заполнить</span><?php endif; ?>
            </label>
            <input type="password" name="password" id="password" required>
            
            <label for="confirm_password">
                Подтвердите пароль
                <?php if($error === 'Подтвердите пароль'): ?><span class="error">Необходимо заполнить</span><?php endif; ?>
            </label>
            <input type="password" name="confirm_password" id="confirm_password" required>
            
            <button type="submit">Сменить пароль</button>
            
            <?php if(!empty($error)): ?><p class="error"><?php echo $error; ?></p><?php endif; ?>
            <?php if(!empty($success)): ?><p class="success"><?php echo $success; ?></p><?php endif; ?>
            
            <!-- 4. Кнопка выхода из учетной записи -->
            <p><a href="user.php?logout=1" class="logout-btn">Выйти из учетной записи</a></p>
        </form>
    </div>
</body>
</html>