# AGSV-Project
## Configuração inicial do administrador

Se a base de dados ainda não tiver nenhuma conta `ADMIN`, abra no navegador:

`/setup_admin.php`

Preencha o nome, utilizador, email e uma palavra-passe com pelo menos 8 caracteres. A página cria a conta com `role = ADMIN`, guarda a palavra-passe usando `password_hash()` e bloqueia automaticamente a criação quando já existe um administrador.

Depois da criação, entre normalmente por `PHP_Pages/login.php` e faça a gestão de outros utilizadores em `PHP_Pages/admin/utilizadores.php`.

## Configuração

Copiar `config/db.example.php` para `config/db.php` e preencher os dados de acesso à base de dados (o `config/db.php` não é versionado). Criar a base de dados com `config/database.sql`.
