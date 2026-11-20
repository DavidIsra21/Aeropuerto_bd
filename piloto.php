<?php
// Carga la conexión (y las funciones e(), jse(), mensajeTrigger())
require __DIR__ . '/conexion.php';

$errores = [];
$mensaje = '';
$datos = ['nombre_piloto' => '', 'numero_licencia' => '', 'fecha_nacimiento' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accionPost = $_POST['accion'] ?? '';
    $accion = '';
    $id = '';

    // Identificar la acción desde el valor del botón
    if ($accionPost === 'Registrar') {
        $accion = 'Registrar';
    } elseif (strpos($accionPost, 'Modificar_') === 0) {
        $accion = 'Modificar';
        $id = substr($accionPost, 10); // Extrae el ID
    } elseif (strpos($accionPost, 'Eliminar_') === 0) {
        $accion = 'Eliminar';
        $id = substr($accionPost, 9); // Extrae el ID
    }

    // --- ACCIONES: REGISTRAR Y MODIFICAR ---
    if ($accion === 'Registrar' || $accion === 'Modificar') {
        $datos['nombre_piloto']    = trim($_POST['nombre_piloto'] ?? '');
        $datos['numero_licencia']  = trim($_POST['numero_licencia'] ?? '');
        $datos['fecha_nacimiento'] = $_POST['fecha_nacimiento'] ?? '';

        if ($datos['nombre_piloto'] === '' || mb_strlen($datos['nombre_piloto']) > 200) {
            $errores[] = 'El nombre es obligatorio (máximo 200 caracteres).';
        }

        // Igual que el CHECK ck_piloto_licencia
        if (!preg_match('/^[0-9]{6}$/', $datos['numero_licencia'])) {
            $errores[] = 'El número de licencia debe tener exactamente 6 dígitos.';
        }

        // Igual que el trigger tr_calcular_edad_piloto
        $fecha = DateTime::createFromFormat('!Y-m-d', $datos['fecha_nacimiento']);
        $hoy   = new DateTime('today');
        if (!$fecha || $fecha->format('Y-m-d') !== $datos['fecha_nacimiento'] || $fecha >= $hoy) {
            $errores[] = 'La fecha de nacimiento debe ser anterior a la fecha actual.';
        } elseif ($fecha->diff($hoy)->y < 18) {
            $errores[] = 'El piloto debe ser mayor de 18 años.';
        }

        if (!$errores) {
            try {
                // edad_piloto NO se manda: la calcula el trigger a partir de fecha_nacimiento
                if ($accion === 'Registrar') {
                    $sql = "INSERT INTO persona.piloto (nombre_piloto, numero_licencia, fecha_nacimiento)
                            VALUES (:nombre, :licencia, :fecha)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':nombre'   => $datos['nombre_piloto'],
                        ':licencia' => $datos['numero_licencia'],
                        ':fecha'    => $datos['fecha_nacimiento'],
                    ]);
                    $mensaje = 'Piloto registrado.';
                } else {
                    $sql = "UPDATE persona.piloto
                            SET nombre_piloto = :nombre, numero_licencia = :licencia, fecha_nacimiento = :fecha
                            WHERE piloto_id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':nombre'   => $datos['nombre_piloto'],
                        ':licencia' => $datos['numero_licencia'],
                        ':fecha'    => $datos['fecha_nacimiento'],
                        ':id'       => $id,
                    ]);
                    if ($stmt->rowCount() === 0) {
                        $errores[] = 'El piloto no existe.';
                    } else {
                        $mensaje = 'Piloto modificado.';
                    }
                }

                if (!$errores) {
                    $datos = ['nombre_piloto' => '', 'numero_licencia' => '', 'fecha_nacimiento' => ''];
                }

            } catch (PDOException $ex) {
                if ($ex->getCode() === '23505') {          // UNIQUE
                    $errores[] = 'Ese número de licencia ya está registrado.';
                } elseif ($ex->getCode() === '23514') {    // CHECK
                    $errores[] = 'Algún dato no cumple las restricciones de la base de datos.';
                } else {                                   // RAISE EXCEPTION del trigger
                    $errores[] = mensajeTrigger($ex);
                }
            }
        }
    }
    // --- ACCIÓN: ELIMINAR ---
    elseif ($accion === 'Eliminar' && !empty($id)) {
        try {
            $sql = "DELETE FROM persona.piloto WHERE piloto_id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
            if ($stmt->rowCount() === 0) {
                $errores[] = 'El piloto no existe.';
            } else {
                $mensaje = 'Piloto eliminado.';
            }
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23503') {              // FOREIGN KEY
                $errores[] = 'No se puede eliminar: el piloto está asignado a uno o más itinerarios.';
            } else {
                $errores[] = 'Error al eliminar: ' . mensajeTrigger($ex);
            }
        }
    }
}

