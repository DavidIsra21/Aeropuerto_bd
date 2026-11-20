<?php
// Carga la conexión (y las funciones e(), jse(), mensajeTrigger())
require __DIR__ . '/conexion.php';

// Mismos valores que los CHECK de catalogo.aeronave
$MODELOS     = ['Boeing 737-800', 'Airbus A320-200', 'Embraer E190', 'Boeing 787-9', 'Airbus A350-900'];
$CAPACIDADES = ['16', '20'];

$errores = [];
$mensaje = '';
$datos = ['modelo' => '', 'matricula' => '', 'capacidad_asientos' => ''];

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
        $datos['modelo']             = $_POST['modelo'] ?? '';
        $datos['matricula']          = strtoupper(trim($_POST['matricula'] ?? ''));
        $datos['capacidad_asientos'] = $_POST['capacidad_asientos'] ?? '';

        if (!in_array($datos['modelo'], $MODELOS, true)) {
            $errores[] = 'Selecciona un modelo válido.';
        }

        if ($datos['matricula'] === '' || mb_strlen($datos['matricula']) > 200) {
            $errores[] = 'La matrícula es obligatoria (máximo 200 caracteres).';
        }

        if (!in_array($datos['capacidad_asientos'], $CAPACIDADES, true)) {
            $errores[] = 'La capacidad debe ser 16 o 20 asientos.';
        }

        // Si la aeronave ya tiene itinerarios, sus asientos ya se generaron con la capacidad actual.
        if (!$errores && $accion === 'Modificar') {
            $stmt = $pdo->prepare(
                "SELECT a.capacidad_asientos,
                        (SELECT COUNT(*) FROM operacion.itinerario i WHERE i.aeronave_id = a.aeronave_id) AS itinerarios
                 FROM catalogo.aeronave a
                 WHERE a.aeronave_id = :id"
            );
            $stmt->execute([':id' => $id]);
            $actual = $stmt->fetch();

            if (!$actual) {
                $errores[] = 'La aeronave no existe.';
            } elseif ($actual['itinerarios'] > 0 && (string)$actual['capacidad_asientos'] !== $datos['capacidad_asientos']) {
                $errores[] = 'No se puede cambiar la capacidad: la aeronave ya tiene itinerarios con asientos generados.';
            }
        }

        if (!$errores) {
            try {
                if ($accion === 'Registrar') {
                    $sql = "INSERT INTO catalogo.aeronave (modelo, matricula, capacidad_asientos)
                            VALUES (:modelo, :matricula, :capacidad)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':modelo'    => $datos['modelo'],
                        ':matricula' => $datos['matricula'],
                        ':capacidad' => $datos['capacidad_asientos'],
                    ]);
                    $mensaje = 'Aeronave registrada.';
                } else {
                    $sql = "UPDATE catalogo.aeronave
                            SET modelo = :modelo, matricula = :matricula, capacidad_asientos = :capacidad
                            WHERE aeronave_id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':modelo'    => $datos['modelo'],
                        ':matricula' => $datos['matricula'],
                        ':capacidad' => $datos['capacidad_asientos'],
                        ':id'        => $id,
                    ]);
                    if ($stmt->rowCount() === 0) {
                        $errores[] = 'La aeronave no existe.';
                    } else {
                        $mensaje = 'Aeronave modificada.';
                    }
                }

                if (!$errores) {
                    $datos = ['modelo' => '', 'matricula' => '', 'capacidad_asientos' => ''];
                }

            } catch (PDOException $ex) {
                if ($ex->getCode() === '23505') {          // UNIQUE
                    $errores[] = 'Esa matrícula ya está registrada.';
                } elseif ($ex->getCode() === '23514') {    // CHECK
                    $errores[] = 'Algún dato no cumple las restricciones de la base de datos.';
                } else {
                    $errores[] = 'Error al guardar: ' . mensajeTrigger($ex);
                }
            }
        }
    }
    // --- ACCIÓN: ELIMINAR ---
    elseif ($accion === 'Eliminar' && !empty($id)) {
        try {
            $sql = "DELETE FROM catalogo.aeronave WHERE aeronave_id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
            if ($stmt->rowCount() === 0) {
                $errores[] = 'La aeronave no existe.';
            } else {
                $mensaje = 'Aeronave eliminada.';
            }
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23503') {              // FOREIGN KEY
                $errores[] = 'No se puede eliminar: la aeronave está asignada a uno o más itinerarios.';
            } else {
                $errores[] = 'Error al eliminar: ' . mensajeTrigger($ex);
            }
        }
    }
}

