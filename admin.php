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
$admin = $db->query("SELECT id, login, type, name, surname FROM users WHERE token = '$token'")->fetch();

// Если пользователь не найден или токен неверный
if (!$admin) {
    // Сбрасываем токен и перенаправляем на страницу авторизации
    $_SESSION['token'] = '';
    header('Location: login.php');
    exit();
}

// Проверяем тип пользователя
if ($admin['type'] !== 'admin') {
    // Если не admin - перенаправляем на страницу user
    header('Location: user.php');
    exit();
}

// Обработка выхода из системы
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    // Сбрасываем токен в сессии
    $_SESSION['token'] = '';
    // Сбрасываем токен в БД
    $adminId = $admin['id'];
    $db->query("UPDATE users SET token = '' WHERE id = $adminId");
    // Перенаправляем на страницу авторизации
    header('Location: login.php');
    exit();
}

// Обработка разблокировки пользователя
if (isset($_GET['unblock']) && !empty($_GET['unblock'])) {
    $userId = (int)$_GET['unblock'];
    $db->query("UPDATE users SET blocked = 0, amountAttempt = 0 WHERE id = $userId");
    header('Location: admin.php');
    exit();
}

// Обработка блокировки пользователя
if (isset($_GET['block']) && !empty($_GET['block'])) {
    $userId = (int)$_GET['block'];
    $db->query("UPDATE users SET blocked = 1 WHERE id = $userId");
    header('Location: admin.php');
    exit();
}

// Получаем список всех пользователей
$users = $db->query("
    SELECT 
        id,
        login,
        name,
        surname,
        blocked,
        amountAttempt,
        latest as last_activity,
        type
    FROM users 
    ORDER BY login ASC
")->fetchAll();

// Обработка добавления нового пользователя
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_user') {
    $newLogin = trim($_POST['login'] ?? '');
    $newPassword = trim($_POST['password'] ?? '');
    $newName = trim($_POST['name'] ?? '');
    $newSurname = trim($_POST['surname'] ?? '');
    $newType = $_POST['type'] ?? 'user';

    if (empty($newLogin) || empty($newPassword)) {
        $error = 'Логин и пароль обязательны для заполнения';
    } else {
        // Проверяем, не существует ли уже такой логин
        $check = $db->query("SELECT id FROM users WHERE login = '$newLogin'")->fetch();
        if ($check) {
            $error = 'Пользователь с таким логином уже существует';
        } else {
            $db->query("INSERT INTO users (login, password, name, surname, type) 
                       VALUES ('$newLogin', '$newPassword', '$newName', '$newSurname', '$newType')");
            header('Location: admin.php');
            exit();
        }
    }
}

// Обработка редактирования пользователя
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_user') {
    $userId = (int)$_POST['user_id'];
    $editName = trim($_POST['name'] ?? '');
    $editSurname = trim($_POST['surname'] ?? '');
    $editPassword = trim($_POST['password'] ?? '');
    
    $updateFields = [];
    if (!empty($editName)) $updateFields[] = "name = '$editName'";
    if (!empty($editSurname)) $updateFields[] = "surname = '$editSurname'";
    if (!empty($editPassword)) $updateFields[] = "password = '$editPassword'";
    
    if (!empty($updateFields)) {
        $updateQuery = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = $userId";
        $db->query($updateQuery);
    }
    
    header('Location: admin.php');
    exit();
}

