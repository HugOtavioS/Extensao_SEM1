<?php
use Models\Session\Session;
use Models\Request\Request;

Session::init();

if (isset($_GET["error"])) {

    switch ($_GET["error"]) {
        case 0:
            $error = "Funcionário não encontrado";
            break;
        case 1:
            $error = "Coloque seu Email";
            break;
        case 2:
            $error = "Coloque sua Senha";
        default:
    }
    
}

if (Session::get("user")) {
    Request::redirect("/");
}

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
    <?php require "Components/headerInit.php" ?>

    <div class="flex items-center justify-center min-h-screen bg-gray-100">
        <div class="text-center">
            <h1 class="text-4xl font-bold text-gray-800">Bem-vindo ao nosso site!</h1>
            <h2 class="text-gray-800"><?php echo $this->title ?></h2>
            <form action="login/create" method="post" class="mt-8 space-y-6">
                <fieldset class="border-0">

                    <legend class="text-2xl font-semibold text-gray-800 mb-6 text-center">Faça seu Login</legend>
                    <?php if (isset($error)) echo "<p class='text-red-600'>$error</p>" ?>
                    <div class="flex flex-col space-y-2">
                        <label for="funcionario" class="text-left text-gray-600">Funcionário</label>
                        <input type="text" name="funcionario" id="funcionario" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 hover:bg-gray-300 duration-100">
                    </div>

                    <div class="flex flex-col space-y-2">
                        <label for="senha" class="text-left text-gray-600">Senha</label>
                        <input type="password" name="senha" id="senha" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 hover:bg-gray-300 duration-100">
                    </div>

                    <button type="submit" class="w-full mt-6 px-6 py-3 text-white bg-blue-500 rounded-lg hover:bg-blue-600 transition-colors">
                        Entrar
                    </button>

                </fieldset>
            </form>
        </div>
    </div>

</body>
</html>