<?php
require_once __DIR__ . '/../config/bootstrap.php';

$pdo = null;
$errorConexion = '';

try {
    $pdo = db();
} catch (Throwable $e) {
    error_log($e->getMessage());
    $errorConexion = 'No se pudo conectar con MySQL. Revisa que MySQL esté encendido y que hayas importado la base de datos.';
}

$usuario = $_SESSION['usuario'] ?? null;
$mensaje = $_SESSION['mensaje'] ?? '';
$tipoMensaje = $_SESSION['tipo_mensaje'] ?? 'success';
unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarToken()) {
        flash('La solicitud venció. Actualiza la página e inténtalo otra vez.', 'error');
        redirigir();
    }

    if (!$pdo) {
        flash('No hay conexión con la base de datos.', 'error');
        redirigir();
    }

    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'registro') {
            $nombre = trim($_POST['nombre'] ?? '');
            $correo = trim($_POST['correo'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($nombre === '' || strlen($nombre) > 80 ||
                !filter_var($correo, FILTER_VALIDATE_EMAIL) ||
                !is_string($password) || strlen($password) < 8) {
                flash('Revisa tus datos. La contraseña debe tener al menos 8 caracteres.', 'error');
                redirigir();
            }

            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$correo]);

            if ($stmt->fetch()) {
                flash('Ese correo ya tiene una cuenta.', 'error');
                redirigir();
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
            $stmt->execute([$nombre, $correo, $hash]);

            flash('Tu cuenta se creó correctamente. Ahora puedes iniciar sesión.');
            redirigir();
        }

        if ($accion === 'login') {
            $correo = trim($_POST['correo'] ?? '');
            $password = $_POST['password'] ?? '';

            $stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
            $stmt->execute([$correo]);
            $datos = $stmt->fetch();

            if (!$datos || !is_string($password) || !password_verify($password, $datos['password_hash'])) {
                flash('Correo o contraseña incorrectos.', 'error');
                redirigir();
            }

            session_regenerate_id(true);
            $_SESSION['usuario'] = [
                'id' => (int)$datos['id'],
                'nombre' => $datos['name'],
                'correo' => $datos['email']
            ];

            flash('¡Qué bueno tenerte aquí, ' . $datos['name'] . '!');
            redirigir();
        }

        if ($accion === 'logout') {
            unset($_SESSION['usuario']);
            session_regenerate_id(true);
            flash('Cerraste sesión.');
            redirigir();
        }

        if ($accion === 'publicar') {
            if (!$usuario) {
                flash('Inicia sesión para publicar.', 'error');
                redirigir();
            }

            $tipo = $_POST['tipo'] ?? '';
            $titulo = trim($_POST['titulo'] ?? '');
            $categoria = trim($_POST['categoria'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $ubicacion = trim($_POST['ubicacion'] ?? '');
            $rutaImagen = null;

            // La foto es opcional. Solo se aceptan imágenes JPG, PNG o WEBP de hasta 4 MB.
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
                    flash('No se pudo subir la imagen. Intenta con otra foto de máximo 4 MB.', 'error');
                    redirigir();
                }

                if ($_FILES['imagen']['size'] > 4 * 1024 * 1024) {
                    flash('La imagen no debe pesar más de 4 MB.', 'error');
                    redirigir();
                }

                $infoImagen = new finfo(FILEINFO_MIME_TYPE);
                $tipoMime = $infoImagen->file($_FILES['imagen']['tmp_name']);
                $extensionesPermitidas = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];

                if (!isset($extensionesPermitidas[$tipoMime])) {
                    flash('Formato no válido. Sube una imagen JPG, PNG o WEBP.', 'error');
                    redirigir();
                }

                $carpetaSubidas = __DIR__ . '/uploads';
                if (!is_dir($carpetaSubidas) && !mkdir($carpetaSubidas, 0755, true)) {
                    flash('No se pudo preparar la carpeta para las imágenes.', 'error');
                    redirigir();
                }

                $nombreImagen = bin2hex(random_bytes(16)) . '.' . $extensionesPermitidas[$tipoMime];
                if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $carpetaSubidas . '/' . $nombreImagen)) {
                    flash('No se pudo guardar la imagen. Inténtalo nuevamente.', 'error');
                    redirigir();
                }

                $rutaImagen = 'uploads/' . $nombreImagen;
            }

            $tiposValidos = ['ofrezco', 'necesito'];
            $categoriasValidas = ['Ropa y calzado', 'Útiles escolares', 'Alimentos no perecederos', 'Muebles y hogar', 'Apoyo y servicios', 'Otros'];

            if (!in_array($tipo, $tiposValidos, true) ||
                !in_array($categoria, $categoriasValidas, true) ||
                $titulo === '' || strlen($titulo) > 100 ||
                $descripcion === '' || strlen($descripcion) > 600 ||
                $ubicacion === '' || strlen($ubicacion) > 160) {
                flash('Completa correctamente todos los campos de la publicación.', 'error');
                redirigir();
            }

            $stmt = $pdo->prepare(
                'INSERT INTO posts (user_id, post_type, title, category, description, location, image_path)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$usuario['id'], $tipo, $titulo, $categoria, $descripcion, $ubicacion, $rutaImagen]);

            flash('Tu publicación ya está visible en la comunidad.');
            redirigir();
        }

        if ($accion === 'solicitar') {
            if (!$usuario) {
                flash('Inicia sesión para responder a una publicación.', 'error');
                redirigir();
            }

            $postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$postId || $postId < 1) {
                flash('La publicación seleccionada no es válida.', 'error');
                redirigir();
            }

            $stmt = $pdo->prepare('SELECT user_id, status FROM posts WHERE id = ?');
            $stmt->execute([$postId]);
            $post = $stmt->fetch();

            if (!$post || $post['status'] !== 'open' || (int)$post['user_id'] === (int)$usuario['id']) {
                flash('No puedes responder a esta publicación.', 'error');
                redirigir();
            }

            $stmt = $pdo->prepare('INSERT INTO post_responses (post_id, user_id) VALUES (?, ?)');
            $stmt->execute([$postId, $usuario['id']]);

            flash('Tu interés fue enviado. Puedes ponerte en contacto con la persona mediante los datos que acuerden compartir.');
            redirigir();
        }

        if ($accion === 'completar') {
            if (!$usuario) {
                flash('Inicia sesión para realizar esta acción.', 'error');
                redirigir();
            }

            $postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$postId || $postId < 1) {
                flash('La publicación seleccionada no es válida.', 'error');
                redirigir();
            }

            $stmt = $pdo->prepare("UPDATE posts SET status = 'completed' WHERE id = ? AND user_id = ? AND status = 'open'");
            $stmt->execute([$postId, $usuario['id']]);

            flash($stmt->rowCount() ? 'La publicación se marcó como completada.' : 'No se pudo actualizar la publicación.', $stmt->rowCount() ? 'success' : 'error');
            redirigir();
        }

        flash('La acción seleccionada no existe.', 'error');
        redirigir();

    } catch (PDOException $e) {
        error_log($e->getMessage());
        if ($e->getCode() === '23000') {
            flash('Ya enviaste una respuesta a esta publicación.', 'error');
        } else {
            flash('Ocurrió un problema al guardar la información. Revisa la base de datos.', 'error');
        }
        redirigir();
    } catch (Throwable $e) {
        error_log($e->getMessage());
        flash('Ocurrió un error inesperado. Intenta nuevamente.', 'error');
        redirigir();
    }
}