// Получение данных пользователя для редактирования
$editUserId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$editUserData = null;
if ($editUserId) {
    $editUserData = $db->query("SELECT id, login, name, surname FROM users WHERE id = $editUserId")->fetch();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style.css">
    <title>Панель администратора</title>
    <style>
        .admin-panel {
            width: 100%;
            max-width: 1000px;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px var(--shadow-color);
        }
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .users-table th,
        .users-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .users-table th {
            background-color: var(--background-color);
            font-weight: 600;
        }
        .status-blocked {
            color: var(--error-color);
        }
        .unblock-btn {
            display: inline-block;
            background-color: var(--success-color);
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
        }
        .block-btn {
            display: inline-block;
            background-color: var(--error-color);
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
        }
        .unblock-btn:hover,
        .block-btn:hover {
            opacity: 0.9;
            color: white;
            text-decoration: none;
        }
        .admin-info {
            padding: 15px;
            background-color: var(--background-color);
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .form-section {
            margin: 20px 0;
            padding: 20px;
            background-color: var(--background-color);
            border-radius: 8px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .form-group label {
            font-weight: 500;
            font-size: 14px;
        }
        .form-group input, .form-group select {
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }
        .edit-btn {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            margin-right: 5px;
        }
        .edit-btn:hover {
            background-color: var(--primary-hover);
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="admin-panel">
        <h1>Панель администратора</h1>
        
        <div class="admin-info">
            <p>Администратор: <?php echo htmlspecialchars($admin['name'] . ' ' . $admin['surname']); ?></p>
        </div>

        <?php if ($editUserData): ?>
            <!-- Форма редактирования пользователя -->
            <div class="form-section">
                <h2>Редактировать пользователя</h2>
                <form method="POST" action="admin.php">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" value="<?php echo $editUserData['id']; ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Логин</label>
                            <input type="text" value="<?php echo htmlspecialchars($editUserData['login']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label>Новый пароль</label>
                            <input type="password" name="password" placeholder="Оставьте пустым, чтобы не менять">
                        </div>
                        <div class="form-group">
                            <label>Имя</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($editUserData['name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Фамилия</label>
                            <input type="text" name="surname" value="<?php echo htmlspecialchars($editUserData['surname'] ?? ''); ?>">
                        </div>
                    </div>
                    <button type="submit" class="unblock-btn">Сохранить изменения</button>
                    <a href="admin.php" class="block-btn">Отмена</a>
                </form>
            </div>
        <?php else: ?>
            <!-- Форма добавления нового пользователя -->
            <div class="form-section">
                <h2>Добавить пользователя</h2>
                <form method="POST" action="admin.php">
                    <input type="hidden" name="action" value="add_user">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Логин *</label>
                            <input type="text" name="login" required>
                        </div>
                        <div class="form-group">
                            <label>Пароль *</label>
                            <input type="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label>Имя</label>
                            <input type="text" name="name">
                        </div>
                        <div class="form-group">
                            <label>Фамилия</label>
                            <input type="text" name="surname">
                        </div>
                        <div class="form-group">
                            <label>Тип пользователя</label>
                            <select name="type">
                                <option value="user">Пользователь</option>
                                <option value="admin">Администратор</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="unblock-btn">Добавить пользователя</button>
                    <?php if (isset($error)): ?>
                        <p class="error"><?php echo htmlspecialchars($error); ?></p>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>

        <h2>Список пользователей</h2>
        <table class="users-table">
            <thead>
                <tr>
                    <th>Логин</th>
                    <th>Имя</th>
                    <th>Фамилия</th>
                    <th>Статус</th>
                    <th>Попыток входа</th>
                    <th>Последняя активность</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['login']); ?></td>
                        <td><?php echo htmlspecialchars($user['name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($user['surname'] ?? ''); ?></td>
                        <td><?php echo $user['blocked'] ? '<span class="status-blocked">Заблокирован</span>' : 'Активен'; ?></td>
                        <td><?php echo htmlspecialchars($user['amountAttempt']); ?></td>
                        <td><?php echo $user['last_activity'] ? date('d.m.Y H:i', strtotime($user['last_activity'])) : '-'; ?></td>
                        <td>
                            <?php if ($user['type'] !== 'admin' || $admin['id'] == $user['id']): ?>
                                <a href="admin.php?edit=<?php echo $user['id']; ?>" class="edit-btn">Редактировать</a>
                                <?php if ($user['type'] !== 'admin'): ?>
                                    <?php if ($user['blocked']): ?>
                                        <a href="admin.php?unblock=<?php echo $user['id']; ?>" class="unblock-btn">Разблокировать</a>
                                    <?php else: ?>
                                        <a href="admin.php?block=<?php echo $user['id']; ?>" class="block-btn">Заблокировать</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p><a href="admin.php?logout=1" class="logout-btn">Выйти из учетной записи</a></p>
    </div>
</body>
</html> 