// Consultar la lista actualizada
$aeronaves = $pdo->query(
    "SELECT aeronave_id, modelo, matricula, capacidad_asientos
     FROM catalogo.aeronave
     ORDER BY aeronave_id ASC"
)->fetchAll();
?>

<!-- Formulario en HTML -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Aeronaves</title>
</head>
<body>

    <p><a href="index.php">&larr; Menú</a></p>

    <h2>Registrar aeronave</h2>

    <?php foreach ($errores as $err): ?>
        <p style="color: red;"><strong>Error: <?= e($err) ?></strong></p>
    <?php endforeach; ?>

    <?php if ($mensaje): ?>
        <p style="color: green;"><strong><?= e($mensaje) ?></strong></p>
    <?php endif; ?>

    <!-- Formulario Principal -->
    <form id="formPrincipal" method="POST" action="aeronave.php">
        <label for="modelo">Modelo</label><br>
        <select id="modelo" name="modelo" required>
            <option value="">-- Selecciona --</option>
            <?php foreach ($MODELOS as $m): ?>
                <option value="<?= e($m) ?>" <?= $datos['modelo'] === $m ? 'selected' : '' ?>><?= e($m) ?></option>
            <?php endforeach; ?>
        </select>
        <br><br>

        <label for="matricula">Matrícula</label><br>
        <input type="text" id="matricula" name="matricula" maxlength="200" value="<?= e($datos['matricula']) ?>" required>
        <br><br>

        <label for="capacidad_asientos">Capacidad de asientos</label><br>
        <select id="capacidad_asientos" name="capacidad_asientos" required>
            <option value="">-- Selecciona --</option>
            <?php foreach ($CAPACIDADES as $c): ?>
                <option value="<?= e($c) ?>" <?= $datos['capacidad_asientos'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
        </select>
        <br><br>

        <button type="submit" name="accion" value="Registrar">Registrar</button>
    </form>
    <hr>

    <h2>Aeronaves registradas</h2>

    <table border="1" cellpadding="6" style="border-collapse: collapse;">
        <tr>
            <th>ID</th>
            <th>Modelo</th>
            <th>Matrícula</th>
            <th>Capacidad</th>
            <th>Acciones</th>
        </tr>

        <?php foreach ($aeronaves as $a): ?>

        <tr class="fila-tabla" onclick="seleccionarFila('<?= jse($a['modelo']) ?>', '<?= jse($a['matricula']) ?>', '<?= jse($a['capacidad_asientos']) ?>')">
            <td><?= e($a['aeronave_id']) ?></td>
            <td><?= e($a['modelo']) ?></td>
            <td><?= e($a['matricula']) ?></td>
            <td><?= e($a['capacidad_asientos']) ?></td>
            <td style="white-space: nowrap;">

                <button type="submit" form="formPrincipal" name="accion" value="Modificar_<?= e($a['aeronave_id']) ?>" onclick="event.stopPropagation();">
                    Modificar
                </button>

                <button type="submit" form="formPrincipal" name="accion" value="Eliminar_<?= e($a['aeronave_id']) ?>" formnovalidate onclick="event.stopPropagation(); return confirm('¿Eliminar esta aeronave?');">
                    Eliminar
                </button>

            </td>
        </tr>
        <?php endforeach; ?>

        <?php if (!$aeronaves): ?>
        <tr><td colspan="5">No hay aeronaves registradas.</td></tr>
        <?php endif; ?>
    </table>

    <script>
        function seleccionarFila(modelo, matricula, capacidad) {
            document.getElementById('modelo').value = modelo;
            document.getElementById('matricula').value = matricula;
            document.getElementById('capacidad_asientos').value = capacidad;
        }
    </script>
</body>
</html>
