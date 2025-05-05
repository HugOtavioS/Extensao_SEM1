<?php
namespace Controllers;

use Config\Database\Database;
use Config\env;
use Controllers\ViewController;
use Controllers\Interfaces\ControllerInterface;

class CadastroController implements ControllerInterface {

    private $view;
    private $db;

    public function __construct() {
        $this->db = new Database(new env());
        $this->view = new ViewController();
    }

    public function index(...$args) {
        $this->view->load('cadastro', [
            'title' => 'Cadastro',
            'description' => 'Página de cadastro',
        ]);
    }

    public function create() {
        $nome = $_POST["nome"];
        $email = $_POST["email"];
        $senha = $_POST["senha"];

        $this->db->create([
            'nome' => -$nome,
            'email' => $email,
            'senha' => $senha,
        ], 'users');
    }
}