<?php
session_start();

// Обработка выхода из системы
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    // Удаляем токен из сессии
    $_SESSION['token'] = '';
    // Перенаправляем на страницу авторизации
    header("Location: login.php");
    exit;
}

$db = new PDO('mysql:host=localhost; dbname=module; charset=utf8', 
'root', 
null, 
[PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

// 1. Проверка авторизации
// Если пользователь не авторизован (нет или не правильный токен) -> на страницу login
// Если тип пользователя = admin -> на страницу admin
// Если тип пользователя = user -> остаемся на этой странице

// Проверяем наличие токена в сессии
if (!isset($_SESSION['token']) || empty($_SESSION['token'])) {
    // Пользователь не авторизован, оставляем на странице login
} else {
    // Пользователь авторизован, проверяем тип
    $token = $_SESSION['token'];
    $user = $db->query("SELECT id, type FROM users WHERE token = '$token'")->fetch();
    
    // Если токен неверный или не найден в БД
    if (!$user) {
        // Сбрасываем токен
        $_SESSION['token'] = '';
    } else {
        // Проверяем тип пользователя
        if ($user['type'] === 'admin') {
            // Если admin - перенаправляем на страницу admin
            header('Location: admin.php');
            exit;
        } else if ($user['type'] === 'user') {
            // Если user - перенаправляем на страницу user
            header('Location: user.php');
            exit;
        }
    }
}

//  Проверака логина и пароля с БД , запись токена в БД, редирект
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Получить отправленные данные (логин и пароль)
    $login = $_POST['login'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // 2. Проверить переданы ли они, если нет вернуть ошибку
    // Если да -> ничего не делаем
    // Если нет -> ошибка : поля необходтмо заполнить
    if (empty($login) || empty($password)) {
        $error = 'Поля необходимо заполнить';
    } else {
        // 3. Сравнить с данными в БД
        // Если совпали -> генерируем токен, записываем в сессию и бд, редирект
        // Если нет -> Ошибка : неверный логин или пароль
        $user = $db->query("SELECT id, password, type, amountAttempt, blocked FROM users WHERE login = '$login'")->fetch();
        
        // Проверяем, не заблокирован ли пользователь
        if ($user && $user['blocked'] == 1) {
            $error = 'Пользователь заблокирован. Обратитесь к администратору.';
        } else {
            if ($user && $user['password'] === $password) {
                // Успешный вход - сбрасываем количество попыток
                $userId = $user['id'];
                $db->query("UPDATE users SET amountAttempt = 0 WHERE id = $userId");
                
                // Генерируем токен
                $token = bin2hex(random_bytes(16));
                
                // Записываем токен в сессию
                $_SESSION['token'] = $token;
                
                // Записываем токен в БД и обновляем время последней активности
                $currentTime = date('Y-m-d H:i:s');
                $db->query("UPDATE users SET token = '$token', latest = '$currentTime' WHERE id = $userId");
                
                // Редирект в зависимости от типа пользователя
                if ($user['type'] === 'admin') {
                    header('Location: admin.php');
                    exit;
                } else {
                    header('Location: user.php');
                    exit;
                }
            } else {
                // Неуспешный вход
                if ($user) {
                    // Увеличиваем количество попыток
                    $userId = $user['id'];
                    $newAttempts = ($user['amountAttempt'] ?? 0) + 1;
                    $db->query("UPDATE users SET amountAttempt = $newAttempts WHERE id = $userId");
                    
                    // Если попыток больше 3, блокируем пользователя
                    if ($newAttempts > 3) {
                        $db->query("UPDATE users SET blocked = 1 WHERE id = $userId");
                        $error = 'Пользователь заблокирован. Обратитесь к администратору.';
                    } else {
                        $error = 'Неверный логин или пароль. Осталось попыток: ' . (3 - $newAttempts);
                    }
                } else {
                    $error = 'Неверный логин или пароль';
                }
            }
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
    <title>Авторизация</title>
</head>
<body>
    <div class="login">
        <form method="POST" action="login.php">
            <h1>Авторизация</h1>
            <label for="login">
                Введите логин
                <?php if(isset($error) && empty($login)): ?><span class="error">Необходимо заполнить</span><?php endif; ?>
            </label>
            <input type="text" name="login" id="login" >
            <label for="password">
                Введите пароль
                <?php if(isset($error) && empty($password)): ?><span class="error">Необходимо заполнить</span><?php endif; ?>
            </label>
            <input type="password" name="password" id="password" >
            <button type="submit">Войти</button>
            <?php if(isset($error)): ?><p class="error"><?php echo $error; ?></p><?php endif; ?>
        </form>
    </div>
</body>
</html>