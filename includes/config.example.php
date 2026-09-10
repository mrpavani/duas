<?php
// ==========================================================================
// DUÁS - MODELO DE CONFIGURAÇÃO
//
// Este arquivo é só um modelo, sem senhas — pode ficar no versionamento.
//
// Copie e preencha com os dados reais:
//   desenvolvimento:  includes/config.php
//   Hostinger:        includes/config.prod.php
//
// Os dois arquivos preenchidos estão no .gitignore e NÃO vão para o git.
// O config.prod.php precisa ser enviado ao servidor manualmente, uma vez.
//
// O includes/db.php procura nesta ordem:
//   1. config.php   2. config.prod.php   3. variáveis DUAS_DB_*
// ==========================================================================

return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'nome_do_banco',
        'user'    => 'usuario_do_banco',
        'pass'    => 'senha_do_banco',
        'charset' => 'utf8mb4',
    ],

    // true só em desenvolvimento. Em produção os erros vão para o log,
    // nunca são exibidos ao visitante.
    'debug' => false,
];
