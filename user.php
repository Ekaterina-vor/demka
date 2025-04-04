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
        <form>
            <h1>Пользователь</h1>
            <label for="login">
                Пароль
                <span class="error">Необходимо заполнить</span>
            </label>
            <input type="text" name="login" id="login">
            <label for="password">
                Подтвердите пароль
                <span class="error">Необходимо заполнить</span>
            </label>
            <input type="text" name="password" id="password">
            <button type="submit">Сменить пароль</button>
            <p class="error">Неверный пароль</p>
        </form>
    </div>
</body>
</html>