$publicaciones = [];
if ($pdo) {
    try {
        $stmt = $pdo->query(
            'SELECT posts.*, users.name AS author
             FROM posts
             INNER JOIN users ON users.id = posts.user_id
             ORDER BY posts.created_at DESC'
        );
        $publicaciones = $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log($e->getMessage());
        $mensaje = 'No se pudieron cargar las publicaciones.';
        $tipoMensaje = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manos Comunitarias</title>
    <link rel="stylesheet" href="styles.css">
    <script src="app.js" defer></script>
</head>
<body>
<header class="hero">
    <nav class="topbar" aria-label="Navegación principal">
        <a class="brand" href="index.php"><span class="brand-mark">MC</span> Manos Comunitarias</a>
        <a class="nav-link" href="#publicaciones">Ver publicaciones</a>
    </nav>
    <div class="hero-content">
        <span class="eyebrow">Juntos hacemos comunidad</span>
        <h1>Compartir también<br>es una forma de ayudar.</h1>
        <p>Conecta con personas de tu comunidad, ofrece lo que ya no necesitas y encuentra el apoyo que buscas.</p>
        <a class="hero-button" href="#cuenta">Quiero participar <span aria-hidden="true">→</span></a>
    </div>
    <div class="hero-note"><span class="note-icon">♥</span> Cada pequeño gesto puede cambiar un día.</div>
</header>

<main class="container">
    <?php if ($errorConexion): ?>
        <div class="alert error"><?= e($errorConexion) ?></div>
    <?php endif; ?>

    <?php if ($mensaje): ?>
        <div class="alert <?= $tipoMensaje === 'error' ? 'error' : 'success' ?>"><?= e($mensaje) ?></div>
    <?php endif; ?>

    <section class="intro panel">
        <span class="section-kicker">La ayuda empieza contigo</span>
        <h2>Una comunidad que se apoya</h2>
        <p>Publica algo que puedas compartir o encuentra apoyo entre las personas de tu comunidad. Puedes ofrecer ropa, útiles escolares, artículos para el hogar o apoyo con algún servicio.</p>
    </section>

    <section class="categories" aria-label="Formas de ayudar">
        <div class="section-heading category-heading">
            <div><span class="section-kicker">Comparte, reutiliza, ayuda</span><h2>Hay muchas formas de echar una mano</h2></div>
        </div>
        <div class="category-grid">
            <article class="category-tile">
                <img src="https://images.unsplash.com/photo-1489987707025-afc232f7ea0f?auto=format&fit=crop&w=700&q=85" alt="Ropa doblada y lista para compartir" loading="lazy">
                <div><span>01</span><h3>Ropa y calzado</h3></div>
            </article>
            <article class="category-tile">
                <img src="https://images.unsplash.com/photo-1453738773917-9c3eff1db985?auto=format&fit=crop&w=700&q=85" alt="Cuadernos y materiales escolares" loading="lazy">
                <div><span>02</span><h3>Útiles escolares</h3></div>
            </article>
            <article class="category-tile">
                <img src="https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=700&q=85" alt="Frutas y verduras frescas" loading="lazy">
                <div><span>03</span><h3>Alimentos</h3></div>
            </article>
            <article class="category-tile">
                <img src="https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=700&q=85" alt="Muebles y artículos para el hogar" loading="lazy">
                <div><span>04</span><h3>Hogar y apoyo</h3></div>
            </article>
        </div>
    </section>

    <?php if (!$usuario): ?>
        <div class="two-columns" id="cuenta">
            <section class="panel">
                <h2>Crear una cuenta</h2>
                <form method="POST" data-validate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="accion" value="registro">
                    <label for="nombre">Nombre completo</label>
                    <input id="nombre" name="nombre" maxlength="80" required>
                    <label for="registro-correo">Correo electrónico</label>
                    <input id="registro-correo" type="email" name="correo" maxlength="120" required>
                    <label for="registro-password">Contraseña (mínimo 8 caracteres)</label>
                    <input id="registro-password" type="password" name="password" minlength="8" required>
                    <button type="submit">Crear cuenta</button>
                </form>
            </section>

            <section class="panel">
                <h2>Iniciar sesión</h2>
                <form method="POST" data-validate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="accion" value="login">
                    <label for="login-correo">Correo electrónico</label>
                    <input id="login-correo" type="email" name="correo" required>
                    <label for="login-password">Contraseña</label>
                    <input id="login-password" type="password" name="password" required>
                    <button type="submit">Entrar</button>
                </form>
            </section>
        </div>
    <?php else: ?>
        <section class="panel welcome">
            <div>
                <h2>¡Hola, <?= e($usuario['nombre']) ?>!</h2>
                <p>Gracias por formar parte de esta comunidad.</p>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="accion" value="logout">
                <button class="button-secondary" type="submit">Cerrar sesión</button>
            </form>
        </section>

        <section class="panel">
            <h2>Crear una publicación</h2>
            <p class="muted">Cuéntale a la comunidad qué puedes ofrecer o qué necesitas.</p>
            <form method="POST" enctype="multipart/form-data" data-validate>
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="accion" value="publicar">
                <label for="tipo">¿Qué deseas publicar?</label>
                <select id="tipo" name="tipo" required>
                    <option value="">Selecciona una opción</option>
                    <option value="ofrezco">Ofrezco ayuda o un artículo</option>
                    <option value="necesito">Necesito ayuda o un artículo</option>
                </select>
                <label for="titulo">Título</label>
                <input id="titulo" name="titulo" maxlength="100" placeholder="Ej. Donación de útiles escolares" required>
                <label for="categoria">Categoría</label>
                <select id="categoria" name="categoria" required>
                    <option value="">Selecciona una categoría</option>
                    <option>Ropa y calzado</option>
                    <option>Útiles escolares</option>
                    <option>Alimentos no perecederos</option>
                    <option>Muebles y hogar</option>
                    <option>Apoyo y servicios</option>
                    <option>Otros</option>
                </select>
                <label for="descripcion">Descripción</label>
                <textarea id="descripcion" name="descripcion" maxlength="600" rows="4" required></textarea>
                <label for="ubicacion">Zona o colonia (no publiques tu domicilio exacto)</label>
                <input id="ubicacion" name="ubicacion" maxlength="160" required>
                <label for="imagen">Foto de la donación (opcional)</label>
                <input id="imagen" name="imagen" type="file" accept="image/jpeg,image/png,image/webp">
                <small class="field-help">Puedes subir JPG, PNG o WEBP de máximo 4 MB.</small>
                <button type="submit">Publicar</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="section-heading" id="publicaciones">
        <div>
            <span class="section-kicker">Personas ayudando a personas</span>
            <h2>Publicaciones de la comunidad</h2>
            <p>Conoce las ofertas y solicitudes de apoyo.</p>
        </div>
        <span class="community-stamp">♥ Comunidad<br>que comparte</span>
    </section>

    <?php if (!$publicaciones): ?>
        <section class="panel empty">
            <h3>Aún no hay publicaciones</h3>
            <p>Cuando alguien publique una oferta o solicitud, aparecerá aquí.</p>
        </section>
    <?php else: ?>
        <div class="posts-grid">
            <?php foreach ($publicaciones as $post): ?>
                <article class="post-card">
                    <?php if (!empty($post['image_path'])): ?>
                        <button class="post-image-button" type="button"
                                data-image="<?= e($post['image_path']) ?>"
                                data-image-alt="<?= e($post['title']) ?>"
                                aria-label="Ampliar foto de <?= e($post['title']) ?>">
                            <img class="post-image" src="<?= e($post['image_path']) ?>" alt="Foto de la donación: <?= e($post['title']) ?>" loading="lazy">
                            <span class="image-hint">Ver foto ampliada</span>
                        </button>
                    <?php endif; ?>
                    <span class="tag <?= $post['post_type'] === 'ofrezco' ? 'tag-offer' : 'tag-need' ?>">
                        <?= $post['post_type'] === 'ofrezco' ? 'Ofrece ayuda' : 'Solicita apoyo' ?>
                    </span>
                    <h3><?= e($post['title']) ?></h3>
                    <p class="category"><?= e($post['category']) ?></p>
                    <p><?= nl2br(e($post['description'])) ?></p>
                    <div class="post-meta">
                        <span><strong>Zona:</strong> <?= e($post['location']) ?></span>
                        <span><strong>Publicado por:</strong> <?= e($post['author']) ?></span>
                    </div>
                    <p class="status <?= $post['status'] === 'open' ? 'open' : 'completed' ?>">
                        <?= $post['status'] === 'open' ? 'Disponible' : 'Completada' ?>
                    </p>

                    <?php if ($usuario && (int)$usuario['id'] !== (int)$post['user_id'] && $post['status'] === 'open'): ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="accion" value="solicitar">
                            <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                            <button type="submit">Me interesa</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($usuario && (int)$usuario['id'] === (int)$post['user_id'] && $post['status'] === 'open'): ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="accion" value="completar">
                            <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                            <button class="button-secondary" type="submit">Marcar como completada</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<div class="image-modal" id="imageModal" aria-hidden="true">
    <div class="image-modal-backdrop" data-close-modal></div>
    <div class="image-modal-content" role="dialog" aria-modal="true" aria-label="Foto de la donación">
        <button class="image-modal-close" type="button" data-close-modal aria-label="Cerrar imagen">×</button>
        <img id="modalDonationImage" src="" alt="">
        <p id="modalDonationCaption"></p>
    </div>
</div>

<footer>
    <div class="footer-heart">♥</div>
    <p class="footer-title">Manos Comunitarias</p>
    <p>Pequeñas acciones, grandes cambios.</p>
    <small>Cuida tus datos personales: comparte solo la zona general y acuerda entregas en lugares públicos.</small>
</footer>
</body>
</html>
