<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro 500</title>
    <link href="index.css" rel="stylesheet">
</head>
<body class="bg-red-100">
    <div class="flex items-center justify-center min-h-screen">
        <div class="text-center">
            <h1 class="text-5xl font-bold text-red-600">Erro 500</h1>
            <h2 class="text-red-500 mt-4"> <?php echo $this->msg ?> </h2>
            <p class="mt-6 inline-block px-6 py-3 text-white bg-red-500 rounded-lg">Por favor, tenha paciência enquanto resolvemos o problema.</p>
        </div>
    </div>
</body>