// Consultar la lista actualizada
$pilotos = $pdo->query(
    "SELECT piloto_id, nombre_piloto, numero_licencia, fecha_nacimiento, edad_piloto
     FROM persona.piloto
     ORDER BY piloto_id ASC"
)->fetchAll();

// Fecha máxima permitida en el calendario (hace 18 años)
$fechaMaxima = (new DateTime('today'))->modify('-18 years')->format('Y-m-d');
?>

<!-- Formulario en HTML -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Pilotos</title>
</head>
<body>

    <p><a href="index.php">&larr; Menú</a></p>

    <h2>Registrar piloto</h2>

    <?php foreach ($errores as $err): ?>
        <p style="color: red;"><strong>Error: <?= e($err) ?></strong></p>
    <?php endforeach; ?>

    <?php if ($mensaje): ?>
        <p style="color: green;"><strong><?= e($mensaje) ?></strong></p>
    <?php endif; ?>

    <!-- Formulario Principal -->
    <form id="formPrincipal" method="POST" action="piloto.php">
        <label for="nombre_piloto">Nombre completo</label><br>
        <input type="text" id="nombre_piloto" name="nombre_piloto" maxlength="200" value="<?= e($datos['nombre_piloto']) ?>" required>
        <br><br>

        <label for="numero_licencia">Número de licencia (6 dígitos)</label><br>
        <input type="text" id="numero_licencia" name="numero_licencia" maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
               title="Exactamente 6 dígitos" value="<?= e($datos['numero_licencia']) ?>" required>
        <br><br>

        <label for="fecha_nacimiento">Fecha de nacimiento</label><br>
        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" max="<?= e($fechaMaxima) ?>" value="<?= e($datos['fecha_nacimiento']) ?>" required>
        <br><br>

        <button type="submit" name="accion" value="Registrar">Registrar</button>
    </form>
    <hr>

    <h2>Pilotos registrados</h2>

    <table border="1" cellpadding="6" style="border-collapse: collapse;">
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Licencia</th>
            <th>Nacimiento</th>
            <th>Edad</th>
            <th>Acciones</th>
        </tr>

        <?php foreach ($pilotos as $p): ?>

        <tr class="fila-tabla" onclick="seleccionarFila('<?= jse($p['nombre_piloto']) ?>', '<?= jse($p['numero_licencia']) ?>', '<?= jse($p['fecha_nacimiento']) ?>')">
            <td><?= e($p['piloto_id']) ?></td>
            <td><?= e($p['nombre_piloto']) ?></td>
            <td><?= e($p['numero_licencia']) ?></td>
            <td><?= e($p['fecha_nacimiento']) ?></td>
            <td><?= e($p['edad_piloto']) ?> años</td>
            <td style="white-space: nowrap;">

                <button type="submit" form="formPrincipal" name="accion" value="Modificar_<?= e($p['piloto_id']) ?>" onclick="event.stopPropagation();">
                    Modificar
                </button>

                <button type="submit" form="formPrincipal" name="accion" value="Eliminar_<?= e($p['piloto_id']) ?>" formnovalidate onclick="event.stopPropagation(); return confirm('¿Eliminar este piloto?');">
                    Eliminar
                </button>

            </td>
        </tr>
        <?php endforeach; ?>

        <?php if (!$pilotos): ?>
        <tr><td colspan="6">No hay pilotos registrados.</td></tr>
        <?php endif; ?>
    </table>

    <script>
        function seleccionarFila(nombre, licencia, fecha) {
            document.getElementById('nombre_piloto').value = nombre;
            document.getElementById('numero_licencia').value = licencia;
            document.getElementById('fecha_nacimiento').value = fecha;
        }
    </script>
</body>
</html>
