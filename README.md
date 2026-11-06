# Manos Comunitarias

Aplicación web comunitaria desarrollada con PHP, MySQL, HTML, CSS y JavaScript.

## Funciones
- Registro e inicio/cierre de sesión.
- Publicaciones para ofrecer artículos o solicitar apoyo.
- Categorías de ropa, útiles escolares, alimentos no perecederos, muebles, servicios y otros.
- Responder a publicaciones de otras personas.
- Marcar como completadas las publicaciones propias.
- Validación de formularios y protección CSRF.

## Instalación en XAMPP
1. Copia la carpeta `Manos_Comunitarios` a `C:\\xampp\\htdocs\\`.
2. En XAMPP inicia Apache y MySQL.
3. Abre `http://localhost/phpmyadmin/`.
4. Importa el archivo `database.sql`.
5. Abre `http://localhost/Manos_Comunitarios/public/`.

La conexión predeterminada de MySQL está configurada para XAMPP (`root`, contraseña vacía). Si tu instalación usa otros datos, edita `config/database.php`.

## Estructura
- `public/index.php`: página principal y procesamiento de formularios.
- `public/app.js`: validación del lado del navegador.
- `public/styles.css`: estilos y diseño adaptable.
- `config/bootstrap.php`: sesión, token CSRF y funciones auxiliares.
- `config/database.php`: conexión PDO a MySQL.
- `database.sql`: creación de la base de datos y tablas.
- `LEEME.txt`: guía rápida.

Por seguridad, no publiques direcciones particulares ni contraseñas. La aplicación funciona en `localhost`; para hacerla pública se necesita alojamiento web y configuración adicional.
