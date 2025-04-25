<?php
use Models\Session\Session;

Session::init();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link href="index.css" rel="stylesheet">
</head>
<body>
    <?php
    if (Session::get("user")) {
        require "Components/header.php";
    }else {
        require "Components/headerInit.php";
    }
    ?>
    <div class="flex items-center justify-center min-h-screen w-full bg-gray-100">
        <div class="w-full max-w-md bg-white p-8 rounded-lg shadow-md">
            <h1 class="text-2xl font-bold text-gray-800 mb-6 text-center">Criar Novo Usuário</h1>
            <form action="/cadastro/create" method="POST">
                <div class="mb-4">
                    <label for="nome" class="block text-gray-700 font-medium mb-2">Nome</label>
                    <input type="text" id="nome" name="nome" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="mb-4">
                    <label for="email" class="block text-gray-700 font-medium mb-2">Email</label>
                    <input type="email" id="email" name="email" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="mb-4">
                    <label for="senha" class="block text-gray-700 font-medium mb-2">Senha</label>
                    <input type="password" id="senha" name="senha" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <button type="submit" class="w-full bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600">Criar Usuário</button>
            </form>
        </div>
    </div>

</body>
</html>