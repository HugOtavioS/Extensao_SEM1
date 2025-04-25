<?php
use Models\Session\Session;

Session::init();

$style = file_get_contents(__STYLE__) ;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        <?php echo $style ?>
    </style>
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

    <div class="flex items-center justify-center min-h-screen bg-gray-100">
        <div class="text-center">
            <h1 class="text-4xl font-bold text-gray-800">Bem-vindo ao nosso site!</h1>
            <h2 class="text-gray-800"><?php echo $this->title ?></h2>
            <p class="mt-4 text-lg text-gray-600">Estamos felizes em tê-lo aqui. Explore nossos produtos e serviços.</p>
            <a href="/produtos" class="mt-6 inline-block px-6 py-3 text-white bg-blue-500 rounded-lg hover:bg-blue-600">Ver Produtos</a>
        </div>
    </div>

</body>
</html>